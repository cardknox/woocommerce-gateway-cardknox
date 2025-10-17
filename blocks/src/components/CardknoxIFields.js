/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { useEffect } from '@wordpress/element';

const cardLogoUrl = 'https://plugin.cardknox.net/demo/wpdemo/wp-content/plugins/woocommerce-gateway-cardknox/images/card-logos.png';

const CardknoxIFields = ({ errors, onExpiryChange, ValidationInputError, cardData }) => {
    

    const validateExpiry = () => {

        if (!cardData.expiryMonth || !cardData.expiryYear) {
            return false;
        }

        const currentDate = new Date();
        const currentYear = currentDate.getFullYear();
        const currentMonth = currentDate.getMonth() + 1;
        const expMonth = parseInt(cardData.expiryMonth, 10);
        const expYear = parseInt(cardData.expiryYear, 10);

        if (expYear < currentYear || (expYear === currentYear && expMonth < currentMonth)) {
            return false;
        }

        return true;
    };

    // Ensure no container borders; styling is inside iField
    useEffect(() => {
        const cardContainer = document.querySelector('.cardknox-iframe-container');
        const cvvContainer = document.querySelector('.cvv-container');
        if (cardContainer) cardContainer.style.border = '0';
        if (cvvContainer) cvvContainer.style.border = '0';
        
    }, []);

    return (
        <div className="wc-cardknox-ifields credit-row">
            <div id="ifieldsError" style={{ display: 'none', color: 'red', marginBottom: '10px' }}></div>

            <div className="form-row form-row-wide" style={{ paddingBottom: '0', margin: '0 0 15px 0', width: '100%' }}>
                <label style={{ margin: '0 0 5px 0', lineHeight: 'inherit', display: 'block', fontWeight: 400 }} htmlFor="cardknox-card-number">
                    {__('Card Number', 'woocommerce-gateway-cardknox')} <span className="required">*</span>
                </label>

                <div className="cardknox-card-number-wrapper" style={{ position: 'relative' }}>
                    <div className="cardknox-iframe-container" style={{ position: 'relative', overflow: 'hidden', backgroundColor: '#fff', height: '65px' }}>
                        <iframe
                            data-ifields-id="card-number"
                            data-ifields-placeholder={__('Card Number', 'woocommerce-gateway-cardknox')}
                            src="https://cdn.cardknox.com/ifields/3.0.2503.2101/ifield.htm"
                            frameBorder="0"
                            width="100%"
                            height="100%"
                            style={{ border: 0 }}
                            title="Card Number"
                        ></iframe>
                    </div>

                    <div
                        className="card-logos"
                        style={{
                            position: 'absolute',
                            right: '50px',
                            top: '40%',
                            transform: 'translateY(-50%)',
                            height: '40px',
                            width: '100%',
                            backgroundImage: `url(${cardLogoUrl})`,
                            backgroundSize: 'auto',
                            backgroundRepeat: 'no-repeat',
                            backgroundPosition: 'center right',
                            pointerEvents: 'none',
                        }}
                    />
                </div>

                <div className="cardknox-error-container">
                    {/* Container used by the SDK + React error fallback */}
                    <div data-ifields-id="card-number-error"></div>
                    {errors.cardNumber && <ValidationInputError errorMessage={errors.cardNumber} />}
                </div>
            </div>

            <div className="cardknox-row" style={{ display: 'flex', gap: '15px', marginBottom: '0px' }}>
                <div className="form-row form-row-first" style={{ flex: '1', margin: '0' }}>
                    <label style={{ margin: '0 0 5px 0', lineHeight: 'inherit', display: 'block', fontWeight: 400 }} htmlFor="cardknox-expiry">
                        {__('Expiry (MM/YY)', 'woocommerce-gateway-cardknox')} <span className="required">*</span>
                    </label>
                    <input
                        id="cardknox-card-expiry"
                        className="input-text wc-credit-card-form-card-expiry"
                        inputMode="numeric"
                        autoComplete="cc-exp"
                        autoCorrect="no"
                        autoCapitalize="no"
                        spellCheck="no"
                        type="tel"
                        placeholder={__('MM / YY', 'woocommerce-gateway-cardknox')}
                        style={{
                            outline: 'none',
                            border:
                                errors.expiry || (!validateExpiry() && cardData.expiryMonth && cardData.expiryYear)
                                    ? '1px solid red'
                                    : '1px solid #000',
                            borderRadius: '4px',
                            padding: '0.618047em',
                            width: '100%',
                            height: '48px',
                            backgroundColor: '#fff',
                            fontWeight: 'inherit',
                            boxSizing: 'border-box',
                            fontSize: '16px',
                        }}
                        onChange={(e) => {
                            const value = e.target.value.replace(/\D/g, '');
                            let formattedValue = value;

                            if (value.length >= 2) {
                                formattedValue = value.substr(0, 2) + ' / ' + value.substr(2, 2);
                            }

                            e.target.value = formattedValue;

                            if (value.length >= 2) {
                                const month = value.substr(0, 2);
                                onExpiryChange('expiryMonth', month);
                            }

                            if (value.length === 4) {
                                const year = '20' + value.substr(2, 2);
                                onExpiryChange('expiryYear', year);
                            }
                        }}
                    />
                    {(errors.expiry || (!validateExpiry() && cardData.expiryMonth && cardData.expiryYear)) && (
                        <ValidationInputError errorMessage={errors.expiry || __('Expiration must be in the future', 'woocommerce-gateway-cardknox')} />
                    )}
                </div>

                <div className="form-row form-row-last" style={{ flex: '1', margin: '0' }}>
                    <label style={{ margin: '0 0 5px 0', lineHeight: 'inherit', display: 'block', fontWeight: 400 }} htmlFor="cardknox-cvv">
                        {__('CVV Code', 'woocommerce-gateway-cardknox')} <span className="required">*</span>
                    </label>
                    <div className="cardknox-iframe-container cvv-container" style={{ position: 'relative', overflow: 'hidden', backgroundColor: '#fff', height: '65px' }}>
                        <iframe
                            data-ifields-id="cvv"
                            data-ifields-placeholder={__('CVV', 'woocommerce-gateway-cardknox')}
                            src="https://cdn.cardknox.com/ifields/3.0.2503.2101/ifield.htm"
                            frameBorder="0"
                            width="100%"
                            height="100%"
                            style={{ border: 0 }}
                            title="CVV"
                        ></iframe>
                    </div>
                    <div data-ifields-id="cvv-error"></div>
                    {errors.cvv && <ValidationInputError errorMessage={errors.cvv} />}
                </div>
            </div>

            {/* Hidden fields for tokens */}
            <input type="hidden" data-ifields-id="card-number-token" name="xCardNum" />
            <input type="hidden" data-ifields-id="cvv-token" name="xCVV" />

            <div className="clear"></div>
        </div>
    );
};

export default CardknoxIFields;