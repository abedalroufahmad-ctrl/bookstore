#!/bin/bash
# Clean academic titles (د.، أ.د.، أ.، الدكتور) from author names in the bookstore database.
# Usage:
#   ./clean-author-titles.sh          # Apply changes
#   ./clean-author-titles.sh --dry-run # Preview changes only

cd "$(dirname "$0")/api" || exit 1

php artisan authors:clean-titles "$@"
