#!/usr/bin/env bash
# Inspired by https://github.com/sjungwirth/githooks/blob/949d55e84e92dfd84e9a73a8b7624bb2e4dbc872/bin/git/init-hooks

set -e

SCRIPT_DIR=$(dirname "$0")
NATIVE_HOOKS_DIR=$(git -C "$SCRIPT_DIR" rev-parse --show-toplevel)/.git/hooks
CUSTOM_HOOKS_DIR=$SCRIPT_DIR/hooks
HOOKS_WRAPPER=$(realpath -s --relative-to="$NATIVE_HOOKS_DIR" "$SCRIPT_DIR")/hooks-wrapper.sh

cd "$CUSTOM_HOOKS_DIR"
HOOK_DIRS=(*.d)

for hook_dir in "${HOOK_DIRS[@]}"; do
  hookname=${hook_dir:0:-2}
  if [ ! -L "$NATIVE_HOOKS_DIR/$hookname" ]; then
    if [ -f "$NATIVE_HOOKS_DIR/$hookname" ]; then
      mv "$NATIVE_HOOKS_DIR/$hookname" "$NATIVE_HOOKS_DIR/$hookname.local"
    fi
    ln -s "$HOOKS_WRAPPER" "$NATIVE_HOOKS_DIR/$hookname"
  fi
done
