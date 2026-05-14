@echo off
REM PostgreSQL Setup Script for Windows
REM This script helps setup PostgreSQL database

echo ==========================================
echo PostgreSQL Setup for SaaS Application
echo ==========================================
echo.

REM Check if psql is available
where psql >nul 2>nul
if %ERRORLEVEL% NEQ 0 (
    echo [ERROR] PostgreSQL is not installed or not in PATH!
    echo Please install PostgreSQL first from postgresql.org
    echo Or add PostgreSQL bin directory to PATH
    pause
    exit /b 1
)

echo [OK] PostgreSQL is installed
echo.

REM Get database credentials
set /p DB_NAME="Enter database name [saas_app]: "
if "%DB_NAME%"=="" set DB_NAME=saas_app

set /p DB_USER="Enter database user [postgres]: "
if "%DB_USER%"=="" set DB_USER=postgres

set /p DB_PASSWORD="Enter database password: "

REM Create database
echo.
echo Creating database...
set PGPASSWORD=%DB_PASSWORD%
psql -U %DB_USER% -h 127.0.0.1 -c "CREATE DATABASE %DB_NAME%;" 2>nul

if %ERRORLEVEL% EQU 0 (
    echo [OK] Database '%DB_NAME%' created successfully
) else (
    echo [WARNING] Database might already exist or check your credentials
)

REM Update .env file
echo.
echo Updating .env file...

if exist .env (
    REM Backup .env
    copy .env .env.backup >nul
    
    REM Create temporary file with updated values
    (
        for /f "tokens=*" %%a in (.env) do (
            set "line=%%a"
            setlocal enabledelayedexpansion
            if "!line:~0,14!"=="DB_CONNECTION=" (
                echo DB_CONNECTION=pgsql
            ) else if "!line:~0,8!"=="DB_HOST=" (
                echo DB_HOST=127.0.0.1
            ) else if "!line:~0,8!"=="DB_PORT=" (
                echo DB_PORT=5432
            ) else if "!line:~0,12!"=="DB_DATABASE=" (
                echo DB_DATABASE=%DB_NAME%
            ) else if "!line:~0,12!"=="DB_USERNAME=" (
                echo DB_USERNAME=%DB_USER%
            ) else if "!line:~0,12!"=="DB_PASSWORD=" (
                echo DB_PASSWORD=%DB_PASSWORD%
            ) else (
                echo !line!
            )
            endlocal
        )
    ) > .env.tmp
    
    move /y .env.tmp .env >nul
    echo [OK] .env file updated
) else (
    echo [ERROR] .env file not found!
    echo Please copy .env.example to .env first
    pause
    exit /b 1
)

REM Clear Laravel cache
echo.
echo Clearing Laravel cache...
php artisan config:clear
php artisan cache:clear
echo [OK] Cache cleared

REM Run migrations
echo.
set /p RUN_MIGRATIONS="Do you want to run migrations now? (y/n): "

if /i "%RUN_MIGRATIONS%"=="y" (
    echo.
    echo Running migrations...
    php artisan migrate
    
    if %ERRORLEVEL% EQU 0 (
        echo [OK] Migrations completed successfully
    ) else (
        echo [ERROR] Migration failed! Please check your database connection
        pause
        exit /b 1
    )
)

REM Test connection
echo.
echo Testing database connection...
php artisan tinker --execute="echo 'Connected to PostgreSQL successfully';"

echo.
echo ==========================================
echo [OK] PostgreSQL setup completed!
echo ==========================================
echo.
echo Database Details:
echo   - Connection: pgsql
echo   - Host: 127.0.0.1
echo   - Port: 5432
echo   - Database: %DB_NAME%
echo   - Username: %DB_USER%
echo.
echo Next steps:
echo   1. Start your Laravel server: php artisan serve
echo   2. Access API docs: http://localhost:8000/api/documentation
echo.
pause
