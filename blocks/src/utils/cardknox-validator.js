// ============================================
// File: blocks/src/utils/cardknox-validator.js
// ============================================

/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Validate card data
 * @param {Object} cardData - Card data to validate
 * @returns {Object} Validation errors
 */
export const validateCardData = (cardData) => {
    const errors = {};

    // Validate expiry month
    if (!cardData.expiryMonth) {
        errors.expiry = __('Expiry month is required', 'woocommerce-gateway-cardknox');
    } else {
        const month = parseInt(cardData.expiryMonth, 10);
        if (month < 1 || month > 12) {
            errors.expiry = __('Invalid expiry month', 'woocommerce-gateway-cardknox');
        }
    }

    // Validate expiry year
    if (!cardData.expiryYear) {
        errors.expiry = __('Expiry year is required', 'woocommerce-gateway-cardknox');
    } else {
        const currentYear = new Date().getFullYear();
        const year = parseInt(cardData.expiryYear, 10);
        
        if (year < currentYear) {
            errors.expiry = __('Card has expired', 'woocommerce-gateway-cardknox');
        }
        
        // Check if card expires this year but month has passed
        if (year === currentYear && cardData.expiryMonth) {
            const currentMonth = new Date().getMonth() + 1;
            const expMonth = parseInt(cardData.expiryMonth, 10);
            
            if (expMonth < currentMonth) {
                errors.expiry = __('Card has expired', 'woocommerce-gateway-cardknox');
            }
        }
    }

    return errors;
};

/**
 * Luhn algorithm to validate card number
 * @param {string} cardNumber - Card number to validate
 * @returns {boolean} Is valid
 */
export const luhnCheck = (cardNumber) => {
    const digits = cardNumber.replace(/\D/g, '');
    let sum = 0;
    let isEven = false;

    for (let i = digits.length - 1; i >= 0; i--) {
        let digit = parseInt(digits[i], 10);

        if (isEven) {
            digit *= 2;
            if (digit > 9) {
                digit -= 9;
            }
        }

        sum += digit;
        isEven = !isEven;
    }

    return sum % 10 === 0;
};

/**
 * Get card type from card number
 * @param {string} cardNumber - Card number
 * @returns {string} Card type
 */
export const getCardType = (cardNumber) => {
    const patterns = {
        visa: /^4/,
        mastercard: /^5[1-5]/,
        amex: /^3[47]/,
        discover: /^6(?:011|5)/,
        jcb: /^35/,
        diners: /^3(?:0[0-5]|[68])/,
    };

    const digits = cardNumber.replace(/\D/g, '');

    for (const [type, pattern] of Object.entries(patterns)) {
        if (pattern.test(digits)) {
            return type;
        }
    }

    return 'unknown';
};

/**
 * Format card number for display
 * @param {string} cardNumber - Card number
 * @returns {string} Formatted card number
 */
export const formatCardNumber = (cardNumber) => {
    const digits = cardNumber.replace(/\D/g, '');
    const cardType = getCardType(digits);

    if (cardType === 'amex') {
        // Format: 4-6-5
        return digits.replace(/(\d{4})(\d{6})(\d{5})/, '$1 $2 $3').trim();
    } else {
        // Format: 4-4-4-4
        return digits.replace(/(\d{4})(?=\d)/g, '$1 ').trim();
    }
};

/**
 * Validate CVV based on card type
 * @param {string} cvv - CVV code
 * @param {string} cardType - Type of card
 * @returns {boolean} Is valid
 */
export const validateCVV = (cvv, cardType) => {
    const cvvDigits = cvv.replace(/\D/g, '');
    
    if (cardType === 'amex') {
        // American Express uses 4-digit CVV
        return cvvDigits.length === 4;
    } else {
        // Other cards use 3-digit CVV
        return cvvDigits.length === 3;
    }
};

/**
 * Mask card number for display
 * @param {string} cardNumber - Card number
 * @returns {string} Masked card number
 */
export const maskCardNumber = (cardNumber) => {
    const digits = cardNumber.replace(/\D/g, '');
    const last4 = digits.slice(-4);
    const masked = digits.slice(0, -4).replace(/\d/g, '•');
    return formatCardNumber(masked + last4);
};

/**
 * Validate card holder name
 * @param {string} name - Card holder name
 * @returns {boolean} Is valid
 */
export const validateCardholderName = (name) => {
    // Allow letters, spaces, hyphens, and apostrophes
    const namePattern = /^[a-zA-Z\s\-']+$/;
    return name && name.length >= 2 && namePattern.test(name);
};

/**
 * Get card issuer name from card type
 * @param {string} cardType - Card type
 * @returns {string} Card issuer name
 */
export const getCardIssuerName = (cardType) => {
    const issuers = {
        visa: 'Visa',
        mastercard: 'Mastercard',
        amex: 'American Express',
        discover: 'Discover',
        jcb: 'JCB',
        diners: 'Diners Club',
        unknown: 'Unknown',
    };
    
    return issuers[cardType] || issuers.unknown;
};

/**
 * Check if expiry date is within valid range
 * @param {string} month - Expiry month
 * @param {string} year - Expiry year
 * @returns {Object} Validation result with isValid and message
 */
export const validateExpiryDate = (month, year) => {
    const result = {
        isValid: true,
        message: '',
    };

    if (!month || !year) {
        result.isValid = false;
        result.message = __('Expiry date is required', 'woocommerce-gateway-cardknox');
        return result;
    }

    const currentDate = new Date();
    const currentYear = currentDate.getFullYear();
    const currentMonth = currentDate.getMonth() + 1;
    const expMonth = parseInt(month, 10);
    const expYear = parseInt(year, 10);

    // Check if month is valid
    if (expMonth < 1 || expMonth > 12) {
        result.isValid = false;
        result.message = __('Invalid expiry month', 'woocommerce-gateway-cardknox');
        return result;
    }

    // Check if card has expired
    if (expYear < currentYear || (expYear === currentYear && expMonth < currentMonth)) {
        result.isValid = false;
        result.message = __('Card has expired', 'woocommerce-gateway-cardknox');
        return result;
    }

    // Check if expiry date is too far in the future (more than 20 years)
    if (expYear > currentYear + 20) {
        result.isValid = false;
        result.message = __('Invalid expiry year', 'woocommerce-gateway-cardknox');
        return result;
    }

    return result;
};