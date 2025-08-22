import { useCallback, useRef } from '@wordpress/element';

const useCardknoxIFields = () => {
	const isInitializedRef = useRef(false);
	const updateCallbackRef = useRef(null);

	// Clears WooCommerce checkout banners that sometimes stick around
	const clearWooNotices = () => {
		const groups = document.querySelectorAll(
			'.woocommerce-NoticeGroup-checkout, .woocommerce-error, .woocommerce-message'
		);
		groups.forEach((g) => g.remove());
	};

	const initializeIFields = useCallback(
		({
			iFieldsKey,
			softwareName,
			softwareVersion,
			onUpdate, // (optional) last iFields state callback
		}) => {
			if (isInitializedRef.current) return;
			if (!window.setAccount) return;

			updateCallbackRef.current = onUpdate || null;

			// Init account
			window.setAccount(iFieldsKey, softwareName, softwareVersion);

			// Base styles INSIDE iframes (the iField itself)
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
				'font-family':
					'-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
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
				'font-family':
					'-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
			};

			const validStyle = {
				...defaultStyle,
				border: '1px solid #46b450',
				'background-color': '#f0f9f0',
			};
			const invalidStyle = {
				...defaultStyle,
				border: '1px solid #d63638',
				'background-color': '#fef5f5',
			};
			const validStyleCvv = {
				...defaultStyleCvv,
				border: '1px solid #46b450',
				'background-color': '#f0f9f0',
			};
			const invalidStyleCvv = {
				...defaultStyleCvv,
				border: '1px solid #d63638',
				'background-color': '#fef5f5',
			};

			// Set initial styles
			if (window.setIfieldStyle) {
				window.setIfieldStyle('card-number', defaultStyle);
				window.setIfieldStyle('cvv', defaultStyleCvv);
			}

			// Input formatting & ENTER support
			window.enableAutoFormatting && window.enableAutoFormatting();
			if (window.enableEnterKey) {
				window.enableEnterKey('card-number');
				window.enableEnterKey('cvv');
			}

			// Modern callback -> fires on every keystroke with rich data
			if (window.addIfieldCallback && window.setIfieldStyle) {
				window.addIfieldCallback('input', function (data) {
					updateCallbackRef.current?.(data);

					// DOM nodes for inline errors (make sure these exist in your markup)
					const cardNumberError = document.querySelector(
						'[data-ifields-id="card-number-error"]'
					);
					const cvvError = document.querySelector('[data-ifields-id="cvv-error"]');

					const cardLen =
						typeof data.cardNumberLength === 'number' ? data.cardNumberLength : 0;
					const cvvLen =
						typeof data.cvvLength === 'number' ? data.cvvLength : 0;

					// Card Number visuals + message
					window.setIfieldStyle(
						'card-number',
						cardLen <= 0
							? defaultStyle
							: data.cardNumberIsValid
							? validStyle
							: invalidStyle
					);

					if (cardNumberError) {
						cardNumberError.textContent =
							cardLen <= 0
								? 'Card Number is required'
								: data.cardNumberIsValid
								? ''
								: 'Invalid card number';
						cardNumberError.setAttribute(
							'aria-hidden',
							cardNumberError.textContent ? 'false' : 'true'
						);
					}

					// CVV visuals + message (depends on issuer)
					const amex = data.issuer === 'amex';
					const expectedLen = amex ? 4 : 3;
					const cvvLooksValid = cvvLen === expectedLen && data.cvvIsValid;

					window.setIfieldStyle(
						'cvv',
						data.issuer === 'unknown' || cvvLen <= 0
							? defaultStyleCvv
							: cvvLooksValid
							? validStyleCvv
							: invalidStyleCvv
					);
					if (cvvError) {
						cvvError.textContent =
							cvvLen <= 0
								? 'CVV is required'
								: cvvLooksValid
								? ''
								: 'Invalid CVV';
						cvvError.setAttribute(
							'aria-hidden',
							cvvError.textContent ? 'false' : 'true'
						);
					}

					// If both fields are valid, also clear sticky Woo notices
					if (data.cardNumberIsValid && cvvLooksValid) {
						clearWooNotices();
					}
				});

				// Update CVV when issuer changes (e.g. 3 -> 4 for Amex)
				window.addIfieldCallback('issuerupdated', function (data) {
					const cvvLen =
						typeof data.cvvLength === 'number' ? data.cvvLength : 0;
					const amex = data.issuer === 'amex';
					const expectedLen = amex ? 4 : 3;
					const cvvLooksValid = cvvLen === expectedLen && data.cvvIsValid;

					window.setIfieldStyle(
						'cvv',
						data.issuer === 'unknown' || cvvLen <= 0
							? defaultStyleCvv
							: cvvLooksValid
							? validStyleCvv
							: invalidStyleCvv
					);
				});
			}
			// Fallback (older iFields only expose keypress callback)
			else if (window.addIfieldKeyPressCallback && window.setIfieldStyle) {
				window.addIfieldKeyPressCallback(function (data) {
					updateCallbackRef.current?.(data);

					const cardNumberToken = document.querySelector(
						'[data-ifields-id="card-number-token"]'
					)?.value;
					const cvvToken = document.querySelector('[data-ifields-id="cvv-token"]')
						?.value;
					const cardNumberError = document.querySelector(
						'[data-ifields-id="card-number-error"]'
					);
					const cvvError = document.querySelector('[data-ifields-id="cvv-error"]');

					// Card
					if (cardNumberToken || data.cardNumberIsValid) {
						window.setIfieldStyle('card-number', validStyle);
						if (cardNumberError) cardNumberError.textContent = '';
					} else if (data.cardNumberLength > 0) {
						window.setIfieldStyle('card-number', invalidStyle);
						if (cardNumberError)
							cardNumberError.textContent = 'Invalid card number';
					} else {
						window.setIfieldStyle('card-number', defaultStyle);
						if (cardNumberError)
							cardNumberError.textContent = 'Card Number is required';
					}

					// CVV
					if (cvvToken || data.cvvIsValid) {
						window.setIfieldStyle('cvv', validStyleCvv);
						if (cvvError) cvvError.textContent = '';
					} else if (data.cvvLength > 0) {
						window.setIfieldStyle('cvv', invalidStyleCvv);
						if (cvvError) cvvError.textContent = 'Invalid CVV';
					} else {
						window.setIfieldStyle('cvv', defaultStyleCvv);
						if (cvvError) cvvError.textContent = 'CVV is required';
					}

					if (
						(cardNumberToken || data.cardNumberIsValid) &&
						(cvvToken || data.cvvIsValid)
					) {
						clearWooNotices();
					}
				});
			}

			isInitializedRef.current = true;
		},
		[]
	);

	const getTokens = useCallback(() => {
		return new Promise((resolve, reject) => {
			if (!window.getTokens) {
				reject(new Error('iFields not initialized'));
				return;
			}

			// If tokens are already present, skip re-validation
			const currentCardTok = document.querySelector(
				'[data-ifields-id="card-number-token"]'
			)?.value;
			const currentCvvTok = document.querySelector('[data-ifields-id="cvv-token"]')
				?.value;
			if (currentCardTok && currentCvvTok) {
				clearWooNotices();
				resolve({
					cardNumberToken: currentCardTok,
					cvvToken: currentCvvTok,
				});
				return;
			}

			// Prepare inline error nodes
			const cardNumberError = document.querySelector(
				'[data-ifields-id="card-number-error"]'
			);
			const cvvError = document.querySelector('[data-ifields-id="cvv-error"]');
			if (cardNumberError) cardNumberError.textContent = '';
			if (cvvError) cvvError.textContent = '';

			// Call iFields to tokenize; add our own timeout guard
			let finished = false;
			const tid = setTimeout(() => {
				if (finished) return;
				finished = true;
				reject(new Error('Timed out while validating card fields'));
			}, 15000);

			window.getTokens(
				() => {
					if (finished) return;
					finished = true;
					clearTimeout(tid);

					const cardNumberToken = document.querySelector(
						'[data-ifields-id="card-number-token"]'
					)?.value;
					const cvvToken = document.querySelector(
						'[data-ifields-id="cvv-token"]'
					)?.value;

					// If either is missing, show "required" and focus first
					if (!cardNumberToken || !cvvToken) {
						if (!cardNumberToken) {
							cardNumberError &&
								(cardNumberError.textContent = 'Card Number is required');
							window.setIfieldStyle &&
								window.setIfieldStyle('card-number', {
									border: '1px solid #d63638',
								});
							window.focusIfield && window.focusIfield('card-number');
						}
						if (!cvvToken) {
							cvvError && (cvvError.textContent = 'CVV is required');
							window.setIfieldStyle &&
								window.setIfieldStyle('cvv', {
									border: '1px solid #d63638',
								});
							if (cardNumberToken) {
								window.focusIfield && window.focusIfield('cvv');
							}
						}
						reject(new Error('Please fill in all required fields'));
						return;
					}

					// Success: mark valid, clear error text, clear Woo banners
					window.setIfieldStyle &&
						window.setIfieldStyle('card-number', { border: '1px solid #46b450' });
					window.setIfieldStyle &&
						window.setIfieldStyle('cvv', { border: '1px solid #46b450' });

					if (cardNumberError) {
						cardNumberError.textContent = '';
						cardNumberError.setAttribute('aria-hidden', 'true');
					}
					if (cvvError) {
						cvvError.textContent = '';
						cvvError.setAttribute('aria-hidden', 'true');
					}

					clearWooNotices();
					resolve({ cardNumberToken, cvvToken });
				},
				(error) => {
					if (finished) return;
					finished = true;
					clearTimeout(tid);

					// Conservative messages if the lib did not populate our errors
					const cardNumberErrorEl = document.querySelector('[data-ifields-id="card-number-error"]');
					const cvvErrorEl = document.querySelector('[data-ifields-id="cvv-error"]');

					if (cardNumberErrorEl && !cardNumberErrorEl.textContent) {
						cardNumberErrorEl.textContent = 'Invalid card number';
					}
					if (cvvErrorEl && !cvvErrorEl.textContent) {
						cvvErrorEl.textContent = 'Invalid CVV';
					}

					// Focus likely-invalid field to help user
					if (window.focusIfield) {
						// Prefer card first unless we know it's valid
						const last =
							window.getLastIfieldState && window.getLastIfieldState();
						const target =
							last && last.cardNumberIsValid ? 'cvv' : 'card-number';
						window.focusIfield(target);
					}

					reject(new Error(typeof error === 'string' ? error : 'Failed to get tokens'));
				},
				15000 // native lib timeout
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