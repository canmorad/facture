// src/services/bankRemittanceApi.js
import axios from "axios";

const API_BASE = "/api";

export const bankRemittanceApi = {
  /**
   * Get all bank remittances with optional filters
   */
  async getAll(filters = {}) {
    const params = {};
    if (filters.status) params.status = filters.status;
    if (filters.search) params.search = filters.search;
    if (filters.date_from) params.date_from = filters.date_from;
    if (filters.date_to) params.date_to = filters.date_to;
    if (filters.bank_account_id) params.bank_account_id = filters.bank_account_id;
    if (filters.per_page) params.per_page = filters.per_page;
    if (filters.page) params.page = filters.page;

    const { data } = await axios.get(`${API_BASE}/bank-remittances`, { params });
    return data;
  },

  /**
   * Get creation data (bank accounts, pending documents)
   */
  async getCreationData() {
    const { data } = await axios.get(`${API_BASE}/bank-remittances/create`);
    return data;
  },

  /**
   * Get a single bank remittance by ID
   */
  async getById(id) {
    const { data } = await axios.get(`${API_BASE}/bank-remittances/${id}`);
    return data;
  },

  /**
   * Get available actions for a remittance
   */
  async getActions(id) {
    const { data } = await axios.get(`${API_BASE}/bank-remittances/${id}/actions`);
    return data;
  },

  /**
   * Create a new bank remittance draft
   */
  async createDraft(remittanceData) {
    const { data } = await axios.post(`${API_BASE}/bank-remittances`, remittanceData);
    return data;
  },

  /**
   * Update a bank remittance
   */
  async update(id, remittanceData) {
    const { data } = await axios.put(`${API_BASE}/bank-remittances/${id}`, remittanceData);
    return data;
  },

  /**
   * Add a payment document to a remittance
   */
  async addDocument(remittanceId, documentId) {
    const { data } = await axios.post(
      `${API_BASE}/bank-remittances/${remittanceId}/documents/${documentId}`
    );
    return data;
  },

  /**
   * Remove a payment document from a remittance
   */
  async removeDocument(remittanceId, documentId) {
    const { data } = await axios.delete(
      `${API_BASE}/bank-remittances/${remittanceId}/documents/${documentId}`
    );
    return data;
  },

  /**
   * Finalize a bank remittance
   */
  async finalize(remittanceId) {
    const { data } = await axios.put(`${API_BASE}/bank-remittances/${remittanceId}/finalize`);
    return data;
  },

  /**
   * Mark remittance as sent to bank
   */
  async send(remittanceId) {
    const { data } = await axios.put(`${API_BASE}/bank-remittances/${remittanceId}/send`);
    return data;
  },

  /**
   * Mark remittance as deposited
   */
  async markDeposited(remittanceId, depositSlipRef = null) {
    const { data } = await axios.put(`${API_BASE}/bank-remittances/${remittanceId}/deposit`, {
      deposit_slip_reference: depositSlipRef,
    });
    return data;
  },

  /**
   * Cancel a bank remittance
   */
  async cancel(remittanceId) {
    const { data } = await axios.put(`${API_BASE}/bank-remittances/${remittanceId}/cancel`);
    return data;
  },

  /**
   * Delete a bank remittance (draft only)
   */
  async delete(remittanceId) {
    const { data } = await axios.delete(`${API_BASE}/bank-remittances/${remittanceId}`);
    return data;
  },

  /**
   * Get pending payment documents (cheques/LCN waiting for remittance)
   */
  async getPendingDocuments() {
    const { data } = await axios.get(`${API_BASE}/bank-remittances/pending-documents`);
    return data;
  },

  /**
   * Mark a payment document as returned
   */
  async markDocumentReturned(documentId, reason = null) {
    const { data } = await axios.put(`${API_BASE}/payment-documents/${documentId}/mark-returned`, {
      return_reason: reason,
    });
    return data;
  },

  /**
   * Mark a payment document as paid manually
   */
  async markDocumentPaid(documentId) {
    const { data } = await axios.put(`${API_BASE}/payment-documents/${documentId}/mark-paid`);
    return data;
  },
};