# Import Books from ODS

Import books from an OpenDocument Spreadsheet (`.ods`) file into the bookstore database.

## Requirements

- At least one **warehouse** must exist (create via Admin → Warehouses)
- ODS file with a header row and data rows

## ODS Column Format

The first row should contain column headers. Column names are auto-detected (case-insensitive). Supported aliases:

| Field      | Aliases (examples) |
|-----------|---------------------|
| title     | title, اسم الكتاب, عنوان, book, name |
| author    | author, المؤلف, مؤلف, authors |
| category  | category, التصنيف, تصنيف, subject, قسم, نوع |
| link      | link, url, رابط, book link |
| isbn      | isbn, رقم isbn, رقم الكتاب |
| price     | price, السعر, cost, الثمن |
| stock     | stock, quantity, الكمية, المخزون |
| description | description, الوصف, desc, نبذة |
| publisher | publisher, الناشر, دار النشر |
| pages     | pages, الصفحات |
| year      | year, سنة, publish_year, سنة النشر |

**Required:** `title`  
**Optional:** `author`, `category`, `link`, `isbn`, `price`  
**If category is missing:** Uses "General" (or `--default-category`) and creates it automatically.

## Cover Images

If the `link` column contains a URL to the book page, the importer will:

1. Fetch the page HTML
2. Look for `og:image` meta tag (common on product pages)
3. Or look for `<img>` with class/id containing "cover"
4. Download the image and store it in `storage/app/public/covers/`

Use `--skip-cover` to skip this step (faster import, no covers).

## Usage

```bash
# Import from ODS file
./import-books.sh path/to/books.ods

# Dry run (preview without importing)
./import-books.sh books.ods --dry-run

# Clear existing data first, then import
./import-books.sh books.ods --clear

# Skip cover download (faster)
./import-books.sh books.ods --skip-cover

# Use specific warehouse
./import-books.sh books.ods --warehouse=WAREHOUSE_MONGODB_ID

# Override column mapping (0-based indices) if auto-detect fails
./import-books.sh books.ods --title-col=0 --author-col=1 --category-col=2 --link-col=3 --isbn-col=4 --price-col=5

# Custom default category when column is empty
./import-books.sh books.ods --default-category="غير مصنف"
```

Or run the artisan command directly:

```bash
cd api
php artisan books:import-ods path/to/books.ods
```

## Behavior

- **Authors** and **categories** are created automatically if they don't exist
- **ISBN** must be unique; duplicates are skipped
- If ISBN is missing, a unique placeholder is generated
- Default stock: 10 if not specified
- Default price: 0 if not specified
