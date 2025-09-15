/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "./src/cardknox-payment-method.js":
/*!****************************************!*\
  !*** ./src/cardknox-payment-method.js ***!
  \****************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_html_entities__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/html-entities */ "@wordpress/html-entities");
/* harmony import */ var _wordpress_html_entities__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_html_entities__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _components_CardknoxPaymentForm__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./components/CardknoxPaymentForm */ "./src/components/CardknoxPaymentForm.js");
/* harmony import */ var _utils_constants__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ./utils/constants */ "./src/utils/constants.js");
var _settings$supports, _settings$showSaveOpt;
/**
 * External dependencies
 */




/**
 * Internal dependencies
 */



// Get settings from global WC object
const getSettings = () => {
  if (window.wc && window.wc.wcSettings) {
    return window.wc.wcSettings.getSetting('cardknox_data', {});
  }
  // Fallback to checking wcSettings global
  if (window.wcSettings) {
    return window.wcSettings.getSetting('cardknox_data', {});
  }
  return {};
};
const settings = getSettings();
const defaultLabel = (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Credit Card (Cardknox)', 'woocommerce-gateway-cardknox');
const label = (0,_wordpress_html_entities__WEBPACK_IMPORTED_MODULE_1__.decodeEntities)(settings.title) || defaultLabel;

/**
 * Content component wrapper
 */
const Content = props => (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_2__.createElement)(_components_CardknoxPaymentForm__WEBPACK_IMPORTED_MODULE_3__["default"], props);

/**
 * Label component
 */
const Label = () => (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_2__.createElement)('span', null, label);

/**
 * Cardknox payment method configuration
 */
const CardknoxPaymentMethod = {
  name: _utils_constants__WEBPACK_IMPORTED_MODULE_4__.PAYMENT_METHOD_NAME,
  label: (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_2__.createElement)(Label),
  content: (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_2__.createElement)(Content),
  edit: (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_2__.createElement)(Content),
  canMakePayment: () => true,
  ariaLabel: label,
  supports: {
    features: (_settings$supports = settings.supports) !== null && _settings$supports !== void 0 ? _settings$supports : ['products'],
    showSaveOption: (_settings$showSaveOpt = settings.showSaveOption) !== null && _settings$showSaveOpt !== void 0 ? _settings$showSaveOpt : false,
    showSavedCards: Array.isArray(settings.savedCards) && settings.savedCards.length > 0
  },
  placeOrderButtonLabel: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Place Order', 'woocommerce-gateway-cardknox')
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (CardknoxPaymentMethod);

/***/ }),

/***/ "./src/components/CardknoxIFields.js":
/*!*******************************************!*\
  !*** ./src/components/CardknoxIFields.js ***!
  \*******************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_2__);

/**
 * External dependencies
 */


const cardLogoUrl = 'https://plugin.cardknox.net/demo/wpdemo/wp-content/plugins/woocommerce-gateway-cardknox/images/card-logos.png';
const CardknoxIFields = ({
  errors,
  onExpiryChange,
  ValidationInputError,
  cardData
}) => {
  /*const currentYear = new Date().getFullYear();*/

  const validateExpiry = () => {
    if (!cardData.expiryMonth || !cardData.expiryYear) {
      return false;
    }
    const currentDate = new Date();
    const currentYear = currentDate.getFullYear();
    const currentMonth = currentDate.getMonth() + 1;
    const expMonth = parseInt(cardData.expiryMonth, 10);
    const expYear = parseInt(cardData.expiryYear, 10);
    if (expYear < currentYear || expYear === currentYear && expMonth < currentMonth) {
      return false;
    }
    return true;
  };

  // Ensure no container borders; styling is inside iField
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_2__.useEffect)(() => {
    const cardContainer = document.querySelector('.cardknox-iframe-container');
    const cvvContainer = document.querySelector('.cvv-container');
    if (cardContainer) cardContainer.style.border = '0';
    if (cvvContainer) cvvContainer.style.border = '0';
  }, []);
  return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "wc-cardknox-ifields credit-row"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    id: "ifieldsError",
    style: {
      display: 'none',
      color: 'red',
      marginBottom: '10px'
    }
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "form-row form-row-wide",
    style: {
      paddingBottom: '0',
      margin: '0 0 15px 0',
      width: '100%'
    }
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("label", {
    style: {
      margin: '0 0 5px 0',
      lineHeight: 'inherit',
      display: 'block',
      fontWeight: 400
    },
    htmlFor: "cardknox-card-number"
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Card Number', 'woocommerce-gateway-cardknox'), " ", (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", {
    className: "required"
  }, "*")), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "cardknox-card-number-wrapper",
    style: {
      position: 'relative'
    }
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "cardknox-iframe-container",
    style: {
      position: 'relative',
      overflow: 'hidden',
      backgroundColor: '#fff',
      height: '65px'
    }
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("iframe", {
    "data-ifields-id": "card-number",
    "data-ifields-placeholder": (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Card Number', 'woocommerce-gateway-cardknox'),
    src: "https://cdn.cardknox.com/ifields/3.0.2503.2101/ifield.htm",
    frameBorder: "0",
    width: "100%",
    height: "100%",
    style: {
      border: 0
    },
    title: "Card Number"
  })), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "card-logos",
    style: {
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
      pointerEvents: 'none'
    }
  })), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "cardknox-error-container"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    "data-ifields-id": "card-number-error"
  }), errors.cardNumber && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(ValidationInputError, {
    errorMessage: errors.cardNumber
  }))), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "cardknox-row",
    style: {
      display: 'flex',
      gap: '15px',
      marginBottom: '0px'
    }
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "form-row form-row-first",
    style: {
      flex: '1',
      margin: '0'
    }
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("label", {
    style: {
      margin: '0 0 5px 0',
      lineHeight: 'inherit',
      display: 'block',
      fontWeight: 400
    },
    htmlFor: "cardknox-expiry"
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Expiry (MM/YY)', 'woocommerce-gateway-cardknox'), " ", (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", {
    className: "required"
  }, "*")), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("input", {
    id: "cardknox-card-expiry",
    className: "input-text wc-credit-card-form-card-expiry",
    inputMode: "numeric",
    autoComplete: "cc-exp",
    autoCorrect: "no",
    autoCapitalize: "no",
    spellCheck: "no",
    type: "tel",
    placeholder: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('MM / YY', 'woocommerce-gateway-cardknox'),
    style: {
      outline: 'none',
      border: errors.expiry || !validateExpiry() && cardData.expiryMonth && cardData.expiryYear ? '1px solid red' : '1px solid #000',
      borderRadius: '4px',
      padding: '0.618047em',
      width: '100%',
      height: '48px',
      backgroundColor: '#fff',
      fontWeight: 'inherit',
      boxSizing: 'border-box',
      fontSize: '16px'
    },
    onChange: e => {
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
    }
  }), (errors.expiry || !validateExpiry() && cardData.expiryMonth && cardData.expiryYear) && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(ValidationInputError, {
    errorMessage: errors.expiry || (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Expiration must be in the future', 'woocommerce-gateway-cardknox')
  })), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "form-row form-row-last",
    style: {
      flex: '1',
      margin: '0'
    }
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("label", {
    style: {
      margin: '0 0 5px 0',
      lineHeight: 'inherit',
      display: 'block',
      fontWeight: 400
    },
    htmlFor: "cardknox-cvv"
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('CVV Code', 'woocommerce-gateway-cardknox'), " ", (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", {
    className: "required"
  }, "*")), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "cardknox-iframe-container cvv-container",
    style: {
      position: 'relative',
      overflow: 'hidden',
      backgroundColor: '#fff',
      height: '65px'
    }
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("iframe", {
    "data-ifields-id": "cvv",
    "data-ifields-placeholder": (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('CVV', 'woocommerce-gateway-cardknox'),
    src: "https://cdn.cardknox.com/ifields/3.0.2503.2101/ifield.htm",
    frameBorder: "0",
    width: "100%",
    height: "100%",
    style: {
      border: 0
    },
    title: "CVV"
  })), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    "data-ifields-id": "cvv-error"
  }), errors.cvv && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(ValidationInputError, {
    errorMessage: errors.cvv
  }))), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("input", {
    type: "hidden",
    "data-ifields-id": "card-number-token",
    name: "xCardNum"
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("input", {
    type: "hidden",
    "data-ifields-id": "cvv-token",
    name: "xCVV"
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "clear"
  }));
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (CardknoxIFields);

/***/ }),

/***/ "./src/components/CardknoxPaymentForm.js":
/*!***********************************************!*\
  !*** ./src/components/CardknoxPaymentForm.js ***!
  \***********************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _CardknoxIFields__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./CardknoxIFields */ "./src/components/CardknoxIFields.js");
/* harmony import */ var _SavePaymentCheckbox__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ./SavePaymentCheckbox */ "./src/components/SavePaymentCheckbox.js");
/* harmony import */ var _hooks_useCardknoxIFields__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ../hooks/useCardknoxIFields */ "./src/hooks/useCardknoxIFields.js");
/* harmony import */ var _utils_cardknox_validator__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! ../utils/cardknox-validator */ "./src/utils/cardknox-validator.js");

/**
 * External dependencies
 */



/**
 * Internal dependencies
 */





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
const CardknoxPaymentForm = props => {
  // Handle both possible prop structures
  const eventRegistration = props.eventRegistration || props.events;
  const emitResponse = props.emitResponse;
  const components = props.components || {};
  const settings = getSettings();
  const [errors, setErrors] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useState)({});
  const [isValid, setIsValid] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useState)(false);
  const [saveCard, setSaveCard] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useState)(false);
  const [selectedToken, setSelectedToken] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useState)('new');
  const [cardData, setCardData] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useState)({
    cardNumber: '',
    cvv: '',
    expiryMonth: '',
    expiryYear: '',
    cardNumberToken: '',
    cvvToken: ''
  });

  // Debug refs
  const renderCountRef = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useRef)(0);
  const prevGetTokensRef = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useRef)(null);
  const prevCardDataRef = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useRef)(cardData);

  // Latest refs to avoid re-subscribing payment handler on every render
  const emitResponseRef = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useRef)(emitResponse);
  const getTokensRef = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useRef)(null);
  const cardDataRef = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useRef)(cardData);
  const saveCardRef = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useRef)(saveCard);
  const selectedTokenRef = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useRef)(selectedToken);
  const errorsRef = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useRef)(errors);
  const eventRegistrationRef = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useRef)(eventRegistration);
  const paymentSubscriptionRef = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useRef)({
    subscribed: false,
    unsubscribe: null
  });

  // Log every render (throttled)
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useEffect)(() => {
    renderCountRef.current += 1;
  });
  const {
    initializeIFields,
    getTokens,
    clearFields,
    focusField
  } = (0,_hooks_useCardknoxIFields__WEBPACK_IMPORTED_MODULE_5__["default"])();

  // Keep latest values in refs
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useEffect)(() => {
    emitResponseRef.current = emitResponse;
  }, [emitResponse]);
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useEffect)(() => {
    getTokensRef.current = getTokens;
  }, [getTokens]);
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useEffect)(() => {
    cardDataRef.current = cardData;
  }, [cardData]);
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useEffect)(() => {
    saveCardRef.current = saveCard;
  }, [saveCard]);
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useEffect)(() => {
    selectedTokenRef.current = selectedToken;
  }, [selectedToken]);
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useEffect)(() => {
    errorsRef.current = errors;
  }, [errors]);
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useEffect)(() => {
    eventRegistrationRef.current = eventRegistration;
  }, [eventRegistration]);

  // Log getTokens identity stability
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useEffect)(() => {
    const changed = prevGetTokensRef.current !== getTokens;
    prevGetTokensRef.current = getTokens;
  }, [getTokens]);

  // Log cardData ref stability
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useEffect)(() => {
    const sameRef = prevCardDataRef.current === cardData;
    prevCardDataRef.current = cardData;
  }, [cardData]);

  // Get ValidationInputError component or create a fallback
  const ValidationInputError = components.ValidationInputError || (({
    errorMessage
  }) => {
    if (!errorMessage) return null;
    return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
      className: "wc-block-components-validation-error",
      role: "alert"
    }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", null, errorMessage));
  });
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useEffect)(() => {
    // Initialize iFields when component mounts
    const attemptInit = () => {
      const hasKey = !!settings.iFieldsKey;
      const hasSDK = !!window.setAccount && !!window.setIfieldStyle && !!window.addIfieldCallback;
      if (hasKey && hasSDK) {
        initializeIFields({
          iFieldsKey: settings.iFieldsKey,
          softwareName: settings.softwareName || 'WooCommerce',
          softwareVersion: settings.softwareVersion || '1.0.0',
          onUpdate: handleIFieldUpdate
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
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useEffect)(() => {
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
              meta: {
                paymentMethodData: {
                  wc_token: selected
                }
              }
            };
          }

          // Validate only expiry here; let iFields handle number + cvv
          const validationErrors = (0,_utils_cardknox_validator__WEBPACK_IMPORTED_MODULE_6__.validateCardData)(card) || {};
          if (card.expiryMonth && card.expiryYear) {
            const currentDate = new Date();
            const currentYear = currentDate.getFullYear();
            const currentMonth = currentDate.getMonth() + 1;
            const expMonth = parseInt(card.expiryMonth, 10);
            const expYear = parseInt(card.expiryYear, 10);
            if (expYear < currentYear || expYear === currentYear && expMonth < currentMonth) {
              validationErrors.expiry = (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Expiration must be in the future', 'woocommerce-gateway-cardknox');
            }
          } else {
            validationErrors.expiry = (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Expiry date is required', 'woocommerce-gateway-cardknox');
          }
          if (Object.keys(validationErrors).length > 0) {
            setErrors(validationErrors);
            return {
              type: emitRes.responseTypes.ERROR,
              message: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Please check your card details.', 'woocommerce-gateway-cardknox')
            };
          }

          // Clear any component-side errors before tokenizing
          setErrors({});

          // Request tokens (this will also set/clear inline errors + focus invalid fields)
          const tokens = await getTokensRef.current();
          if (!tokens?.cardNumberToken || !tokens?.cvvToken) {
            return {
              type: emitRes.responseTypes.ERROR,
              message: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Unable to process card data. Please try again.', 'woocommerce-gateway-cardknox')
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
                'wc-cardknox-new-payment-method': saveCardRef.current ? '1' : ''
              }
            }
          };
        } catch (error) {
          return {
            type: emitRes.responseTypes.ERROR,
            message: error?.message || (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Payment processing failed.', 'woocommerce-gateway-cardknox')
          };
        }
      });
      paymentSubscriptionRef.current = {
        subscribed: true,
        unsubscribe
      };
    };
    trySubscribe();
    return () => {
      isUnmounted = true;
      if (timeoutId) {
        window.clearTimeout(timeoutId);
      }
      const {
        unsubscribe
      } = paymentSubscriptionRef.current || {};
      if (typeof unsubscribe === 'function') {
        unsubscribe();
      }
    };
  }, []);
  const handleIFieldUpdate = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useCallback)(data => {
    // Update validation state based on iField data
    const newErrors = {
      ...errors
    };

    // Check if tokens exist first
    const cardNumberToken = document.querySelector('[data-ifields-id="card-number-token"]')?.value;
    const cvvToken = document.querySelector('[data-ifields-id="cvv-token"]')?.value;
    if (data.lastActiveField === 'card-number' || data.cardNumberLength !== undefined) {
      if (cardNumberToken || data.cardNumberIsValid) {
        delete newErrors.cardNumber;
      } else if (data.cardNumberLength > 0 && !data.cardNumberIsValid) {
        newErrors.cardNumber = (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Invalid card number', 'woocommerce-gateway-cardknox');
      } else {
        delete newErrors.cardNumber;
      }
    }
    if (data.lastActiveField === 'cvv' || data.cvvLength !== undefined) {
      if (cvvToken || data.cvvIsValid) {
        delete newErrors.cvv;
      } else if (data.cvvLength > 0 && !data.cvvIsValid) {
        newErrors.cvv = (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Invalid CVV', 'woocommerce-gateway-cardknox');
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
      [field]: value
    }));

    // Clear expiry errors
    const newErrors = {
      ...errors
    };
    delete newErrors.expiry;
    setErrors(newErrors);
  };
  const handleTokenChange = token => {
    setSelectedToken(token);
    if (token !== 'new') {
      clearFields();
    }
  };
  return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "wc-cardknox-payment-form"
  }, selectedToken === 'new' && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(react__WEBPACK_IMPORTED_MODULE_0__.Fragment, null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_CardknoxIFields__WEBPACK_IMPORTED_MODULE_3__["default"], {
    errors: errors,
    onExpiryChange: handleExpiryChange,
    ValidationInputError: ValidationInputError,
    cardData: cardData
  }), settings.showSaveOption && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_SavePaymentCheckbox__WEBPACK_IMPORTED_MODULE_4__["default"], {
    checked: saveCard,
    onChange: setSaveCard
  })));
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (CardknoxPaymentForm);

/***/ }),

/***/ "./src/components/SavePaymentCheckbox.js":
/*!***********************************************!*\
  !*** ./src/components/SavePaymentCheckbox.js ***!
  \***********************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__);

/**
 * External dependencies
 */

const SavePaymentCheckbox = ({
  checked,
  onChange
}) => {
  return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "wc-payment-gateway-cardknox__save-payment-method wc-block-components-payment-methods__save-card-info"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("label", {
    className: "wc-block-components-checkbox wc-block-components-payment-methods__save-card-infos-checkbox"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("input", {
    type: "checkbox",
    name: "wc-cardknox-new-payment-method",
    id: "wc-cardknox-new-payment-method",
    className: "wc-block-components-checkbox__input",
    checked: checked,
    onChange: e => onChange(e.target.checked)
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("svg", {
    className: "wc-block-components-checkbox__mark",
    "aria-hidden": "true",
    xmlns: "http://www.w3.org/2000/svg",
    viewBox: "0 0 24 20"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("path", {
    d: "M9 16.2L4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4L9 16.2z"
  })), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", {
    className: "wc-block-components-checkbox__label"
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Save payment information to my account for future purchases.', 'woocommerce-gateway-cardknox'))));
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (SavePaymentCheckbox);

/***/ }),

/***/ "./src/hooks/useCardknoxIFields.js":
/*!*****************************************!*\
  !*** ./src/hooks/useCardknoxIFields.js ***!
  \*****************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);

const useCardknoxIFields = () => {
  const isInitializedRef = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useRef)(false);
  const updateCallbackRef = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useRef)(null);

  // Clears WooCommerce checkout banners that sometimes stick around
  const clearWooNotices = () => {
    const groups = document.querySelectorAll('.woocommerce-NoticeGroup-checkout, .woocommerce-error, .woocommerce-message');
    groups.forEach(g => g.remove());
  };
  const initializeIFields = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(({
    iFieldsKey,
    softwareName,
    softwareVersion,
    onUpdate // (optional) last iFields state callback
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
      'font-family': '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif'
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
      'font-family': '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif'
    };
    const validStyle = {
      ...defaultStyle,
      border: '1px solid #46b450',
      'background-color': '#f0f9f0'
    };
    const invalidStyle = {
      ...defaultStyle,
      border: '1px solid #d63638',
      'background-color': '#fef5f5'
    };
    const validStyleCvv = {
      ...defaultStyleCvv,
      border: '1px solid #46b450',
      'background-color': '#f0f9f0'
    };
    const invalidStyleCvv = {
      ...defaultStyleCvv,
      border: '1px solid #d63638',
      'background-color': '#fef5f5'
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
        const cardNumberError = document.querySelector('[data-ifields-id="card-number-error"]');
        const cvvError = document.querySelector('[data-ifields-id="cvv-error"]');
        const cardLen = typeof data.cardNumberLength === 'number' ? data.cardNumberLength : 0;
        const cvvLen = typeof data.cvvLength === 'number' ? data.cvvLength : 0;

        // Card Number visuals + message
        window.setIfieldStyle('card-number', cardLen <= 0 ? defaultStyle : data.cardNumberIsValid ? validStyle : invalidStyle);
        if (cardNumberError) {
          cardNumberError.textContent = cardLen <= 0 ? 'Card Number is required' : data.cardNumberIsValid ? '' : 'Invalid card number';
          cardNumberError.setAttribute('aria-hidden', cardNumberError.textContent ? 'false' : 'true');
        }

        // CVV visuals + message (depends on issuer)
        const amex = data.issuer === 'amex';
        const expectedLen = amex ? 4 : 3;
        const cvvLooksValid = cvvLen === expectedLen && data.cvvIsValid;
        window.setIfieldStyle('cvv', data.issuer === 'unknown' || cvvLen <= 0 ? defaultStyleCvv : cvvLooksValid ? validStyleCvv : invalidStyleCvv);
        if (cvvError) {
          cvvError.textContent = cvvLen <= 0 ? 'CVV is required' : cvvLooksValid ? '' : 'Invalid CVV';
          cvvError.setAttribute('aria-hidden', cvvError.textContent ? 'false' : 'true');
        }

        // If both fields are valid, also clear sticky Woo notices
        if (data.cardNumberIsValid && cvvLooksValid) {
          clearWooNotices();
        }
      });

      // Update CVV when issuer changes (e.g. 3 -> 4 for Amex)
      window.addIfieldCallback('issuerupdated', function (data) {
        const cvvLen = typeof data.cvvLength === 'number' ? data.cvvLength : 0;
        const amex = data.issuer === 'amex';
        const expectedLen = amex ? 4 : 3;
        const cvvLooksValid = cvvLen === expectedLen && data.cvvIsValid;
        window.setIfieldStyle('cvv', data.issuer === 'unknown' || cvvLen <= 0 ? defaultStyleCvv : cvvLooksValid ? validStyleCvv : invalidStyleCvv);
      });
    }
    // Fallback (older iFields only expose keypress callback)
    else if (window.addIfieldKeyPressCallback && window.setIfieldStyle) {
      window.addIfieldKeyPressCallback(function (data) {
        updateCallbackRef.current?.(data);
        const cardNumberToken = document.querySelector('[data-ifields-id="card-number-token"]')?.value;
        const cvvToken = document.querySelector('[data-ifields-id="cvv-token"]')?.value;
        const cardNumberError = document.querySelector('[data-ifields-id="card-number-error"]');
        const cvvError = document.querySelector('[data-ifields-id="cvv-error"]');

        // Card
        if (cardNumberToken || data.cardNumberIsValid) {
          window.setIfieldStyle('card-number', validStyle);
          if (cardNumberError) cardNumberError.textContent = '';
        } else if (data.cardNumberLength > 0) {
          window.setIfieldStyle('card-number', invalidStyle);
          if (cardNumberError) cardNumberError.textContent = 'Invalid card number';
        } else {
          window.setIfieldStyle('card-number', defaultStyle);
          if (cardNumberError) cardNumberError.textContent = 'Card Number is required';
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
        if ((cardNumberToken || data.cardNumberIsValid) && (cvvToken || data.cvvIsValid)) {
          clearWooNotices();
        }
      });
    }
    isInitializedRef.current = true;
  }, []);
  const getTokens = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(() => {
    return new Promise((resolve, reject) => {
      if (!window.getTokens) {
        reject(new Error('iFields not initialized'));
        return;
      }

      // If tokens are already present, skip re-validation
      const currentCardTok = document.querySelector('[data-ifields-id="card-number-token"]')?.value;
      const currentCvvTok = document.querySelector('[data-ifields-id="cvv-token"]')?.value;
      if (currentCardTok && currentCvvTok) {
        clearWooNotices();
        resolve({
          cardNumberToken: currentCardTok,
          cvvToken: currentCvvTok
        });
        return;
      }

      // Prepare inline error nodes
      const cardNumberError = document.querySelector('[data-ifields-id="card-number-error"]');
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
      window.getTokens(() => {
        if (finished) return;
        finished = true;
        clearTimeout(tid);
        const cardNumberToken = document.querySelector('[data-ifields-id="card-number-token"]')?.value;
        const cvvToken = document.querySelector('[data-ifields-id="cvv-token"]')?.value;

        // If either is missing, show "required" and focus first
        if (!cardNumberToken || !cvvToken) {
          if (!cardNumberToken) {
            cardNumberError && (cardNumberError.textContent = 'Card Number is required');
            window.setIfieldStyle && window.setIfieldStyle('card-number', {
              border: '1px solid #d63638'
            });
            window.focusIfield && window.focusIfield('card-number');
          }
          if (!cvvToken) {
            cvvError && (cvvError.textContent = 'CVV is required');
            window.setIfieldStyle && window.setIfieldStyle('cvv', {
              border: '1px solid #d63638'
            });
            if (cardNumberToken) {
              window.focusIfield && window.focusIfield('cvv');
            }
          }
          reject(new Error('Please fill in all required fields'));
          return;
        }

        // Success: mark valid, clear error text, clear Woo banners
        window.setIfieldStyle && window.setIfieldStyle('card-number', {
          border: '1px solid #46b450'
        });
        window.setIfieldStyle && window.setIfieldStyle('cvv', {
          border: '1px solid #46b450'
        });
        if (cardNumberError) {
          cardNumberError.textContent = '';
          cardNumberError.setAttribute('aria-hidden', 'true');
        }
        if (cvvError) {
          cvvError.textContent = '';
          cvvError.setAttribute('aria-hidden', 'true');
        }
        clearWooNotices();
        resolve({
          cardNumberToken,
          cvvToken
        });
      }, error => {
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
          const last = window.getLastIfieldState && window.getLastIfieldState();
          const target = last && last.cardNumberIsValid ? 'cvv' : 'card-number';
          window.focusIfield(target);
        }
        reject(new Error(typeof error === 'string' ? error : 'Failed to get tokens'));
      }, 15000 // native lib timeout
      );
    });
  }, []);
  const clearFields = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(() => {
    if (window.clearIfield) {
      window.clearIfield('card-number');
      window.clearIfield('cvv');
    }
  }, []);
  const focusField = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(fieldName => {
    if (window.focusIfield) {
      window.focusIfield(fieldName);
    }
  }, []);
  return {
    initializeIFields,
    getTokens,
    clearFields,
    focusField
  };
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (useCardknoxIFields);

/***/ }),

/***/ "./src/utils/cardknox-validator.js":
/*!*****************************************!*\
  !*** ./src/utils/cardknox-validator.js ***!
  \*****************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   formatCardNumber: () => (/* binding */ formatCardNumber),
/* harmony export */   getCardIssuerName: () => (/* binding */ getCardIssuerName),
/* harmony export */   getCardType: () => (/* binding */ getCardType),
/* harmony export */   luhnCheck: () => (/* binding */ luhnCheck),
/* harmony export */   maskCardNumber: () => (/* binding */ maskCardNumber),
/* harmony export */   validateCVV: () => (/* binding */ validateCVV),
/* harmony export */   validateCardData: () => (/* binding */ validateCardData),
/* harmony export */   validateCardholderName: () => (/* binding */ validateCardholderName),
/* harmony export */   validateExpiryDate: () => (/* binding */ validateExpiryDate)
/* harmony export */ });
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__);
// ============================================
// File: blocks/src/utils/cardknox-validator.js
// ============================================

/**
 * External dependencies
 */


/**
 * Validate card data
 * @param {Object} cardData - Card data to validate
 * @returns {Object} Validation errors
 */
const validateCardData = cardData => {
  const errors = {};

  // Validate expiry month
  if (!cardData.expiryMonth) {
    errors.expiry = (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Expiry month is required', 'woocommerce-gateway-cardknox');
  } else {
    const month = parseInt(cardData.expiryMonth, 10);
    if (month < 1 || month > 12) {
      errors.expiry = (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Invalid expiry month', 'woocommerce-gateway-cardknox');
    }
  }

  // Validate expiry year
  if (!cardData.expiryYear) {
    errors.expiry = (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Expiry year is required', 'woocommerce-gateway-cardknox');
  } else {
    const currentYear = new Date().getFullYear();
    const year = parseInt(cardData.expiryYear, 10);
    if (year < currentYear) {
      errors.expiry = (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Card has expired', 'woocommerce-gateway-cardknox');
    }

    // Check if card expires this year but month has passed
    if (year === currentYear && cardData.expiryMonth) {
      const currentMonth = new Date().getMonth() + 1;
      const expMonth = parseInt(cardData.expiryMonth, 10);
      if (expMonth < currentMonth) {
        errors.expiry = (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Card has expired', 'woocommerce-gateway-cardknox');
      }
    }
  }
  return errors;
};

/**
 * Luhn algorithm to validate card number
 * @param {string} cardNumber - Card number to validate
 * @returns {boolean} Is valid
 */
const luhnCheck = cardNumber => {
  const digits = cardNumber.replace(/\D/g, '');
  let sum = 0;
  let isEven = false;
  for (let i = digits.length - 1; i >= 0; i--) {
    let digit = parseInt(digits[i], 10);
    if (isEven) {
      digit *= 2;
      if (digit > 9) {
        digit -= 9;
      }
    }
    sum += digit;
    isEven = !isEven;
  }
  return sum % 10 === 0;
};

/**
 * Get card type from card number
 * @param {string} cardNumber - Card number
 * @returns {string} Card type
 */
const getCardType = cardNumber => {
  const patterns = {
    visa: /^4/,
    mastercard: /^5[1-5]/,
    amex: /^3[47]/,
    discover: /^6(?:011|5)/,
    jcb: /^35/,
    diners: /^3(?:0[0-5]|[68])/
  };
  const digits = cardNumber.replace(/\D/g, '');
  for (const [type, pattern] of Object.entries(patterns)) {
    if (pattern.test(digits)) {
      return type;
    }
  }
  return 'unknown';
};

/**
 * Format card number for display
 * @param {string} cardNumber - Card number
 * @returns {string} Formatted card number
 */
const formatCardNumber = cardNumber => {
  const digits = cardNumber.replace(/\D/g, '');
  const cardType = getCardType(digits);
  if (cardType === 'amex') {
    // Format: 4-6-5
    return digits.replace(/(\d{4})(\d{6})(\d{5})/, '$1 $2 $3').trim();
  } else {
    // Format: 4-4-4-4
    return digits.replace(/(\d{4})(?=\d)/g, '$1 ').trim();
  }
};

/**
 * Validate CVV based on card type
 * @param {string} cvv - CVV code
 * @param {string} cardType - Type of card
 * @returns {boolean} Is valid
 */
const validateCVV = (cvv, cardType) => {
  const cvvDigits = cvv.replace(/\D/g, '');
  if (cardType === 'amex') {
    // American Express uses 4-digit CVV
    return cvvDigits.length === 4;
  } else {
    // Other cards use 3-digit CVV
    return cvvDigits.length === 3;
  }
};

/**
 * Mask card number for display
 * @param {string} cardNumber - Card number
 * @returns {string} Masked card number
 */
const maskCardNumber = cardNumber => {
  const digits = cardNumber.replace(/\D/g, '');
  const last4 = digits.slice(-4);
  const masked = digits.slice(0, -4).replace(/\d/g, '•');
  return formatCardNumber(masked + last4);
};

/**
 * Validate card holder name
 * @param {string} name - Card holder name
 * @returns {boolean} Is valid
 */
const validateCardholderName = name => {
  // Allow letters, spaces, hyphens, and apostrophes
  const namePattern = /^[a-zA-Z\s\-']+$/;
  return name && name.length >= 2 && namePattern.test(name);
};

/**
 * Get card issuer name from card type
 * @param {string} cardType - Card type
 * @returns {string} Card issuer name
 */
const getCardIssuerName = cardType => {
  const issuers = {
    visa: 'Visa',
    mastercard: 'Mastercard',
    amex: 'American Express',
    discover: 'Discover',
    jcb: 'JCB',
    diners: 'Diners Club',
    unknown: 'Unknown'
  };
  return issuers[cardType] || issuers.unknown;
};

/**
 * Check if expiry date is within valid range
 * @param {string} month - Expiry month
 * @param {string} year - Expiry year
 * @returns {Object} Validation result with isValid and message
 */
const validateExpiryDate = (month, year) => {
  const result = {
    isValid: true,
    message: ''
  };
  if (!month || !year) {
    result.isValid = false;
    result.message = (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Expiry date is required', 'woocommerce-gateway-cardknox');
    return result;
  }
  const currentDate = new Date();
  const currentYear = currentDate.getFullYear();
  const currentMonth = currentDate.getMonth() + 1;
  const expMonth = parseInt(month, 10);
  const expYear = parseInt(year, 10);

  // Check if month is valid
  if (expMonth < 1 || expMonth > 12) {
    result.isValid = false;
    result.message = (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Invalid expiry month', 'woocommerce-gateway-cardknox');
    return result;
  }

  // Check if card has expired
  if (expYear < currentYear || expYear === currentYear && expMonth < currentMonth) {
    result.isValid = false;
    result.message = (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Card has expired', 'woocommerce-gateway-cardknox');
    return result;
  }

  // Check if expiry date is too far in the future (more than 20 years)
  if (expYear > currentYear + 20) {
    result.isValid = false;
    result.message = (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Invalid expiry year', 'woocommerce-gateway-cardknox');
    return result;
  }
  return result;
};

/***/ }),

/***/ "./src/utils/constants.js":
/*!********************************!*\
  !*** ./src/utils/constants.js ***!
  \********************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   API_ENDPOINTS: () => (/* binding */ API_ENDPOINTS),
/* harmony export */   CARD_NUMBER_LENGTHS: () => (/* binding */ CARD_NUMBER_LENGTHS),
/* harmony export */   CARD_PATTERNS: () => (/* binding */ CARD_PATTERNS),
/* harmony export */   CARD_TYPES: () => (/* binding */ CARD_TYPES),
/* harmony export */   CVV_LENGTHS: () => (/* binding */ CVV_LENGTHS),
/* harmony export */   DEFAULT_CONFIG: () => (/* binding */ DEFAULT_CONFIG),
/* harmony export */   ERROR_MESSAGES: () => (/* binding */ ERROR_MESSAGES),
/* harmony export */   FIELD_LABELS: () => (/* binding */ FIELD_LABELS),
/* harmony export */   FIELD_PLACEHOLDERS: () => (/* binding */ FIELD_PLACEHOLDERS),
/* harmony export */   IFIELDS_CDN_URL: () => (/* binding */ IFIELDS_CDN_URL),
/* harmony export */   IFIELDS_IFRAME_URL: () => (/* binding */ IFIELDS_IFRAME_URL),
/* harmony export */   IFIELDS_VERSION: () => (/* binding */ IFIELDS_VERSION),
/* harmony export */   IFIELD_EVENTS: () => (/* binding */ IFIELD_EVENTS),
/* harmony export */   IFIELD_STYLES: () => (/* binding */ IFIELD_STYLES),
/* harmony export */   PAYMENT_METHOD_NAME: () => (/* binding */ PAYMENT_METHOD_NAME),
/* harmony export */   REGEX: () => (/* binding */ REGEX),
/* harmony export */   STORAGE_KEYS: () => (/* binding */ STORAGE_KEYS),
/* harmony export */   SUCCESS_MESSAGES: () => (/* binding */ SUCCESS_MESSAGES),
/* harmony export */   TIMEOUTS: () => (/* binding */ TIMEOUTS)
/* harmony export */ });
/**
 * Payment method constants
 */
const PAYMENT_METHOD_NAME = 'cardknox';

/**
 * iFields configuration
 */
const IFIELDS_VERSION = '2.15.2302.0801';
const IFIELDS_CDN_URL = `https://cdn.cardknox.com/ifields/${IFIELDS_VERSION}/ifields.min.js`;
const IFIELDS_IFRAME_URL = `https://cdn.cardknox.com/ifields/${IFIELDS_VERSION}/ifield.htm`;

/**
 * Card types mapping
 */
const CARD_TYPES = {
  visa: 'Visa',
  mastercard: 'Mastercard',
  amex: 'American Express',
  discover: 'Discover',
  jcb: 'JCB',
  diners: 'Diners Club',
  unknown: 'Unknown'
};

/**
 * Card type patterns for detection
 */
const CARD_PATTERNS = {
  visa: /^4[0-9]{12}(?:[0-9]{3})?$/,
  mastercard: /^5[1-5][0-9]{14}$/,
  amex: /^3[47][0-9]{13}$/,
  discover: /^6(?:011|5[0-9]{2})[0-9]{12}$/,
  jcb: /^(?:2131|1800|35\d{3})\d{11}$/,
  diners: /^3(?:0[0-5]|[68][0-9])[0-9]{11}$/
};

/**
 * Error messages
 */
const ERROR_MESSAGES = {
  INVALID_CARD: 'Invalid card number',
  INVALID_CVV: 'Invalid security code',
  INVALID_EXPIRY: 'Invalid expiry date',
  CARD_EXPIRED: 'Card has expired',
  REQUIRED_FIELD: 'This field is required',
  PROCESSING_ERROR: 'An error occurred while processing your payment',
  TOKEN_ERROR: 'Unable to tokenize card data',
  NETWORK_ERROR: 'Network error. Please check your connection and try again',
  TIMEOUT_ERROR: 'Request timed out. Please try again',
  INVALID_NAME: 'Please enter a valid cardholder name',
  SERVER_ERROR: 'Server error. Please try again later',
  VALIDATION_ERROR: 'Please check your card details and try again'
};

/**
 * Success messages
 */
const SUCCESS_MESSAGES = {
  PAYMENT_SUCCESS: 'Payment processed successfully',
  CARD_SAVED: 'Card saved successfully',
  CARD_DELETED: 'Card deleted successfully'
};

/**
 * Field placeholders
 */
const FIELD_PLACEHOLDERS = {
  CARD_NUMBER: '1234 5678 9012 3456',
  CVV: 'CVV',
  CVV_AMEX: 'CVVV',
  CARD_NAME: 'Name on card',
  EXPIRY_MONTH: 'MM',
  EXPIRY_YEAR: 'YYYY'
};

/**
 * Field labels
 */
const FIELD_LABELS = {
  CARD_NUMBER: 'Card Number',
  CVV: 'Security Code',
  CARD_NAME: 'Cardholder Name',
  EXPIRY_DATE: 'Expiry Date',
  EXPIRY_MONTH: 'Month',
  EXPIRY_YEAR: 'Year',
  SAVE_CARD: 'Save to account'
};

/**
 * iFields event types
 */
const IFIELD_EVENTS = {
  INPUT: 'input',
  CLICK: 'click',
  FOCUS: 'focus',
  BLUR: 'blur',
  SUBMIT: 'submit',
  ESCAPE: 'escape',
  TAB: 'tab',
  SHIFT_TAB: 'shifttab',
  ENTER: 'enter',
  AUTOFILL: 'autofill',
  UPDATE: 'update'
};

/**
 * iFields style states
 */
const IFIELD_STYLES = {
  DEFAULT: {
    border: '1px solid #ddd',
    'font-size': '14px',
    padding: '12px',
    'border-radius': '4px',
    width: '100%',
    height: '48px',
    'box-sizing': 'border-box',
    'font-family': '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif',
    'line-height': '1.4',
    color: '#32373c',
    'background-color': '#fff',
    transition: 'border-color 0.3s ease'
  },
  VALID: {
    border: '1px solid #46b450',
    'background-color': '#f0f9f0'
  },
  INVALID: {
    border: '1px solid #d63638',
    'background-color': '#fef5f5'
  },
  FOCUSED: {
    border: '1px solid #007cba',
    outline: 'none',
    'box-shadow': '0 0 0 1px #007cba'
  }
};

/**
 * Timeout values (in milliseconds)
 */
const TIMEOUTS = {
  TOKEN_REQUEST: 15000,
  // 15 seconds
  API_REQUEST: 30000,
  // 30 seconds
  VALIDATION: 500 // 500ms debounce for validation
};

/**
 * Regular expressions for validation
 */
const REGEX = {
  NUMBERS_ONLY: /^\d+$/,
  LETTERS_ONLY: /^[a-zA-Z\s]+$/,
  ALPHANUMERIC: /^[a-zA-Z0-9]+$/,
  EMAIL: /^[^\s@]+@[^\s@]+\.[^\s@]+$/,
  WHITESPACE: /\s/g
};

/**
 * API endpoints
 */
const API_ENDPOINTS = {
  PROCESS_PAYMENT: '/wp-json/wc/store/checkout',
  VALIDATE_CARD: '/wp-json/cardknox/v1/validate',
  SAVE_CARD: '/wp-json/cardknox/v1/save-card',
  DELETE_CARD: '/wp-json/cardknox/v1/delete-card'
};

/**
 * Local storage keys
 */
const STORAGE_KEYS = {
  LAST_CARD_TYPE: 'cardknox_last_card_type',
  PREFERRED_SAVE_METHOD: 'cardknox_save_preference'
};

/**
 * Card number lengths by type
 */
const CARD_NUMBER_LENGTHS = {
  visa: [13, 16, 19],
  mastercard: [16],
  amex: [15],
  discover: [16, 19],
  jcb: [16, 17, 18, 19],
  diners: [14, 15, 16, 17, 18, 19]
};

/**
 * CVV lengths by card type
 */
const CVV_LENGTHS = {
  visa: 3,
  mastercard: 3,
  amex: 4,
  discover: 3,
  jcb: 3,
  diners: 3
};

/**
 * Default configuration
 */
const DEFAULT_CONFIG = {
  autoFormat: true,
  autoSubmit: true,
  enableValidation: true,
  showCardIcon: true,
  requireCVV: true,
  allowSaveCard: true,
  validateOnBlur: true,
  maskCardNumber: true,
  animateOnError: true
};

/***/ }),

/***/ "@wordpress/element":
/*!*********************************!*\
  !*** external ["wp","element"] ***!
  \*********************************/
/***/ ((module) => {

module.exports = window["wp"]["element"];

/***/ }),

/***/ "@wordpress/html-entities":
/*!**************************************!*\
  !*** external ["wp","htmlEntities"] ***!
  \**************************************/
/***/ ((module) => {

module.exports = window["wp"]["htmlEntities"];

/***/ }),

/***/ "@wordpress/i18n":
/*!******************************!*\
  !*** external ["wp","i18n"] ***!
  \******************************/
/***/ ((module) => {

module.exports = window["wp"]["i18n"];

/***/ }),

/***/ "react":
/*!************************!*\
  !*** external "React" ***!
  \************************/
/***/ ((module) => {

module.exports = window["React"];

/***/ })

/******/ 	});
/************************************************************************/
/******/ 	// The module cache
/******/ 	var __webpack_module_cache__ = {};
/******/ 	
/******/ 	// The require function
/******/ 	function __webpack_require__(moduleId) {
/******/ 		// Check if module is in cache
/******/ 		var cachedModule = __webpack_module_cache__[moduleId];
/******/ 		if (cachedModule !== undefined) {
/******/ 			return cachedModule.exports;
/******/ 		}
/******/ 		// Create a new module (and put it into the cache)
/******/ 		var module = __webpack_module_cache__[moduleId] = {
/******/ 			// no module.id needed
/******/ 			// no module.loaded needed
/******/ 			exports: {}
/******/ 		};
/******/ 	
/******/ 		// Execute the module function
/******/ 		__webpack_modules__[moduleId](module, module.exports, __webpack_require__);
/******/ 	
/******/ 		// Return the exports of the module
/******/ 		return module.exports;
/******/ 	}
/******/ 	
/************************************************************************/
/******/ 	/* webpack/runtime/compat get default export */
/******/ 	(() => {
/******/ 		// getDefaultExport function for compatibility with non-harmony modules
/******/ 		__webpack_require__.n = (module) => {
/******/ 			var getter = module && module.__esModule ?
/******/ 				() => (module['default']) :
/******/ 				() => (module);
/******/ 			__webpack_require__.d(getter, { a: getter });
/******/ 			return getter;
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/define property getters */
/******/ 	(() => {
/******/ 		// define getter functions for harmony exports
/******/ 		__webpack_require__.d = (exports, definition) => {
/******/ 			for(var key in definition) {
/******/ 				if(__webpack_require__.o(definition, key) && !__webpack_require__.o(exports, key)) {
/******/ 					Object.defineProperty(exports, key, { enumerable: true, get: definition[key] });
/******/ 				}
/******/ 			}
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/hasOwnProperty shorthand */
/******/ 	(() => {
/******/ 		__webpack_require__.o = (obj, prop) => (Object.prototype.hasOwnProperty.call(obj, prop))
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/make namespace object */
/******/ 	(() => {
/******/ 		// define __esModule on exports
/******/ 		__webpack_require__.r = (exports) => {
/******/ 			if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 				Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 			}
/******/ 			Object.defineProperty(exports, '__esModule', { value: true });
/******/ 		};
/******/ 	})();
/******/ 	
/************************************************************************/
var __webpack_exports__ = {};
// This entry needs to be wrapped in an IIFE because it needs to be isolated against other modules in the chunk.
(() => {
/*!**********************!*\
  !*** ./src/index.js ***!
  \**********************/
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _cardknox_payment_method__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./cardknox-payment-method */ "./src/cardknox-payment-method.js");
/**
 * External dependencies - using global WooCommerce objects
 */
const {
  registerPaymentMethod
} = window.wc.wcBlocksRegistry;

/**
 * Internal dependencies
 */


// Register the payment method when DOM is ready
if (registerPaymentMethod) {
  registerPaymentMethod(_cardknox_payment_method__WEBPACK_IMPORTED_MODULE_0__["default"]);
}
})();

/******/ })()
;
//# sourceMappingURL=index.js.map