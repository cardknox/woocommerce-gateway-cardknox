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
    const emitResponse = props.emitResponse || props.emitResponse;
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
        if (window.setAccount && settings.iFieldsKey) {
            initializeIFields({
                iFieldsKey: settings.iFieldsKey,
                softwareName: settings.softwareName || 'WooCommerce',
                softwareVersion: settings.softwareVersion || '1.0.0',
                onUpdate: handleIFieldUpdate,
                onSubmit: handleIFieldSubmit,
            });
        }
    }, [settings.iFieldsKey]);

    useEffect(() => {
        if (!eventRegistration || !emitResponse) {
            return;
        }

        const { onPaymentProcessing, onPaymentSetup } = eventRegistration;
        
        // Use the appropriate event based on what's available
        const paymentEvent = onPaymentProcessing || onPaymentSetup;
        
        if (!paymentEvent) {
            return;
        }

        const unsubscribe = paymentEvent(async () => {
            if (selectedToken !== 'new') {
                // Using saved card
                return {
                    type: emitResponse.responseTypes.SUCCESS,
                    meta: {
                        paymentMethodData: {
                            wc_token: selectedToken,
                        },
                    },
                };
            }

            // Validate form data
            const validationErrors = validateCardData(cardData);
            
            // Additional validation for expiry date format like classic checkout
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
            
            if (Object.keys(validationErrors).length > 0) {
                setErrors(validationErrors);
                return {
                    type: emitResponse.responseTypes.ERROR,
                    message: __('Please check your card details.', 'woocommerce-gateway-cardknox'),
                };
            }

            try {
                // Clear any existing errors before getting tokens
                setErrors({});
                
                // Get tokens from iFields
                const tokens = await getTokens();
                
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
                return {
                    type: emitResponse.responseTypes.ERROR,
                    message: error.message || __('Payment processing failed.', 'woocommerce-gateway-cardknox'),
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
        // Update validation state based on iField data - only show errors for invalid input, not empty fields
        if (data.event === 'input') {
            const newErrors = { ...errors };
            
            // Check if tokens exist first
            const cardNumberToken = document.querySelector('[data-ifields-id="card-number-token"]')?.value;
            const cvvToken = document.querySelector('[data-ifields-id="cvv-token"]')?.value;
            
            if (data.lastActiveField === 'card-number') {
                if (cardNumberToken || data.cardNumberIsValid) {
                    // Valid card number or token exists
                    delete newErrors.cardNumber;
                } else if (data.cardNumberLength > 0 && !data.cardNumberIsValid) {
                    // Invalid card number (has content but invalid)
                    newErrors.cardNumber = __('Invalid card number', 'woocommerce-gateway-cardknox');
                } else {
                    // Empty field - remove any existing errors but don't add new ones
                    delete newErrors.cardNumber;
                }
            }
            
            if (data.lastActiveField === 'cvv') {
                if (cvvToken || data.cvvIsValid) {
                    // Valid CVV or token exists
                    delete newErrors.cvv;
                } else if (data.cvvLength > 0 && !data.cvvIsValid) {
                    // Invalid CVV (has content but invalid)
                    newErrors.cvv = __('Invalid CVV', 'woocommerce-gateway-cardknox');
                } else {
                    // Empty field - remove any existing errors but don't add new ones
                    delete newErrors.cvv;
                }
            }
            
            setErrors(newErrors);
            // Consider valid if tokens exist OR if iField validation passes
            const cardNumberValid = cardNumberToken || data.cardNumberIsValid;
            const cvvValid = cvvToken || data.cvvIsValid;
            setIsValid(cardNumberValid && cvvValid);
        }
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