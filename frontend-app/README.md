# Vue.js Frontend - SaaS Application

Frontend SPA untuk SaaS Application menggunakan Vue.js 3 dengan best practices.

## Features

- ✅ Vue 3 dengan Composition API
- ✅ Vue Router untuk routing
- ✅ Pinia untuk state management
- ✅ Axios untuk HTTP requests
- ✅ Authentication system dengan token-based auth
- ✅ Route guards untuk protected routes
- ✅ Responsive design
- ✅ Best practices untuk SaaS application

## Tech Stack

- **Vue 3** - Progressive JavaScript Framework
- **Vite** - Next Generation Frontend Tooling
- **Vue Router** - Official router for Vue.js
- **Pinia** - State management
- **Axios** - HTTP client
- **@vueuse/core** - Collection of Vue Composition Utilities

## Requirements

- Node.js >= 18
- npm atau yarn

## Installation

1. Install dependencies:
```bash
npm install
```

2. Copy environment file:
```bash
cp .env.example .env
```

3. Update `.env` dengan API URL yang sesuai:
```env
VITE_API_URL=http://localhost:8000/api
```

## Running the Application

### Development
```bash
npm run dev
```

Application akan berjalan di `http://localhost:5173`

### Build for Production
```bash
npm run build
```

### Preview Production Build
```bash
npm run preview
```

## Project Structure

```
src/
├── assets/          # Static assets (CSS, images)
├── components/      # Reusable Vue components
├── router/          # Vue Router configuration
├── services/        # API services dan utilities
│   └── api.js      # Axios instance dengan interceptors
├── stores/          # Pinia stores
│   └── auth.js     # Authentication store
├── views/           # Page components
│   ├── auth/       # Authentication pages
│   │   ├── LoginView.vue
│   │   └── RegisterView.vue
│   ├── DashboardView.vue
│   └── HomeView.vue
├── App.vue          # Root component
└── main.js          # Application entry point
```

## Best Practices Implemented

### 1. State Management dengan Pinia
- Centralized state management
- Composition API style
- TypeScript support ready
- DevTools integration

### 2. API Service Layer
- Centralized API configuration
- Request/Response interceptors
- Automatic token injection
- Error handling

### 3. Authentication
- Token-based authentication
- Persistent login dengan localStorage
- Automatic token refresh
- Protected routes dengan route guards

### 4. Routing
- Lazy loading untuk code splitting
- Route guards untuk authentication
- Meta fields untuk route configuration

### 5. Component Organization
- Separation of concerns
- Reusable components
- Composition API untuk logic reuse

### 6. Security
- XSS protection
- CSRF protection via Sanctum
- Secure token storage
- Input validation

## Environment Variables

```env
VITE_API_URL=http://localhost:8000/api
```

## API Integration

API service dikonfigurasi di `src/services/api.js` dengan:
- Base URL dari environment variable
- Automatic Bearer token injection
- Response interceptor untuk handle 401 errors
- CORS credentials support

## Authentication Flow

1. User login/register
2. Token disimpan di localStorage
3. Token otomatis ditambahkan ke setiap request
4. Jika token expired (401), user di-redirect ke login
5. Logout menghapus token dan redirect ke login

## Deployment

### Vercel
```bash
npm run build
vercel --prod
```

### Netlify
```bash
npm run build
netlify deploy --prod --dir=dist
```

### Docker
```dockerfile
FROM node:18-alpine as build
WORKDIR /app
COPY package*.json ./
RUN npm install
COPY . .
RUN npm run build

FROM nginx:alpine
COPY --from=build /app/dist /usr/share/nginx/html
EXPOSE 80
CMD ["nginx", "-g", "daemon off;"]
```

## Performance Optimization

1. **Code Splitting**: Route-based lazy loading
2. **Tree Shaking**: Vite automatically removes unused code
3. **Asset Optimization**: Images dan assets di-optimize saat build
4. **Caching**: Proper cache headers untuk static assets
5. **Lazy Loading**: Components di-load on-demand

## Testing

```bash
# Unit tests
npm run test:unit

# E2E tests
npm run test:e2e
```

## Recommended VS Code Extensions

- Volar (Vue Language Features)
- ESLint
- Prettier
- Vue VSCode Snippets

## Additional Features to Implement

- [ ] Multi-tenancy support
- [ ] Role-based access control (RBAC)
- [ ] Real-time notifications
- [ ] File upload functionality
- [ ] Advanced search and filtering
- [ ] Data export functionality
- [ ] User profile management
- [ ] Settings and preferences
- [ ] Dark mode support
- [ ] Internationalization (i18n)

## License

MIT License
