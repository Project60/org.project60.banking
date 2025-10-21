#!/bin/bash
set -euo pipefail

# shellcheck disable=SC2155
readonly PHP=$(which "${PHP:-php}")
# shellcheck disable=SC2155
readonly COMPOSER=$(which "${COMPOSER:-composer}")
# shellcheck disable=SC2155
readonly JQ=$(which "${JQ:-jq}")

if [ -z "$PHP" ]; then
  echo "php not found" >&2
  exit 1
fi

if [ -z "$COMPOSER" ]; then
  echo "composer not found" >&2
  exit 1
fi

if [ -z "$JQ" ]; then
  echo "jq not found" >&2
  exit 1
fi

# shellcheck disable=SC2155
readonly SCRIPT_NAME=$(basename "$0")

usage() {
  cat <<EOD
Usage: $SCRIPT_NAME [-h|--help] [--dry-run] [--no-composer] [--no-pot-update] [version] [develStage] [nextVersion]

Arguments:
  version  Version of the release (e.g. 1.2.3 or 1.2.3-alpha1)
    Default: The version in info.xml without "-dev".
  develStage  Development stage (dev, alpha, beta, stable).
    Default: Detected from version.
  nextVersion  Version after the release.
    Default: Increased version with "dev" as pre-release part.

All values that are determined programmatically have to be confirmed.

Options:
  --no-composer  Do not add composer dependencies.
  --no-pot-update  Do not update .pot file.
  --dry-run  Do nothing, just print what would be done.
  -h|--help  Show this help.

Help:
  This script can be used when creating a new CiviCRM extension release. It will
  update the info.xml, add composer dependencies (if any), make a git commit and
  a git tag for the release. Then the info.xml is updated again using
  nextVersion, composer dependencies are removed, branch alias in composer.json
  gets updated if on main/master branch, and the changes are commited. The
  changes have to be pushed manually.

  Before this is done, the .pot file will be updated if existent. If it differs
  from the currently commited one, no further changes will be made and you must
  first update the translation and push the changes to the repository. This can
  be disabled with the option --no-pot-update if necessary.

  The script has to be executed in the directory of the extension to release.
EOD
}

run() {
  echo "$@"
  [ $DRY_RUN -eq 1 ] || "$@"
}

composer() {
  "$PHP" "$COMPOSER" "$@"
}

detectVersion() {
  local -r version=$(grep '<version>.*</version>' info.xml | sed 's#[[:space:]]*<version>\(.*\)</version>[[:space:]]*#\1#')
  if [ -z "$version" ]; then
    echo "Version not found in info.xml" >&2
    exit 1
  fi

  if ! [[ "$version" =~ ^([0-9]+\.[0-9]+\.[0-9]+)-dev$ ]]; then
    echo "The version number $version doesn't match the form a.b.c-dev" >&2
    exit 1
  fi

  echo "${BASH_REMATCH[1]}"
}

detectDevelStage() {
  local -r version=$1
  if [[ "$version" = *alpha* ]]; then
    echo alpha
  elif [[ "$version" = *beta* ]]; then
    echo beta
  elif [[ "$version" = 0.* ]]; then
    echo dev
  else
    echo stable
  fi
}

detectNextVersion() {
  local -r version=$1
  [[ "$version" =~ ^([0-9]+)\.([0-9]+)\.([0-9]+) ]]

  local -r major=${BASH_REMATCH[1]}
  local minor=${BASH_REMATCH[2]}
  local patch=${BASH_REMATCH[3]}

  if [[ "$version" = *-* ]]; then
    # $version has pre-release
    true
  elif [ "$major" = 0 ]; then
    patch=$((patch+1))
  elif [ "$patch" = 0 ]; then
    minor=$((minor+1))
  else
    patch=$((patch+1))
  fi

  echo "$major.$minor.$patch-dev"
}

validateVersion() {
  local -r version=$1
  if ! [[ "$version" =~ ^[0-9]+\.[0-9]+\.[0-9]+(-(alpha|beta)[1-9][0-9]*)?$ ]]; then
    echo "The version number $version is not a valid release version" >&2
    exit 1
  fi

  if [ -n "$(git tag -l "$version")" ]; then
    echo "git tag $version already exists" >&2
    exit 1
  fi
}

validateDevelopmentStage() {
  local -r develStage=$1
  if ! [[ "$develStage" =~ ^(stable|alpha|beta|dev)$ ]]; then
    echo "$develStage is not a valid development stage" >&2
    exit 1
  fi
}

validateNextVersion() {
  local -r nextVersion=$1
  if ! [[ "$nextVersion" =~ ^[0-9]+\.[0-9]+\.[0-9]+-dev$ ]]; then
    echo "The version number $nextVersion is not a valid next version" >&2
    exit 1
  fi
}

validateInfoXml() {
  if [ ! -f info.xml ]; then
    echo "info.xml not found in working directory" >&2
    exit 1
  fi

  # Ensure info.xml contains elements so that they can be replaced in updateInfoXml.
  if ! grep -q "<version>.*</version>" info.xml; then
    echo "version element not found in info.xml" >&2
    exit 1
  fi

  if ! grep -q "<develStage>.*</develStage>" info.xml; then
    echo "develStage element not found in info.xml" >&2
    exit 1
  fi

  if ! grep -q -e "<releaseDate/>" -e "<releaseDate>.*</releaseDate>" info.xml; then
    echo "releaseDate element not found in info.xml" >&2
    exit 1
  fi
}

updateInfoXml() {
  local -r version=$1
  local -r develStage=$2
  local -r releaseDate=${3:-}

  if [ -z "$releaseDate" ]; then
    local -r releaseDateXml="<releaseDate/>"
  else
    local -r releaseDateXml="<releaseDate>$releaseDate</releaseDate>"
  fi

  sed -i -e "s#<version>.*</version>#<version>$version</version>#g" \
    -e "s#<develStage>.*</develStage>#<develStage>$develStage</develStage>#g" \
    -e "s#<releaseDate>.*</releaseDate>#$releaseDateXml#g" \
    -e "s#<releaseDate/>#$releaseDateXml#g" \
    info.xml
}

hasComposerRequires() {
  if [ ! -f composer.json ]; then
    return 1
  fi

  # All requires that are not "php" or "ext-*".
  requires=$("$JQ" -r '.require|keys|.[]' composer.json 2>/dev/null | sed -e '/^php$/d' -e '/ext-.*/d')
  [ "$requires" != "" ]
}

isVersionLesser() {
  "$PHP" -r "if (version_compare('$1', '$2', '>=')) exit(1);"
}

getMinPhpVersion() {
  local phpVersion=""
  local -r phpConstraint=$("$JQ" --raw-output --monochrome-output .require.php composer.json)
  if [ "$phpConstraint" = "null" ]; then
    echo "PHP version constraint not found in composer.json. Please consider adding it" >&2
    echo -n "Minimal supported PHP version: " >&2
    read -r phpVersion
  else
    local -r oldIfs=$IFS
    IFS=' |'
    local constraint
    for constraint in $phpConstraint; do
      if [[ "$constraint" =~ ^(\^|~|>=)([0-9]+(\.[0-9]+(\.[0-9]+)?)?)$ ]]; then
        if [ -z "$phpVersion" ] || isVersionLesser "${BASH_REMATCH[2]}" "$phpVersion"; then
          phpVersion=${BASH_REMATCH[2]}
        fi
      fi
    done
    IFS=$oldIfs

    if [ -n "$phpVersion" ]; then
      echo -n "Minimal supported PHP version [$phpVersion]: " >&2
      read -r input
      if [ "$input" != "" ]; then
        phpVersion=$input
      fi
    else
      echo "Minimal supported PHP version could not be detected from composer version constraint. (Supported operators: ^, ~, >=, |)" >&2
      echo -n "Minimal supported PHP version: " >&2
      read -r phpVersion
    fi
  fi

  echo "$phpVersion"
}

validateMinPhpVersion() {
  if ! [[ "$1" =~ ^[0-9]+(\.[0-9]+(\.[0-9]+)?)?$ ]]; then
    echo "$1 is not a supported minimal PHP version" >&2
    exit 1
  fi
}

updatePot() {
  local -r potFiles=(l10n/*.pot)
  if [ ${#potFiles[@]} -ge 1 ] && [ -e "${potFiles[0]}" ] && [ -x tools/update-pot.sh ]; then
    echo "Update .pot file"
    tools/update-pot.sh
    if ! git diff --no-patch --exit-code "${potFiles[*]}"; then
      echo ".pot file has changed. Please update the translation and push changes to the repository." >&2
      exit 1
    fi
  fi
}

main() {
  DRY_RUN=0
  local noComposer=0
  local noPotUpdate=0

  while [ $# -gt 0 ]; do
    case $1 in
      -h|--help)
        usage
        exit 0
        ;;

      --dry-run)
        DRY_RUN=1
        shift
        ;;

      --no-composer)
        noComposer=1
        shift
        ;;

      --no-pot-update)
        noPotUpdate=1
        shift
        ;;

      *)
        break
        ;;
    esac
  done

  if [ $# -gt 3 ]; then
    usage >&2
    exit 1
  fi

  validateInfoXml

  local version
  local nextVersion
  local develStage

  if [ $# -ge 1 ]; then
    version=$1
  else
    version=$(detectVersion)
    echo -n "Version [$version]: "
    read -r input
    if [ -n "$input" ]; then
      version=$input
    fi
  fi
  validateVersion "$version"

  if [ $# -ge 2 ]; then
    develStage=$2
  else
    develStage=$(detectDevelStage "$version")
    echo -n "Development stage [$develStage]: "
    read -r input
    if [ -n "$input" ]; then
      develStage=$input
    fi
  fi
  validateDevelopmentStage "$develStage"

  if [ $# -ge 3 ]; then
    nextVersion=$3
  else
    nextVersion=$(detectNextVersion "$version")
    echo -n "Next version [$nextVersion]: "
    read -r input
    if [ -n "$input" ]; then
      nextVersion=$input
    fi
  fi
  validateNextVersion "$nextVersion"

  if [ $noComposer -eq 0 ] && ! hasComposerRequires; then
    noComposer=1
  fi

  if [ $noComposer -eq 0 ]; then
    local -r minPhpVersion=$(getMinPhpVersion)
    validateMinPhpVersion "$minPhpVersion"
  fi

  if [ $noPotUpdate -eq 0 ]; then
    updatePot
  fi

  local -r releaseDate=$(date +%Y-%m-%d)
  run updateInfoXml "$version" "$develStage" "$releaseDate"
  run git add info.xml

  if [ $noComposer -eq 0 ]; then
    local -r previousPlatformPhp=$(composer config platform.php 2>/dev/null ||:)
    run composer config platform.php "$minPhpVersion"
    run composer update --no-dev --optimize-autoloader
    if [ -n "$previousPlatformPhp" ]; then
      run composer config platform.php "$previousPlatformPhp"
    else
      run composer config --unset platform.php
    fi
    run git add -f composer.lock vendor
  fi

  run git commit -m "Set version to $version"
  run git tag "$version"

  run updateInfoXml "$nextVersion" "dev"
  run git add info.xml

  if [ $noComposer -eq 0 ]; then
    run git rm -r composer.lock vendor
  fi

  if [ -f composer.json ]; then
    local -r branch=$(git branch --show-current)
    if [ "$branch" = "main" ] || [ "$branch" = "master" ]; then
      [[ "$nextVersion" =~ ^([0-9]+\.[0-9]+)\.[0-9]+ ]]
      local -r alias=${BASH_REMATCH[1]}.x-dev
      run composer config "extra.branch-alias.dev-$branch" "$alias"
      run git add composer.json
    fi
  fi

  run git commit -m "Set version to $nextVersion"

  echo ""
  echo "Push changes with: git push && git push --tags"
}

main "$@"
