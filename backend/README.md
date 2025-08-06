# Hysterka Backend

Modern PHP backend pro multi-user Hysterka s SSO podporou a pokročilými bezpečnostnými funkcemi.

## 🚀 Technológie

- **PHP 8.3+** - Najnovšia stabilná verzia
- **Laravel 11** - Modern PHP framework
- **MySQL/PostgreSQL** - Databáza
- **Redis** - Cache a sessions
- **Laravel Sanctum** - API autentifikácia
- **Laravel Socialite** - SSO (Google, Facebook, Apple)
- **Spatie Packages** - Permissions, Activity Log, Backup
- **Laravel Horizon** - Queue monitoring
- **Laravel Telescope** - Debugging

## 🔐 Bezpečnostné funkcie

### Autentifikácia & Autorizácia
- **Multi-provider SSO** (Google, Facebook, Apple/iCloud)
- **JWT tokens** cez Laravel Sanctum
- **Role-based permissions** (Spatie Permission)
- **Rate limiting** na všetkých endpointoch
- **CSRF protection**
- **XSS protection**

### Dátová bezpečnosť
- **Encrypted sessions** (Redis)
- **Hashed passwords** (Bcrypt/Argon2)
- **SQL injection protection** (Eloquent ORM)
- **Input validation** a sanitization
- **Activity logging** všetkých akcií
- **Soft deletes** pre dôležité dáta

### Infraštruktúra
- **HTTPS only** v produkcii
- **Secure headers** (HSTS, CSP, atď.)
- **Environment-based config**
- **Automated backups** (S3)
- **Health monitoring**

## 📦 Inštalácia

### 1. Composer Dependencies
```bash
cd backend
composer install
```

### 2. Environment Setup
```bash
cp env.example .env
php artisan key:generate
```

### 3. Database Setup
```bash
php artisan migrate
php artisan db:seed
```

### 4. Storage & Cache
```bash
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 5. Queue Workers
```bash
php artisan horizon
# alebo
php artisan queue:work --queue=crawler,default
```

## 🔧 Konfigurácia

### OAuth Providers

#### Google OAuth
1. Vytvorte projekt v [Google Cloud Console](https://console.cloud.google.com/)
2. Aktivujte Google+ API
3. Vytvorte OAuth 2.0 credentials
4. Nastavte authorized redirect URI: `https://yourdomain.com/api/v1/auth/google/callback`
5. Pridajte do `.env`:
```env
GOOGLE_CLIENT_ID=your_client_id
GOOGLE_CLIENT_SECRET=your_client_secret
```

#### Facebook OAuth
1. Vytvorte aplikáciu na [Facebook Developers](https://developers.facebook.com/)
2. Pridajte Facebook Login product
3. Nastavte Valid OAuth Redirect URI
4. Pridajte do `.env`:
```env
FACEBOOK_CLIENT_ID=your_app_id
FACEBOOK_CLIENT_SECRET=your_app_secret
```

#### Apple OAuth
1. Registrujte sa v [Apple Developer Program](https://developer.apple.com/)
2. Vytvorte App ID a Service ID
3. Konfigurujte Sign in with Apple
4. Pridajte do `.env`:
```env
APPLE_CLIENT_ID=your_service_id
APPLE_CLIENT_SECRET=your_client_secret
```

### Database
```env
DB_CONNECTION="pgsql"
DB_HOST="postgresql.r6.websupport.sk"
DB_PORT="5432"
DB_DATABASE="bazos_crawler"
DB_USERNAME="MD4TKVMf"
DB_PASSWORD="<J7Hf,m0cj6m-:DA3j1:"
```

### Redis
```env
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

### Email (SMTP)
```env
MAIL_MAILER=smtp
MAIL_HOST="smtp.m1.websupport.sk"
MAIL_PORT="465"
MAIL_USERNAME="no-reply@hysterka.com"
MAIL_PASSWORD="$}**2]V5@-te?9~&:w,l"
MAIL_ENCRYPTION="ssl"
MAIL_FROM_ADDRESS="no-reply@hysterka.com"
MAIL_FROM_NAME="${APP_NAME}"
```

### File Storage (S3)
```env
AWS_ACCESS_KEY_ID="QOZP4V2HY8U2HV6EB4VC"
AWS_SECRET_ACCESS_KEY="fBVGmP0XgcUsXFm06cVxUWT5vHi0Y6IMOolyZwKb"
AWS_DEFAULT_REGION="eu-central-1"
AWS_BUCKET="hysterka"
```

## 🔄 API Endpoints

### Autentifikácia
- `GET /api/v1/auth/{provider}/redirect` - OAuth redirect
- `GET /api/v1/auth/{provider}/callback` - OAuth callback
- `POST /api/v1/auth/logout` - Logout
- `POST /api/v1/auth/refresh` - Refresh token
- `GET /api/v1/auth/me` - User info

### Crawler Searches
- `GET /api/v1/searches` - List searches
- `POST /api/v1/searches` - Create search
- `GET /api/v1/searches/{id}` - Get search
- `PUT /api/v1/searches/{id}` - Update search
- `DELETE /api/v1/searches/{id}` - Delete search
- `POST /api/v1/searches/{id}/toggle` - Toggle active
- `POST /api/v1/searches/{id}/run` - Manual run

### Found Items
- `GET /api/v1/items` - List items
- `GET /api/v1/items/{id}` - Get item
- `POST /api/v1/items/{id}/favorite` - Toggle favorite
- `GET /api/v1/favorites` - List favorites

### User Management
- `GET /api/v1/user/profile` - Get profile
- `PUT /api/v1/user/profile` - Update profile
- `POST /api/v1/user/avatar` - Upload avatar
- `GET /api/v1/user/settings` - Get settings
- `PUT /api/v1/user/settings` - Update settings

## 🎯 Funkcie

### Multi-User Support
- **User roles**: Admin, Pro, Premium, Free
- **Search quotas**: 5/20/50 searches podľa plánu
- **Personal dashboards**
- **Isolated user data**

### Crawler Engine
- **Concurrent crawling** s rate limitingom
- **Smart parsing** Bazos.sk stránok
- **Image downloading** a storage
- **Availability checking**
- **Duplicate detection**

### Notifications
- **Email notifications** pre nové items
- **Push notifications** (cez Pusher)
- **Weekly summaries**
- **Price drop alerts**

### Advanced Features
- **Search filters** (keywords, price, location)
- **Item favorites** a hiding
- **Export functionality** (JSON, CSV)
- **Activity logging**
- **System monitoring**

## 🚀 Deployment

### Docker
```yaml
version: '3.8'
services:
  app:
    build: .
    ports:
      - "8000:8000"
    environment:
      - APP_ENV=production
    volumes:
      - ./storage:/var/www/storage
```

### Production Checklist
- [ ] HTTPS konfigurované
- [ ] Environment variables nastavené
- [ ] Database migrations spustené
- [ ] Queue workers spustené
- [ ] Cron jobs nakonfigurované
- [ ] Backups aktivované
- [ ] Monitoring nastavené

## 📊 Monitoring

### Health Checks
```bash
php artisan health:check
```

### Queue Monitoring
```bash
php artisan horizon:status
```

### Logs
```bash
tail -f storage/logs/laravel.log
```

## 🔒 Security Best Practices

1. **Pravidelné updates** PHP a dependencies
2. **Environment variables** pre sensitive data
3. **Rate limiting** na všetkých endpointoch
4. **Input validation** a sanitization
5. **HTTPS only** v produkcii
6. **Regular backups**
7. **Activity monitoring**
8. **Security headers**

## 📄 Licencia

MIT License - pozri [LICENSE](LICENSE) súbor.

## 🤝 Podpora

Pre technickú podporu alebo otázky vytvorte issue v GitHub repozitári.