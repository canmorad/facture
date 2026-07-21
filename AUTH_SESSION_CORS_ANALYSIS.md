# ANALYSE COMPLÈTE - PROBLÈME SESSION F5 + CORS PREFLIGHT

## SYMPTÔMES
1. **Perte de session au rafraîchissement F5** - Utilisateur connecté est immédiatement déconnecté après refresh
2. **Requêtes POST/PUT bloquées au preflight OPTIONS** - Les requêtes d'action restent bloquées

---

## CAUSES RACINES IDENTIFIÉES

### PROBLÈME CRITIQUE #1: Middleware EnsureLocalRequestsAreStateful NE garantit PAS les requêtes stateful

**Fichier**: `backend/app/Http/Middleware/EnsureLocalRequestsAreStateful.php`

**Bug identifié**:
```php
protected function stateful(?string $domain): bool
{
    // Le domaine inclut le port (ex: "127.0.0.1:5173")
    // Donc "localhost" !== "localhost:5173"
    if ($domain === 'localhost' || $domain === '127.0.0.1') {
        return true;
    }
    // Pour les domaines avec port, on passait ici et ça pouvait échouer
    return parent::stateful($domain);
}
```

**Solution appliquée**: Extraction du base domain (sans port) pour la comparaison:
```php
$baseDomain = explode(':', $domain)[0];
if (in_array($baseDomain, ['localhost', '127.0.0.1', '::1'])) {
    return true;
}
```

---

### PROBLÈME CRITIQUE #2: CORS Preflight ne fonctionnait pas car middleware non-global

**Fichier**: `backend/bootstrap/app.php`

**Bug identifié**: HandleOptionsRequests était seulement dans les middleware API/Web, pas globaux. Les requêtes OPTIONS qui ne correspondent à aucune route ne passaient pas par ces middleware.

**Solution appliquée**: Déplacement de HandleOptionsRequests en middleware global avec prepend():
```php
$middleware->prepend(\App\Http\Middleware\HandleOptionsRequests::class);
```

---

### PROBLÈME CRITIQUE #3: CORS ne gère pas tous les ports localhost

**Fichier**: `backend/app/Http/Middleware/HandleOptionsRequests.php`

**Bug identifié**: Seuls les origines explicitement listées étaient acceptées. Les autres ports localhost (ex: :3000) étaient rejetés.

**Solution appliquée**: Logique pour accepter tous les ports localhost/127.0.0.1:
```php
if (in_array($host, ['localhost', '127.0.0.1', '::1'])) {
    $baseOrigin = $origin; // Accepte n'importe quel port
}
```

---

### PROBLÈME CRITIQUE #4: Guard routeur bloquait les routes publiques

**Fichier**: `frontend/src/router/guard.js`

**Bug identifié**: Le guard attendait `fetchAuthStatus()` même pour les routes publiques (login, register), causant des délais inutiles.

**Solution appliquée**: Pour les routes publiques, ne pas bloquer en attendant l'auth:
```javascript
if (publicRoutes.includes(to.name)) {
    if (!auth.isInitialized && !auth.isFetchingAuth) {
        auth.fetchAuthStatus().catch(() => {}); // Non-blocking
    }
    return true;
}
```

---

### PROBLÈME CRITIQUE #5: Store auth ne restaura pas les données depuis localStorage

**Fichier**: `frontend/src/stores/auth.js`

**Bug identifié**: Au rafraîchissement, le store ne restaurait pas les données depuis localStorage, laissant `user` à `null`.

**Solution appliquée**: Restauration de l'utilisateur depuis localStorage pour un état instantané:
```javascript
let initialUser = null;
if (storedUser) {
    try {
        initialUser = JSON.parse(storedUser);
    } catch (e) {
        localStorage.removeItem('auth_user');
    }
}

return {
    user: initialUser,
    isAuthenticated: !!initialUser,
    companies: initialUser?.companies || [],
    // ...
};
```

---

### PROBLÈME CRITIQUE #6: Configuration CORS paths

**Fichier**: `backend/config/cors.php`

**Bug identifié**: `'login'` et `'logout'` dans paths ne correspondaient à rien car les routes sont `/api/login` et `/api/logout`.

**Solution appliquée**: Nettoyage des paths:
```php
'paths' => ['api/*', 'sanctum/*'],
```

---

## SOLUTIONS APPLIQUÉES ✅

### 1. ✅ EnsureLocalRequestsAreStateful corrigé
- Extraction du base domain pour la comparaison
- Accepte tous les ports pour localhost/127.0.0.1

### 2. ✅ HandleOptionsRequests déplacé en middleware global
- Traite TOUTES les requêtes OPTIONS avant routage
- Accepte tous les ports localhost/127.0.0.1

### 3. ✅ Guard routeur optimisé
- Non-blocking pour routes publiques
- Meilleure gestion de l'état d'initialisation

### 4. ✅ Store auth amélioré
- Restauration depuis localStorage pour état instantané
- `isAuthenticated` basé sur les données stockées

### 5. ✅ Configuration CORS nettoyée
- Paths correctement configurés
- Support de tous les ports localhost

---

## TESTS DE VÉRIFICATION

### CORS Preflight:
```bash
# Test avec localhost:3000 (port différent)
curl -i -X OPTIONS http://127.0.0.1:8080/api/companies \
  -H "Origin: http://localhost:3000" \
  -H "Access-Control-Request-Method: POST"

# Résultat: ✅ Headers CORS corrects retournés
Access-Control-Allow-Origin: http://localhost:3000
Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS
Access-Control-Allow-Credentials: true
```

### Session après F5:
1. Utilisateur connecté → Données stockées dans localStorage
2. F5 → Store restaure instantanément les données depuis localStorage
3. Guard routeur voit `isAuthenticated = true` → Continue
4. fetchAuthStatus() confirme avec l'API → Met à jour si nécessaire

---

## RÉSUMÉ

Les deux problèmes principaux ont été résolus:

1. **CORS Preflight bloqué**: Le middleware HandleOptionsRequests a été déplacé en middleware global pour traiter toutes les requêtes OPTIONS avant le routage. Maintenant tous les ports localhost/127.0.0.1 sont acceptés.

2. **Session perdue au F5**: Le store auth restaure maintenant les données depuis localStorage pour un état instantané, et le middleware EnsureLocalRequestsAreStateful reconnaît correctement les requêtes localhost avec ports comme stateful.

L'application devrait maintenant:
- Permettre les requêtes POST/PUT vers l'API
- Maintenir la session après rafraîchissement de page
- Fonctionner avec différents ports de développement
