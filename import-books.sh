#!/bin/bash
# Import books from an ODS spreadsheet into the bookstore database.
# Usage: ./import-books.sh <path-to-file.ods> [options]
#
# The ODS file should have a header row with columns such as:
#   title, author, category, link (URL to book page), isbn, price, stock, description, publisher, pages, year,
#   size, weight, edition
#
# Edition format: "year\edition_number" (e.g. "2024\3" = publish year 2024, edition number 3).
# Size: e.g. "17x24" or "8.5x11". Weight: in kg (e.g. "0.5" or "0.5 kg").
#
# Column names are auto-detected (supports English and Arabic).
# Covers are fetched from the book link (og:image or cover img) unless --skip-cover is used.
#
# Examples:
#   ./import-books.sh books.ods
#   ./import-books.sh books.ods --skip-cover
#   ./import-books.sh books.ods --dry-run
#   ./import-books.sh books.ods --warehouse=WAREHOUSE_ID

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
API_DIR="${SCRIPT_DIR}/api"
ODS_FILE="${1:-}"

if [ -z "$ODS_FILE" ]; then
    echo "Usage: $0 <path-to-file.ods> [options]"
    echo ""
    echo "Options (passed to artisan):"
    echo "  --clear               Delete all books, authors, and categories before importing"
    echo "  --skip-cover          Skip downloading cover images from book links"
    echo "  --dry-run             Show what would be imported without making changes"
    echo "  --warehouse=ID        Use specific warehouse (default: first warehouse)"
    echo "  --title-col=N         Column index for title (0-based)"
    echo "  --author-col=N        Column index for author"
    echo "  --category-col=N      Column index for category"
    echo "  --link-col=N          Column index for book URL"
    echo "  --isbn-col=N          Column index for ISBN"
    echo "  --price-col=N         Column index for price"
    echo "  --size-col=N          Column index for size (e.g. 17x24)"
    echo "  --weight-col=N        Column index for weight (kg)"
    echo "  --edition-col=N       Column index for edition (format: year\\edition_number or Arabic: الأولى=1)"
    echo "  --pages-col=N         Column index for page count"
    echo "  --default-category=X  Default category when not found (default: General)"
    echo ""
    echo "Example: $0 ./my-books.ods"
    exit 1
fi

if [ ! -f "$ODS_FILE" ]; then
    echo "Error: File not found: $ODS_FILE"
    exit 1
fi

# Resolve path if relative
if [[ "$ODS_FILE" != /* ]]; then
    ODS_FILE="$(cd "$(dirname "$ODS_FILE")" && pwd)/$(basename "$ODS_FILE")"
fi

cd "$API_DIR"

if [ ! -f "artisan" ]; then
    echo "Error: Laravel API not found at $API_DIR"
    exit 1
fi

echo "Importing books from: $ODS_FILE"
echo ""

php artisan books:import-ods "$ODS_FILE" "${@:2}"
