/**
 * External dependencies
 */
import { useState, useEffect, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import CardknoxIFields from './CardknoxIFields';
import SavePaymentCheckbox from './SavePaymentCheckbox';
import useCardknoxIFields from '../hooks/useCardknoxIFields';
import { validateCardData } from '../utils/cardknox-validator';

// Get settings helper
const getSettings = () => {
    if (window.wc && window.wc.wcSettings) {
        return window.wc.wcSettings.getSetting('cardknox_data', {});
    }
    if (window.wcSettings) {
        return window.wcSettings.getSetting('cardknox_data', {});
    }
    return {};
};

const CardknoxPaymentForm = (props) => {
    // Handle both possible prop structures
    const eventRegistration = props.eventRegistration || props.events;
    const emitResponse = props.emitResponse;
    const components = props.components || {};
    
    const settings = getSettings();
    const [errors, setErrors] = useState({});
    const [isValid, setIsValid] = useState(false);
    const [saveCard, setSaveCard] = useState(false);
    const [selectedToken, setSelectedToken] = useState('new');
    const [cardData, setCardData] = useState({
        cardNumber: '',
        cvv: '',
        expiryMonth: '',
        expiryYear: '',
        cardNumberToken: '',
        cvvToken: '',
    });

    const {
        initializeIFields,
        getTokens,
        clearFields,
        focusField,
    } = useCardknoxIFields();

    // Get ValidationInputError component or create a fallback
    const ValidationInputError = components.ValidationInputError || 
        (({ errorMessage }) => {
            if (!errorMessage) return null;
            return <div className="wc-block-components-validation-error" role="alert">
                <span>{errorMessage}</span>
            </div>;
        });

    useEffect(() => {
        // Initialize iFields when component mounts
        const attemptInit = () => {
            const hasKey = !!settings.iFieldsKey;
            const hasSDK = !!window.setAccount && !!window.setIfieldStyle && !!window.addIfieldCallback;
            // eslint-disable-next-line no-console
            console.log('[Cardknox][CardknoxPaymentForm] attempt init', { hasKey, hasSDK });
            if (hasKey && hasSDK) {
                initializeIFields({
                    iFieldsKey: settings.iFieldsKey,
                    softwareName: settings.softwareName || 'WooCommerce',
                    softwareVersion: settings.softwareVersion || '1.0.0',
                    onUpdate: handleIFieldUpdate,
                    onSubmit: handleIFieldSubmit,
                });
            }
        };

        attemptInit();
        // As the SDK may be enqueued separately, re-attempt after a short delay
        const t = window.setTimeout(attemptInit, 300);
        return () => window.clearTimeout(t);
    }, [settings.iFieldsKey]);

    useEffect(() => {
        if (!eventRegistration || !emitResponse) {
            return;
        }

        const { onPaymentProcessing, onPaymentSetup } = eventRegistration;
        
        // Prefer onPaymentSetup to avoid deprecation warnings; fallback to onPaymentProcessing
        const paymentEvent = onPaymentSetup || onPaymentProcessing;
        
        if (!paymentEvent) {
            return;
        }

        const unsubscribe = paymentEvent(async () => {
            // eslint-disable-next-line no-console
            console.log('[Cardknox][CardknoxPaymentForm] payment event fired');
            try {
                if (selectedToken !== 'new') {
                    return {
                        type: emitResponse.responseTypes.SUCCESS,
                        meta: { paymentMethodData: { wc_token: selectedToken } },
                    };
                }

                // Validate expiry fields (card number and cvv validation is handled by getTokens/iFields)
                const validationErrors = validateCardData(cardData);
                if (cardData.expiryMonth && cardData.expiryYear) {
                    const currentDate = new Date();
                    const currentYear = currentDate.getFullYear();
                    const currentMonth = currentDate.getMonth() + 1;
                    const expMonth = parseInt(cardData.expiryMonth, 10);
                    const expYear = parseInt(cardData.expiryYear, 10);
                    if (expYear < currentYear || (expYear === currentYear && expMonth < currentMonth)) {
                        validationErrors.expiry = __('Expiration must be in the future', 'woocommerce-gateway-cardknox');
                    }
                } else {
                    validationErrors.expiry = __('Expiry date is required', 'woocommerce-gateway-cardknox');
                }

                // Capture any inline errors already present from iFields
                const cardNumberInlineError = (document.querySelector('[data-ifields-id="card-number-error"]')?.textContent || '').trim();
                const cvvInlineError = (document.querySelector('[data-ifields-id="cvv-error"]')?.textContent || '').trim();

                if (Object.keys(validationErrors).length > 0 || cardNumberInlineError || cvvInlineError || errors.cardNumber || errors.cvv) {
                    // eslint-disable-next-line no-console
                    console.warn('[Cardknox][CardknoxPaymentForm] inline validation failed', { validationErrors, cardNumberInlineError, cvvInlineError, errors });
                    setErrors(validationErrors);
                    return {
                        type: emitResponse.responseTypes.ERROR,
                        message: __('Please check your card details.', 'woocommerce-gateway-cardknox'),
                    };
                }

                // Clear any existing errors before getting tokens
                setErrors({});

                // If tokens already exist, reuse; otherwise fetch
                const existingCardToken = document.querySelector('[data-ifields-id="card-number-token"]')?.value;
                const existingCvvToken = document.querySelector('[data-ifields-id="cvv-token"]')?.value;
                const tokens = existingCardToken && existingCvvToken
                    ? { cardNumberToken: existingCardToken, cvvToken: existingCvvToken }
                    : await getTokens();
                // eslint-disable-next-line no-console
                console.log('[Cardknox][CardknoxPaymentForm] tokens result', tokens);
                if (!tokens.cardNumberToken || !tokens.cvvToken) {
                    return {
                        type: emitResponse.responseTypes.ERROR,
                        message: __('Unable to process card data. Please try again.', 'woocommerce-gateway-cardknox'),
                    };
                }

                return {
                    type: emitResponse.responseTypes.SUCCESS,
                    meta: {
                        paymentMethodData: {
                            cardknox_card_token: tokens.cardNumberToken,
                            cardknox_cvv_token: tokens.cvvToken,
                            cardknox_exp_month: cardData.expiryMonth,
                            cardknox_exp_year: cardData.expiryYear,
                            cardknox_save_card: saveCard ? 'yes' : 'no',
                            'wc-cardknox-new-payment-method': saveCard ? '1' : '',
                        },
                    },
                };
            } catch (error) {
                // eslint-disable-next-line no-console
                console.error('[Cardknox][CardknoxPaymentForm] payment event error', error);
                return {
                    type: emitResponse.responseTypes.ERROR,
                    message: error?.message || __('Payment processing failed.', 'woocommerce-gateway-cardknox'),
                };
            }
        });

        return () => {
            if (typeof unsubscribe === 'function') {
                unsubscribe();
            }
        };
    }, [
        eventRegistration,
        emitResponse,
        cardData,
        saveCard,
        selectedToken,
        getTokens,
    ]);

    const handleIFieldUpdate = useCallback((data) => {
        // Update validation state based on iField data
        // eslint-disable-next-line no-console
        console.log('[Cardknox][CardknoxPaymentForm] onUpdate', data);
        const newErrors = { ...errors };

        // Check if tokens exist first
        const cardNumberToken = document.querySelector('[data-ifields-id="card-number-token"]')?.value;
        const cvvToken = document.querySelector('[data-ifields-id="cvv-token"]')?.value;

        if (data.lastActiveField === 'card-number' || data.cardNumberLength !== undefined) {
            if (cardNumberToken || data.cardNumberIsValid) {
                delete newErrors.cardNumber;
            } else if (data.cardNumberLength > 0 && !data.cardNumberIsValid) {
                newErrors.cardNumber = __('Invalid card number', 'woocommerce-gateway-cardknox');
            } else {
                delete newErrors.cardNumber;
            }
        }

        if (data.lastActiveField === 'cvv' || data.cvvLength !== undefined) {
            if (cvvToken || data.cvvIsValid) {
                delete newErrors.cvv;
            } else if (data.cvvLength > 0 && !data.cvvIsValid) {
                newErrors.cvv = __('Invalid CVV', 'woocommerce-gateway-cardknox');
            } else {
                delete newErrors.cvv;
            }
        }

        setErrors(newErrors);
        const cardNumberValid = !!(cardNumberToken || data.cardNumberIsValid);
        const cvvValid = !!(cvvToken || data.cvvIsValid);
        setIsValid(cardNumberValid && cvvValid);

        // Containers must not draw borders now; iField content handles it. Clear any container borders.
        const cardContainer = document.querySelector('.cardknox-iframe-container');
        const cvvContainer = document.querySelector('.cvv-container');
        if (cardContainer) cardContainer.style.border = '0';
        if (cvvContainer) cvvContainer.style.border = '0';
    }, [errors]);

    const handleIFieldSubmit = useCallback(() => {
        // Handle enter key press in iFields
        // eslint-disable-next-line no-console
        console.log('[Cardknox][CardknoxPaymentForm] onSubmit ENTER');
        const placeOrderButton = document.querySelector('.wc-block-components-checkout-place-order-button');
        if (placeOrderButton) {
            placeOrderButton.click();
        }
    }, []);

    const handleExpiryChange = (field, value) => {
        setCardData(prev => ({
            ...prev,
            [field]: value,
        }));
        
        // Clear expiry errors
        const newErrors = { ...errors };
        delete newErrors.expiry;
        setErrors(newErrors);
    };

    const handleTokenChange = (token) => {
        setSelectedToken(token);
        if (token !== 'new') {
            clearFields();
        }
    };

    return (
        <div className="wc-cardknox-payment-form">
            {selectedToken === 'new' && (
                <>
                    <CardknoxIFields
                        errors={errors}
                        onExpiryChange={handleExpiryChange}
                        ValidationInputError={ValidationInputError}
                        cardData={cardData}
                    />
                    
                    {settings.showSaveOption && (
                        <SavePaymentCheckbox
                            checked={saveCard}
                            onChange={setSaveCard}
                        />
                    )}
                </>
            )}
        </div>
    );
};

export default CardknoxPaymentForm;