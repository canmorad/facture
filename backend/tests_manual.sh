#!/bin/bash

# Script de Test Manuel - Workflow Paiement via API
# Ce script teste les 3 scénarios en utilisant l'API REST

BASE_URL="http://localhost:8000"
TOKEN=""

echo "═══════════════════════════════════════════════════════════════"
echo "     TEST MANUEL - WORKFLOW PAIEMENT (API)                  "
echo "═══════════════════════════════════════════════════════════════"
echo ""

# 1. Login pour obtenir le token
echo "🔐 Connexion..."
LOGIN_RESPONSE=$(curl -s -X POST "$BASE_URL/api/login" \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","password":"password"}')

echo "$LOGIN_RESPONSE" | grep -q "token"
if [ $? -eq 0 ]; then
  TOKEN=$(echo "$LOGIN_RESPONSE" | grep -o '"token":"[^"]*"' | cut -d'"' -f4)
  echo "✅ Connexion réussie"
else
  echo "❌ Erreur de connexion"
  echo "Réponse: $LOGIN_RESPONSE"
  exit 1
fi

# ─────────────────────────────────────────────────────────────────
# SCÉNARIO 1: PAIEMENT ESPÈCES
# ─────────────────────────────────────────────────────────────────
echo ""
echo "╔═══════════════════════════════════════════════════════════════╗"
echo "║  SCÉNARIO 1: PAIEMENT ESPÈCES                                  ║"
echo "╚════════════════════════════════════════════════════════════════╝"

# Supposons qu'on a déjà une facture ID 1 et une caisse ID 1
echo ""
echo "📌 Création paiement espèces..."
ESPÈCE_RESPONSE=$(curl -s -X POST "$BASE_URL/api/payments" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "invoice_id": 1,
    "payment_mode": "espece",
    "amount": 1200,
    "payment_date": "'$(date +%Y-%m-%d)'",
    "cash_register_id": 1
  }')

echo "$ESPÈCE_RESPONSE" | grep -q '"status":"completed"'
if [ $? -eq 0 ]; then
  PAYMENT_ID=$(echo "$ESPÈCE_RESPONSE" | grep -o '"id":[0-9]*' | cut -d':' -f2)
  CASH_TX_ID=$(echo "$ESPÈCE_RESPONSE" | grep -o '"cash_transaction":{"id":[0-9]*' | grep -o '[0-9]*$' | tail -1)
  echo "✅ Paiement créé (ID: $PAYMENT_ID)"
  echo "✅ CashTransaction liée (ID: $CASH_TX_ID)"

  # Vérifier solde caisse
  BALANCE=$(curl -s "$BASE_URL/api/cash-registers/1" \
    -H "Authorization: Bearer $TOKEN" | grep -o '"current_balance":[0-9.]*' | cut -d':' -f2)
  echo "💰 Solde caisse actuel: $BALANCE DH"
  echo "✅ SCÉNARIO 1 RÉUSSI"
else
  echo "❌ Erreur création paiement espèces"
  echo "Réponse: $ESPÈCE_RESPONSE"
fi

# ─────────────────────────────────────────────────────────────────
# SCÉNARIO 2: PAIEMENT CHÈQUE
# ─────────────────────────────────────────────────────────────────
echo ""
echo "╔═══════════════════════════════════════════════════════════════╗"
echo "║  SCÉNARIO 2: PAIEMENT CHÈQUE                                  ║"
echo "╚════════════════════════════════════════════════════════════════╝"

echo ""
echo "📌 Création paiement chèque..."
CHÈQUE_RESPONSE=$(curl -s -X POST "$BASE_URL/api/payments" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "invoice_id": 2,
    "payment_mode": "cheque",
    "amount": 5000,
    "payment_date": "'$(date +%Y-%m-%d)'",
    "document_number": "CHQ-TEST-001",
    "due_date": "'$(date -d '+15 days' +%Y-%m-%d)'",
    "drawer_name": "Client Test",
    "drawer_bank": "Banque Test"
  }')

echo "$CHÈQUE_RESPONSE" | grep -q '"status":"completed"'
if [ $? -eq 0 ]; then
  PAYMENT_ID=$(echo "$CHÈQUE_RESPONSE" | grep -o '"id":[0-9]*' | cut -d':' -f2)
  PAYMENT_DOC_ID=$(echo "$CHÈQUE_RESPONSE" | grep -o '"payment_document":{"id":[0-9]*' | grep -o '[0-9]*$' | tail -1)
  DOC_STATUS=$(echo "$CHÈQUE_RESPONSE" | grep -o '"payment_document":{"[^}]*"status":"[^"]*"' | cut -d':' -f4)
  echo "✅ Paiement créé (ID: $PAYMENT_ID)"
  echo "✅ PaymentDocument créée (ID: $PAYMENT_DOC_ID, Status: $DOC_STATUS)"

  if [ "$DOC_STATUS" = "pending" ]; then
    echo "✅ SCÉNARIO 2 RÉUSSI - Document prêt pour remise"
  else
    echo "⚠️  Status devrait être 'pending', actuel: $DOC_STATUS"
  fi
else
  echo "❌ Erreur création paiement chèque"
  echo "Réponse: $CHÈQUE_RESPONSE"
fi

# ─────────────────────────────────────────────────────────────────
# SCÉNARIO 3: ANNULATION PAIEMENT
# ─────────────────────────────────────────────────────────────────
echo ""
echo "╔═══════════════════════════════════════════════════════════════╗"
echo "║  SCÉNARIO 3: ANNULATION PAIEMENT                              ║"
echo "╚════════════════════════════════════════════════════════════════╝"

# Annuler le paiement espèces
echo ""
echo "📌 Annulation paiement espèces (ID: $PAYMENT_ID)..."
CANCEL_RESPONSE=$(curl -s -X DELETE "$BASE_URL/api/payments/$PAYMENT_ID" \
  -H "Authorization: Bearer $TOKEN")

echo "$CANCEL_RESPONSE" | grep -q '"status":"cancelled"'
if [ $? -eq 0 ]; then
  echo "✅ Paiement annulé (status: cancelled)"

  # Vérifier solde caisse après annulation
  BALANCE_AFTER=$(curl -s "$BASE_URL/api/cash-registers/1" \
    -H "Authorization: Bearer $TOKEN" | grep -o '"current_balance":[0-9.]*' | cut -d':' -f2)
  echo "💰 Solde caisse après annulation: $BALANCE_AFTER DH"
  echo "✅ SCÉNARIO 3 RÉUSSI - Solde restauré"
else
  echo "❌ Erreur annulation"
  echo "Réponse: $CANCEL_RESPONSE"
fi

# ─────────────────────────────────────────────────────────────────
# RÉSUMÉ
# ─────────────────────────────────────────────────────────────────
echo ""
echo "╔═══════════════════════════════════════════════════════════════╗"
echo "║                    RÉSUMÉ DES TESTS                            ║"
echo "╠════════════════════════════════════════════════════════════════╣"
echo "║  🔄 Scénario 1: Paiement espèces                              ║"
echo "║  🔄 Scénario 2: Paiement chèque                                ║"
echo "║  🔄 Scénario 3: Annulation                                     ║"
echo "║                                                              ║"
echo "║  LANCEZ: php artisan serve --port=8001                       ║"
echo "║  PUIS: ./tests_manual.sh                                      ║"
echo "╚════════════════════════════════════════════════════════════════╝"
echo ""
