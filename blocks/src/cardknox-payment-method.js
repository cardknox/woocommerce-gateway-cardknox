/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { decodeEntities } from '@wordpress/html-entities';
import { createElement } from '@wordpress/element';

/**
 * Internal dependencies
 */
import CardknoxPaymentForm from './components/CardknoxPaymentForm';
import { PAYMENT_METHOD_NAME } from './utils/constants';

// Get settings from global WC object
const getSettings = () => {
    if (window.wc && window.wc.wcSettings) {
        return window.wc.wcSettings.getSetting('cardknox_data', {});
    }
    // Fallback to checking wcSettings global
    if (window.wcSettings) {
        return window.wcSettings.getSetting('cardknox_data', {});
    }
    return {};
};

const settings = getSettings();

const defaultLabel = __('Credit Card (Sola)', 'woocommerce-gateway-cardknox');
const label = decodeEntities(settings.title) || defaultLabel;

/**
 * Content component wrapper
 */
const Content = (props) => createElement(CardknoxPaymentForm, props);

/**
 * Label component
 */
const Label = () => createElement('span', null, label);

/**
 * Cardknox payment method configuration
 */
const CardknoxPaymentMethod = {
    name: PAYMENT_METHOD_NAME,
    label: createElement(Label),
    content: createElement(Content),
    edit: createElement(Content),
    canMakePayment: () => true,
    ariaLabel: label,
    supports: {
        features: settings.supports ?? ['products'],
        showSaveOption: settings.showSaveOption ?? false,
        showSavedCards: Array.isArray(settings.savedCards) && settings.savedCards.length > 0,
    },
    placeOrderButtonLabel: __('Place Order', 'woocommerce-gateway-cardknox'),
};

export default CardknoxPaymentMethod;