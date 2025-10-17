/**
 * External dependencies - using global WooCommerce objects
 */
const { registerPaymentMethod } = window.wc.wcBlocksRegistry;

/**
 * Internal dependencies
 */
import CardknoxPaymentMethod from './cardknox-payment-method';
import CardknoxApplePayMethod from './cardknox-applepay-method';

// Register the payment method when DOM is ready
if (registerPaymentMethod) {
    registerPaymentMethod(CardknoxPaymentMethod);
    registerPaymentMethod(CardknoxApplePayMethod);
}