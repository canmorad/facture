# Guide de Test - Résolution Erreur 413

## Problème
Erreur 413 (Content Too Large) lors de l'upload de facture pour analyse IA.

## Solutions

### Solution 1 : Utiliser le PDF ✅ RECOMMANDÉ
1. Ouvrez `facture_test_techzone.html` dans votre navigateur
2. Cliquez sur **"Télécharger PDF"**
3. Le PDF généré est beaucoup plus léger (~50-100 KB)
4. Testez avec le fichier PDF dans votre application

### Solution 2 : Utiliser l'Image JPEG optimisée
1. Ouvrez `facture_test_techzone.html` dans votre navigateur
2. Cliquez sur **"Télécharger Image"**
3. Le fichier sera maintenant en **JPEG** (plus léger que PNG)
4. Le fichier fait environ 200-300 KB au lieu de 2-3 MB

### Solution 3 : Réduire encore la taille de l'image
Dans le fichier HTML, modifiez la fonction `downloadImage()` :

```javascript
const canvas = await html2canvas(element, {
    scale: 1,  // Au lieu de 1.5 ou 2
    useCORS: true,
    backgroundColor: '#ffffff'
});

// JPEG avec plus de compression
link.href = canvas.toDataURL('image/jpeg', 0.7); // 70% qualité
```

### Solution 4 : Configuration serveur (déjà appliquée)
Le fichier `.htaccess` a été mis à jour avec :
```apache
php_value upload_max_filesize 50M
php_value post_max_size 50M
```

## Test rapide

```bash
# Redémarrez le serveur Laravel
php artisan serve

# Dans le navigateur, testez avec le fichier PDF
# Le PDF ne devrait pas donner l'erreur 413
```

## Pourquoi le PDF est préférable ?

| Format | Taille | Qualité OCR | Vitesse |
|--------|--------|-------------|---------|
| PNG | 2-3 MB | Excellente | Lent |
| JPEG | 200-500 KB | Bonne | Rapide |
| PDF | 50-100 KB | Excellente | Très rapide |

**Gemini API gère mieux les PDF pour l'analyse de factures.**

## Commande de test

```bash
cd backend
php artisan purchase-invoice:test-gemini /chemin/vers/facture.pdf
```

---

**Conseil : Utilisez toujours le PDF pour tester - c'est plus rapide et plus fiable !**
