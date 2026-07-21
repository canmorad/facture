// src/services/paymentApi.js
import axios from "axios";

const API_BASE = "/api";

export const paymentApi = {
  /**
   * Get all payments with optional filters
   */
  async getPayments(filters = {}) {
    const params = {};
    if (filters.document_id) params.document_id = filters.document_id;
    // Legacy support
    if (filters.invoice_id) params.invoice_id = filters.invoice_id;
    if (filters.status) params.status = filters.status;
    if (filters.payment_mode) params.payment_mode = filters.payment_mode;
    if (filters.customer_id) params.customer_id = filters.customer_id;
    if (filters.date_from) params.date_from = filters.date_from;
    if (filters.date_to) params.date_to = filters.date_to;
    if (filters.per_page) params.per_page = filters.per_page;

    const { data } = await axios.get(`${API_BASE}/payments`, { params });
    return data;
  },

  /**
   * Get a single payment by ID
   */
  async getPayment(id) {
    const { data } = await axios.get(`${API_BASE}/payments/${id}`);
    return data;
  },

  /**
   * Create a new payment
   */
  async createPayment(paymentData) {
    const { data } = await axios.post(`${API_BASE}/payments`, paymentData);
    return data;
  },

  /**
   * Cancel a payment
   */
  async cancelPayment(id) {
    const { data } = await axios.delete(`${API_BASE}/payments/${id}`);
    return data;
  },

  /**
   * Get payments for a specific document (generic)
   */
  async getDocumentPayments(documentId) {
    const { data } = await axios.get(`${API_BASE}/payments/documents/${documentId}/payments`);
    return data;
  },

  /**
   * Get payment summary for a document (generic)
   */
  async getDocumentPaymentSummary(documentId) {
    const { data } = await axios.get(`${API_BASE}/payments/documents/${documentId}/payment-summary`);
    return data;
  },

  /**
   * Get payments for a specific invoice (legacy method for backward compatibility)
   */
  async getInvoicePayments(invoiceId) {
    const { data } = await axios.get(`${API_BASE}/invoices/${invoiceId}/payments`);
    return data;
  },

  /**
   * Get payment summary for an invoice (legacy method for backward compatibility)
   */
  async getInvoicePaymentSummary(invoiceId) {
    const { data } = await axios.get(`${API_BASE}/invoices/${invoiceId}/payment-summary`);
    return data;
  },

  /**
   * Get available cash registers for a company
   */
  async getCashRegisters() {
    const { data } = await axios.get(`${API_BASE}/cash-registers/all`);
    return data;
  },

  /**
   * Get payment creation data (cash registers, active sessions, bank accounts)
   */
  async getCreationData() {
    const { data } = await axios.get(`${API_BASE}/payments/create`);
    return data;
  },
};
