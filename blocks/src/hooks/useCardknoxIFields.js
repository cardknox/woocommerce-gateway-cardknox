import { useCallback, useRef } from '@wordpress/element';

const getErrorNode = (fieldName) =>
	document.querySelector(`[data-cardknox-error="${fieldName}"]`);

const createValidationErrorIcon = () => {
	const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
	svg.setAttribute('xmlns', 'http://www.w3.org/2000/svg');
	svg.setAttribute('viewBox', '0 0 24 24');
	svg.setAttribute('width', '24');
	svg.setAttribute('height', '24');
	svg.setAttribute('aria-hidden', 'true');
	svg.setAttribute('focusable', 'false');

	const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
	path.setAttribute(
		'd',
		'M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10 10-4.5 10-10S17.5 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z'
	);
	svg.appendChild(path);
	return svg;
};

const useCardknoxIFields = () => {
	const isInitializedRef = useRef(false);
	const updateCallbackRef = useRef(null);
	const autofillSuppressUntilRef = useRef(0);

	// Clears WooCommerce checkout banners that sometimes stick around
	const clearWooNotices = () => {
		const groups = document.querySelectorAll(
			'.woocommerce-NoticeGroup-checkout, .woocommerce-error, .woocommerce-message'
		);
		groups.forEach((g) => g.remove());
	};

	const setInlineError = (fieldName, message = '') => {
		const errorNode = getErrorNode(fieldName);
		if (!errorNode) {
			return;
		}

		// Clear with safe DOM APIs (no innerHTML) to avoid XSS findings.
		while (errorNode.firstChild) {
			errorNode.removeChild(errorNode.firstChild);
		}

		if (message) {
			errorNode.className = 'cardknox-field-error wc-block-components-validation-error';
			errorNode.appendChild(createValidationErrorIcon());

			const text = document.createElement('span');
			text.textContent = message;
			errorNode.appendChild(text);

			errorNode.setAttribute('data-cardknox-visible', 'true');
			errorNode.setAttribute('aria-hidden', 'false');
			errorNode.setAttribute('role', 'alert');
		} else {
			errorNode.className = 'cardknox-field-error';
			errorNode.setAttribute('data-cardknox-visible', 'false');
			errorNode.setAttribute('aria-hidden', 'true');
			errorNode.removeAttribute('role');
		}
	};

	const isAutofillSuppressed = () => Date.now() < autofillSuppressUntilRef.current;

	const initializeIFields = useCallback(
		({
			iFieldsKey,
			softwareName,
			softwareVersion,
			threedsEnv,
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
				'background-color': 'transparent',
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

			if (window.addIfieldCallback && window.setIfieldStyle) {
				const prevLengths = { card: 0, cvv: 0 };

				const markAutofillWindow = () => {
					autofillSuppressUntilRef.current = Date.now() + 1000;
					setInlineError('card-number', '');
					setInlineError('cvv', '');
				};

				// Autofill must never flash validation messages.
				window.addIfieldCallback('autofill', function (data) {
					markAutofillWindow();
					updateCallbackRef.current?.({
						...data,
						__cardknoxAutofill: true,
						__cardknoxCardError: '',
						__cardknoxCvvError: '',
					});
				});

				window.addIfieldCallback('input', function (data) {
					const cardLen =
						typeof data.cardNumberLength === 'number' ? data.cardNumberLength : 0;
					const cvvLen =
						typeof data.cvvLength === 'number' ? data.cvvLength : 0;
					const amex = data.issuer === 'amex';
					const expectedLen = amex ? 4 : 3;
					const cvvLooksValid = cvvLen === expectedLen && !!data.cvvIsValid;

					// Browser/password-manager autofill usually dumps many digits at once.
					if (cardLen - prevLengths.card >= 5 || (prevLengths.card === 0 && cardLen >= 12)) {
						markAutofillWindow();
					}
					if (cvvLen - prevLengths.cvv >= 3 && prevLengths.cvv === 0 && cardLen > 0) {
						markAutofillWindow();
					}
					prevLengths.card = cardLen;
					prevLengths.cvv = cvvLen;

					const autofillActive = isAutofillSuppressed();

					window.setIfieldStyle(
						'card-number',
						cardLen <= 0
							? defaultStyle
							: data.cardNumberIsValid
							? validStyle
							: invalidStyle
					);

					window.setIfieldStyle(
						'cvv',
						data.issuer === 'unknown' || cvvLen <= 0
							? defaultStyleCvv
							: cvvLooksValid
							? validStyleCvv
							: invalidStyleCvv
					);

					// Show live messages for invalid non-empty values, except during autofill.
					let cardErrorMessage = '';
					let cvvErrorMessage = '';

					if (!autofillActive && cardLen > 0 && !data.cardNumberIsValid) {
						cardErrorMessage = 'Invalid card number';
					}
					if (!autofillActive && cvvLen > 0 && !cvvLooksValid) {
						cvvErrorMessage = 'Invalid CVV';
					}

					setInlineError('card-number', cardErrorMessage);
					setInlineError('cvv', cvvErrorMessage);

					updateCallbackRef.current?.({
						...data,
						__cardknoxAutofill: autofillActive,
						__cardknoxCardError: cardErrorMessage,
						__cardknoxCvvError: cvvErrorMessage,
					});

					if (data.cardNumberIsValid && cvvLooksValid) {
						clearWooNotices();
					}
				});

				// Update CVV style when issuer changes (e.g. 3 -> 4 for Amex)
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

					if (cvvLooksValid || isAutofillSuppressed()) {
						setInlineError('cvv', '');
					}
				});
			}
			// Fallback (older iFields only expose keypress callback)
			else if (window.addIfieldKeyPressCallback && window.setIfieldStyle) {
				window.addIfieldKeyPressCallback(function (data) {
					const cardNumberToken = document.querySelector(
						'[data-ifields-id="card-number-token"]'
					)?.value;
					const cvvToken = document.querySelector('[data-ifields-id="cvv-token"]')
						?.value;
					const cardLen =
						typeof data.cardNumberLength === 'number' ? data.cardNumberLength : 0;
					const cvvLen = typeof data.cvvLength === 'number' ? data.cvvLength : 0;
					const amex = data.issuer === 'amex';
					const expectedLen = amex ? 4 : 3;
					const cvvLooksValid = cvvLen === expectedLen && !!data.cvvIsValid;
					let cardErrorMessage = '';
					let cvvErrorMessage = '';

					if (cardNumberToken || data.cardNumberIsValid) {
						window.setIfieldStyle('card-number', validStyle);
						setInlineError('card-number', '');
					} else if (cardLen > 0) {
						window.setIfieldStyle('card-number', invalidStyle);
						cardErrorMessage = 'Invalid card number';
						setInlineError('card-number', cardErrorMessage);
					} else {
						window.setIfieldStyle('card-number', defaultStyle);
						setInlineError('card-number', '');
					}

					if (cvvToken || cvvLooksValid) {
						window.setIfieldStyle('cvv', validStyleCvv);
						setInlineError('cvv', '');
					} else if (cvvLen > 0) {
						window.setIfieldStyle('cvv', invalidStyleCvv);
						cvvErrorMessage = 'Invalid CVV';
						setInlineError('cvv', cvvErrorMessage);
					} else {
						window.setIfieldStyle('cvv', defaultStyleCvv);
						setInlineError('cvv', '');
					}

					updateCallbackRef.current?.({
						...data,
						__cardknoxCardError: cardErrorMessage,
						__cardknoxCvvError: cvvErrorMessage,
					});

					if (
						(cardNumberToken || data.cardNumberIsValid) &&
						(cvvToken || cvvLooksValid)
					) {
						clearWooNotices();
					}
				});
			}

			// Initialize 3DS session (required by merchant account even when full 3DS challenge is disabled)
			if (typeof window.enable3DS === 'function' && threedsEnv) {
				window.setTimeout(() => {
					window.enable3DS(threedsEnv, null);
				}, 1000);
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

			setInlineError('card-number', '');
			setInlineError('cvv', '');

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
							setInlineError('card-number', 'Card Number is required');
							window.setIfieldStyle &&
								window.setIfieldStyle('card-number', {
									border: '1px solid #d63638',
								});
							window.focusIfield && window.focusIfield('card-number');
						}
						if (!cvvToken) {
							setInlineError('cvv', 'CVV is required');
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

					setInlineError('card-number', '');
					setInlineError('cvv', '');

					clearWooNotices();
					resolve({ cardNumberToken, cvvToken });
				},
				(error) => {
					if (finished) return;
					finished = true;
					clearTimeout(tid);

					const cardNumberErrorEl = getErrorNode('card-number');
					const cvvErrorEl = getErrorNode('cvv');

					if (cardNumberErrorEl && !cardNumberErrorEl.textContent) {
						setInlineError('card-number', 'Invalid card number');
					}
					if (cvvErrorEl && !cvvErrorEl.textContent) {
						setInlineError('cvv', 'Invalid CVV');
					}

					if (window.focusIfield) {
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
