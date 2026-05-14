#!/bin/bash

# Setup Menu Tables Script
# This script creates menu tables in outlet schemas and seeds initial data

echo "=========================================="
echo "Setup Menu Tables for Outlets"
echo "=========================================="
echo ""

# Check if we're in the right directory
if [ ! -f "artisan" ]; then
    echo "❌ Error: artisan file not found. Please run this script from the backend-api directory."
    exit 1
fi

# Function to check command success
check_status() {
    if [ $? -eq 0 ]; then
        echo "✅ Success"
    else
        echo "❌ Failed"
        exit 1
    fi
}

# Step 1: Create menu tables
echo "Step 1: Creating menu tables in outlet schemas..."
php artisan outlets:create-menu-tables
check_status
echo ""

# Step 2: Seed bahan baku (if not already done)
echo "Step 2: Seeding bahan baku data (includes coffee ingredients)..."
php artisan db:seed --class=BahanBakuSeeder
check_status
echo ""

# Step 3: Seed menu data
echo "Step 3: Seeding menu data (categories and Americano menu)..."
php artisan db:seed --class=MenuSeeder
check_status
echo ""

echo "=========================================="
echo "✅ Setup completed successfully!"
echo "=========================================="
echo ""
echo "You can now:"
echo "1. Access kategori menu at: /outlets/{outletId}/kategori-menu"
echo "2. Access menu at: /outlets/{outletId}/menu"
echo "3. View the sample Americano menu"
echo ""
echo "To verify, run:"
echo "  php artisan tinker"
echo "  > \$outlet = \\App\\Models\\Outlet::first();"
echo "  > DB::statement(\"SET search_path TO {\$outlet->schema_name}, public\");"
echo "  > DB::table('menu')->get();"
echo ""
