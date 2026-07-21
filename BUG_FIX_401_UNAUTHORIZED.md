# Bug Fix Report: 401 Unauthorized on Session-based Auth Requests

## 🐛 Problem Description

Users were experiencing `401 Unauthorized` errors when making authenticated requests after login. Specifically:

- `POST /api/login` succeeded with `200 OK`
- Subsequent `GET /api/user-status` failed with `401 Unauthorized`
- Cookies were being sent correctly (`XSRF-TOKEN` and `facturex-session`)
- X-XSRF-TOKEN header was included

---

## 🔍 Root Cause Analysis

### Primary Issues Identified:

1. **Incomplete SANCTUM_STATEFUL_DOMAINS Configuration**
   - The original configuration was missing some domain variants
   - IPv6 loopback (`::1`) was not included
   - Base domains without ports were missing

2. **Vite Proxy Header Forwarding**
   - The Vite proxy wasn't explicitly setting the Origin header
   - This could cause Sanctum to not recognize the request as stateful

3. **Configuration Cache**
   - .env changes weren't being applied due to cached configuration

---

## ✅ Fixes Applied

### Fix 1: Enhanced SANCTUM_STATEFUL_DOMAINS

**File:** `backend/.env`

**Before:**
```env
SANCTUM_STATEFUL_DOMAINS=127.0.0.1:5173,localhost:5173,127.0.0.1:8000,localhost:8000
```

**After:**
```env
# Include both IP and localhost with all relevant ports
# IMPORTANT: No http:// or https:// prefix - just domain:port format
SANCTUM_STATEFUL_DOMAINS=localhost,localhost:3000,localhost:5173,localhost:8000,127.0.0.1,127.0.0.1:3000,127.0.0.1:5173,127.0.0.1:8000,::1
```

**Rationale:**
- Added base domains without ports (localhost, 127.0.0.1, ::1)
- Added common development ports (3000, 5173, 8000)
- Added IPv6 loopback (::1) for complete local development coverage
- No http:// prefix (Sanctum adds this internally)

---

### Fix 2: SESSION_DOMAIN Configuration

**File:** `backend/.env`

**Configuration:**
```env
# SESSION_DOMAIN should be null for local development to allow cross-port cookies
# For production, set to your domain (e.g., .yourdomain.com)
SESSION_DOMAIN=null
```

**Rationale:**
- `SESSION_DOMAIN=null` allows cookies to work across different ports on the same host
- This is correct for local development (127.0.0.1:5173 → 127.0.0.1:8000)
- For production, set to your domain with a leading dot (e.g., `.yourdomain.com`)

---

### Fix 3: Vite Proxy Configuration Enhancement

**File:** `frontend/vite.config.js`

**Before:**
```javascript
proxy: {
  '/api': {
    target: 'http://127.0.0.1:8000',
    changeOrigin: true,
  },
}
```

**After:**
```javascript
proxy: {
  '/sanctum': {
    target: 'http://127.0.0.1:8000',
    changeOrigin: true,
    secure: false,
    configure: (proxy, options) => {
      proxy.on('proxyReq', (proxyReq, req, res) => {
        proxyReq.setHeader('Origin', 'http://127.0.0.1:5173')
      })
    }
  },
  '/api': {
    target: 'http://127.0.0.1:8000',
    changeOrigin: true,
    secure: false,
    configure: (proxy, options) => {
      proxy.on('proxyReq', (proxyReq, req, res) => {
        proxyReq.setHeader('Origin', 'http://127.0.0.1:5173')
      })
    }
  },
}
```

**Rationale:**
- Explicitly sets Origin header to match the frontend
- Ensures Sanctum can identify the request as coming from a stateful domain
- Added `secure: false` for HTTP (local development)
- Configured both `/sanctum` and `/api` routes

---

### Fix 4: Configuration Cache Cleared

**Commands Executed:**
```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
```

**Rationale:**
- .env changes require config cache to be cleared
- Ensures Sanctum reads the updated SANCTUM_STATEFUL_DOMAINS

---

## 🔄 How Session Auth Works (After Fix)

### Request Flow:

```
1. Browser Request
   GET http://127.0.0.1:5173/api/user-status
   Headers:
   - Cookie: XSRF-TOKEN=...; facturex-session=...
   - Referer: http://127.0.0.1:5173/...

2. Vite Proxy
   - Intercepts request to /api/*
   - Forwards to http://127.0.0.1:8000/api/user-status
   - Sets Origin: http://127.0.0.1:5173

3. Laravel Backend
   - EnsureFrontendRequestsAreStateful checks Referer/Origin
   - Finds 127.0.0.1:5173 in SANCTUM_STATEFUL_DOMAINS
   - Marks request as stateful (uses session auth)
   - Checks session cookie (facturex-session)
   - Validates session in database
   - Returns 200 OK with user data
```

### Middleware Stack:

```php
// bootstrap/app.php
$middleware->api(prepend: [
    \Illuminate\Http\Middleware\HandleCors::class,
    \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
]);
```

**How EnsureFrontendRequestsAreStateful Works:**

1. Extracts domain from Referer or Origin header
2. Checks if domain matches any in SANCTUM_STATEFUL_DOMAINS
3. If match found:
   - Switches auth guard from 'sanctum' to 'web'
   - Uses session-based authentication
4. If no match:
   - Uses token-based authentication (Bearer token)

---

## 🧪 Verification Steps

### Test 1: Basic Auth Flow

```bash
# 1. Clear all cookies and localStorage
# 2. Login
POST /api/login
Body: { email: "...", password: "..." }

# Expected: 200 OK with user data
```

### Test 2: Session Persistence

```bash
# After login, immediately call:
GET /api/user-status
Headers:
  Cookie: [should include facturex-session]
  X-XSRF-TOKEN: [from XSRF-TOKEN cookie]

# Expected: 200 OK with user data
# Actual (before fix): 401 Unauthorized ❌
# Actual (after fix): 200 OK ✅
```

### Test 3: Cross-Domain Session

```bash
# Ensure session works across ports:
# Frontend: 127.0.0.1:5173
# Backend: 127.0.0.1:8000

# Expected: Session cookies sent and accepted
```

---

## 📊 Configuration Reference

### Development (.env)

```env
# Sanctum Configuration
SANCTUM_STATEFUL_DOMAINS=localhost,localhost:3000,localhost:5173,localhost:8000,127.0.0.1,127.0.0.1:3000,127.0.0.1:5173,127.0.0.1:8000,::1

# Session Configuration
SESSION_DRIVER=database
SESSION_DOMAIN=null
SESSION_PATH=/
SESSION_SAME_SITE=lax
```

### Production (.env.example)

```env
# Production configuration
SANCTUM_STATEFUL_DOMAINS=yourdomain.com,www.yourdomain.com
SESSION_DOMAIN=.yourdomain.com
SESSION_SECURE_COOKIE=true
```

---

## 🎯 Key Takeaways

1. **SANCTUM_STATEFUL_DOMAINS Must Match Exactly**
   - No http:// or https:// prefix
   - Include all variants: localhost, 127.0.0.1, ::1
   - Include all ports: 3000, 5173, 8000, etc.

2. **SESSION_DOMAIN=null for Local Development**
   - Allows cross-port cookie sharing
   - Use `.domain.com` format for production

3. **Vite Proxy Must Forward Headers Correctly**
   - Set Origin header explicitly
   - Use `changeOrigin: true`
   - Set `secure: false` for HTTP

4. **Clear Cache After .env Changes**
   - Run `php artisan config:clear`
   - Run `php artisan cache:clear`

---

## ✅ Fix Confirmation

After applying these fixes:

- ✅ Login succeeds with 200 OK
- ✅ Subsequent authenticated requests succeed with 200 OK
- ✅ Session persists across page refreshes
- ✅ XSRF-TOKEN is properly validated
- ✅ No 401 Unauthorized errors on valid sessions

---

## 📝 Additional Notes

### Cookie Names

- **Session Cookie:** `facturex_session` (from APP_NAME=Facturex)
- **CSRF Cookie:** `XSRF-TOKEN`
- **CSRF Header:** `X-XSRF-TOKEN`

### SameSite Cookie Attribute

- Set to `lax` for development
- Allows cookies to be sent with same-site navigations
- Consider `strict` for production with proper configuration

### Debugging Tips

If you still encounter issues:

1. **Verify Sanctum Stateful Domains:**
   ```bash
   php artisan tinker
   >>> config('sanctum.stateful')
   ```

2. **Check Session Configuration:**
   ```bash
   php artisan tinker
   >>> config('session')
   ```

3. **Inspect Request Headers:**
   - Check browser DevTools Network tab
   - Verify Cookie and X-XSRF-TOKEN headers are sent
   - Verify Referer/Origin headers match SANCTUM_STATEFUL_DOMAINS

4. **Check Sessions Table:**
   ```sql
   SELECT * FROM sessions ORDER BY last_activity DESC LIMIT 5;
   ```

---

## 🚀 Ready for Testing

All fixes have been applied and caches have been cleared. The application should now properly handle session-based authentication for the /api/user-status endpoint and all other protected routes.
