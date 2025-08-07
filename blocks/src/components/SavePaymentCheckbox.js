/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

const SavePaymentCheckbox = ({ checked, onChange }) => {
    return (
        <div className="wc-payment-gateway-cardknox__save-payment-method wc-block-components-payment-methods__save-card-info">
            <label className="wc-block-components-checkbox wc-block-components-payment-methods__save-card-infos-checkbox">
                <input
                    type="checkbox"
                    name="wc-cardknox-new-payment-method"
                    id="wc-cardknox-new-payment-method"
                    className="wc-block-components-checkbox__input"
                    checked={checked}
                    onChange={(e) => onChange(e.target.checked)}
                />
                <svg className="wc-block-components-checkbox__mark" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 20">
                    <path d="M9 16.2L4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4L9 16.2z" />
                </svg>
                <span className="wc-block-components-chesckbox__label">
                    {__('Save payment information to my account for future purchases.', 'woocommerce-gateway-cardknox')}
                </span>
            </label>
        </div>
    );
};

export default SavePaymentCheckbox;