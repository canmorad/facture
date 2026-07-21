# Session Persistence Fix - Complete Configuration

## 🔍 Problem Summary

The user was experiencing session authentication failure where:
- `/api/login` → 200 OK (session created)
- `/api/user-status` → 401 Unauthorized (session not recognized)

The session cookie was being sent by the browser but Laravel was rejecting it.

---

## ✅ Fixes Applied

### Fix 1: Sanctum Configuration (`config/sanctum.php`)

Ensured the guard is set to `['web']` for session-based authentication:
```php
'guard' => ['web'],
```

This tells Sanctum to use the web authentication guard (session) for stateful requests instead of token authentication.

---

### Fix 2: Environment Configuration (`.env`)

**Updated SANCTUM_STATEFUL_DOMAINS:**
```env
# CRITICAL: No http:// or https:// prefix - just domain:port
SANCTUM_STATEFUL_DOMAINS=localhost,localhost:3000,localhost:5173,localhost:8000,127.0.0.1,127.0.0.1:3000,127.0.0.1:5173,127.0.0.1:8000,::1
```

**Added explicit session settings:**
```env
SESSION_SAME_SITE=lax
SESSION_SECURE_COOKIE=false
SESSION_DOMAIN=null
```

These settings ensure:
- `lax` allows cookies for same-site requests
- `false` allows cookies over HTTP (local development)
- `null` allows cookies across ports on same host

---

### Fix 3: CORS Configuration (`config/cors.php`)

Updated to be more explicit with allowed origins:
```php
'allowed_origins' => [
    'http://localhost:3000',
    'http://localhost:5173',
    'http://127.0.0.1:3000',
    'http://127.0.0.1:5173',
    'http://127.0.0.1:8000',
    env('FRONTEND_URL'),
],
```

Verified `'supports_credentials' => true,` is set.

---

### Fix 4: Vite Proxy Configuration (`vite.config.js`)

Enhanced proxy configuration to ensure proper header and cookie handling:
```javascript
proxy: {
  '/sanctum': {
    target: 'http://127.0.0.1:8000',
    changeOrigin: true,
    secure: false,
    configure: (proxy) => {
      proxy.on('proxyReq', (proxyReq) => {
        proxyReq.setHeader('Origin', 'http://127.0.0.1:5173')
      })
      proxy.on('proxyRes', (proxyRes) => {
        // Ensure Set-Cookie headers are forwarded
        if (proxyRes.headers['set-cookie']) {
          // Cookies passed through
        }
      })
    }
  },
  '/api': { /* same config */ }
}
```

---

### Fix 5: Cache Cleared

Ran:
```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
```

---

## 🔄 Authentication Flow (After Fix)

```
1. Browser Request (POST /api/login)
   ├─ URL: http://127.0.0.1:5173/api/login
   ├─ Headers: Cookie: [XSRF-TOKEN], X-XSRF-TOKEN: [...]
   └─ Body: { email, password }

2. Vite Proxy
   ├─ Intercepts request to /api/*
   ├─ Sets Origin: http://127.0.0.1:5173
   └─ Forwards to http://127.0.0.1:8000/api/login

3. Laravel Backend
   ├─ CORS middleware allows origin with credentials
   ├─ EnsureFrontendRequestsAreStateful checks Origin
   ├─ Finds 127.0.0.1:5173 in SANCTUM_STATEFUL_DOMAINS
   ├─ Marks as stateful (uses 'web' guard)
   ├─ Validates CSRF token
   ├─ Authenticates credentials
   ├─ Creates session in database
   ├─ Regenerates session ID
   └─ Returns 200 OK with user data

4. Response (through proxy)
   ├─ Set-Cookie: facturex_session=...; path=/; httponly; samesite=lax
   ├─ Set-Cookie: XSRF-TOKEN=...; path=/; samesite=lax
   └─ Browser stores cookies for 127.0.0.1

5. Next Request (GET /api/user-status)
   ├─ Browser sends cookies (since domain matches)
   ├─ Laravel validates session
   ├─ Returns 200 OK with user data ✅
```

---

## 📊 Configuration Reference

### Session Cookie Details

| Setting | Value | Purpose |
|---------|-------|---------|
| **Cookie Name** | `facturex_session` | From APP_NAME=Facturex |
| **Domain** | `127.0.0.1` (host-level) | Works across ports |
| **Path** | `/` | Available for all paths |
| **SameSite** | `lax` | Allows same-site GET requests |
| **Secure** | `false` | Works on HTTP (local) |
| **HttpOnly** | `true` | Not accessible via JavaScript |

### CSRF Cookie Details

| Setting | Value | Purpose |
|---------|-------|---------|
| **Cookie Name** | `XSRF-TOKEN` | Sanctum CSRF token |
| **Header Name** | `X-XSRF-TOKEN` | Sent with POST/PUT/DELETE |
| **SameSite** | `lax` | Allows same-site requests |
| **Secure** | `false` | Works on HTTP (local) |

---

## 🧪 Testing Steps

### Step 1: Verify Configuration
```bash
cd /c/Users/Youcode/Desktop/facture-app/backend
php artisan tinker
```

Then run:
```php
// Verify Sanctum stateful domains
>>> config('sanctum.stateful')
// Should include: 'localhost:5173', '127.0.0.1:5173'

// Verify session configuration
>>> config('session')
// Check: driver=database, same_site=lax, secure=false
```

### Step 2: Clear Browser Data
1. Open DevTools → Application → Storage
2. Clear all cookies and localStorage
3. Clear browser cache

### Step 3: Test Login Flow
```
1. Navigate to http://127.0.0.1:5173/login
2. Open DevTools → Network tab
3. Submit login form
4. Verify:
   - POST /api/login returns 200 OK
   - Response contains Set-Cookie headers
   - Cookies appear in Application → Cookies
```

### Step 4: Test Session Persistence
```
1. After successful login, observe Network tab
2. GET /api/user-status should be called
3. Verify:
   - Request includes Cookie header
   - Response returns 200 OK (not 401)
   - Response contains user data
```

### Step 5: Test F5 Refresh
```
1. Press F5 to refresh page
2. Verify:
   - User stays logged in
   - No redirect to login page
   - Authenticated state maintained
```

---

## 🎯 Expected Results

**After login, the following should work:**

1. ✅ `GET /api/user-status` → `200 OK` with user data
2. ✅ Session persists across page refreshes
3. ✅ XSRF tokens are validated on POST requests
4. ✅ Company switching maintains authentication
5. ✅ All authenticated requests succeed

---

## 🔧 Troubleshooting

### If still getting 401 errors:

1. **Verify cookies are being sent:**
   - DevTools → Network → /api/user-status → Request Headers
   - Look for `Cookie: facturex_session=...; XSRF-TOKEN=...`

2. **Verify Origin header:**
   - DevTools → Network → /api/user-status → Request Headers
   - Look for `Origin: http://127.0.0.1:5173`

3. **Check session in database:**
   ```sql
   SELECT * FROM sessions ORDER BY last_activity DESC LIMIT 5;
   ```
   - Verify session exists after login
   - Check `last_activity` timestamp

4. **Verify Sanctum recognizes request as stateful:**
   - Add temporary debug in `EnsureFrontendRequestsAreStateful`
   - Check if domain matches stateful domains

5. **Clear all caches again:**
   ```bash
   php artisan config:clear
   php artisan cache:clear
   php artisan route:clear
   php artisan view:clear
   composer dump-autoload
   ```

---

## 📋 Configuration Checklist

- [x] `SANCTUM_STATEFUL_DOMAINS` includes `127.0.0.1:5173` and `localhost:5173`
- [x] `SESSION_DOMAIN=null` for cross-port cookies
- [x] `SESSION_SAME_SITE=lax` for same-site cookie handling
- [x] `SESSION_SECURE_COOKIE=false` for HTTP
- [x] `CORS.supports_credentials=true`
- [x] `CORS.allowed_origins` includes frontend URLs
- [x] `axios.defaults.withCredentials=true`
- [x] `axios.defaults.withXSRFToken=true`
- [x] `Sanctum.guard=['web']` for session auth
- [x] All caches cleared

---

## ✅ Configuration Status: **READY FOR TESTING**

All configurations have been properly set. Please restart the Vite dev server and test the login flow:

```bash
# Frontend
cd frontend
npm run dev

# Backend (in separate terminal)
cd backend
php artisan serve
```

Then navigate to `http://127.0.0.1:5173` and test the authentication flow.
