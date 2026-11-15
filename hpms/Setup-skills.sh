#!/usr/bin/env bash
# =============================================================================
# Setup-Skills  (setup-design-skills.sh)
# Installs 5 frontend design skills + CLAUDE.md / AGENTS.md into the CURRENT
# project folder. Run it from your project root, in a normal terminal.
#
#   ./setup-design-skills.sh                 # interactive menu: pick a design
#   ./setup-design-skills.sh --brand linear  # pick a design directly
#   ./setup-design-skills.sh --list          # list all designs and exit
#   ./setup-design-skills.sh --skip-design   # skills only, no DESIGN.md
#   ./setup-design-skills.sh --debug         # show full output of every command
#
# Optional extras (the script ASKS you; flags skip the question):
#   --security / --no-security         Claude-Red offensive security skills
#   --superpowers / --no-superpowers   obra/superpowers workflow skills
# =============================================================================

# --- must run under bash (not sh/dash) ---
if [ -z "${BASH_VERSION:-}" ]; then
  if command -v bash >/dev/null 2>&1; then exec bash "$0" "$@"; fi
  echo "ERROR: this script needs bash. Run:  bash $0"; exit 1
fi
if [ "${BASH_VERSINFO[0]}" -lt 4 ]; then
  echo "ERROR: bash 4+ required (you have $BASH_VERSION)."; exit 1
fi

set -uo pipefail
export GIT_TERMINAL_PROMPT=0          # never hang waiting for a git password
export CI=1 NO_COLOR=1 FORCE_COLOR=0   # no spinners / color noise from npx tools

BRAND="${BRAND:-}"; SKIP_DESIGN=0; LIST_ONLY=0; DEBUG=0; WANT_SECURITY=""; WANT_SUPERPOWERS=""
while [ $# -gt 0 ]; do
  case "$1" in
    --brand|-b)    BRAND="${2:-}"; shift 2 ;;
    --list|-l)     LIST_ONLY=1; shift ;;
    --skip-design) SKIP_DESIGN=1; shift ;;
    --debug)       DEBUG=1; shift ;;
    --security)       WANT_SECURITY=1; shift ;;
    --no-security)    WANT_SECURITY=0; shift ;;
    --superpowers)    WANT_SUPERPOWERS=1; shift ;;
    --no-superpowers) WANT_SUPERPOWERS=0; shift ;;
    -h|--help)     sed -n '2,/^# =====/p' "$0"; exit 0 ;;
    *) echo "Unknown option: $1 (use --help)"; exit 1 ;;
  esac
done

PROJECT_ROOT="$(pwd)"
TMP_DIR="$(mktemp -d)"
LOG_DIR="$PROJECT_ROOT/.design-setup-logs"
trap 'rm -rf "$TMP_DIR"' EXIT

# ---- colors (auto-disabled when output is not a terminal) ----
if [ -t 1 ]; then
  GREEN=$'\033[0;32m'; YELLOW=$'\033[1;33m'; RED=$'\033[0;31m'; CYAN=$'\033[0;36m'
  MAGENTA=$'\033[0;35m'; BOLD=$'\033[1m'; DIM=$'\033[2m'; NC=$'\033[0m'
else
  GREEN=''; YELLOW=''; RED=''; CYAN=''; MAGENTA=''; BOLD=''; DIM=''; NC=''
fi
ok()   { echo "${GREEN}✔${NC} $*"; }
warn() { echo "${YELLOW}⚠${NC} $*"; }
err()  { echo "${RED}✖${NC} $*"; }
info() { echo "${CYAN}➜${NC} $*"; }
step() { echo; echo "${BOLD}${CYAN}▶ $*${NC}"; echo "${DIM}$(_rep '─' 64)${NC}"; }

# ---- box drawing (ASCII text inside boxes keeps the borders aligned) ----
BOX_W=62
_rep()    { printf '%*s' "$2" '' | sed "s/ /$1/g"; }
box_top() { printf '%s╔%s╗%s\n' "$1" "$(_rep '═' $BOX_W)" "$NC"; }
box_bot() { printf '%s╚%s╝%s\n' "$1" "$(_rep '═' $BOX_W)" "$NC"; }
box_row() { printf '%s║%s %-*s %s║%s\n' "$1" "$NC" $((BOX_W-2)) "$2" "$1" "$NC"; }
note_box() {   # $1 = color, $2 = title, rest = lines
  local c="$1" t="$2"; shift 2
  box_top "$c"; box_row "$c" "$t"; box_row "$c" ""
  local l; for l in "$@"; do box_row "$c" "$l"; done
  box_bot "$c"
}
banner() {
  local line
  echo
  while IFS= read -r line; do printf '%s%s%s\n' "$CYAN" "$line" "$NC"; done < <(figlet_art SETUP)
  box_top "$CYAN"
  box_row "$CYAN" "   Impeccable * Taste * Vercel Guidelines * Playwright"
  box_row "$CYAN" "   + optional: Security skills, Superpowers"
  box_bot "$CYAN"
}

# ---- figlet-style ASCII headings (pre-rendered, no figlet install needed) ----
figlet_art() {
  case "$1" in
    INSTRUCTIONS)
cat <<'ART_EOF'
 ___ _   _ ____ _____ ____  _   _  ____ _____ ___ ___  _   _ ____
|_ _| \ | / ___|_   _|  _ \| | | |/ ___|_   _|_ _/ _ \| \ | / ___|
 | ||  \| \___ \ | | | |_) | | | | |     | |  | | | | |  \| \___ \
 | || |\  |___) || | |  _ <| |_| | |___  | |  | | |_| | |\  |___) |
|___|_| \_|____/ |_| |_| \_\\___/ \____| |_| |___\___/|_| \_|____/

ART_EOF
    ;;
    SETUP)
cat <<'ART_EOF'
 ____       _                    ____  _    _ _ _
/ ___|  ___| |_ _   _ _ __      / ___|| | _(_) | |___
\___ \ / _ \ __| | | | '_ \ ____\___ \| |/ / | | / __|
 ___) |  __/ |_| |_| | |_) |_____|__) |   <| | | \__ \
|____/ \___|\__|\__,_| .__/     |____/|_|\_\_|_|_|___/
                     |_|
ART_EOF
    ;;
    DESIGN)
cat <<'ART_EOF'
 ____  _____ ____ ___ ____ _   _   ______   ______ _____ _____ __  __
|  _ \| ____/ ___|_ _/ ___| \ | | / ___\ \ / / ___|_   _| ____|  \/  |
| | | |  _| \___ \| | |  _|  \| | \___ \\ V /\___ \ | | |  _| | |\/| |
| |_| | |___ ___) | | |_| | |\  |  ___) || |  ___) || | | |___| |  | |
|____/|_____|____/___\____|_| \_| |____/ |_| |____/ |_| |_____|_|  |_|

ART_EOF
    ;;
    IMPECCABLE)
cat <<'ART_EOF'
 ___ __  __ ____  _____ ____ ____    _    ____  _     _____
|_ _|  \/  |  _ \| ____/ ___/ ___|  / \  | __ )| |   | ____|
 | || |\/| | |_) |  _|| |  | |     / _ \ |  _ \| |   |  _|
 | || |  | |  __/| |__| |__| |___ / ___ \| |_) | |___| |___
|___|_|  |_|_|   |_____\____\____/_/   \_\____/|_____|_____|

ART_EOF
    ;;
    TASTE)
cat <<'ART_EOF'
 _____  _    ____ _____ _____   ____  _  _____ _     _
|_   _|/ \  / ___|_   _| ____| / ___|| |/ /_ _| |   | |
  | | / _ \ \___ \ | | |  _|   \___ \| ' / | || |   | |
  | |/ ___ \ ___) || | | |___   ___) | . \ | || |___| |___
  |_/_/   \_\____/ |_| |_____| |____/|_|\_\___|_____|_____|

ART_EOF
    ;;
    VERCEL)
cat <<'ART_EOF'
__     _______ ____   ____ _____ _
\ \   / / ____|  _ \ / ___| ____| |
 \ \ / /|  _| | |_) | |   |  _| | |
  \ V / | |___|  _ <| |___| |___| |___
   \_/  |_____|_| \_\\____|_____|_____|

ART_EOF
    ;;
    PLAYWRIGHT)
cat <<'ART_EOF'
 ____  _        _ __   ____        ______  ___ ____ _   _ _____
|  _ \| |      / \\ \ / /\ \      / /  _ \|_ _/ ___| | | |_   _|
| |_) | |     / _ \\ V /  \ \ /\ / /| |_) || | |  _| |_| | | |
|  __/| |___ / ___ \| |    \ V  V / |  _ < | | |_| |  _  | | |
|_|   |_____/_/   \_\_|     \_/\_/  |_| \_\___\____|_| |_| |_|

ART_EOF
    ;;
    SECURITY)
cat <<'ART_EOF'
 ____  _____ ____ _   _ ____  ___ _______   __
/ ___|| ____/ ___| | | |  _ \|_ _|_   _\ \ / /
\___ \|  _|| |   | | | | |_) || |  | |  \ V /
 ___) | |__| |___| |_| |  _ < | |  | |   | |
|____/|_____\____|\___/|_| \_\___| |_|   |_|

ART_EOF
    ;;
    SUPERPOWERS)
cat <<'ART_EOF'
 ____  _   _ ____  _____ ____  ____   _____        _______ ____  ____
/ ___|| | | |  _ \| ____|  _ \|  _ \ / _ \ \      / / ____|  _ \/ ___|
\___ \| | | | |_) |  _| | |_) | |_) | | | \ \ /\ / /|  _| | |_) \___ \
 ___) | |_| |  __/| |___|  _ <|  __/| |_| |\ V  V / | |___|  _ < ___) |
|____/ \___/|_|   |_____|_| \_\_|    \___/  \_/\_/  |_____|_| \_\____/

ART_EOF
    ;;
    USAGE)
cat <<'ART_EOF'
 _   _  _____        __  _____ ___    _   _ ____  _____
| | | |/ _ \ \      / / |_   _/ _ \  | | | / ___|| ____|
| |_| | | | \ \ /\ / /    | || | | | | | | \___ \|  _|
|  _  | |_| |\ V  V /     | || |_| | | |_| |___) | |___
|_| |_|\___/  \_/\_/      |_| \___/   \___/|____/|_____|

ART_EOF
    ;;
  esac
}
LAST_ART=""
# usage: heading <ART_KEY> <step title>   (art is shown once per key)
heading() {
  local key="$1" title="$2" color="$CYAN" line
  if [ -n "$key" ] && [ "$key" != "$LAST_ART" ]; then
    case "$key" in
      DESIGN) color="$CYAN" ;;   IMPECCABLE) color="$MAGENTA" ;; TASTE) color="$YELLOW" ;;
      VERCEL) color="$GREEN" ;;  PLAYWRIGHT) color="$CYAN" ;;    SECURITY) color="$RED" ;;
      SUPERPOWERS) color="$MAGENTA" ;; USAGE) color="$GREEN" ;; INSTRUCTIONS) color="$YELLOW" ;;
    esac
    echo
    while IFS= read -r line; do printf '%s%s%s\n' "$color" "$line" "$NC"; done < <(figlet_art "$key")
    LAST_ART="$key"
  fi
  step "$title"
}
key_for() {
  case "$1" in
    *Impeccable*) echo IMPECCABLE ;; *Taste*) echo TASTE ;; *Vercel*) echo VERCEL ;;
    *Claude-Red*|*security*) echo SECURITY ;; *Superpowers*) echo SUPERPOWERS ;; *) echo "" ;;
  esac
}

# run a command with a timeout, log output, show tail on failure
# usage: run_logged <logname> <timeout-seconds> <cmd...>
run_logged() {
  local name="$1" secs="$2"; shift 2
  mkdir -p "$LOG_DIR"
  local log="$LOG_DIR/$name.log" rc
  local tcmd=()
  command -v timeout >/dev/null 2>&1 && tcmd=(timeout "$secs")
  if [ "$DEBUG" = "1" ]; then
    "${tcmd[@]}" "$@" </dev/null 2>&1 | tee "$log"; rc=${PIPESTATUS[0]}
  else
    "${tcmd[@]}" "$@" </dev/null >"$log" 2>&1; rc=$?
  fi
  if [ "$rc" -ne 0 ]; then
    [ "$rc" -eq 124 ] && err "Timed out after ${secs}s: $*"
    err "Command failed (exit $rc): $*"
    echo "---- last lines of $log ----"; tail -n 12 "$log"; echo "----------------------------"
  fi
  return "$rc"
}

# yes/no question. $1 = question, $2 = preset (1 = yes, 0 = no, empty = ask). Default answer is NO.
INPUT_DEV=""
detect_input() {
  INPUT_DEV=""
  if ( exec </dev/tty ) 2>/dev/null; then INPUT_DEV="/dev/tty"
  elif [ -t 0 ]; then INPUT_DEV="/dev/stdin"; fi
}
ask_yn() {
  local q="$1" preset="${2:-}" ans
  [ "$preset" = "1" ] && return 0
  [ "$preset" = "0" ] && return 1
  detect_input
  if [ -z "$INPUT_DEV" ]; then
    warn "No interactive terminal - skipping this optional step (use its --flag to force)."
    return 1
  fi
  read -r -p "${BOLD}? $q${NC} [y/N]: " ans <"$INPUT_DEV" || return 1
  [[ "$ans" =~ ^[Yy]([Ee][Ss])?$ ]]
}

# ---------- 0. Prerequisites ----------
[ "$LIST_ONLY" = "1" ] || banner
step "Checking prerequisites"
for cmd in git node npx; do
  command -v "$cmd" >/dev/null 2>&1 || { err "'$cmd' is not installed (need git + Node.js 18+)."; exit 1; }
done
NODE_MAJOR="$(node -v | sed 's/^v//; s/\..*//')"
if [ "${NODE_MAJOR:-0}" -lt 18 ]; then
  err "Node $(node -v) is too old. The skills CLI needs Node 18+. Update Node and re-run."; exit 1
fi
ok "bash $BASH_VERSION | git | node $(node -v)"
info "Project root: $PROJECT_ROOT"

# quick internet check (GitHub)
if ! git ls-remote --heads https://github.com/voltagent/awesome-design-md.git >/dev/null 2>&1; then
  err "Cannot reach github.com. Check your internet / proxy / firewall (git ls-remote failed)."
  exit 1
fi
ok "GitHub reachable"

[ "$LIST_ONLY" = "1" ] || mkdir -p "$PROJECT_ROOT/.claude/skills" "$PROJECT_ROOT/.agents/skills"

# ---------- skill helpers ----------
skill_present() {   # $1 = skill folder name -> true if it has a SKILL.md somewhere in the project
  [ -f "$PROJECT_ROOT/.claude/skills/$1/SKILL.md" ] || [ -f "$PROJECT_ROOT/.agents/skills/$1/SKILL.md" ]
}

# git fallback: clone repo, copy every folder containing SKILL.md
# usage: fallback_install <owner/repo> [only-folder-name]
fallback_install() {
  local repo="$1" only="${2:-}" dest="$TMP_DIR/fb-$(basename "$1")"
  warn "Using git fallback for $repo"
  run_logged "fallback-$(basename "$repo")" 120 git clone --depth 1 "https://github.com/$repo.git" "$dest" || return 1
  local found=0 f dir name target
  while IFS= read -r f; do
    dir="$(dirname "$f")"; name="$(basename "$dir")"
    [ -n "$only" ] && [ "$name" != "$only" ] && continue
    for target in ".claude/skills" ".agents/skills"; do
      mkdir -p "$PROJECT_ROOT/$target/$name"
      cp -r "$dir"/. "$PROJECT_ROOT/$target/$name/"
    done
    ok "Copied skill '$name'"
    found=1
  done < <(find "$dest" -name SKILL.md -not -path "*/node_modules/*" -not -path "*/.git/*")
  [ "$found" -eq 1 ] || { err "No SKILL.md found in $repo${only:+ (looking for '$only')}"; return 1; }
}

# usage: install_skill <label> <expected-folder-name(s) comma-sep> <fallback-repo> <fallback-only> <npx source args...>
install_skill() {
  local label="$1" expect="$2" repo="$3" only="$4"; shift 4
  heading "$(key_for "$label")" "Installing: $label"
  run_logged "npx-$(echo "$label" | tr -c 'A-Za-z0-9' '_')" 240 npx --yes skills add "$@" -a claude-code -y
  local all_ok=1 e
  IFS=',' read -ra EXP <<< "$expect"
  for e in "${EXP[@]}"; do skill_present "$e" || all_ok=0; done
  if [ "$all_ok" -eq 1 ]; then
    ok "$label installed (verified: $expect)"
  else
    warn "$label NOT found on disk after 'npx skills add' - trying git fallback"
    if fallback_install "$repo" "$only" ; then ok "$label installed via fallback"
    else err "$label FAILED. Log: $LOG_DIR/ - or install manually from its GitHub page."; FAILED+=("$label"); fi
  fi
}
FAILED=()

# ---------- 1. Design System ----------
heading DESIGN "1/5 Design System - choose your design"

if [ "$SKIP_DESIGN" = "1" ]; then
  warn "Skipping DESIGN.md (--skip-design)"
else
  if ! run_logged "clone-design-md" 120 git clone --depth 1 https://github.com/voltagent/awesome-design-md.git "$TMP_DIR/design-md"; then
    err "Could not download the design list. Get a DESIGN.md manually: https://getdesign.md/"
    FAILED+=("DESIGN.md")
  else
    NAMES=(); PATHS=()
    while IFS= read -r f; do
      NAMES+=("$(basename "$(dirname "$f")")"); PATHS+=("$f")
    done < <(find "$TMP_DIR/design-md" -iname "DESIGN.md" -not -path "*/.git/*" | sort)

    if [ "${#NAMES[@]}" -eq 0 ]; then
      err "Repo downloaded but no DESIGN.md files found (layout changed?). Use https://getdesign.md/"
      FAILED+=("DESIGN.md")
    else
      # always show the list (also works in non-interactive terminals)
      echo "Found ${#NAMES[@]} designs:"
      n=0
      for i in "${!NAMES[@]}"; do
        n=$((n+1)); printf "  %s%3d%s) %-24s" "$CYAN" "$n" "$NC" "${NAMES[$i]}"; [ $((n % 3)) -eq 0 ] && echo
      done
      echo; echo
      if [ "$LIST_ONLY" = "1" ]; then
        printf '%s\n' "${NAMES[@]}" > "$PROJECT_ROOT/design-list.txt"
        ok "Full list also saved to design-list.txt"; exit 0
      fi

      SELECTED=-1
      resolve() {   # $1 = text/number -> sets SELECTED (exact, number, then unique partial)
        local q="$1" i hits=()
        SELECTED=-1
        if [[ "$q" =~ ^[0-9]+$ ]] && [ "$q" -ge 1 ] && [ "$q" -le "${#NAMES[@]}" ]; then SELECTED=$((q-1)); return; fi
        for i in "${!NAMES[@]}"; do [ "${NAMES[$i],,}" == "${q,,}" ] && { SELECTED="$i"; return; }; done
        for i in "${!NAMES[@]}"; do [[ "${NAMES[$i],,}" == *"${q,,}"* ]] && hits+=("$i"); done
        if [ "${#hits[@]}" -eq 1 ]; then SELECTED="${hits[0]}"
        elif [ "${#hits[@]}" -gt 1 ]; then
          warn "'$q' matches several: $(for h in "${hits[@]}"; do printf '%s ' "${NAMES[$h]}"; done)"
        fi
      }

      if [ -n "$BRAND" ]; then
        resolve "$BRAND"; [ "$SELECTED" -lt 0 ] && warn "Brand '$BRAND' not found / ambiguous."
      fi

      if [ "$SELECTED" -lt 0 ]; then
        # pick an input source for the prompt: tty if usable, else stdin if piped, else none
        INPUT_DEV=""
        if ( exec </dev/tty ) 2>/dev/null; then INPUT_DEV="/dev/tty"
        elif [ -t 0 ]; then INPUT_DEV="/dev/stdin"; fi

        if [ -z "$INPUT_DEV" ]; then
          warn "No interactive terminal available, so I can't show a prompt."
          warn "Pick one from the list above and run:  ./setup-design-skills.sh --brand <name>"
        else
          tries=0
          while [ "$SELECTED" -lt 0 ] && [ "$tries" -lt 20 ]; do
            tries=$((tries+1))
            read -r -p "Enter number or name (q = skip, l = show list again): " ans <"$INPUT_DEV" || break
            case "$ans" in
              q|Q) break ;;
              l|L) n=0; for i in "${!NAMES[@]}"; do n=$((n+1)); printf "  %s%3d%s) %-24s" "$CYAN" "$n" "$NC" "${NAMES[$i]}"; [ $((n % 3)) -eq 0 ] && echo; done; echo ;;
              "")  ;;
              *)   resolve "$ans"; [ "$SELECTED" -lt 0 ] && warn "No unique match for '$ans', try again." ;;
            esac
          done
        fi
      fi

      if [ "$SELECTED" -ge 0 ]; then
        [ -f "$PROJECT_ROOT/DESIGN.md" ] && { cp "$PROJECT_ROOT/DESIGN.md" "$PROJECT_ROOT/DESIGN.md.bak"; warn "Old DESIGN.md saved as DESIGN.md.bak"; }
        cp "${PATHS[$SELECTED]}" "$PROJECT_ROOT/DESIGN.md"
        ok "DESIGN.md for '${NAMES[$SELECTED]}' copied to project root"
      else
        warn "No DESIGN.md chosen. Re-run with --brand <name> any time."
      fi
    fi
  fi
fi

# ---------- 2-4. Skills ----------
install_skill "2/5 Impeccable"  "impeccable" "pbakaus/impeccable" "impeccable" pbakaus/impeccable
install_skill "3/5 Taste Skill" "design-taste-frontend" "Leonxlnx/taste-skill" "" Leonxlnx/taste-skill
install_skill "4/5 Vercel Web Design Guidelines" "web-design-guidelines" "vercel-labs/agent-skills" "web-design-guidelines" \
  https://github.com/vercel-labs/agent-skills --skill web-design-guidelines

# ---------- OPTIONAL A: Claude-Red security skills ----------
heading SECURITY "Optional: security skills (SnailSploit/Claude-Red)"
note_box "$YELLOW" "WARNING: offensive security / red-team skill pack" \
  "~50-80 skills: pentesting, exploitation, EDR evasion..." \
  "Use ONLY on systems you own or are authorized to test." \
  "Skills run with your agent's full permissions and add" \
  "to the skill list it loads. Install only if needed."
if ask_yn "Install the Claude-Red security skills?" "$WANT_SECURITY"; then
  install_skill "Claude-Red security skills" "offensive-advanced-redteam" "SnailSploit/Claude-Red" "" \
    SnailSploit/Claude-Red --skill '*'
else
  ok "Skipped security skills"
fi

# ---------- OPTIONAL B: Superpowers ----------
heading SUPERPOWERS "Optional: Superpowers workflow skills (obra/superpowers)"
note_box "$MAGENTA" "Superpowers (obra/superpowers)" \
  "15 engineering-workflow skills: brainstorming," \
  "writing-plans, test-driven-development," \
  "systematic-debugging, code review, git worktrees..."
if ask_yn "Install the Superpowers skills?" "$WANT_SUPERPOWERS"; then
  install_skill "Superpowers skills" "using-superpowers,brainstorming,test-driven-development" "obra/superpowers" "" \
    obra/superpowers --skill '*'
  command -v claude >/dev/null 2>&1 && \
    echo "Tip: inside Claude Code you can also install the full plugin (adds hooks):  /plugin install superpowers@claude-plugins-official"
else
  ok "Skipped Superpowers"
fi

# ---------- mirror skills into .agents/skills (used by many other coding agents) ----------
step "Mirroring skills to .agents/skills for other agents"
mkdir -p "$PROJECT_ROOT/.agents/skills"
for d in "$PROJECT_ROOT"/.claude/skills/*/; do
  [ -d "$d" ] || continue
  name="$(basename "$d")"
  if [ ! -f "$PROJECT_ROOT/.agents/skills/$name/SKILL.md" ]; then
    mkdir -p "$PROJECT_ROOT/.agents/skills/$name"
    cp -rL "$d". "$PROJECT_ROOT/.agents/skills/$name/" && ok "Mirrored $name"
  fi
done

# ---------- 5. Playwright MCP ----------
heading PLAYWRIGHT "5/5 Playwright MCP"
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
  ok "Created .mcp.json with Playwright MCP"
elif grep -q '"playwright"' "$PROJECT_ROOT/.mcp.json"; then
  ok ".mcp.json already has playwright"
else
  warn ".mcp.json exists - add manually: \"playwright\": { \"command\": \"npx\", \"args\": [\"@playwright/mcp@latest\"] }"
fi

if command -v claude >/dev/null 2>&1; then
  run_logged "claude-mcp-add" 60 claude mcp add playwright npx '@playwright/mcp@latest' \
    && ok "Registered Playwright MCP with Claude Code" \
    || warn "'claude mcp add' failed (maybe already added) - .mcp.json is in place."
fi

echo "Installing Chromium for Playwright (can take a few minutes)..."
run_logged "playwright-chromium" 600 npx --yes playwright install chromium \
  && ok "Chromium installed" \
  || warn "Chromium install failed. Run manually: npx playwright install chromium"

# ---------- 6. CLAUDE.md / AGENTS.md ----------
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
  if [ -f "$PROJECT_ROOT/$f" ] && grep -qF "$MARKER" "$PROJECT_ROOT/$f"; then ok "$f already has the rules"
  elif [ -f "$PROJECT_ROOT/$f" ]; then { echo; cat "$RULES_FILE"; } >> "$PROJECT_ROOT/$f"; ok "Appended rules to $f"
  else cp "$RULES_FILE" "$PROJECT_ROOT/$f"; ok "Created $f"; fi
done

# ---------- 7. skill-instructions.md (the file you tell your AI agent to read) ----------
heading INSTRUCTIONS "Generating skill-instructions.md"
INSTR_FILE="$PROJECT_ROOT/skill-instructions.md"

skill_path() {   # relative path of a skill's SKILL.md (prefers .claude, else .agents)
  if [ -f "$PROJECT_ROOT/.claude/skills/$1/SKILL.md" ]; then echo ".claude/skills/$1/SKILL.md"
  else echo ".agents/skills/$1/SKILL.md"; fi
}
skill_desc() {   # short description from the SKILL.md front matter (cut at a word boundary)
  local f d
  f="$PROJECT_ROOT/$(skill_path "$1")"
  d="$(awk '/^---[ \t]*$/{c++; next} c==1 && /^description:/{sub(/^description:[ \t]*/,""); gsub(/^"|"$/,""); gsub(/\047/,""); print; exit}' "$f" 2>/dev/null)"
  case "$d" in ""|">"|"|"|">-"|"|-") d="see its SKILL.md" ;; esac
  if [ "${#d}" -gt 95 ]; then d="${d:0:95}"; d="${d% *}..."; fi
  echo "$d"
}

{
echo "# skill-instructions.md"
echo
echo "> Generated by Setup-Skills on $(date +%Y-%m-%d). Re-run the script to refresh this file."
echo
cat <<'INSTR_EOF'
**AI AGENT: read this whole file at the start of every session in this project, then follow it for ALL work. It stays in force until the user says otherwise.**

## 0. Your operating rules (MANDATORY)

1. Read this file first. Then read `./CLAUDE.md` or `./AGENTS.md` and `./DESIGN.md` (if they exist).
2. Before every task, decide which skills below apply. Open each applicable `SKILL.md` and follow it. Start your reply by naming the skills you are using.
3. Never skip a skill to save time. Never invent skill rules you have not read.
4. If instructions conflict, use this priority: the user's explicit request > `DESIGN.md` (colors, fonts, spacing) > skill instructions > your own defaults. Tell the user when you overrule a skill because of their request.
5. A UI task is NOT finished until it passes the checklist in section 3.
6. If a skill file is missing or unreadable, say so, then continue with best effort.

INSTR_EOF

echo "## 1. Installed skills (open the SKILL.md before using a skill)"
echo
if skill_present impeccable; then
  echo "### Impeccable - \`$(skill_path impeccable)\`"
  echo "- Use for: critiquing, auditing, polishing, simplifying or improving any frontend UI."
  echo "- Commands: \`/impeccable audit\`, then \`/impeccable polish\`. If you have no slash commands, open the SKILL.md and apply its audit and polish procedure yourself."
  echo
fi
if skill_present design-taste-frontend; then
  echo "### Taste Skill - \`$(skill_path design-taste-frontend)\`"
  echo "- Use for: building or redesigning pages so they do not look generic or AI-made."
  echo "- Respect the Taste dials: BOLD (design variance), MOTION (motion intensity), DENSITY (visual density). Ask the user for values if they are not set."
  echo
fi
if skill_present web-design-guidelines; then
  echo "### Vercel Web Design Guidelines - \`$(skill_path web-design-guidelines)\`"
  echo "- Use for: reviewing UI code for accessibility, UX and interaction quality. Run it before you call any UI task done."
  echo
fi
if skill_present using-superpowers; then
  echo "### Superpowers workflow skills"
  echo "- Start with \`using-superpowers\`, then use the skill that matches the situation:"
  for n in using-superpowers brainstorming writing-plans executing-plans test-driven-development systematic-debugging requesting-code-review receiving-code-review verification-before-completion subagent-driven-development dispatching-parallel-agents using-git-worktrees finishing-a-development-branch; do
    skill_present "$n" && echo "  - \`$n\` - $(skill_desc "$n") (\`$(skill_path "$n")\`)"
  done
  echo "- Habit: plan before code (brainstorming, writing-plans), write tests first, debug systematically, verify before saying done."
  echo
fi
if skill_present offensive-advanced-redteam; then
  SEC_COUNT="$(ls -d "$PROJECT_ROOT"/.claude/skills/offensive-* 2>/dev/null | wc -l | tr -d ' ')"
  echo "### Security skills (\`offensive-*\`, $SEC_COUNT installed) - SAFETY RULES"
  echo "- These are offensive-security skills. Use them ONLY for systems the user owns or is explicitly authorized to test (own lab, CTF, scoped engagement)."
  echo "- Before using any \`offensive-*\` skill, ask the user to confirm the target and that they have authorization. If they do not confirm, do not proceed."
  echo "- Never run them against third-party or unknown systems. Never use them for anything unrelated to authorized security work."
  echo
fi

cat <<'INSTR_EOF'
## 2. Workflow for every UI task

1. **Ground** - read `DESIGN.md`. Use its exact hex colors, type scale, spacing, radius and shadows. Never invent new tokens.
2. **Plan** - state which skills you will use and what you will build (keep it short).
3. **Build** - follow the Taste Skill so the result does not look generic.
4. **Audit and polish** - run Impeccable on COMPONENTS, TYPOGRAPHY and BACKGROUND layers.
5. **Review** - run the Vercel Web Design Guidelines on the code.
6. **Browser QA** - open the live localhost page with Playwright, click the interactive elements, take a real screenshot, and fix every visual bug.
7. **Report** - tell the user what you built, which skills you used, and what you verified.

## 3. Definition of done (check every line before you finish)

- [ ] Colors, fonts and spacing match `DESIGN.md`
- [ ] No purple-to-blue gradient headlines; SVG icons, not emoji tiles above headings
- [ ] Buttons and close icons have at least a 24x24px hit area (44x44px on mobile)
- [ ] Browser zoom is not disabled (`user-scalable=no` / `maximum-scale=1` are forbidden)
- [ ] Every async action shows a loading state (`<Spinner />`) while pending
- [ ] Impeccable audit and polish done; Vercel guidelines review done
- [ ] Screenshot taken in a real browser; no overflow or overlapping text or buttons

## 4. Hard rules

- Always read `./DESIGN.md` before creating or changing any UI component.
- Never use emoji icon tiles above feature headings; use clean SVG icons.
- Never disable browser zoom.
- Never say "done" before section 3 is satisfied.

## 5. Browser QA tools

- Preferred: Playwright MCP (configured in `.mcp.json`). Use its navigate, click and `take_screenshot` tools.
- If MCP is not available, run `npx playwright screenshot http://localhost:3000 shot.png` and inspect the image. If the browser is missing, run `npx playwright install chromium`.

## 6. For the human: how to use this file

- Start your agent in this project folder and say: **"Read skill-instructions.md and follow it for everything in this project."**
- Then give normal tasks, for example: "Build a pricing page", "Audit and polish the dashboard", "Review this component for accessibility".
- To change the design, run `./setup-design-skills.sh --brand <name>` (the old `DESIGN.md` is kept as `DESIGN.md.bak`), then ask the agent to re-read `DESIGN.md`.
- If the agent seems to forget the rules, repeat the sentence above.
INSTR_EOF
} > "$INSTR_FILE"
ok "Created skill-instructions.md"

# make the agent rules files point to it, so agents that read CLAUDE.md / AGENTS.md find it automatically
for f in CLAUDE.md AGENTS.md; do
  if ! grep -qF "skill-instructions.md" "$PROJECT_ROOT/$f" 2>/dev/null; then
    { echo; echo "## Skill instructions (MANDATORY)"; echo "At the start of every session, read ./skill-instructions.md and follow it for all work in this project."; } >> "$PROJECT_ROOT/$f"
    ok "Added skill-instructions.md pointer to $f"
  fi
done

# ---------- Summary ----------
step "SUMMARY"
SUM_COLOR="$GREEN"; [ "${#FAILED[@]}" -gt 0 ] && SUM_COLOR="$RED"
srow() {   # $1 = 1/0, $2 = label, $3 = text when missing (default: MISSING)
  if [ "$1" = "1" ]; then box_row "$SUM_COLOR" "[OK]  $2"; else box_row "$SUM_COLOR" "[--]  $2 (${3:-MISSING})"; fi
}
f_ok() { [ -f "$PROJECT_ROOT/$1" ] && echo 1 || echo 0; }
sk()   { skill_present "$1" && echo 1 || echo 0; }
box_top "$SUM_COLOR"
box_row "$SUM_COLOR" "INSTALL REPORT"
box_row "$SUM_COLOR" "Folder: ${PROJECT_ROOT:0:50}"
box_row "$SUM_COLOR" ""
srow "$(f_ok DESIGN.md)"  "DESIGN.md (design system)" "not installed"
srow "$(f_ok CLAUDE.md)"  "CLAUDE.md (agent rules)"
srow "$(f_ok AGENTS.md)"  "AGENTS.md (agent rules)"
srow "$(f_ok .mcp.json)"  "Playwright MCP (.mcp.json)"
srow "$(f_ok skill-instructions.md)" "skill-instructions.md (tell your agent to read it)"
srow "$(sk impeccable)"             "Skill: Impeccable"
srow "$(sk design-taste-frontend)"  "Skill: Taste Skill"
srow "$(sk web-design-guidelines)"  "Skill: Vercel Web Design Guidelines"
srow "$(sk offensive-advanced-redteam)" "Optional: Claude-Red security skills" "not installed"
srow "$(sk using-superpowers)"          "Optional: Superpowers skills" "not installed"
box_row "$SUM_COLOR" ""
box_row "$SUM_COLOR" "Total skill folders: $(ls -1 .claude/skills 2>/dev/null | wc -l | tr -d ' ')"
box_bot "$SUM_COLOR"

if [ "${#FAILED[@]}" -gt 0 ]; then
  echo; err "Some steps failed: ${FAILED[*]}"
  info "Logs are in: $LOG_DIR   (re-run with --debug to see everything live)"
  exit 1
fi
rm -rf "$LOG_DIR" 2>/dev/null
echo
note_box "$CYAN" "ALL DONE - NEXT STEPS" \
  "1. Start your AI agent in this folder" \
  "2. Tell it: Read skill-instructions.md and follow it" \
  "3. Claude Code: try  /impeccable audit" \
  "   Other agents: Use the Impeccable skill to audit this page"

# =============================================================================
# HOW TO USE YOUR SKILLS (printed on screen + saved to DESIGN-SKILLS-GUIDE.md)
# =============================================================================
GUIDE_FILE="$PROJECT_ROOT/DESIGN-SKILLS-GUIDE.md"
cat > "$GUIDE_FILE" <<'GUIDE_EOF'
# HOW TO USE YOUR SKILLS

## Quick start
- Tell your AI agent:  "Read skill-instructions.md and follow it for everything in this project."
- That file lists your skills and the exact workflow the agent must follow.

## 1. What is now in your project folder
- DESIGN.md          = the design system (colors, fonts, spacing) your UI must follow
- skill-instructions.md = the file you tell your agent to read: skills + mandatory workflow
- CLAUDE.md          = frontend rules your AI agent reads at the start of a session
- AGENTS.md          = the same rules, for agents that use AGENTS.md
- .claude/skills/    = the installed skills (Claude Code and compatible agents)
- .agents/skills/    = a copy of the same skills for other agents
- .mcp.json          = Playwright MCP: lets the agent open a real browser and take screenshots

## 2. Daily workflow (works with any AI coding agent)
1. Open a terminal in THIS project folder (the one that contains DESIGN.md).
2. Start your agent there: claude, freebuff, codex, gemini, cursor-agent, etc.
3. Check that it sees your setup. Ask:
   "What frontend rules, DESIGN.md and skills do you have in this project?"
4. Give it a UI task. Example:
   "Read DESIGN.md and CLAUDE.md. Build a pricing page following the rules."
5. Ask it to verify visually:
   "Open the localhost page with Playwright, take a screenshot and fix any visual bugs."
6. Run the audit skills, review the result, then commit.

## 3. Tips for each kind of agent
- Claude Code: reads CLAUDE.md and .claude/skills automatically. Skills start from your
  request, or call one by name, e.g.  /impeccable audit  (type / to see the list).
  The first time, approve the Playwright server from .mcp.json (check with /mcp).
- Freebuff / Codebuff and other agents: auto-loading of skills and MCP can differ.
  If the agent does not know your rules, start with this first prompt:
  "Read CLAUDE.md, DESIGN.md and the SKILL.md files inside .agents/skills before any UI work."
  Slash commands like /impeccable only work in agents that support skills; otherwise say:
  "Follow the Impeccable skill and audit this page."
- Cursor / Codex and others: most read AGENTS.md. Gemini CLI reads GEMINI.md by default,
  so run:  cp AGENTS.md GEMINI.md   (check your agent's docs for the exact file name).
- Agent without MCP support: take screenshots yourself and let the agent look at the file:
  npx playwright screenshot http://localhost:3000 shot.png

## 4. Example prompts
- Design system:  "Use DESIGN.md strictly: exact hex colors, type scale, spacing, radius, shadows."
- Impeccable:     "/impeccable audit"  then  "/impeccable polish"  on components, typography, background.
- Taste Skill:    "Use the Taste skill. Bold = high, Motion = medium, Density = low. Redesign the hero."
- Vercel rules:   "Review this page with the Web Design Guidelines skill: accessibility, hit targets, zoom, loading states."
- Playwright QA:  "Open http://localhost:3000, click every button, screenshot, fix overflow or overlap."
- Full pipeline:  "Build the dashboard from DESIGN.md, polish it, audit it, then QA it in the browser."

## 5. Change the design later
- Run  ./setup-design-skills.sh --list  to see all designs, then
  ./setup-design-skills.sh --brand stripe  to switch. Your old DESIGN.md is kept as DESIGN.md.bak.
- Preview designs first at https://getdesign.md/

## 6. If something does not work
- Agent ignores the rules: restart the agent session and say "Read CLAUDE.md and DESIGN.md first."
- Skill not found: run  ls .claude/skills  then say
  "Read .claude/skills/<skill-name>/SKILL.md and follow it for this task."
- Screenshot fails (no browser): run  npx playwright install chromium
- Something failed during setup: re-run the script (it is safe to repeat) or add --debug.
GUIDE_EOF

# add the optional packs that were actually installed
{
  if skill_present using-superpowers; then
    echo
    echo "## 7. Superpowers skills (installed)"
    echo "- They start from your request. Try: \"Use brainstorming to plan this feature\","
    echo "  \"Write the plan first\", \"Use test-driven-development\", \"Use systematic-debugging on this bug\"."
    echo "- Best habit: ask for a plan before code, then ask it to verify before it says done."
  fi
  if skill_present offensive-advanced-redteam; then
    echo
    echo "## 8. Security skills (installed)"
    echo "- Use ONLY on systems you own or are authorized to test (your lab, CTF, scoped engagement)."
    echo "- Name the skill and the authorized target in your prompt. Skills are in .claude/skills/offensive-*"
    echo "- Remove them when not needed: delete the offensive-* folders from .claude/skills and .agents/skills."
  fi
} >> "$GUIDE_FILE"

heading USAGE "How to use your skills in an AI coding agent"
while IFS= read -r line; do
  case "$line" in
    "# "*)  ;;                                            # file title (already shown as heading)
    "## "*) echo "${BOLD}${CYAN}${line#\#\# }${NC}" ;;
    "")     echo ;;
    *)      echo "  $line" ;;
  esac
done < "$GUIDE_FILE"
echo
ok "Guide saved to DESIGN-SKILLS-GUIDE.md (open it any time)"
echo
