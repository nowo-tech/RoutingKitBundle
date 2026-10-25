#!/usr/bin/env bash
# REQ-TWIG-005 — fail when bundle or demo Twig uses raw <form> / <input> / <textarea> / <select>.
# Every panel form (toolbar actions, import, row deletes, path and redirect forms) is a Symfony Form view rendered with
# form_start / the child loop / form_end. The only exception is listed in ALLOWLIST below.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

# Form-theme exception (REQ-TWIG-005 "Form themes"): the file implements the Symfony `checkbox_row` theme block,
# so it emits the <input> by design.
ALLOWLIST=(
  src/Resources/views/panel/_form_theme.html.twig
)

paths=()
[[ -d src/Resources/views ]] && paths+=(src/Resources/views)
for dir in demo/*/templates; do
  [[ -d "$dir" ]] && paths+=("$dir")
done
if [[ ${#paths[@]} -eq 0 ]]; then
  echo "check-no-raw-html-form: no Twig directories — OK"
  exit 0
fi

matches="$(grep -RInE '<(form|input|textarea|select)[[:space:]>]' "${paths[@]}" --include='*.twig' || true)"
for allowed in "${ALLOWLIST[@]}"; do
  matches="$(printf '%s\n' "$matches" | grep -v -F "${allowed}:" || true)"
done
matches="$(printf '%s' "$matches" | sed '/^$/d')"
if [[ -n "$matches" ]]; then
  echo "ERROR: raw <form> / <input> / <textarea> / <select> in Twig (REQ-TWIG-005):" >&2
  echo "$matches" >&2
  exit 1
fi

echo "check-no-raw-html-form: OK"
