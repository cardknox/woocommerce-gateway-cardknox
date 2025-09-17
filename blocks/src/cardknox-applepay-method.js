/* global ApplePaySession, WCCardknoxApplePayBlocks */
import ApplePayButton from './components/ApplePayButton';
import { createElement } from '@wordpress/element';

const settings = window.WCCardknoxApplePayBlocks || {};
const merchantId =
	settings.merchantId ||
	settings.merchant_identifier ||
	settings.merchantIdentifier ||
	'';

// basic availability checks; keeps the radio hidden on unsupported browsers
function supportsApplePaySync() {
	try {
		return !!(window.ApplePaySession && ApplePaySession.canMakePayments());
	} catch (e) {
		return false;
	}
}
async function canMakeApplePay() {
	const isSecure =
		(typeof window !== 'undefined' && window.isSecureContext === true) ||
		(typeof location !== 'undefined' && location.protocol === 'https:');

	if (!merchantId || !isSecure) return false;
	if (!supportsApplePaySync()) return false;

	// Prefer strict Safari check when available
	if (typeof ApplePaySession?.canMakePaymentsWithActiveCard === 'function') {
		try {
			return await ApplePaySession.canMakePaymentsWithActiveCard(merchantId);
		} catch (e) {
			return false;
		}
	}

	try {
		return ApplePaySession.canMakePayments();
	} catch (e) {
		return false;
	}
}

const CardknoxApplePayMethod = {
	// MUST match your gateway id and PHP Blocks class
	name: 'cardknox-applepay',
	label: 'Apple Pay',
	ariaLabel: 'Apple Pay',

	// React elements (not functions)
	content: createElement(ApplePayButton, { settings }),
	edit: createElement(ApplePayButton, { settings, isEditor: true }),

	// Async is supported by Blocks; will hide radio when Apple Pay isn’t available
	canMakePayment: () => canMakeApplePay(),

	supports: { features: ['products'] },
};

export default CardknoxApplePayMethod;