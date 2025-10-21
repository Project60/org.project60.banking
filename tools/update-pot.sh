#!/bin/bash

set -euo pipefail

readonly SCRIPT_PATH="$0"
SCRIPT_NAME=$(basename "$SCRIPT_PATH")
readonly SCRIPT_NAME
SCRIPT_DIR=$(dirname "$SCRIPT_PATH")
readonly SCRIPT_DIR

usage() {
  cat <<EOD
Usage: $SCRIPT_NAME [-h|--help]
  -h, --help
            Print this help.

Extracts translatable strings using civistrings and updates the .pot file.
EOD
}

if [ $# -eq 1 ]; then
  usage
  if [ $1 = -h ] || [ $1 = --help ]; then
    exit
  fi
  exit 1
elif [ $# -gt 1 ]; then
  usage
  exit 1
fi

cd "$SCRIPT_DIR/.."

[ -d l10n ] || mkdir l10n
civistrings -o "l10n/banking.pot" - < <(git ls-files)
