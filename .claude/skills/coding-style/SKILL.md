# Coding Style Skill - Facture App

## Règles Globales

### 1. Zéro Commentaire
Aucun commentaire dans le code. Ni `//`, ni `/* */`, ni PHPDoc. Le code doit être auto-documenté.

### 2. Langue
- **Backend**: Messages d'erreur en **français**
- **Frontend**: Notifications SweetAlert2 en **français**
- **Variables/fonctions**: En **anglais** (ex: `getCreationData`, `markPaid`)

### 3. Naming Conventions

**Backend (PHP/Laravel)**:
- Classes: PascalCase (`InvoiceService`, `CheckCompanyHeader`)
- Méthodes: camelCase (`getAll`, `createDraft`, `getAvailableActions`)
- Variables: camelCase (`$companyId`, `$invoiceService`)
- Tables: snake_case (`user_companies`, `role_permissions`)
- Routes: kebab-case (`/delivery-notes/{id}/convert-to-invoice`)

**Frontend (Vue 3/JS)**:
- Composants: PascalCase (`InvoiceIndex.vue`, `DocumentForm.vue`)
- Composables: `use` + PascalCase (`usePermission.js`, `useDocumentLock.js`)
- Stores: camelCase (`authStore`, `useAuthStore`)
- Variables/fonctions: camelCase (`fetchInvoices`, `selectedStatus`)

### 4. Structure de Fichiers

**Backend**:
```
app/
├── Http/
│   ├── Controllers/Auth/        (AuthenticatedSessionController, UserStatusController)
│   ├── Controllers/             (InvoiceController, QuoteController, ...)
│   ├── Middleware/               (CheckCompanyHeader, CheckIfOwner)
│   └── Requests/                 (InvoiceRequest, DepositRequest)
├── Mail/                         (InvitationMail, SendDocumentMail)
├── Models/                       (Invoice, Document, User, ...)
├── Services/                     (InvoiceService, QuoteService, ...)
└── Traits/                       (HasPermissions, LogsActivityTrait)
```

**Frontend**:
```
src/
├── components/                   (CompanySelect, DocumentForm, DropdownSelect)
├── composables/                  (usePermission, useDocumentLock)
├── helpers/                      (notifications.js)
├── layouts/                      (AuthenticatedLayout, SettingsLayout)
├── router/                       (index.js, guard.js)
├── stores/                       (auth.js)
└── views/
    ├── Auth/                     (Login, Register, AcceptInvitation)
    ├── invoices/                 (InvoiceIndex, CreateInvoice)
    ├── quotes/                   (QuoteIndex, CreateQuote)
    ├── delivery-notes/           (DeliveryNoteIndex, CreateDeliveryNote)
    ├── purchase-orders/          (...)
    ├── deposits/                 (...)
    ├── expenses/                 (...)
    ├── recurring-invoices/       (...)
    └── settings/                 (Theme, Users, Coordinates, ...)
```

### 5. Indentation
- PHP: 4 espaces
- JavaScript/Vue: 2 espaces
- Pas de tabs

### 6. Imports
Toujours en haut du fichier, groupés par type:
1. Framework/Library imports
2. Project imports (`@/stores/auth`, `@/helpers/notifications`)

### 7. Messages d'erreur
```php
// ✅ Correct
throw new \RuntimeException('Aucune entreprise sélectionnée.');
throw new \InvalidArgumentException('Le document n\'est pas une facture.');

// ❌ Interdit
throw new \RuntimeException('No company selected.');
```

---

## Palette de Couleurs (Tailwind)

| Rôle | Code | Usage |
|---|---|---|
| Fond page | `bg-gray-50` | Arrière-plan global |
| Fond card | `bg-[#F4F7F7]` | Fond des cards/blocs |
| Texte principal | `#062121` | Titres, th de tableau, texte actif |
| Texte secondaire | `#0F172A` | Labels (InputLabel), bouton primaire |
| Accent (jaune fluo) | `#C5F82A` | Tab actif (border-bottom), focus input, spinner |
| Border principal | `border-gray-200` | Bordures des cards et barre de tabs |
| Border léger | `border-gray-100` | Séparateurs entre sections du formulaire |
| Texte gris | `text-gray-500` / `text-gray-600` / `text-gray-700` | Texte inactif, placeholders, descriptions |
| Bouton primaire | `bg-[#0F172A]` avec texte blanc | Bouton d'action principal (Enregistrer, Ajouter) |
| Input border | `border-[#E2E8F0]` | Bordure par défaut des inputs |
| Input focus | `focus:border-[#C5F82A] focus:ring-[#C5F82A]/20` | Focus sur les inputs (jaune fluo) |
| Erreur | `text-red-600` | Messages d'erreur (InputError) |

---

## Structure de Page CRUD (Liste + Formulaire)

Toute page de gestion (clients, fournisseurs, factures, produits, etc.) suit ce pattern avec **tabs** (Liste / Ajouter).

### Pattern Général

```vue
<template>
  <AuthenticatedLayout>
    <div class="bg-gray-50 min-h-screen font-sans antialiased">
      <div class="p-6 lg:p-8">
        <div class="rounded-2xl border border-gray-200 bg-[#F4F7F7] shadow-sm overflow-hidden">

          <!-- Barre de tabs -->
          <div class="border-b border-gray-200 px-6 pt-4 pb-3">
            <div class="flex gap-6">
              <button
                @click="changeTab('list')"
                :class="[
                  'pb-3 text-sm font-bold transition-colors flex items-center gap-2',
                  activeTab === 'list'
                    ? 'text-[#062121] border-b-2 border-[#C5F82A]'
                    : 'text-gray-500 hover:text-gray-700',
                ]"
              >
                <i class="fas fa-list"></i>
                Liste des X
                <span
                  v-if="items.length > 0"
                  class="text-xs bg-gray-200 text-gray-600 px-2 py-0.5 rounded-full"
                >{{ items.length }}</span>
              </button>

              <button
                @click="changeTab('add')"
                :class="[
                  'pb-3 text-sm font-bold transition-colors flex items-center gap-2',
                  activeTab === 'add'
                    ? 'text-[#062121] border-b-2 border-[#C5F82A]'
                    : 'text-gray-500 hover:text-gray-700',
                ]"
              >
                <i class="fas" :class="editingId ? 'fa-edit' : 'fa-plus-circle'"></i>
                {{ editingId ? 'Modifier' : 'Ajouter' }}
              </button>
            </div>
          </div>

          <!-- Contenu: Liste -->
          <div v-if="activeTab === 'list'" class="p-6 lg:p-8">
            <!-- État chargement -->
            <div v-if="isLoadingItems" class="text-center py-12">
              <svg class="animate-spin h-8 w-8 mx-auto text-[#C5F82A]" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
              </svg>
              <p class="mt-2 text-gray-500">Chargement...</p>
            </div>

            <!-- État vide -->
            <div v-else-if="items.length === 0" class="text-center py-12">
              <i class="fas fa-icon text-5xl text-gray-300 mb-4 block"></i>
              <p class="text-gray-500">Aucun élément enregistré pour le moment.</p>
              <button
                @click="changeTab('add')"
                class="!p-[10px] !bg-[#0F172A] !text-white border-none rounded-lg font-bold text-xs sm:text-sm justify-center transition-all duration-300 hover:-translate-y-[1px] hover:shadow-[0_8px_15px_rgba(15,23,42,0.15)] inline-flex items-center gap-2 mt-4"
              >
                <i class="fas fa-plus-circle"></i> Ajouter votre premier X
              </button>
            </div>

            <!-- Tableau -->
            <div v-else class="overflow-x-auto">
              <table class="min-w-full">
                <thead>
                  <tr class="border-b border-gray-200">
                    <th class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">Colonne 1</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-[#062121] uppercase tracking-wider">Colonne 2</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-[#062121] uppercase tracking-wider">Actions</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                  <tr v-for="item in items" :key="item.id" class="group hover:bg-white/50 transition-colors duration-200">
                    <td class="px-4 py-4">
                      <div class="text-sm font-semibold text-gray-900">{{ item.name }}</div>
                    </td>
                    <td class="px-4 py-4 text-right">
                      <div class="flex items-center justify-end gap-2">
                        <button
                          @click="editItem(item)"
                          title="Modifier"
                          class="w-8 h-8 rounded-lg text-blue-500 hover:bg-blue-50 hover:text-blue-700 transition-all duration-200"
                        >
                          <i class="fas fa-edit text-sm"></i>
                        </button>
                        <button
                          @click="deleteItem(item.id)"
                          title="Supprimer"
                          class="w-8 h-8 rounded-lg text-gray-400 hover:bg-red-500 hover:text-white transition-all duration-200"
                        >
                          <i class="fas fa-trash-alt text-sm"></i>
                        </button>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Contenu: Formulaire -->
          <form v-else-if="activeTab === 'add'" @submit.prevent="submit" class="p-6 lg:p-8">
            <div class="space-y-8">
              <InputError :message="errors.server" />

              <!-- Groupe de champs -->
              <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                <div class="md:col-span-2">
                  <InputLabel for="name" value="Nom *" />
                  <TextInput
                    id="name"
                    type="text"
                    class="mt-1 block w-full"
                    v-model="form.name"
                    placeholder="..."
                  />
                  <InputError class="mt-2" :message="errors.name" />
                </div>
                <div>
                  <InputLabel for="field1" value="Champ 1" />
                  <TextInput
                    id="field1"
                    type="text"
                    class="mt-1 block w-full"
                    v-model="form.field1"
                    placeholder="..."
                  />
                  <InputError class="mt-2" :message="errors.field1" />
                </div>
                <div>
                  <InputLabel for="field2" value="Champ 2" />
                  <TextInput
                    id="field2"
                    type="text"
                    class="mt-1 block w-full"
                    v-model="form.field2"
                    placeholder="..."
                  />
                  <InputError class="mt-2" :message="errors.field2" />
                </div>
              </div>

              <!-- Séparateur entre sections -->
              <div class="border-t border-gray-100 pt-6 mt-6">
                <h3 class="mb-4 text-base font-semibold text-[#062121]">Titre de section</h3>
                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                  <!-- ... champs ... -->
                </div>
              </div>

              <!-- Footer (Annuler + Enregistrer) -->
              <div class="flex justify-end gap-3 pt-6 border-t border-gray-100">
                <button
                  type="button"
                  @click="changeTab('list')"
                  class="px-4 py-2 text-gray-600 hover:text-gray-800 font-medium"
                >
                  Annuler
                </button>
                <PrimaryButton :disabled="isLoading">
                  <span v-if="isLoading">
                    <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white inline" fill="none" viewBox="0 0 24 24">
                      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                    </svg>
                    Enregistrement...
                  </span>
                  <span v-else>{{ editingId ? 'Modifier' : 'Enregistrer' }}</span>
                </PrimaryButton>
              </div>
            </div>
          </form>

        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
```

---

## Composants Réutilisables

**Toujours utiliser ces composants** pour les formulaires. Ne jamais écrire de `<input>` ou `<label>` natif directement.

| Composant | Chemin | Description |
|---|---|---|
| `InputLabel` | `@/components/InputLabel.vue` | Label de champ (text-xs/sm font-semibold text-[#0F172A]) |
| `TextInput` | `@/components/TextInput.vue` | Input text avec focus jaune #C5F82A |
| `InputError` | `@/components/InputError.vue` | Message d'erreur rouge |
| `PrimaryButton` | `@/components/PrimaryButton.vue` | Bouton principal (fond #0F172A, texte blanc, hover translate) |
| `AuthenticatedLayout` | `@/layouts/AuthenticatedLayout.vue` | Layout de page authentifiée |

### InputLabel
```vue
<InputLabel for="name" value="Nom *" />
```
→ Rend un `<label class="block text-xs sm:text-[13px] font-semibold text-[#0F172A] mb-[6px]">`

### TextInput
```vue
<TextInput id="name" type="text" class="mt-1 block w-full" v-model="form.name" placeholder="..." />
```
→ Input avec style par défaut : `border-[#E2E8F0] bg-[#F8FAFC]`, focus `border-[#C5F82A] focus:ring-[#C5F82A]/20`

### InputError
```vue
<InputError class="mt-2" :message="errors.name" />
```
→ Affiche un `<p class="text-sm text-red-600">` seulement si `message` est non-vide.

### PrimaryButton
```vue
<PrimaryButton :disabled="isLoading">
  <span v-if="isLoading">Enregistrement...</span>
  <span v-else>Enregistrer</span>
</PrimaryButton>
```
→ Bouton `bg-[#0F172A] text-white rounded-lg font-bold` avec animation `hover:-translate-y-[2px]`

---

## Système de Tabs

Les tabs utilisent **uniquement des boutons** (pas de `<a>`, pas de router-link). La tab active a :
- `text-[#062121]` (vert foncé)
- `border-b-2 border-[#C5F82A]` (ligne jaune fluo en bas)

La tab inactive a :
- `text-gray-500 hover:text-gray-700`
- Pas de border-bottom

```vue
<button
  @click="changeTab('add')"
  :class="[
    'pb-3 text-sm font-bold transition-colors flex items-center gap-2',
    activeTab === 'add'
      ? 'text-[#062121] border-b-2 border-[#C5F82A]'
      : 'text-gray-500 hover:text-gray-700',
  ]"
>
  <i class="fas fa-plus-circle"></i>
  Ajouter un X
</button>
```

**Icônes utilisées :**
- Liste : `fas fa-list`
- Ajouter : `fas fa-plus-circle`
- Modifier : `fas fa-edit` (bouton bleu dans le tableau)
- Supprimer : `fas fa-trash-alt` (bouton rouge au hover dans le tableau)
- Spécifique client : `fa-user-plus`
- Spécifique fournisseur : `fa-truck-field`
- Email : `fas fa-envelope`
- Téléphone : `fas fa-phone`
- Ville : `fas fa-city`

---

## Validation et Erreurs

### Structure `form` et `errors`
```js
const form = reactive({
  name: "",
  email: "",
  phone: "",
});

const errors = reactive({
  name: "",
  email: "",
  phone: "",
  server: "",
});
```

### Gestion des erreurs 422
```js
try {
  // ... submit ...
} catch (err) {
  if (err.response?.status === 422) {
    const validationErrors = err.response.data.errors;
    Object.keys(validationErrors).forEach((key) => {
      if (errors[key] !== undefined) errors[key] = validationErrors[key][0];
    });
  } else {
    errors.server = "Une erreur est survenue lors de l'enregistrement.";
    error("Erreur", "Message d'erreur.");
  }
}
```

### Notifications (SweetAlert2)
```js
import { success, error, confirm } from "@/helpers/notifications";

// Succès
success("Titre !", "Message de succès.");

// Erreur
error("Erreur", "Message d'erreur.");

// Confirmation avant suppression
const result = await confirm("Titre", "Message de confirmation ?");
if (!result.isConfirmed) return;
```

---

## Radios personnalisés
```vue
<div class="mb-6">
  <label class="block text-sm font-medium text-gray-700 mb-2">Label</label>
  <div class="flex gap-4">
    <label class="inline-flex items-center">
      <input type="radio" value="valeur1" v-model="form.type"
        class="form-radio text-[#C5F82A] focus:ring-[#C5F82A]" />
      <span class="ml-2 text-sm text-gray-700">Label 1</span>
    </label>
    <label class="inline-flex items-center">
      <input type="radio" value="valeur2" v-model="form.type"
        class="form-radio text-[#C5F82A] focus:ring-[#C5F82A]" />
      <span class="ml-2 text-sm text-gray-700">Label 2</span>
    </label>
  </div>
  <InputError class="mt-2" :message="errors.type" />
</div>
```

---

## Badges de statut
```vue
<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
  :class="condition ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800'">
  {{ label }}
</span>
```

---

## Règles à respecter pour tout nouveau formulaire

1. Toujours wrapper dans `<AuthenticatedLayout>`
2. Background de page : `bg-gray-50 min-h-screen font-sans antialiased`
3. Padding : `p-6 lg:p-8`
4. Card : `rounded-2xl border border-gray-200 bg-[#F4F7F7] shadow-sm overflow-hidden`
5. Tabs en haut avec `border-b-2 border-[#C5F82A]` pour l'actif
6. Formulaire dans `space-y-8` avec sections séparées par `border-t border-gray-100 pt-6 mt-6`
7. Grid 2 colonnes : `grid grid-cols-1 gap-6 md:grid-cols-2`
8. Champ pleine largeur : `md:col-span-2`
9. Footer formulaire : `flex justify-end gap-3 pt-6 border-t border-gray-100`
10. Bouton Annuler à gauche (texte gris), PrimaryButton à droite
11. Utiliser les composants `InputLabel`, `TextInput`, `InputError`, `PrimaryButton`
12. Titres de section : `<h3 class="mb-4 text-base font-semibold text-[#062121]">`
13. Toujours gérer les 3 états : chargement (spinner), vide (message + bouton), données (tableau)
14. Les boutons d'action dans le tableau : icône bleue pour édition, grise→rouge pour suppression
15. Ne jamais écrire de `<input>` ou `<label>` natif dans les formulaires - utiliser les composants