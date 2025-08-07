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
        if (isInitializedRef.current || !window.setAccount) {
            return;
        }

        // Store callbacks
        updateCallbackRef.current = onUpdate;
        submitCallbackRef.current = onSubmit;

        // Initialize iFields
        window.setAccount(iFieldsKey, softwareName, softwareVersion);

        // Set up styles to match classic checkout
        // Get the card logos image path
        
        const defaultStyle = {
            outline: 'none',
            border: '1px solid #c3c3c3',
            'border-radius': '4px',
            padding: '0.6180469716em',
            width: '93%',
            height: '40px',
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
            width: '95%',
            height: '30px',
            'background-color': 'transparent',
            'font-weight': 'inherit',
            'box-shadow': 'none',
            'font-size': '16px',
            'font-family': '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
        };

        const validStyle = {
            ...defaultStyle,
            border: '1px solid green',
        };

        const invalidStyle = {
            ...defaultStyle,
            border: '1px solid red',
        };

        const validStyleCvv = {
            ...defaultStyleCvv,
            border: '1px solid green',
        };

        const invalidStyleCvv = {
            ...defaultStyleCvv,
            border: '1px solid red',
        };

        window.setIfieldStyle('card-number', defaultStyle);
        window.setIfieldStyle('cvv', defaultStyleCvv);

        // Auto-format card number
        window.enableAutoFormatting();

        // Auto-submit when Enter is pressed
        window.enableAutoSubmit(function() {
            if (submitCallbackRef.current) {
                submitCallbackRef.current();
            }
        });

        // Add validation callback
        window.addIfieldKeyPressCallback(function(data) {
            if (updateCallbackRef.current) {
                updateCallbackRef.current(data);
            }

            // Update styles based on validation - only show errors for invalid input, not empty fields
            const cardNumberErrorElement = document.querySelector('[data-ifields-id="card-number-error"]');
            const cvvErrorElement = document.querySelector('[data-ifields-id="cvv-error"]');
            
            // Apply styles directly to the parent container for better visibility
            const cardNumberContainer = document.querySelector('.cardknox-iframe-container');
            const cvvContainer = document.querySelector('.cvv-container');
            
            // Check if tokens already exist
            const cardNumberToken = document.querySelector('[data-ifields-id="card-number-token"]')?.value;
            const cvvToken = document.querySelector('[data-ifields-id="cvv-token"]')?.value;
            
            // Card number validation
            if (cardNumberToken || data.cardNumberIsValid) {
                // Valid card number or token exists
                window.setIfieldStyle('card-number', validStyle);
                if (cardNumberErrorElement) cardNumberErrorElement.textContent = '';
                if (cardNumberContainer) cardNumberContainer.style.border = '1px solid green';
            } else if (data.cardNumberLength > 0 && !data.cardNumberIsValid) {
                // Invalid card number (has content but invalid)
                window.setIfieldStyle('card-number', invalidStyle);
                if (cardNumberErrorElement) cardNumberErrorElement.textContent = 'Invalid card number';
                if (cardNumberContainer) cardNumberContainer.style.border = '1px solid red';
            } else {
                // Empty field or neutral state - no error message
                window.setIfieldStyle('card-number', defaultStyle);
                if (cardNumberErrorElement) cardNumberErrorElement.textContent = '';
                if (cardNumberContainer) cardNumberContainer.style.border = '1px solid #c3c3c3';
            }

            // CVV validation
            if (cvvToken || data.cvvIsValid) {
                // Valid CVV or token exists
                window.setIfieldStyle('cvv', validStyleCvv);
                if (cvvErrorElement) cvvErrorElement.textContent = '';
                if (cvvContainer) cvvContainer.style.border = '1px solid green';
            } else if (data.cvvLength > 0 && !data.cvvIsValid) {
                // Invalid CVV (has content but invalid)
                window.setIfieldStyle('cvv', invalidStyleCvv);
                if (cvvErrorElement) cvvErrorElement.textContent = 'Invalid CVV';
                if (cvvContainer) cvvContainer.style.border = '1px solid red';
            } else {
                // Empty field or neutral state - no error message
                window.setIfieldStyle('cvv', defaultStyleCvv);
                if (cvvErrorElement) cvvErrorElement.textContent = '';
                if (cvvContainer) cvvContainer.style.border = '1px solid #c3c3c3';
            }
        });

        isInitializedRef.current = true;
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
                        const cardNumberContainer = document.querySelector('.cardknox-iframe-container');
                        if (cardNumberContainer) cardNumberContainer.style.border = '1px solid red';
                        window.setIfieldStyle('card-number', { border: '1px solid red' });
                    }
                    
                    if (!cvvToken) {
                        if (cvvErrorElement) cvvErrorElement.textContent = 'CVV is required';
                        const cvvContainer = document.querySelector('.cvv-container');
                        if (cvvContainer) cvvContainer.style.border = '1px solid red';
                        window.setIfieldStyle('cvv', { border: '1px solid red' });
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
                    // Error callback - this means validation failed or fields are invalid
                    reject(new Error(error || 'Failed to get tokens'));
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