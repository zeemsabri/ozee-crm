import { ref, watch } from 'vue';

// Reactive reference for conversion rates, will be fetched from API
// This will hold rates where key is currency code and value is its rate to USD (e.g., {'PKR': 0.0034, 'EUR': 1.08})
export const conversionRatesToUSD = ref({});

// Reactive reference for the user's preferred display currency
// Initialize from localStorage, default to 'USD' if not found
const storedDisplayCurrency = localStorage.getItem('displayCurrency');
export const displayCurrency = ref(storedDisplayCurrency || 'USD');

// Watch for changes in displayCurrency and save to localStorage
watch(displayCurrency, (newValue) => {
    localStorage.setItem('displayCurrency', newValue);
});

/**
 * Fetches currency rates from the backend API and updates the reactive conversionRatesToUSD.
 * This should be called once, typically on application startup or a relevant parent component's mount.
 */
export const fetchCurrencyRates = async () => {
    try {
        const response = await window.axios.get('/api/currency-rates');
        conversionRatesToUSD.value = response.data;
        console.log('Fetched currency rates:', conversionRatesToUSD.value);
    } catch (error) {
        console.error('Failed to fetch currency rates:', error);
        // Fallback to mock rates if API fails (for development/testing)
        // In a production environment, you might want a more robust error handling or a default set.
        conversionRatesToUSD.value = {
            PKR: 0.0034, // 1 PKR = 0.0034 USD
            AUD: 0.65,   // 1 AUD = 0.65 USD
            INR: 0.012,  // 1 INR = 0.012 USD
            EUR: 1.08,   // 1 EUR = 1.08 USD
            GBP: 1.28,   // 1 GBP = 1.28 USD
            USD: 1.0     // 1 USD = 1.0 USD
        };
    }
};

/**
 * Formats a numeric amount into a currency string.
 * @param {number} amount - The numeric amount.
 * @param {string} currency - The currency code (e.g., 'USD', 'PKR').
 * @returns {string} The formatted currency string.
 */
export const formatCurrency = (amount, currency = 'USD') => {
    if (isNaN(amount) || amount == null) return '0.00';
    try {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: currency,
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }).format(amount);
    } catch (e) {
        console.warn(`Error formatting currency for ${amount} ${currency}:`, e);
        return `${currency} ${parseFloat(amount).toFixed(2)}`; // Fallback format
    }
};

/**
 * Converts an amount from one currency to another using USD as a base.
 * It relies on `conversionRatesToUSD` being populated.
 * @param {number} amount - The amount to convert.
 * @param {string} fromCurrency - The source currency code.
 * @param {string} toCurrency - The target currency code.
 * @returns {number} The converted amount.
 */
export const convertCurrency = (amount, fromCurrency, toCurrency) => {
    if (isNaN(amount) || amount == null || !fromCurrency || !toCurrency) return 0;
    if (fromCurrency.toUpperCase() === toCurrency.toUpperCase()) return Number(amount);

    const rates = conversionRatesToUSD.value; // Use the reactive rates
    const fromRate = rates[fromCurrency.toUpperCase()];
    const toRate = rates[toCurrency.toUpperCase()]; // Corrected: Use toCurrency.toUpperCase() here

    if (!fromRate || !toRate) {
        console.warn(`Missing conversion rates for: ${fromCurrency} or ${toCurrency}. Cannot perform conversion.`);
        // Fallback: return original amount if rates are not available
        return Number(amount);
    }

    // Convert amount from its original currency to USD
    // If 'rate_to_usd' means "1 unit of currency = X USD", then to get amount in USD, multiply.
    // Example: 100 PKR * 0.0034 = 0.34 USD
    const amountInUSD = Number(amount) * fromRate;

    // Convert amount from USD to the target currency
    // To convert from USD to 'toCurrency', divide by 'toCurrency's rate_to_usd.
    // Example: 0.34 USD / 1.0 = 0.34 USD (if toCurrency is USD)
    // Example: 0.34 USD / 1.08 = 0.3148 EUR (if toCurrency is EUR)
    const convertedAmount = amountInUSD / toRate;

    return Number(convertedAmount.toFixed(2));
};

/**
 * Xero Tax Type config map.
 * rate: GST rate applied on top of the subtotal (0.10 = 10% GST).
 * inclusive: whether the entered amount is GST-inclusive (true) or exclusive (false).
 * label: human-friendly label shown in the UI.
 * description: short explanation for the user.
 */
export const XERO_TAX_CONFIG = {
    INPUT: {
        label: 'GST on Expenses (10%)',
        rate: 0.10,
        description: 'Standard 10% GST — claimable input tax credit.',
    },
    OUTPUT: {
        label: 'GST on Income (10%)',
        rate: 0.10,
        description: 'Standard 10% GST on income received.',
    },
    INPUTTAXED: {
        label: 'GST on Imports',
        rate: 0.10,
        description: '10% GST on imported goods/services.',
    },
    EXEMPTEXPENSES: {
        label: 'GST Free Expenses',
        rate: 0,
        description: 'No GST — expense is GST free (e.g. fresh food, health).',
    },
    EXEMPTOUTPUT: {
        label: 'GST Free Income',
        rate: 0,
        description: 'No GST — income is GST free.',
    },
    BASEXCLUDED: {
        label: 'BAS Excluded',
        rate: 0,
        description: 'Not reportable on BAS (e.g. wages, bank charges).',
    },
};

/**
 * Calculate GST amount for a given subtotal and Xero tax type.
 * @param {number} subtotal - Amount EXCLUDING tax.
 * @param {string} taxType - Xero tax type key.
 * @returns {number} GST amount.
 */
export const calculateGst = (subtotal, taxType) => {
    const config = XERO_TAX_CONFIG[taxType];
    if (!config || config.rate === 0) return 0;
    return parseFloat((parseFloat(subtotal) * config.rate).toFixed(2));
};

/**
 * Extract GST from a GST-inclusive total.
 * @param {number} total - Amount INCLUDING tax.
 * @param {string} taxType - Xero tax type key.
 * @returns {number} GST component.
 */
export const extractGstFromTotal = (total, taxType) => {
    const config = XERO_TAX_CONFIG[taxType];
    if (!config || config.rate === 0) return 0;
    return parseFloat((parseFloat(total) * config.rate / (1 + config.rate)).toFixed(2));
};
