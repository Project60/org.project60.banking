#!/usr/bin/env bash
# Inspired by https://github.com/sjungwirth/githooks/blob/949d55e84e92dfd84e9a73a8b7624bb2e4dbc872/bin/git/hooks-wrapper

# This script needs to be symlinked to .git/hooks/<hookname>.

set -e

HOOKNAME=$(basename "$0")
NATIVE_HOOKS_DIR=$(dirname "$0")
CUSTOM_HOOKS_DIR=$(dirname "$(realpath "$0")")/hooks

# Runs all executables in $CUSTOM_HOOKS_DIR/hooks/$HOOKNAME.d and
# $NATIVE_HOOKS_DIR/$HOOKNAME.local if existent.

exitcode=
for hook in "$CUSTOM_HOOKS_DIR/$HOOKNAME.d/"*; do
  if [ -x "$hook" ]; then
    "$hook" "$@" || exitcode=${exitcode:-$?}
  fi
done

if [ -x "$NATIVE_HOOKS_DIR/$HOOKNAME.local" ]; then
  "$NATIVE_HOOKS_DIR/$HOOKNAME.local" "$@" || exitcode=${exitcode:-$?}
fi

# shellcheck disable=SC2086
exit $exitcode
