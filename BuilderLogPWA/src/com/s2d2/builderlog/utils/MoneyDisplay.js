// Format displayed amounts only; form values, calculations and API data stay numeric.
const moneyFormatter = new Intl.NumberFormat('sr-RS', {
  maximumFractionDigits: 0,
});

export function formatMoney(value) {
  const amount = typeof value === 'string' ? value.trim().replace(',', '.') : value;
  if (amount == null || amount === '' || amount === '-') return '-';
  return moneyFormatter.format(typeof amount === 'bigint' ? amount : Math.trunc(Number(amount)));
}

// Add the currency after formatting, preserving missing-value placeholders.
export function withRsd(value) {
  const text = String(value ?? '').trim();
  if (!text || text === '-') return '-';
  return /RSD$/i.test(text) ? text : text + ' RSD';
}
