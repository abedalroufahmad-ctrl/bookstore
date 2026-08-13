#!/bin/bash
set -euo pipefail

# Defaults: top up to 1,000,000 books with covers (reuse existing authors).
BOOKS=""
AUTHORS=0
CHUNK=5000
TARGET_BOOKS=1000000

while [[ $# -gt 0 ]]; do
  case $1 in
    --books) BOOKS="$2"; shift 2 ;;
    --authors) AUTHORS="$2"; shift 2 ;;
    --chunk) CHUNK="$2"; shift 2 ;;
    --target-books) TARGET_BOOKS="$2"; shift 2 ;;
    --help|-h)
      echo "Usage: $0 [--target-books N] [--books N] [--authors N] [--chunk N]"
      echo "  --target-books  Insert until total book count reaches N (default: 1000000)"
      echo "  --books         Insert exactly N books (overrides --target-books)"
      echo "  --authors       New authors to create (default: 0 = reuse existing)"
      echo "  --chunk         Bulk insert size (default: 5000)"
      exit 0
      ;;
    *) echo "Unknown parameter: $1"; exit 1 ;;
  esac
done

echo "Starting large data seeding..."
echo "Target books: ${TARGET_BOOKS}"
echo "Authors to create: ${AUTHORS}"
echo "Chunk size: ${CHUNK}"
echo "----------------------------------------"

cd "$(dirname "$0")/api"

ARGS=(--authors="$AUTHORS" --chunk="$CHUNK")
if [[ -n "$BOOKS" ]]; then
  ARGS+=(--books="$BOOKS")
  echo "Inserting exactly ${BOOKS} books..."
else
  ARGS+=(--target-books="$TARGET_BOOKS")
  echo "Topping up to ${TARGET_BOOKS} books..."
fi

php artisan db:seed-large "${ARGS[@]}"

echo "----------------------------------------"
echo "Seeding finished."
