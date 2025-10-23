#!/bin/sh

. git-sh-setup
if [ -x "$GIT_DIR/hooks/pre-commit" ]; then
  exec "$GIT_DIR/hooks/pre-commit"
fi
