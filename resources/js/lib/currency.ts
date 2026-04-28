export type CurrencyCode = 'SAR' | 'PKR' | 'USD';

export const SUPPORTED_CURRENCIES: CurrencyCode[] = ['SAR', 'PKR', 'USD'];

export const DEFAULT_CURRENCY: CurrencyCode = 'SAR';

export const CURRENCY_META: Record<CurrencyCode, { label: string; symbol: string; locale: string }> = {
    SAR: { label: 'Saudi Riyal', symbol: 'SAR', locale: 'en-SA' },
    PKR: { label: 'Pakistani Rupee', symbol: 'PKR', locale: 'en-PK' },
    USD: { label: 'US Dollar', symbol: 'USD', locale: 'en-US' },
};

export function normalizeCurrency(value: string | null | undefined): CurrencyCode {
    const upper = (value ?? '').toUpperCase();
    return (SUPPORTED_CURRENCIES as string[]).includes(upper) ? (upper as CurrencyCode) : DEFAULT_CURRENCY;
}

export function formatCurrency(
    amount: number | string | null | undefined,
    currency: string | null | undefined = DEFAULT_CURRENCY,
    options: { fractionDigits?: number; emptyFallback?: string } = {},
): string {
    const { fractionDigits = 0, emptyFallback = '—' } = options;
    if (amount === null || amount === undefined || amount === '') return emptyFallback;
    const num = typeof amount === 'string' ? parseFloat(amount) : amount;
    if (Number.isNaN(num)) return emptyFallback;
    const code = normalizeCurrency(currency);
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: code,
        currencyDisplay: 'code',
        maximumFractionDigits: fractionDigits,
        minimumFractionDigits: fractionDigits,
    }).format(num);
}
