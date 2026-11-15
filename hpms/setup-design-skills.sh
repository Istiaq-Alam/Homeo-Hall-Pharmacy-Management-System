#!/usr/bin/env bash
# =============================================================================
# setup-design-skills.sh
# Installs the 5 open-source frontend design skills + CLAUDE.md / AGENTS.md
# into the CURRENT project folder (run it from your project root).
#
# Usage:
#   chmod +x setup-design-skills.sh
#   ./setup-design-skills.sh                 # interactive menu: pick your design
#   ./setup-design-skills.sh --brand linear  # pick a design directly (partial names work)
#   ./setup-design-skills.sh --list          # just list all available designs
#   ./setup-design-skills.sh --skip-design   # install skills only, no DESIGN.md
#   BRAND=vercel ./setup-design-skills.sh    # same as --brand
# =============================================================================
set -uo pipefail

BRAND="${BRAND:-}"
SKIP_DESIGN=0
LIST_ONLY=0
while [ $# -gt 0 ]; do
  case "$1" in
    --brand|-b) BRAND="${2:-}"; shift 2 ;;
    --list|-l)  LIST_ONLY=1; shift ;;
    --skip-design) SKIP_DESIGN=1; shift ;;
    -h|--help)  sed -n '2,16p' "$0"; exit 0 ;;
    *) echo "Unknown option: $1 (use --help)"; exit 1 ;;
  esac
done
PROJECT_ROOT="$(pwd)"
TMP_DIR="$(mktemp -d)"
trap 'rm -rf "$TMP_DIR"' EXIT

GREEN='\033[0;32m'; YELLOW='\033[1;33m'; RED='\033[0;31m'; NC='\033[0m'
ok()   { echo -e "${GREEN}[OK]${NC} $*"; }
warn() { echo -e "${YELLOW}[WARN]${NC} $*"; }
err()  { echo -e "${RED}[ERR]${NC} $*"; }
step() { echo -e "\n${GREEN}==> $*${NC}"; }

# ---------- 0. Prerequisites ----------
step "Checking prerequisites"
for cmd in git node npx; do
  if ! command -v "$cmd" >/dev/null 2>&1; then
    err "'$cmd' is not installed. Install it first (Node.js 18+ and git are required)."
    exit 1
  fi
done
ok "git, node, npx found (node $(node -v))"
echo "Project root: $PROJECT_ROOT"

[ "$LIST_ONLY" = "1" ] || mkdir -p "$PROJECT_ROOT/.claude/skills" "$PROJECT_ROOT/.agents/skills"

# ---------- helper: manual fallback (git clone -> copy SKILL.md folders) ----------
# usage: fallback_install <github-repo> [only-folder-name]
fallback_install() {
  local repo="$1" only="${2:-}"
  local dest="$TMP_DIR/$(basename "$repo")"
  warn "Using git fallback for $repo"
  if ! git clone --depth 1 "https://github.com/$repo.git" "$dest" >/dev/null 2>&1; then
    err "Could not clone $repo (check your internet connection)."
    return 1
  fi
  local found=0
  while IFS= read -r skill_file; do
    local dir name
    dir="$(dirname "$skill_file")"
    name="$(basename "$dir")"
    if [ -n "$only" ] && [ "$name" != "$only" ]; then continue; fi
    for target in ".claude/skills" ".agents/skills"; do
      mkdir -p "$PROJECT_ROOT/$target/$name"
      cp -r "$dir"/. "$PROJECT_ROOT/$target/$name/"
    done
    ok "Copied skill '$name' to .claude/skills and .agents/skills"
    found=1
  done < <(find "$dest" -name SKILL.md -not -path "*/node_modules/*" 2>/dev/null)
  [ "$found" -eq 1 ] || { err "No SKILL.md found in $repo"; return 1; }
}

# ---------- helper: run `npx skills add`, fall back to git if it fails ----------
# usage: install_skill <label> <repo-for-fallback> <only-folder-or-empty> <npx args...>
install_skill() {
  local label="$1" repo="$2" only="$3"; shift 3
  step "Installing: $label"
  if npx --yes skills add "$@" -y </dev/null; then
    ok "$label installed via 'npx skills add'"
  else
    warn "'npx skills add' failed for $label"
    fallback_install "$repo" "$only" || err "$label FAILED - install manually (see README links)."
  fi
}

# ---------- 1. Awesome DESIGN.md (you choose the design) ----------
step "1/5 Awesome DESIGN.md - choose a design system"

copy_design() {   # $1 = path of the chosen DESIGN.md
  if [ -f "$PROJECT_ROOT/DESIGN.md" ]; then
    cp "$PROJECT_ROOT/DESIGN.md" "$PROJECT_ROOT/DESIGN.md.bak"
    warn "Existing DESIGN.md backed up to DESIGN.md.bak"
  fi
  cp "$1" "$PROJECT_ROOT/DESIGN.md"
}

# Interactive picker. Sets SELECTED_IDX (-1 = skipped).
pick_design() {
  local filter="" ans i n
  SELECTED_IDX=-1
  while true; do
    local idx=()
    for i in "${!NAMES[@]}"; do
      if [ -z "$filter" ] || [[ "${NAMES[$i],,}" == *"${filter,,}"* ]]; then idx+=("$i"); fi
    done
    echo
    echo "Available designs${filter:+ (filter: \"$filter\")}: ${#idx[@]} found"
    n=0
    for i in "${idx[@]}"; do
      n=$((n+1))
      printf "  %3d) %-26s" "$n" "${NAMES[$i]}"
      [ $((n % 3)) -eq 0 ] && echo
    done
    echo
    read -r -p "Type a NUMBER or NAME to select | /text = filter | / = reset | q = skip: " ans </dev/tty || return
    case "$ans" in
      q|Q) SELECTED_IDX=-1; return ;;
      /*)  filter="${ans#/}"; continue ;;
      "")  continue ;;
    esac
    if [[ "$ans" =~ ^[0-9]+$ ]] && [ "$ans" -ge 1 ] && [ "$ans" -le "${#idx[@]}" ]; then
      SELECTED_IDX="${idx[$((ans-1))]}"; return
    fi
    # exact name match (case-insensitive)
    for i in "${!NAMES[@]}"; do
      if [ "${NAMES[$i],,}" == "${ans,,}" ]; then SELECTED_IDX="$i"; return; fi
    done
    # otherwise treat the text as a filter; auto-select if only one match
    filter="$ans"
    local hits=() j
    for j in "${!NAMES[@]}"; do
      [[ "${NAMES[$j],,}" == *"${filter,,}"* ]] && hits+=("$j")
    done
    if [ "${#hits[@]}" -eq 1 ]; then SELECTED_IDX="${hits[0]}"; return; fi
    [ "${#hits[@]}" -eq 0 ] && { warn "No design matches '$ans'"; filter=""; }
  done
}

if [ "$SKIP_DESIGN" = "1" ]; then
  warn "Skipping DESIGN.md (--skip-design)"
elif ! git clone --depth 1 https://github.com/voltagent/awesome-design-md.git "$TMP_DIR/design-md" >/dev/null 2>&1; then
  err "Could not clone awesome-design-md. Download a DESIGN.md manually from https://github.com/voltagent/awesome-design-md"
else
  NAMES=(); PATHS=()
  while IFS= read -r f; do
    NAMES+=("$(basename "$(dirname "$f")")")
    PATHS+=("$f")
  done < <(find "$TMP_DIR/design-md" -iname "DESIGN.md" -not -path "*/.git/*" | sort)

  if [ "${#NAMES[@]}" -eq 0 ]; then
    warn "No DESIGN.md files found in the repo (its layout may have changed)."
  elif [ "$LIST_ONLY" = "1" ]; then
    printf '%s\n' "${NAMES[@]}"
    exit 0
  else
    SELECTED_IDX=-1
    # (a) brand given via --brand / BRAND=
    if [ -n "$BRAND" ]; then
      for i in "${!NAMES[@]}"; do
        [ "${NAMES[$i],,}" == "${BRAND,,}" ] && SELECTED_IDX="$i" && break
      done
      if [ "$SELECTED_IDX" -lt 0 ]; then   # partial match
        for i in "${!NAMES[@]}"; do
          [[ "${NAMES[$i],,}" == *"${BRAND,,}"* ]] && SELECTED_IDX="$i" && break
        done
      fi
      [ "$SELECTED_IDX" -lt 0 ] && warn "Brand '$BRAND' not found."
    fi
    # (b) otherwise ask interactively
    if [ "$SELECTED_IDX" -lt 0 ]; then
      if [ -t 0 ] || [ -e /dev/tty ]; then
        pick_design
      else
        warn "No terminal for a menu. Use --brand NAME or --list."
      fi
    fi
    # (c) apply
    if [ "$SELECTED_IDX" -ge 0 ]; then
      copy_design "${PATHS[$SELECTED_IDX]}"
      ok "DESIGN.md for '${NAMES[$SELECTED_IDX]}' copied to project root"
    else
      warn "No DESIGN.md selected. Re-run the script any time to choose one."
    fi
  fi
fi

# ---------- 2-4. Skills via npx ----------
install_skill "2/5 Impeccable"   "pbakaus/impeccable"        ""                      pbakaus/impeccable
install_skill "3/5 Taste Skill"  "Leonxlnx/taste-skill"      ""                      Leonxlnx/taste-skill
install_skill "4/5 Vercel Web Design Guidelines" "vercel-labs/agent-skills" "web-design-guidelines" \
  https://github.com/vercel-labs/agent-skills --skill web-design-guidelines

# ---------- 5. Playwright MCP ----------
step "5/5 Playwright MCP"

# (a) Universal project-level MCP config (.mcp.json) - works with Claude Code
#     and most MCP-capable agents.
if [ ! -f "$PROJECT_ROOT/.mcp.json" ]; then
  cat > "$PROJECT_ROOT/.mcp.json" <<'EOF'
{
  "mcpServers": {
    "playwright": {
      "command": "npx",
      "args": ["@playwright/mcp@latest"]
    }
  }
}
EOF
  ok "Created .mcp.json with Playwright MCP server"
else
  if grep -q '"playwright"' "$PROJECT_ROOT/.mcp.json"; then
    ok ".mcp.json already contains playwright"
  else
    warn ".mcp.json already exists - add this server manually:"
    echo '  "playwright": { "command": "npx", "args": ["@playwright/mcp@latest"] }'
  fi
fi

# (b) Also register through the Claude Code CLI if it is installed.
if command -v claude >/dev/null 2>&1; then
  claude mcp add playwright npx '@playwright/mcp@latest' </dev/null \
    && ok "Registered Playwright MCP with Claude Code CLI" \
    || warn "'claude mcp add' did not succeed (it may already exist) - .mcp.json is still in place."
else
  warn "Claude Code CLI not found - skipping 'claude mcp add' (not needed for other agents)."
fi

# (c) Playwright needs a browser binary for screenshots.
echo "Installing Chromium for Playwright (needed for screenshots)..."
npx --yes playwright install chromium </dev/null \
  && ok "Chromium installed" \
  || warn "Could not install Chromium automatically. Run: npx playwright install chromium"

# ---------- 6. CLAUDE.md / AGENTS.md rules ----------
step "Writing Frontend Architect rules"
MARKER="# Frontend Design Engineering Rules (Score 98/100 Pipeline)"

RULES_FILE="$TMP_DIR/rules.md"
cat > "$RULES_FILE" <<'EOF'
# Frontend Design Engineering Rules (Score 98/100 Pipeline)

1. DESIGN SYSTEM GROUNDING (Awesome DESIGN.md):
   - Always read ./DESIGN.md before creating or modifying any UI component.
   - Strictly enforce the exact hex colors, typography scale, spacing tokens, border radius, and shadow tokens defined in ./DESIGN.md.

2. ANTI-SLOP GOVERNANCE (Impeccable & Taste-Skill):
   - Never use generic purple-to-blue gradient text headlines.
   - Never place emoji icon tiles above feature headings; use clean SVG icons instead.
   - Run /impeccable audit and /impeccable polish across COMPONENTS, TYPOGRAPHY, and BACKGROUND layers.
   - Respect calibrated Taste parameters: BOLD (Design Variance), MOTION (Motion Intensity), and DENSITY (Visual Density).

3. UX & ACCESSIBILITY AUDIT (Vercel Web Design Guidelines):
   - Every interactive button or close icon must have a minimum hit target of 24x24px (44x44px on mobile touch targets).
   - Never disable browser zoom (user-scalable=no or maximum-scale=1 is strictly forbidden).
   - Every async form submission or action button must display an explicit <Spinner /> loading state while pending.

4. CLOSED-LOOP BROWSER QA (Microsoft Playwright MCP):
   - After building or updating any page, open the live localhost URL using Playwright MCP.
   - Click interactive buttons, take a real browser screenshot (take_screenshot), verify that no close (X) button or CTA overflows its card or overlaps text, and fix all visual bugs before completing the task.
EOF

for f in CLAUDE.md AGENTS.md; do
  if [ -f "$PROJECT_ROOT/$f" ] && grep -qF "$MARKER" "$PROJECT_ROOT/$f"; then
    ok "$f already contains the rules - skipped"
  elif [ -f "$PROJECT_ROOT/$f" ]; then
    { echo; cat "$RULES_FILE"; } >> "$PROJECT_ROOT/$f"
    ok "Appended rules to existing $f"
  else
    cp "$RULES_FILE" "$PROJECT_ROOT/$f"
    ok "Created $f"
  fi
done

# ---------- Summary ----------
step "DONE - summary"
echo "Project root: $PROJECT_ROOT"
[ -f DESIGN.md ]  && ok "DESIGN.md"
[ -f CLAUDE.md ]  && ok "CLAUDE.md"
[ -f AGENTS.md ]  && ok "AGENTS.md"
[ -f .mcp.json ]  && ok ".mcp.json (Playwright MCP)"
echo "Installed skill folders:"
ls -1 .claude/skills .agents/skills skills 2>/dev/null | sed 's/^/   /'
echo
echo "Next: start your AI agent in this folder and try:  /impeccable audit"