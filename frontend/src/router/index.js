// router/index.js
import { createRouter, createWebHistory } from 'vue-router'
import { navigationGuard } from './guard'

import Home from '../views/Home.vue'
import Register from '../views/Auth/Register.vue'
import Login from '../views/Auth/Login.vue'
import Clients from '../views/Clients.vue'
import Products from '../views/Products.vue'

// Quotes
import QuoteIndex from '../views/quotes/QuoteIndex.vue'
import CreateQuote from '../views/quotes/CreateQuote.vue'

import Coordinates from '../views/settings/Coordinates.vue'
import ProductTypes from '../views/settings/ProductTypes.vue'
import Organizations from '../views/settings/Organizations.vue'
import PurchaseOrderIndex from '../views/purchase-orders/PurchaseOrderIndex.vue'
import CreatePurchaseOrder from '../views/purchase-orders/CreatePurchaseOrder.vue'

import DeliveryNoteIndex from '../views/delivery-notes/DeliveryNoteIndex.vue'
import CreateDeliveryNote from '../views/delivery-notes/CreateDeliveryNote.vue'
import InvoiceIndex from '../views/invoices/InvoiceIndex.vue'
import CreateInvoice from '../views/invoices/CreateInvoice.vue'
import DepositIndex from '../views/deposits/DepositIndex.vue'
import CreateDeposit from '../views/deposits/CreateDeposit.vue'
import DocumentPreview from '../views/DocumentPreview.vue'
import DocumentPrint from '../views/DocumentPrint.vue'
import AcceptInvitation from '../views/Auth/AcceptInvitation.vue'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/',
      name: 'home',
      component: Home,
    },
    {
      path: '/settings',
      redirect: '/settings/coordinates',
    },
    {
      path: '/settings/product-types',
      component: ProductTypes,
    },
    {
      path: '/settings/numbering',
      name: 'settings.numbering',
      component: () => import('../views/settings/Numbering.vue'),
    },
    {
      path: '/settings/tax-rates',
      name: 'settings.tax-rates',
      component: () => import('../views/settings/TaxRates.vue'),
    },
    {
      path: '/settings/documents',
      name: 'settings.documents',
      component: () => import('../views/settings/Documents.vue'),
    },
    {
      path: '/settings/payment-conditions',
      component: () => import('../views/settings/PaymentConditions.vue'),
    },
    {
      path: '/settings/payment-modes',
      component: () => import('../views/settings/PaymentModes.vue'),
    },
    {
      path: '/settings/late-fee-interests',
      component: () => import('../views/settings/LateFeeInterests.vue'),
    },
    {
      path: '/settings/theme',
      name: 'settings.theme',
      component: () => import('../views/settings/Theme.vue'),
    },
    {
      path: '/document/preview/:id',
      name: 'document.preview',
      component: DocumentPreview,
    },
    {
      path: '/document/send/:id',
      name: 'document.send',
      component: () => import('../views/DocumentSend.vue'),
    },
    {
      path: '/document/print/:id',
      name: 'document.print',
      component: DocumentPrint,
    },
    {
      path: '/settings/bank-accounts',
      component: () => import('../views/settings/BankAccounts.vue'),
    },
    {
      path: '/settings/organizations',
      name: 'settings.organizations',
      component: Organizations,
    },
    // ----- Routes pour les devis -----
    {
      path: '/quote/create',
      name: 'quote.create',
      component: CreateQuote,
    },
    {
      path: '/quote/edit/:id',
      name: 'quote.edit',
      component: CreateQuote,
    },
    {
      path: '/quote',
      name: 'quote',
      component: QuoteIndex,
    },
    // --------------------------------
    {
      path: '/accept-invitation',
      name: 'accept-invitation',
      component: AcceptInvitation,
    },
    {
      path: '/register',
      name: 'register',
      component: Register,
    },
    {
      path: '/login',
      name: 'login',
      component: Login,
    },
    {
      path: '/verify-email',
      name: 'verify-email',
      component: () => import('../views/Auth/VerifyEmail.vue'),
    },
    {
      path: '/settings/users',
      name: 'settings.users',
      component: () => import('../views/settings/Users.vue'),
    },
    {
      path: '/settings/coordinates',
      name: 'settings.coordinates',
      component: Coordinates,
    },
    {
      path: '/dashboard',
      name: 'dashboard',
      component: () => import('@/views/Dashboard.vue'),
    },
    {
      path: '/purchase-orders',
      name: 'purchase-order.index',
      component: PurchaseOrderIndex,
      meta: { requiresAuth: true },
    },
    {
      path: '/invoices',
      name: 'invoice.index',
      component: InvoiceIndex,
      meta: { requiresAuth: true },
    },
    {
      path: '/invoices/create',
      name: 'invoice.create',
      component: CreateInvoice,
      meta: { requiresAuth: true },
    },
    {
      path: '/invoices/edit/:id',
      name: 'invoice.edit',
      component: CreateInvoice,
      meta: { requiresAuth: true },
    },
    {
      path: '/deposits',
      name: 'deposit.index',
      component: DepositIndex,
      meta: { requiresAuth: true },
    },
    {
      path: '/deposits/create',
      name: 'deposit.create',
      component: CreateDeposit,
      meta: { requiresAuth: true },
    },
    {
      path: '/deposits/edit/:id',
      name: 'deposit.edit',
      component: CreateDeposit,
      meta: { requiresAuth: true },
    },
    {
      path: '/purchase-orders/create',
      name: 'purchase-order.create',
      component: CreatePurchaseOrder,
      meta: { requiresAuth: true },
    },
    {
      path: '/purchase-orders/edit/:id',
      name: 'purchase-order.edit',
      component: CreatePurchaseOrder,
      meta: { requiresAuth: true },
    },
    {
      path: '/delivery-notes',
      name: 'delivery-note.index',
      component: DeliveryNoteIndex,
      meta: { requiresAuth: true },
    },
    {
      path: '/delivery-notes/create',
      name: 'delivery-note.create',
      component: CreateDeliveryNote,
      meta: { requiresAuth: true },
    },
    {
      path: '/delivery-notes/edit/:id',
      name: 'delivery-note.edit',
      component: CreateDeliveryNote,
      meta: { requiresAuth: true },
    },
    {
      path: '/clients',
      name: 'clients',
      component: Clients,
    },
    {
      path: '/products',
      name: 'products',
      component: Products,
    },
    {
      path: '/purchase-invoices',
      name: 'purchase-invoices',
      component: () => import('../views/PurchaseInvoices.vue'),
    },
    {
      path: '/recurring-invoices',
      name: 'recurring-invoice.index',
      component: () => import('../views/recurring-invoices/RecurringInvoiceIndex.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/recurring-invoices/create',
      name: 'recurring-invoice.create',
      component: () => import('../views/recurring-invoices/CreateRecurringInvoice.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/recurring-invoices/edit/:id',
      name: 'recurring-invoice.edit',
      component: () => import('../views/recurring-invoices/CreateRecurringInvoice.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/expenses',
      name: 'expenses.index',
      component: () => import('../views/expenses/ExpenseIndex.vue'),
      meta: { requiresAuth: true },
    },
    // {
    //   path: '/expenses/create',
    //   name: 'expenses.create',
    //   component: () => import('../views/expenses/CreateExpense.vue'),
    //   meta: { requiresAuth: true },
    // },
    {
      path: '/expenses/preview/:id',
      name: 'expenses.preview',
      component: () => import('../views/expenses/PreviewExpense.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/suppliers',
      name: 'suppliers.index',
      component: () => import('../views/suppliers/SupplierIndex.vue'),
      meta: { requiresAuth: true },
    },
  ],
})

router.beforeEach(navigationGuard)

export default router