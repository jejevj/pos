# Laravel Backend API - SaaS Application

Backend API untuk SaaS Application menggunakan Laravel dengan dokumentasi Swagger.

## Features

- ✅ RESTful API dengan Laravel 12
- ✅ Authentication menggunakan Laravel Sanctum
- ✅ API Documentation dengan Swagger (L5-Swagger)
- ✅ CORS Configuration untuk SPA
- ✅ PostgreSQL Database
- ✅ Best practices untuk API development

## Requirements

- PHP >= 8.2
- Composer
- PostgreSQL >= 12

## Installation

1. Install dependencies:
```bash
composer install
```

2. Copy environment file:
```bash
cp .env.example .env
```

3. Generate application key:
```bash
php artisan key:generate
```

4. Setup PostgreSQL database:

**Option 1: Using Setup Script (Recommended)**
```bash
# Linux/macOS
chmod +x setup-postgres.sh
./setup-postgres.sh

# Windows
setup-postgres.bat
```

**Option 2: Manual Setup**
```bash
# Create database in PostgreSQL
psql -U postgres
CREATE DATABASE saas_app;
\q

# Update .env with your PostgreSQL credentials
# DB_CONNECTION=pgsql
# DB_HOST=127.0.0.1
# DB_PORT=5432
# DB_DATABASE=saas_app
# DB_USERNAME=postgres
# DB_PASSWORD=your_password

# Run migrations
php artisan migrate
```

5. Generate Swagger documentation:
```bash
php artisan l5-swagger:generate
```

📖 **See [../POSTGRESQL_SETUP.md](../POSTGRESQL_SETUP.md) for complete PostgreSQL setup guide**

## Running the Application

Start the development server:
```bash
php artisan serve
```

API akan berjalan di `http://localhost:8000`

## API Documentation

Setelah server berjalan, akses dokumentasi Swagger di:
```
http://localhost:8000/api/documentation
```

## API Endpoints

### Authentication

- `POST /api/auth/register` - Register user baru
- `POST /api/auth/login` - Login user
- `POST /api/auth/logout` - Logout user (requires authentication)
- `GET /api/auth/user` - Get authenticated user data (requires authentication)

## Configuration

### CORS Settings

CORS dikonfigurasi di `config/cors.php` untuk mendukung SPA frontend.

Default frontend URL: `http://localhost:5173`

### Sanctum Configuration

Sanctum stateful domains dikonfigurasi di `.env`:
```
SANCTUM_STATEFUL_DOMAINS=localhost,localhost:5173,127.0.0.1,127.0.0.1:5173
```

## Database

Aplikasi ini menggunakan PostgreSQL sebagai database.

### Configuration

Database dikonfigurasi di `.env`:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=saas_app
DB_USERNAME=postgres
DB_PASSWORD=your_password
```

### Setup PostgreSQL

Lihat [POSTGRESQL_SETUP.md](../POSTGRESQL_SETUP.md) untuk panduan lengkap instalasi dan konfigurasi PostgreSQL.

### Quick Setup

```bash
# Create database
psql -U postgres
CREATE DATABASE saas_app;
\q

# Run migrations
php artisan migrate
```

## Testing

Run tests:
```bash
php artisan test
```

## Best Practices

1. **API Versioning**: Gunakan versioning untuk API (contoh: `/api/v1/...`)
2. **Rate Limiting**: Implementasikan rate limiting untuk security
3. **Validation**: Gunakan Form Requests untuk validation yang kompleks
4. **Resources**: Gunakan API Resources untuk transform data
5. **Error Handling**: Implementasikan global error handler
6. **Logging**: Log semua error dan aktivitas penting
7. **Security**: Selalu validate input dan sanitize output

## Production Deployment

1. Set `APP_ENV=production` di `.env`
2. Set `APP_DEBUG=false`
3. Optimize aplikasi:
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
composer install --optimize-autoloader --no-dev
```

4. Setup proper database (MySQL/PostgreSQL)
5. Configure proper CORS settings
6. Setup SSL/HTTPS
7. Implement proper backup strategy

## License

MIT License
