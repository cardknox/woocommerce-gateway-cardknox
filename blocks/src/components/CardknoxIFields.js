/**
 * External dependencies
 */
import { useEffect, useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

// Import the card logos image
// const cardLogoUrl = '/wp-content/plugins/woocommerce-gateway-cardknox/images/card-logos.png';
const cardLogoUrl = 'https://plugin.cardknox.net/demo/wpdemo/wp-content/plugins/woocommerce-gateway-cardknox/images/card-logos.png';

const CardknoxIFields = ({ errors, onExpiryChange, ValidationInputError, cardData }) => {
    const cardNumberRef = useRef(null);
    const cvvRef = useRef(null);

    useEffect(() => {
        // Create iField containers
        if (cardNumberRef.current && !cardNumberRef.current.querySelector('iframe')) {
            const cardNumberIframe = document.createElement('iframe');
            cardNumberIframe.setAttribute('data-ifields-id', 'card-number');
            cardNumberIframe.setAttribute('data-ifields-placeholder', 'Card Number');
            cardNumberIframe.src = 'https://cdn.cardknox.com/ifields/3.0.2503.2101/ifield.htm';
            cardNumberIframe.style.width = '100%';
            cardNumberIframe.style.height = '100%';
            cardNumberIframe.style.border = 'none';
            cardNumberIframe.style.backgroundColor = 'transparent';
            cardNumberIframe.frameBorder = '0';
            cardNumberRef.current.appendChild(cardNumberIframe);
            
            // Add error container after iframe
            const errorContainer = document.createElement('div');
            errorContainer.setAttribute('data-ifields-id', 'card-number-error');
            errorContainer.style.color = 'red';
            errorContainer.style.marginTop = '5px';
            errorContainer.style.marginBottom = '5px';
            errorContainer.style.display = 'block';
            errorContainer.style.clear = 'both';
            errorContainer.style.width = '100%';
            errorContainer.style.fontSize = '14px';
            cardNumberRef.current.appendChild(errorContainer);
        }

        if (cvvRef.current && !cvvRef.current.querySelector('iframe')) {
            const cvvIframe = document.createElement('iframe');
            cvvIframe.setAttribute('data-ifields-id', 'cvv');
            cvvIframe.setAttribute('data-ifields-placeholder', 'CVV');
            cvvIframe.src = 'https://cdn.cardknox.com/ifields/3.0.2503.2101/ifield.htm';
            cvvIframe.style.width = '100%';
            cvvIframe.style.height = '100%';
            cvvIframe.style.border = 'none';
            cvvIframe.style.backgroundColor = 'transparent';
            cvvIframe.frameBorder = '0';
            cvvRef.current.appendChild(cvvIframe);
            
            // Add error container after iframe
            const errorContainer = document.createElement('div');
            errorContainer.setAttribute('data-ifields-id', 'cvv-error');
            errorContainer.style.color = 'red';
            errorContainer.style.marginTop = '5px';
            errorContainer.style.marginBottom = '5px';
            errorContainer.style.display = 'block';
            errorContainer.style.clear = 'both';
            errorContainer.style.width = '100%';
            errorContainer.style.fontSize = '14px';
            cvvRef.current.appendChild(errorContainer);
        }

        // Apply default styles to iFields matching classic checkout
        if (window.setIfieldStyle) {
            const defaultStyle = {
                outline: 'none',
                border: '1px solid #c3c3c3',
                'border-radius': '4px',
                padding: '0.6180469716em',
                width: '95%',
                height: '40px !important',
                'background-color': 'transparent',
                'font-weight': 'inherit',
                'box-shadow': 'none',
                'font-size': '16px',
                'font-family': '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif'
            };
            const defaultStyleCvv = {
                outline: 'none',
                border: '1px solid #c3c3c3',
                'border-radius': '4px',
                padding: '0.6180469716em',
                width: '88%',
                height: '30px',
                'background-color': 'transparent',
                'font-weight': 'inherit',
                'box-shadow': 'none',
                'font-size': '16px',
                'font-family': '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif'
            };

            window.setIfieldStyle('card-number', defaultStyle);
            window.setIfieldStyle('cvv', defaultStyleCvv);
        }
    }, []);

    useEffect(() => {
        // Update iField styles based on validation
        if (window.setIfieldStyle) {
            const validStyle = {
                border: '1px solid #c3c3c3',
            };
            
            const invalidStyle = {
                border: '1px solid red',
            };

            if (errors.cardNumber) {
                window.setIfieldStyle('card-number', invalidStyle);
            } else {
                window.setIfieldStyle('card-number', validStyle);
            }

            if (errors.cvv) {
                window.setIfieldStyle('cvv', invalidStyle);
            } else {
                window.setIfieldStyle('cvv', validStyle);
            }
        }
    }, [errors]);

    const currentYear = new Date().getFullYear();
    const years = Array.from({ length: 20 }, (_, i) => currentYear + i);

    // Validate expiry date
    const validateExpiry = () => {
        if (!cardData.expiryMonth || !cardData.expiryYear) {
            return false;
        }

        const currentDate = new Date();
        const currentYear = currentDate.getFullYear();
        const currentMonth = currentDate.getMonth() + 1;
        const expMonth = parseInt(cardData.expiryMonth, 10);
        const expYear = parseInt(cardData.expiryYear, 10);

        // Check if card has expired
        if (expYear < currentYear || (expYear === currentYear && expMonth < currentMonth)) {
            return false;
        }

        return true;
    };

    return (
        <div className="wc-cardknox-ifields">
            {/* Error display for general card data errors */}
            <div id="ifieldsError" style={{ display: 'none', color: 'red', marginBottom: '10px' }}></div>
            
            <div className="form-row form-row-wide" style={{ paddingBottom: '0', margin: '0 0 15px 0', width: '100%' }}>
                <label style={{ margin: '0 0 5px 0', lineHeight: 'inherit', display: 'block', fontWeight:400, }} htmlFor="cardknox-card-number">
                    {__('Card Number', 'woocommerce-gateway-cardknox')} <span className="required">*</span>
                </label>
                <div className="cardknox-card-number-wrapper" style={{ position: 'relative' }}>
                    <div 
                        ref={cardNumberRef} 
                        className="cardknox-iframe-container"
                        style={{
                            backgroundColor: '#fff',
                            height: '65px',
                            border: '1px solid #c3c3c3',
                            borderRadius: '4px',
                        }}
                    >
                        {/* iFrame will be inserted here */}
                    </div>
                    <div className="card-logos" style={{ 
                        position: 'absolute', 
                        right: '50px', 
                        top: '43%', 
                        transform: 'translateY(-50%)',
                        height: '40px',
                        width: '100%',
                        backgroundImage: `url(${cardLogoUrl})`,
                        backgroundSize: 'auto',
                        backgroundRepeat: 'no-repeat',
                        backgroundPosition: 'center right',
                        pointerEvents: 'none'
                    }}></div>
                </div>
                <div className="cardknox-error-container">
                    {errors.cardNumber && (
                        <ValidationInputError errorMessage={errors.cardNumber} />
                    )}
                </div>
            </div>

            <div className="cardknox-row" style={{ display: 'flex', gap: '15px', marginBottom: '15px' }}>
                <div className="form-row form-row-first" style={{ flex: '1', margin: '0' }}>
                    <label style={{ margin: '0 0 5px 0', lineHeight: 'inherit', display: 'block', fontWeight:400 }} htmlFor="cardknox-expiry">
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
                            border: errors.expiry || (!validateExpiry() && cardData.expiryMonth && cardData.expiryYear) ? '1px solid red' : '1px solid rgb(195, 195, 195)',
                            borderRadius: '4px',
                            padding: '0.618047em',
                            width: '100%',
                            height: '52px',
                            backgroundColor: '#fff',
                            fontWeight: 'inherit',
                            boxSizing: 'border-box',
                            fontSize: '16px'
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
                    <label style={{ margin: '0 0 5px 0', lineHeight: 'inherit', display: 'block', fontWeight:400 }} htmlFor="cardknox-cvv">
                        {__('Card Code', 'woocommerce-gateway-cardknox')} <span className="required">*</span>
                    </label>
                    <div 
                        ref={cvvRef} 
                        className="cardknox-iframe-container cvv-container"
                        style={{
                            backgroundColor: '#fff',
                            height: '65px',
                            border: '1px solid #c3c3c3',
                            borderRadius: '4px',
                        }}
                    >
                        {/* iFrame will be inserted here */}
                    </div>
                    {errors.cvv && (
                        <ValidationInputError errorMessage={errors.cvv} />
                    )}
                </div>
            </div>
            <div style={{ marginBottom: '10px' }}>
                <label data-ifields-id="card-data-error" style={{ color: 'red' }}></label>
            </div>

            {/* Hidden fields for tokens */}
            <input type="hidden" data-ifields-id="card-number-token" name="xCardNum" />
            <input type="hidden" data-ifields-id="cvv-token" name="xCVV" />
            
            <div className="clear"></div>
        </div>
    );
};

export default CardknoxIFields;