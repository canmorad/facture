export function formatPrice(price) {
  return new Intl.NumberFormat('fr-MA', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(price);
}

export function formatCurrency(amount, currency = 'MAD') {
  return `${formatPrice(amount)} ${currency}`;
}

export function formatDate(dateStr) {
  if (!dateStr) return '—';
  return new Date(dateStr).toLocaleDateString('fr-MA');
}

export function formatDateTime(dateStr) {
  if (!dateStr) return '—';
  return new Date(dateStr).toLocaleString('fr-MA');
}

export function formatNumber(number) {
  return new Intl.NumberFormat('fr-MA').format(number);
}
