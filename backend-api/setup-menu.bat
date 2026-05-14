@echo off
REM Setup Menu Tables Script for Windows
REM This script creates menu tables in outlet schemas and seeds initial data

echo ==========================================
echo Setup Menu Tables for Outlets
echo ==========================================
echo.

REM Check if we're in the right directory
if not exist "artisan" (
    echo Error: artisan file not found. Please run this script from the backend-api directory.
    exit /b 1
)

REM Step 1: Create bahan baku tables (must come before menu so menu_bahan_baku FKs resolve)
echo Step 1: Creating bahan baku tables in outlet schemas...
php artisan outlets:create-bahan-baku-tables
if errorlevel 1 (
    echo Failed
    exit /b 1
)
echo Success
echo.

REM Step 2: Create menu tables
echo Step 2: Creating menu tables in outlet schemas...
php artisan outlets:create-menu-tables
if errorlevel 1 (
    echo Failed
    exit /b 1
)
echo Success
echo.

REM Step 3: (Optional) Create transaction tables. Safe to run before or after menu;
REM the order_items -> menu FK is attached when both tables exist.
echo Step 3: Creating transaction tables in outlet schemas...
php artisan outlets:create-transaction-tables
if errorlevel 1 (
    echo Failed
    exit /b 1
)
echo Success
echo.

REM Step 4: Seed bahan baku
echo Step 4: Seeding bahan baku data (includes coffee ingredients)...
php artisan db:seed --class=BahanBakuSeeder
if errorlevel 1 (
    echo Failed
    exit /b 1
)
echo Success
echo.

REM Step 5: Seed menu data
echo Step 5: Seeding menu data (categories and Americano menu)...
php artisan db:seed --class=MenuSeeder
if errorlevel 1 (
    echo Failed
    exit /b 1
)
echo Success
echo.

echo ==========================================
echo Setup completed successfully!
echo ==========================================
echo.
echo You can now:
echo 1. Access kategori menu at: /outlets/{outletId}/kategori-menu
echo 2. Access menu at: /outlets/{outletId}/menu
echo 3. View the sample Americano menu
echo.
echo To verify, run:
echo   php artisan tinker
echo   ^> $outlet = \App\Models\Outlet::first();
echo   ^> DB::statement("SET search_path TO {$outlet-^>schema_name}, public");
echo   ^> DB::table('menu')-^>get();
echo.

pause
