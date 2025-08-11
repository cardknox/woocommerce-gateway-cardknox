/**
 * External dependencies
 */
import { useCallback, useRef } from '@wordpress/element';

const useCardknoxIFields = () => {
    const isInitializedRef = useRef(false);
    const updateCallbackRef = useRef(null);
    const submitCallbackRef = useRef(null);

    const initializeIFields = useCallback(({
        iFieldsKey,
        softwareName,
        softwareVersion,
        onUpdate,
        onSubmit,
    }) => {
        if (isInitializedRef.current) {
            
            return;
        }
        if (!window.setAccount) {
            
            return;
        }

        // Store callbacks
        updateCallbackRef.current = onUpdate;
        submitCallbackRef.current = onSubmit;

        // Initialize iFields
        // eslint-disable-next-line no-console
        
        window.setAccount(iFieldsKey, softwareName, softwareVersion);

        const defaultStyle = {
            outline: 'none',
            border: '1px solid #c3c3c3',
            'border-radius': '4px',
            padding: '0.6180469716em',
            width: '93%',
            height: '30px',
            'background-color': 'transparent',
            'font-weight': 'inherit',
            'box-shadow': 'none',
            'font-size': '16px',
            'font-family': '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
        };

        const defaultStyleCvv = {
            outline: 'none',
            border: '1px solid #c3c3c3',
            'border-radius': '4px',
            padding: '0.6180469716em',
            width: '86%',
            height: '28px',
            'background-color': 'transparent',
            'font-weight': 'inherit',
            'box-shadow': 'none',
            'font-size': '16px',
            'font-family': '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
        };

        // State styles applied inside the iField (not on container)
        const validStyle = { ...defaultStyle, border: '1px solid #46b450', 'background-color': '#f0f9f0' };
        const invalidStyle = { ...defaultStyle, border: '1px solid #d63638', 'background-color': '#fef5f5' };
        const validStyleCvv = { ...defaultStyleCvv, border: '1px solid #46b450', 'background-color': '#f0f9f0' };
        const invalidStyleCvv = { ...defaultStyleCvv, border: '1px solid #d63638', 'background-color': '#fef5f5' };

        if (window.setIfieldStyle) {
            window.setIfieldStyle('card-number', defaultStyle);
            window.setIfieldStyle('cvv', defaultStyleCvv);
        }
        // eslint-disable-next-line no-console
        

        if (window.enableAutoFormatting) {
            window.enableAutoFormatting();
        }
        // eslint-disable-next-line no-console
        

        // Avoid Invalid formId by not using global auto-submit on Blocks; enable ENTER per field
        if (window.enableEnterKey) {
            window.enableEnterKey('card-number');
            window.enableEnterKey('cvv');
            // eslint-disable-next-line no-console
            
        }

        // Prefer modern callbacks like in assets cardknox.js
        if (window.addIfieldCallback && window.setIfieldStyle) {
            window.addIfieldCallback('input', function(data) {
                // eslint-disable-next-line no-console
                
                if (updateCallbackRef.current) {
                    updateCallbackRef.current(data);
                }

                if (data.ifieldValueChanged) {
                    const cardNumberErrorElement = document.querySelector('[data-ifields-id="card-number-error"]');
                    const cvvErrorElement = document.querySelector('[data-ifields-id="cvv-error"]');
                    const cardLen = typeof data.cardNumberFormattedLength === 'number' ? data.cardNumberFormattedLength : (typeof data.cardNumberLength === 'number' ? data.cardNumberLength : 0);
                    const cvvLen = typeof data.cvvLength === 'number' ? data.cvvLength : 0;

                    // card number
                    window.setIfieldStyle(
                        'card-number',
                        cardLen <= 0
                            ? defaultStyle
                            : data.cardNumberIsValid
                            ? validStyle
                            : invalidStyle
                    );

                    // card number error text
                    if (cardNumberErrorElement) {
                        if (cardLen <= 0) {
                            cardNumberErrorElement.textContent = 'Card Number is required';
                        } else if (!data.cardNumberIsValid) {
                            cardNumberErrorElement.textContent = 'Invalid card number';
                        } else {
                            cardNumberErrorElement.textContent = '';
                        }
                    }

                    // cvv depending on what changed
                    if (data.lastIfieldChanged === 'cvv') {
                        window.setIfieldStyle(
                            'cvv',
                            data.issuer === 'unknown' || cvvLen <= 0
                                ? defaultStyleCvv
                                : data.cvvIsValid
                                ? validStyleCvv
                                : invalidStyleCvv
                        );

                        if (cvvErrorElement) {
                            if (cvvLen <= 0) {
                                cvvErrorElement.textContent = 'CVV is required';
                            } else if (!data.cvvIsValid) {
                                cvvErrorElement.textContent = 'Invalid CVV';
                            } else {
                                cvvErrorElement.textContent = '';
                            }
                        }
                    } else if (data.lastIfieldChanged === 'card-number') {
                        if (data.issuer === 'unknown' || cvvLen <= 0) {
                            window.setIfieldStyle('cvv', defaultStyleCvv);
                        } else if (data.issuer === 'amex') {
                            window.setIfieldStyle('cvv', cvvLen === 4 ? validStyleCvv : invalidStyleCvv);
                        } else {
                            window.setIfieldStyle('cvv', cvvLen === 3 ? validStyleCvv : invalidStyleCvv);
                        }

                        if (cvvErrorElement) {
                            if (cvvLen <= 0) {
                                cvvErrorElement.textContent = 'CVV is required';
                            } else if (!data.cvvIsValid) {
                                cvvErrorElement.textContent = 'Invalid CVV';
                            } else {
                                cvvErrorElement.textContent = '';
                            }
                        }
                    }
                }
            });

            window.addIfieldCallback('issuerupdated', function(data) {
                // eslint-disable-next-line no-console
                
                window.setIfieldStyle(
                    'cvv',
                    data.issuer === 'unknown' || data.cvvLength <= 0
                        ? defaultStyleCvv
                        : data.cvvIsValid
                        ? validStyleCvv
                        : invalidStyleCvv
                );
            });
        } else if (window.addIfieldKeyPressCallback && window.setIfieldStyle) {
            // Fallback: basic behavior when only keypress callback exists
            window.addIfieldKeyPressCallback(function(data) {
                // eslint-disable-next-line no-console
            
                if (updateCallbackRef.current) {
                    updateCallbackRef.current(data);
                }

                const cardNumberToken = document.querySelector('[data-ifields-id="card-number-token"]')?.value;
                const cvvToken = document.querySelector('[data-ifields-id="cvv-token"]')?.value;
                const cardNumberErrorElement = document.querySelector('[data-ifields-id="card-number-error"]');
                const cvvErrorElement = document.querySelector('[data-ifields-id="cvv-error"]');

                if (cardNumberToken || data.cardNumberIsValid) {
                    window.setIfieldStyle('card-number', validStyle);
                    if (cardNumberErrorElement) cardNumberErrorElement.textContent = '';
                } else if (data.cardNumberLength > 0) {
                    window.setIfieldStyle('card-number', invalidStyle);
                    if (cardNumberErrorElement) cardNumberErrorElement.textContent = 'Invalid card number';
                } else {
                    window.setIfieldStyle('card-number', defaultStyle);
                    if (cardNumberErrorElement) cardNumberErrorElement.textContent = 'Card Number is required';
                }

                if (cvvToken || data.cvvIsValid) {
                    window.setIfieldStyle('cvv', validStyleCvv);
                    if (cvvErrorElement) cvvErrorElement.textContent = '';
                } else if (data.cvvLength > 0) {
                    window.setIfieldStyle('cvv', invalidStyleCvv);
                    if (cvvErrorElement) cvvErrorElement.textContent = 'Invalid CVV';
                } else {
                    window.setIfieldStyle('cvv', defaultStyleCvv);
                    if (cvvErrorElement) cvvErrorElement.textContent = 'CVV is required';
                }
            });
        }

        isInitializedRef.current = true;
        // eslint-disable-next-line no-console
        
    }, []);

    const getTokens = useCallback(() => {
        return new Promise((resolve, reject) => {
            if (!window.getTokens) {
                reject(new Error('iFields not initialized'));
                return;
            }
            
            // Check for existing tokens first - if they exist, we can skip validation
            const cardNumberToken = document.querySelector('[data-ifields-id="card-number-token"]')?.value;
            const cvvToken = document.querySelector('[data-ifields-id="cvv-token"]')?.value;
            
            // If both tokens exist, we can proceed without further validation
            if (cardNumberToken && cvvToken) {
                resolve({
                    cardNumberToken,
                    cvvToken,
                });
                return;
            }

            // Clear any existing error messages before validation
            const cardNumberErrorElement = document.querySelector('[data-ifields-id="card-number-error"]');
            const cvvErrorElement = document.querySelector('[data-ifields-id="cvv-error"]');
            if (cardNumberErrorElement) cardNumberErrorElement.textContent = '';
            if (cvvErrorElement) cvvErrorElement.textContent = '';

            window.getTokens(
                function() {
                    // Success callback - check if tokens were generated
                    const cardNumberToken = document.querySelector('[data-ifields-id="card-number-token"]')?.value;
                    const cvvToken = document.querySelector('[data-ifields-id="cvv-token"]')?.value;
                    
                    // If no tokens were generated, it means the fields are empty
                    if (!cardNumberToken) {
                        if (cardNumberErrorElement) cardNumberErrorElement.textContent = 'Card Number is required';
                        // Apply invalid style to the inner iField (local style object; avoid outer-scope refs)
                        window.setIfieldStyle('card-number', { border: '1px solid #d63638' });
                    }
                    
                    if (!cvvToken) {
                        if (cvvErrorElement) cvvErrorElement.textContent = 'CVV is required';
                        window.setIfieldStyle('cvv', { border: '1px solid #d63638' });
                    }
                    
                    if (!cardNumberToken || !cvvToken) {
                        reject(new Error('Please fill in all required fields'));
                        return;
                    }
                    
                    resolve({
                        cardNumberToken,
                        cvvToken,
                    });
                },
                function(error) {
                    // Error callback - ensure user sees errors and we pass a safe message back
                    const cardNumberErrorElement = document.querySelector('[data-ifields-id="card-number-error"]');
                    const cvvErrorElement = document.querySelector('[data-ifields-id="cvv-error"]');
                    if (cardNumberErrorElement && !cardNumberErrorElement.textContent) {
                        cardNumberErrorElement.textContent = 'Invalid card number';
                    }
                    if (cvvErrorElement && !cvvErrorElement.textContent) {
                        cvvErrorElement.textContent = 'Invalid CVV';
                    }
                    reject(new Error(typeof error === 'string' ? error : 'Failed to get tokens'));
                },
                15000 // 15 second timeout
            );
        });
    }, []);

    const clearFields = useCallback(() => {
        if (window.clearIfield) {
            window.clearIfield('card-number');
            window.clearIfield('cvv');
        }
    }, []);

    const focusField = useCallback((fieldName) => {
        if (window.focusIfield) {
            window.focusIfield(fieldName);
        }
    }, []);

    return {
        initializeIFields,
        getTokens,
        clearFields,
        focusField,
    };
};

export default useCardknoxIFields;