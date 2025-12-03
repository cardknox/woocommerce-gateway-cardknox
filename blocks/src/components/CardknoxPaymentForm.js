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

            if (hasKey && hasSDK) {
                initializeIFields({
                    iFieldsKey: settings.iFieldsKey,
                    softwareName: settings.softwareName || 'WooCommerce',
                    softwareVersion: settings.softwareVersion || '1.0.0',
                    onUpdate: handleIFieldUpdate,
                });
            }
        };

        attemptInit();
        // As the SDK may be enqueued separately, re-attempt after a short delay
        const t = window.setTimeout(attemptInit, 300);
        return () => {
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

            const unsubscribe = paymentEvent(async () => {
                const emitRes = emitResponseRef.current;
                const selected = selectedTokenRef.current;
                const card = cardDataRef.current;

                try {
                    // If using a saved token, we're done
                    if (selected !== 'new') {
                        return {
                            type: emitRes.responseTypes.SUCCESS,
                            meta: { paymentMethodData: { wc_token: selected } },
                        };
                    }

                    // Validate only expiry here; let iFields handle number + cvv
                    const validationErrors = validateCardData(card) || {};
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

                    if (Object.keys(validationErrors).length > 0) {
                        setErrors(validationErrors);
                        return {
                            type: emitRes.responseTypes.ERROR,
                            message: __('Please check your card details.', 'woocommerce-gateway-cardknox'),
                        };
                    }

                    // Clear any component-side errors before tokenizing
                    setErrors({});

                    // Request tokens (this will also set/clear inline errors + focus invalid fields)
                    const tokens = await getTokensRef.current();

                    if (!tokens?.cardNumberToken || !tokens?.cvvToken) {
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
