/**
 * Payment method constants
 */
export const PAYMENT_METHOD_NAME = 'cardknox';

/**
 * iFields configuration
 */
export const IFIELDS_VERSION = '3.3.2601.2901';
export const IFIELDS_CDN_URL = `https://cdn.cardknox.com/ifields/${IFIELDS_VERSION}/ifields.min.js`;
export const IFIELDS_IFRAME_URL = `https://cdn.cardknox.com/ifields/${IFIELDS_VERSION}/ifield.htm`;

/**
 * Card types mapping
 */
export const CARD_TYPES = {
    visa: 'Visa',
    mastercard: 'Mastercard',
    amex: 'American Express',
    discover: 'Discover',
    jcb: 'JCB',
    diners: 'Diners Club',
    unknown: 'Unknown',
};

/**
 * Card type patterns for detection
 */
export const CARD_PATTERNS = {
    visa: /^4[0-9]{12}(?:[0-9]{3})?$/,
    mastercard: /^5[1-5][0-9]{14}$/,
    amex: /^3[47][0-9]{13}$/,
    discover: /^6(?:011|5[0-9]{2})[0-9]{12}$/,
    jcb: /^(?:2131|1800|35\d{3})\d{11}$/,
    diners: /^3(?:0[0-5]|[68][0-9])[0-9]{11}$/,
};

/**
 * Error messages
 */
export const ERROR_MESSAGES = {
    INVALID_CARD: 'Invalid card number',
    INVALID_CVV: 'Invalid security code',
    INVALID_EXPIRY: 'Invalid expiry date',
    CARD_EXPIRED: 'Card has expired',
    REQUIRED_FIELD: 'This field is required',
    PROCESSING_ERROR: 'An error occurred while processing your payment',
    TOKEN_ERROR: 'Unable to tokenize card data',
    NETWORK_ERROR: 'Network error. Please check your connection and try again',
    TIMEOUT_ERROR: 'Request timed out. Please try again',
    INVALID_NAME: 'Please enter a valid cardholder name',
    SERVER_ERROR: 'Server error. Please try again later',
    VALIDATION_ERROR: 'Please check your card details and try again',
};

/**
 * Success messages
 */
export const SUCCESS_MESSAGES = {
    PAYMENT_SUCCESS: 'Payment processed successfully',
    CARD_SAVED: 'Card saved successfully',
    CARD_DELETED: 'Card deleted successfully',
};

/**
 * Field placeholders
 */
export const FIELD_PLACEHOLDERS = {
    CARD_NUMBER: '1234 5678 9012 3456',
    CVV: 'CVV',
    CVV_AMEX: 'CVVV',
    CARD_NAME: 'Name on card',
    EXPIRY_MONTH: 'MM',
    EXPIRY_YEAR: 'YYYY',
};

/**
 * Field labels
 */
export const FIELD_LABELS = {
    CARD_NUMBER: 'Card Number',
    CVV: 'Security Code',
    CARD_NAME: 'Cardholder Name',
    EXPIRY_DATE: 'Expiry Date',
    EXPIRY_MONTH: 'Month',
    EXPIRY_YEAR: 'Year',
    SAVE_CARD: 'Save to account',
};

/**
 * iFields event types
 */
export const IFIELD_EVENTS = {
    INPUT: 'input',
    CLICK: 'click',
    FOCUS: 'focus',
    BLUR: 'blur',
    SUBMIT: 'submit',
    ESCAPE: 'escape',
    TAB: 'tab',
    SHIFT_TAB: 'shifttab',
    ENTER: 'enter',
    AUTOFILL: 'autofill',
    UPDATE: 'update',
};

/**
 * iFields style states
 */
export const IFIELD_STYLES = {
    DEFAULT: {
        border: '1px solid #ddd',
        'font-size': '14px',
        padding: '12px',
        'border-radius': '4px',
        width: '100%',
        height: '48px',
        'box-sizing': 'border-box',
        'font-family': '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif',
        'line-height': '1.4',
        color: '#32373c',
        'background-color': '#fff',
        transition: 'border-color 0.3s ease',
    },
    VALID: {
        border: '1px solid #46b450',
        'background-color': '#f0f9f0',
    },
    INVALID: {
        border: '1px solid #d63638',
        'background-color': '#fef5f5',
    },
    FOCUSED: {
        border: '1px solid #007cba',
        outline: 'none',
        'box-shadow': '0 0 0 1px #007cba',
    },
};

/**
 * Timeout values (in milliseconds)
 */
export const TIMEOUTS = {
    TOKEN_REQUEST: 15000, // 15 seconds
    API_REQUEST: 30000,   // 30 seconds
    VALIDATION: 500,      // 500ms debounce for validation
};

/**
 * Regular expressions for validation
 */
export const REGEX = {
    NUMBERS_ONLY: /^\d+$/,
    LETTERS_ONLY: /^[a-zA-Z\s]+$/,
    ALPHANUMERIC: /^[a-zA-Z0-9]+$/,
    EMAIL: /^(?=.{1,320}$)(?=.{1,64}@)[A-Za-z0-9!#$%&'*+/=?^_`{|}~-]+(?:\.[A-Za-z0-9!#$%&'*+/=?^_`{|}~-]+)*@(?:[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?\.)+[A-Za-z]{2,63}$/,
    WHITESPACE: /\s/g,
};

/**
 * API endpoints
 */
export const API_ENDPOINTS = {
    PROCESS_PAYMENT: '/wp-json/wc/store/checkout',
    VALIDATE_CARD: '/wp-json/cardknox/v1/validate',
    SAVE_CARD: '/wp-json/cardknox/v1/save-card',
    DELETE_CARD: '/wp-json/cardknox/v1/delete-card',
};

/**
 * Local storage keys
 */
export const STORAGE_KEYS = {
    LAST_CARD_TYPE: 'cardknox_last_card_type',
    PREFERRED_SAVE_METHOD: 'cardknox_save_preference',
};

/**
 * Card number lengths by type
 */
export const CARD_NUMBER_LENGTHS = {
    visa: [13, 16, 19],
    mastercard: [16],
    amex: [15],
    discover: [16, 19],
    jcb: [16, 17, 18, 19],
    diners: [14, 15, 16, 17, 18, 19],
};

/**
 * CVV lengths by card type
 */
export const CVV_LENGTHS = {
    visa: 3,
    mastercard: 3,
    amex: 4,
    discover: 3,
    jcb: 3,
    diners: 3,
};

/**
 * Default configuration
 */
export const DEFAULT_CONFIG = {
    autoFormat: true,
    autoSubmit: true,
    enableValidation: true,
    showCardIcon: true,
    requireCVV: true,
    allowSaveCard: true,
    validateOnBlur: true,
    maskCardNumber: true,
    animateOnError: true,
};