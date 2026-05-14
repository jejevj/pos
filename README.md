# SaaS Application - Full Stack

Full stack SaaS application dengan Laravel backend API dan Vue.js frontend SPA.

## 📁 Project Structure

```
.
├── backend-api/          # Laravel Backend API
│   ├── app/
│   ├── config/
│   ├── database/
│   ├── routes/
│   └── README.md
│
└── frontend-app/         # Vue.js Frontend SPA
    ├── src/
    ├── public/
    └── README.md
```

## 🚀 Quick Start

### Backend Setup

1. Navigate ke backend directory:
```bash
cd backend-api
```

2. Install dependencies:
```bash
composer install
```

3. Setup environment:
```bash
cp .env.example .env
php artisan key:generate
```

4. Setup PostgreSQL database:

**Option 1: Menggunakan Script (Recommended)**
```bash
# Linux/macOS
chmod +x setup-postgres.sh
./setup-postgres.sh

# Windows
setup-postgres.bat
```

**Option 2: Manual Setup**
```bash
# Create database di PostgreSQL
psql -U postgres
CREATE DATABASE saas_app;
\q

# Update .env dengan credentials PostgreSQL Anda
# DB_CONNECTION=pgsql
# DB_HOST=127.0.0.1
# DB_PORT=5432
# DB_DATABASE=saas_app
# DB_USERNAME=postgres
# DB_PASSWORD=your_password

# Run migrations
php artisan migrate
```

5. Generate Swagger docs:
```bash
php artisan l5-swagger:generate
```

6. Start server:
```bash
php artisan serve
```

Backend akan berjalan di `http://localhost:8000`

📖 **Lihat [POSTGRESQL_SETUP.md](POSTGRESQL_SETUP.md) untuk panduan lengkap PostgreSQL**

### Frontend Setup

1. Navigate ke frontend directory:
```bash
cd frontend-app
```

2. Install dependencies:
```bash
npm install
```

3. Setup environment:
```bash
cp .env.example .env
```

4. Start development server:
```bash
npm run dev
```

Frontend akan berjalan di `http://localhost:5173`

## 📚 Documentation

### API Documentation (Swagger)
Akses di: `http://localhost:8000/api/documentation`

### Backend Documentation
Lihat [backend-api/README.md](backend-api/README.md)

### Frontend Documentation
Lihat [frontend-app/README.md](frontend-app/README.md)

## 🔑 Features

### Backend (Laravel)
- ✅ RESTful API
- ✅ Laravel Sanctum Authentication
- ✅ Swagger API Documentation
- ✅ CORS Configuration
- ✅ PostgreSQL Database
- ✅ API Versioning ready
- ✅ Error Handling
- ✅ Request Validation

### Frontend (Vue.js)
- ✅ Vue 3 Composition API
- ✅ Pinia State Management
- ✅ Vue Router
- ✅ Axios HTTP Client
- ✅ Authentication System
- ✅ Protected Routes
- ✅ Responsive Design
- ✅ Token-based Auth

## 🔐 Authentication Flow

1. User register/login melalui frontend
2. Backend mengembalikan token (Laravel Sanctum)
3. Token disimpan di localStorage
4. Setiap request ke API menyertakan token di header
5. Backend memvalidasi token untuk protected routes

## 🛠️ Tech Stack

### Backend
- **Laravel 12** - PHP Framework
- **Laravel Sanctum** - API Authentication
- **L5-Swagger** - API Documentation
- **PostgreSQL** - Database

### Frontend
- **Vue 3** - JavaScript Framework
- **Vite** - Build Tool
- **Vue Router** - Routing
- **Pinia** - State Management
- **Axios** - HTTP Client
- **@vueuse/core** - Composition Utilities

## 📝 API Endpoints

### Authentication
- `POST /api/auth/register` - Register user baru
- `POST /api/auth/login` - Login user
- `POST /api/auth/logout` - Logout user (protected)
- `GET /api/auth/user` - Get user data (protected)

## 🔧 Configuration

### Backend (.env)
```env
APP_URL=http://localhost:8000
FRONTEND_URL=http://localhost:5173
SANCTUM_STATEFUL_DOMAINS=localhost,localhost:5173
```

### Frontend (.env)
```env
VITE_API_URL=http://localhost:8000/api
```

## 🚢 Production Deployment

### Backend
1. Set production environment variables
2. Use proper database (MySQL/PostgreSQL)
3. Enable caching:
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```
4. Setup SSL/HTTPS
5. Configure proper CORS

### Frontend
1. Build production assets:
```bash
npm run build
```
2. Deploy `dist` folder ke hosting (Vercel, Netlify, dll)
3. Update environment variables untuk production API URL

## 📦 Recommended Hosting

### Backend
- Laravel Forge
- DigitalOcean
- AWS EC2
- Heroku

### Frontend
- Vercel (recommended)
- Netlify
- AWS S3 + CloudFront
- Firebase Hosting

## 🔒 Security Best Practices

1. **Environment Variables**: Jangan commit `.env` files
2. **HTTPS**: Gunakan SSL di production
3. **CORS**: Configure proper CORS settings
4. **Rate Limiting**: Implement API rate limiting
5. **Input Validation**: Validate semua user input
6. **SQL Injection**: Gunakan Eloquent ORM
7. **XSS Protection**: Sanitize output
8. **CSRF Protection**: Enabled via Sanctum

## 📈 Scaling Considerations

### Backend
- Implement caching (Redis)
- Database optimization dan indexing
- Queue system untuk heavy tasks
- Load balancing
- CDN untuk static assets

### Frontend
- Code splitting
- Lazy loading
- Image optimization
- Service Workers untuk PWA
- CDN untuk assets

## 🧪 Testing

### Backend
```bash
cd backend-api
php artisan test
```

### Frontend
```bash
cd frontend-app
npm run test:unit
```

## 📄 License

MIT License

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## 📞 Support

Untuk pertanyaan atau dukungan, silakan buka issue di repository ini.
