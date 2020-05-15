#!/usr/bin/env bash

# NAME
#     install.sh - Install Travis CI dependencies
#
# SYNOPSIS
#     install.sh
#
# DESCRIPTION
#     Creates the test fixture.

set -ev

cd "$(dirname "$0")" || exit; source _includes.sh

# Exit early in the absence of a fixture.
[[ -d "$ORCA_FIXTURE_DIR" ]] || exit 0

if [[ "$ORCA_JOB" == "D9_READINESS" ]]; then
composer -d"$ORCA_FIXTURE_DIR" require --dev \
  drupal/facets:1.x-dev
else
composer -d"$ORCA_FIXTURE_DIR" require --dev \
  drupal/facets:1.x-dev
fi
