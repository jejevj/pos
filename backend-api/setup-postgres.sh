#!/bin/bash

# PostgreSQL Setup Script for SaaS Application
# This script helps setup PostgreSQL database

echo "=========================================="
echo "PostgreSQL Setup for SaaS Application"
echo "=========================================="
echo ""

# Check if PostgreSQL is installed
if ! command -v psql &> /dev/null; then
    echo "❌ PostgreSQL is not installed!"
    echo "Please install PostgreSQL first:"
    echo "  - macOS: brew install postgresql@15"
    echo "  - Ubuntu: sudo apt install postgresql"
    echo "  - Windows: Download from postgresql.org"
    exit 1
fi

echo "✅ PostgreSQL is installed"
echo ""

# Get database credentials
read -p "Enter database name [saas_app]: " DB_NAME
DB_NAME=${DB_NAME:-saas_app}

read -p "Enter database user [postgres]: " DB_USER
DB_USER=${DB_USER:-postgres}

read -sp "Enter database password: " DB_PASSWORD
echo ""

# Create database
echo ""
echo "Creating database..."
PGPASSWORD=$DB_PASSWORD psql -U $DB_USER -h 127.0.0.1 -c "CREATE DATABASE $DB_NAME;" 2>/dev/null

if [ $? -eq 0 ]; then
    echo "✅ Database '$DB_NAME' created successfully"
else
    echo "⚠️  Database might already exist or check your credentials"
fi

# Update .env file
echo ""
echo "Updating .env file..."

if [ -f .env ]; then
    # Backup .env
    cp .env .env.backup
    
    # Update database configuration
    sed -i.tmp "s/DB_CONNECTION=.*/DB_CONNECTION=pgsql/" .env
    sed -i.tmp "s/DB_HOST=.*/DB_HOST=127.0.0.1/" .env
    sed -i.tmp "s/DB_PORT=.*/DB_PORT=5432/" .env
    sed -i.tmp "s/DB_DATABASE=.*/DB_DATABASE=$DB_NAME/" .env
    sed -i.tmp "s/DB_USERNAME=.*/DB_USERNAME=$DB_USER/" .env
    sed -i.tmp "s/DB_PASSWORD=.*/DB_PASSWORD=$DB_PASSWORD/" .env
    
    # Remove temporary files
    rm -f .env.tmp
    
    echo "✅ .env file updated"
else
    echo "❌ .env file not found!"
    echo "Please copy .env.example to .env first"
    exit 1
fi

# Clear Laravel cache
echo ""
echo "Clearing Laravel cache..."
php artisan config:clear
php artisan cache:clear
echo "✅ Cache cleared"

# Run migrations
echo ""
read -p "Do you want to run migrations now? (y/n): " RUN_MIGRATIONS

if [ "$RUN_MIGRATIONS" = "y" ] || [ "$RUN_MIGRATIONS" = "Y" ]; then
    echo ""
    echo "Running migrations..."
    php artisan migrate
    
    if [ $? -eq 0 ]; then
        echo "✅ Migrations completed successfully"
    else
        echo "❌ Migration failed! Please check your database connection"
        exit 1
    fi
fi

# Test connection
echo ""
echo "Testing database connection..."
php artisan tinker --execute="echo 'PostgreSQL Version: ' . DB::select('SELECT version()')[0]->version;"

echo ""
echo "=========================================="
echo "✅ PostgreSQL setup completed!"
echo "=========================================="
echo ""
echo "Database Details:"
echo "  - Connection: pgsql"
echo "  - Host: 127.0.0.1"
echo "  - Port: 5432"
echo "  - Database: $DB_NAME"
echo "  - Username: $DB_USER"
echo ""
echo "Next steps:"
echo "  1. Start your Laravel server: php artisan serve"
echo "  2. Access API docs: http://localhost:8000/api/documentation"
echo ""
