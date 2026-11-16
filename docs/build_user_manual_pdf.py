#!/usr/bin/env python3
"""Build docs/USER_MANUAL_BN.pdf from docs/USER_MANUAL_BN.md.

Usage (from anywhere):

    python3 docs/build_user_manual_pdf.py

Requires `pandoc` and `weasyprint` on PATH, plus the Noto Sans Bengali font
for correct Bangla shaping. The layout lives in docs/manual/:

    template.html      cover page, contents page, body wrapper
    style.css          A4 page setup, typography, tables, figure styling
    logo-land-trim.png cover logo

Only the Markdown manual needs editing; run this script afterwards.
"""

from __future__ import annotations

import os
import re
import shutil
import subprocess
import sys
from pathlib import Path
from typing import NoReturn

DOCS = Path(__file__).resolve().parent
SOURCE = DOCS / "USER_MANUAL_BN.md"
OUTPUT = DOCS / "USER_MANUAL_BN.pdf"
ASSETS = DOCS / "manual"
TEMPLATE = ASSETS / "template.html"
STYLE = ASSETS / "style.css"
LOGO = ASSETS / "logo-land-trim.png"
SCREENSHOTS = DOCS / "screenshots"
WORK_HTML = DOCS / ".USER_MANUAL_BN.build.html"

PANDOC_ARGS = [
    "--from=markdown+pipe_tables+task_lists",
    "--to=html5",
    "--standalone",
    "--toc",
    "--toc-depth=2",
]


def fail(message: str) -> NoReturn:
    print(f"error: {message}", file=sys.stderr)
    raise SystemExit(1)


def require_tools() -> None:
    missing = [tool for tool in ("pandoc", "weasyprint") if shutil.which(tool) is None]
    if missing:
        fail(
            "missing required tool(s): "
            + ", ".join(missing)
            + "\n  Install pandoc (https://pandoc.org/installing.html) and "
            "WeasyPrint (`pipx install weasyprint`)."
        )


def read_manual() -> tuple[str, str]:
    if not SOURCE.is_file():
        fail(f"manual source not found: {SOURCE}")

    text = SOURCE.read_text(encoding="utf-8-sig").replace("\r\n", "\n")
    first_line, _, rest = text.partition("\n")
    if not first_line.startswith("# "):
        fail("the first line of the manual must be a single '# Title' heading")
    return first_line[2:].strip(), rest.lstrip("\n")


def check_assets(body: str) -> None:
    for path in (TEMPLATE, STYLE, LOGO):
        if not path.is_file():
            fail(f"missing layout asset: {path}")

    referenced = re.findall(r'<img\s[^>]*src="([^"]+)"', body)
    missing = sorted(
        {
            src
            for src in referenced
            if not src.startswith(("http://", "https://", "data:"))
            and not (DOCS / src).is_file()
        }
    )
    if missing:
        details = "\n".join(f"  - {src}" for src in missing)
        fail(f"manual references {len(missing)} missing image(s):\n{details}")

    if not SCREENSHOTS.is_dir():
        fail(f"screenshot folder not found: {SCREENSHOTS}")


def run(
    command: list[str],
    *,
    cwd: Path,
    what: str,
    input_text: str | None = None,
) -> subprocess.CompletedProcess[str]:
    result = subprocess.run(
        command, cwd=cwd, text=True, capture_output=True, input=input_text
    )
    if result.returncode != 0:
        detail = (result.stderr or result.stdout).strip()
        fail(f"{what} failed (exit {result.returncode}):\n{detail}")
    return result


def render(title: str, body: str) -> Path:
    # The generated HTML lives in docs/ so the manual's relative image paths
    # (`screenshots/...`) and the template's `manual/...` assets resolve.
    html = str(WORK_HTML)
    tmp_pdf = DOCS / ".USER_MANUAL_BN.build.pdf"
    try:
        # Pandoc reads the body (without the cover title) from stdin, so the
        # title is not repeated as a section or a contents entry.
        run(
            [
                "pandoc",
                "-",
                *PANDOC_ARGS,
                f"--template={TEMPLATE}",
                f"--metadata=title:{title}",
                f"--output={html}",
            ],
            cwd=DOCS,
            what="pandoc conversion",
            input_text=body,
        )

        try:
            run(["weasyprint", html, str(tmp_pdf)], cwd=DOCS, what="WeasyPrint rendering")
            if not tmp_pdf.is_file() or tmp_pdf.stat().st_size == 0:
                fail("WeasyPrint did not produce a PDF file")
            with tmp_pdf.open("rb") as handle:
                if handle.read(5) != b"%PDF-":
                    fail("WeasyPrint output is not a PDF document")
            os.replace(tmp_pdf, OUTPUT)
        finally:
            tmp_pdf.unlink(missing_ok=True)
    finally:
        WORK_HTML.unlink(missing_ok=True)

    return OUTPUT


def page_count(pdf: Path) -> str | None:
    if shutil.which("pdfinfo") is None:
        return None
    result = subprocess.run(
        ["pdfinfo", str(pdf)], text=True, capture_output=True
    )
    if result.returncode != 0:
        return None
    match = re.search(r"^Pages:\s+(\d+)$", result.stdout, re.MULTILINE)
    return match.group(1) if match else None


def main() -> None:
    require_tools()
    title, body = read_manual()
    check_assets(body)
    pdf = render(title, body)

    pages = page_count(pdf)
    size = pdf.stat().st_size / 1024
    summary = f"built {pdf.relative_to(DOCS.parent)} — {size:.0f} KB"
    if pages:
        summary += f", {pages} A4 pages"
    print(summary)


if __name__ == "__main__":
    main()
