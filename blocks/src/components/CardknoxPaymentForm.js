/**
 * External dependencies
 */
import { useState, useEffect, useCallback, useRef } from '@wordpress/element';
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

    // Debug refs
    const renderCountRef = useRef(0);
    const prevGetTokensRef = useRef(null);
    const prevCardDataRef = useRef(cardData);

    // Latest refs to avoid re-subscribing payment handler on every render
    const emitResponseRef = useRef(emitResponse);
    const getTokensRef = useRef(null);
    const cardDataRef = useRef(cardData);
    const saveCardRef = useRef(saveCard);
    const selectedTokenRef = useRef(selectedToken);
    const errorsRef = useRef(errors);
    const eventRegistrationRef = useRef(eventRegistration);
    const paymentSubscriptionRef = useRef({ subscribed: false, unsubscribe: null });

    // Log every render (throttled)
    useEffect(() => {
        renderCountRef.current += 1;
        // removed debug logging
    });

    const {
        initializeIFields,
        getTokens,
        clearFields,
        focusField,
    } = useCardknoxIFields();

    // Keep latest values in refs
    useEffect(() => { emitResponseRef.current = emitResponse; }, [emitResponse]);
    useEffect(() => { getTokensRef.current = getTokens; }, [getTokens]);
    useEffect(() => { cardDataRef.current = cardData; }, [cardData]);
    useEffect(() => { saveCardRef.current = saveCard; }, [saveCard]);
    useEffect(() => { selectedTokenRef.current = selectedToken; }, [selectedToken]);
    useEffect(() => { errorsRef.current = errors; }, [errors]);
    useEffect(() => { eventRegistrationRef.current = eventRegistration; }, [eventRegistration]);

    // Log getTokens identity stability
    useEffect(() => {
        const changed = prevGetTokensRef.current !== getTokens;
        prevGetTokensRef.current = getTokens;
    }, [getTokens]);

    // Log cardData ref stability
    useEffect(() => {
        const sameRef = prevCardDataRef.current === cardData;
        prevCardDataRef.current = cardData;
    }, [cardData]);

    // Log state changes that often drive re-renders
    useEffect(() => {
        // removed debug logging
    }, [errors]);

    useEffect(() => {
        // removed debug logging
    }, [selectedToken, saveCard]);

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
            // removed debug logging

            if (hasKey && hasSDK) {
                // removed debug logging
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
        return () => {
            // removed debug logging
            window.clearTimeout(t);
        };
    }, [settings.iFieldsKey]);

    // Subscribe once on mount; retry briefly until registration becomes available
    useEffect(() => {
        let isUnmounted = false;
        let timeoutId = null;

        const trySubscribe = () => {
            if (isUnmounted || paymentSubscriptionRef.current.subscribed) {
                return;
            }

            const registration = eventRegistrationRef.current;
            const onSetup = registration?.onPaymentSetup;
            const onProcessing = registration?.onPaymentProcessing;
            const paymentEvent = onSetup || onProcessing;

            if (!paymentEvent || !emitResponseRef.current) {
                // retry shortly until available
                timeoutId = window.setTimeout(trySubscribe, 200);
                return;
            }

            const subscriptionId = `${Date.now()}-${Math.random().toString(36).slice(2)}`;
            // removed debug logging

            const unsubscribe = paymentEvent(async () => {
            const emitRes = emitResponseRef.current;
            const selected = selectedTokenRef.current;
            const card = cardDataRef.current;
            const currentErrors = errorsRef.current || {};

            // removed debug logging
            try {
                if (selected !== 'new') {
                    
                    return {
                        type: emitRes.responseTypes.SUCCESS,
                        meta: { paymentMethodData: { wc_token: selected } },
                    };
                }

                // Validate expiry fields (card number and cvv validation is handled by getTokens/iFields)
                const validationErrors = validateCardData(card);
                if (card.expiryMonth && card.expiryYear) {
                    const currentDate = new Date();
                    const currentYear = currentDate.getFullYear();
                    const currentMonth = currentDate.getMonth() + 1;
                    const expMonth = parseInt(card.expiryMonth, 10);
                    const expYear = parseInt(card.expiryYear, 10);
                    if (expYear < currentYear || (expYear === currentYear && expMonth < currentMonth)) {
                        validationErrors.expiry = __('Expiration must be in the future', 'woocommerce-gateway-cardknox');
                    }
                } else {
                    validationErrors.expiry = __('Expiry date is required', 'woocommerce-gateway-cardknox');
                }

                // Capture any inline errors already present from iFields
                const cardNumberInlineError = (document.querySelector('[data-ifields-id="card-number-error"]')?.textContent || '').trim();
                const cvvInlineError = (document.querySelector('[data-ifields-id="cvv-error"]')?.textContent || '').trim();

                const willSetErrors = Object.keys(validationErrors).length > 0 || cardNumberInlineError || cvvInlineError || currentErrors.cardNumber || currentErrors.cvv;
                

                if (willSetErrors) {
                    
                    setErrors(validationErrors);
                    return {
                        type: emitRes.responseTypes.ERROR,
                        message: __('Please check your card details.', 'woocommerce-gateway-cardknox'),
                    };
                }

                // Clear any existing errors before getting tokens
                
                setErrors({});

                const existingCardToken = document.querySelector('[data-ifields-id="card-number-token"]')?.value;
                const existingCvvToken = document.querySelector('[data-ifields-id="cvv-token"]')?.value;
                const tokens = existingCardToken && existingCvvToken
                    ? { cardNumberToken: existingCardToken, cvvToken: existingCvvToken }
                    : await getTokensRef.current();

                

                if (!tokens.cardNumberToken || !tokens.cvvToken) {
                    return {
                        type: emitRes.responseTypes.ERROR,
                        message: __('Unable to process card data. Please try again.', 'woocommerce-gateway-cardknox'),
                    };
                }

                return {
                    type: emitRes.responseTypes.SUCCESS,
                    meta: {
                        paymentMethodData: {
                            cardknox_card_token: tokens.cardNumberToken,
                            cardknox_cvv_token: tokens.cvvToken,
                            cardknox_exp_month: card.expiryMonth,
                            cardknox_exp_year: card.expiryYear,
                            cardknox_save_card: saveCardRef.current ? 'yes' : 'no',
                            'wc-cardknox-new-payment-method': saveCardRef.current ? '1' : '',
                        },
                    },
                };
            } catch (error) {
                
                return {
                    type: emitRes.responseTypes.ERROR,
                    message: error?.message || __('Payment processing failed.', 'woocommerce-gateway-cardknox'),
                };
            }
        });

            paymentSubscriptionRef.current = { subscribed: true, unsubscribe };
        };

        trySubscribe();

        return () => {
            isUnmounted = true;
            if (timeoutId) {
                window.clearTimeout(timeoutId);
            }
            const { unsubscribe } = paymentSubscriptionRef.current || {};
            if (typeof unsubscribe === 'function') {
                
                unsubscribe();
            }
        };
    }, []);

const handleIFieldUpdate = useCallback((data) => {
    // Update validation state based on iField data
    
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
        {Array.isArray(settings.savedCards) && settings.savedCards.length > 0 && (
            <div className="wc-cardknox-saved-cards">
                <div className="wc-cardknox-saved-card-option">
                    <label>
                        <input
                            type="radio"
                            name="wc-cardknox-payment-token"
                            value="new"
                            checked={selectedToken === 'new'}
                            onChange={() => handleTokenChange('new')}
                        />{' '}
                        {__('Use a new card', 'woocommerce-gateway-cardknox')}
                    </label>
                </div>
                {settings.savedCards.map((t) => (
                    <div className="wc-cardknox-saved-card-option" key={t.token_id}>
                        <label>
                            <input
                                type="radio"
                                name="wc-cardknox-payment-token"
                                value={String(t.token_id)}
                                checked={selectedToken === String(t.token_id)}
                                onChange={() => handleTokenChange(String(t.token_id))}
                            />{' '}
                            {`${(t.card_type || '').toUpperCase()} •••• ${t.masked || t.last4} ${t.exp_month && t.exp_year ? `(${t.exp_month}/${String(t.exp_year).slice(-2)})` : ''}`}
                        </label>
                    </div>
                ))}
            </div>
        )}
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