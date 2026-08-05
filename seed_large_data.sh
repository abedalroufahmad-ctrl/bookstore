#!/bin/bash

# Default values
BOOKS=100000
AUTHORS=100000
CHUNK=5000

# Parse arguments if provided
while [[ "$#" -gt 0 ]]; do
    case $1 in
        --books) BOOKS="$2"; shift ;;
        --authors) AUTHORS="$2"; shift ;;
        --chunk) CHUNK="$2"; shift ;;
        *) echo "Unknown parameter passed: $1"; exit 1 ;;
    esac
    shift
done

echo "Starting massive data seeding process..."
echo "Books: $BOOKS"
echo "Authors: $AUTHORS"
echo "Chunk Size: $CHUNK"
echo "----------------------------------------"

cd "$(dirname "$0")/api" || exit

# Run the highly optimized Laravel artisan command
php artisan db:seed-large --books=$BOOKS --authors=$AUTHORS --chunk=$CHUNK

echo "----------------------------------------"
echo "Seeding completed successfully!"
