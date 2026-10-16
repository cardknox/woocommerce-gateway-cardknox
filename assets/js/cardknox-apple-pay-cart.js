/*Start PLGN-245 (TICKET RELATED) */
let cartTotal = {
  total: parseFloat(applePaysettings.total).toFixed(2),
};

function getApButtonColor(applePaysettings) {
  // Match checkout: return ifields enum values only (strings break enableApplePay with i[t[e]]).
  var apButtonColor = APButtonColor.black;
  switch (applePaysettings.button_style) {
    case "black":
      apButtonColor = APButtonColor.black;
      break;
    case "white":
      apButtonColor = APButtonColor.white;
      break;
    case "whiteOutline":
      apButtonColor = APButtonColor.whiteOutline;
      break;
    default:
      apButtonColor = APButtonColor.black;
  }
  return apButtonColor;
}

function getApButtonType(applePaysettings) {
  var apButtonType = APButtonType.pay;
  switch (applePaysettings.button_type) {
    case "pay":
      apButtonType = APButtonType.pay;
      break;
    case "buy":
      apButtonType = APButtonType.buy;
      break;
    case "plain":
      apButtonType = APButtonType.plain;
      break;
    case "order":
      apButtonType = APButtonType.order;
      break;
    case "donate":
      apButtonType = APButtonType.donate;
      break;
    case "continue":
      apButtonType = APButtonType.continue;
      break;
    case "checkout":
      apButtonType = APButtonType.checkout;
      break;
    default:
      apButtonType = APButtonType.pay;
  }
  return apButtonType;
}

function ensureCartApplePayContainer() {
  if (document.getElementById("ap-container")) {
    return true;
  }

  // Fallback: theme/PHP may not print #ap-container — create it next to cart checkout buttons.
  var mount =
    document.querySelector(".wc-proceed-to-checkout") ||
    document.querySelector("#divGpay") ||
    document.querySelector(".cart_totals");

  if (!mount) {
    console.warn("Apple Pay: no mount point found on cart page.");
    return false;
  }

  var wrap = document.createElement("div");
  wrap.className = "cardknox-applepay-cart-wrap";
  wrap.innerHTML =
    '<div id="ap-container" class="ap" style="min-height:55px;margin:10px 0;"></div>' +
    '<div class="messages"><div class="message message-error error applepay-error" style="display:none;"></div></div>';

  if (mount.classList && mount.classList.contains("wc-proceed-to-checkout")) {
    mount.appendChild(wrap);
  } else if (mount.parentNode) {
    mount.parentNode.insertBefore(wrap, mount.nextSibling);
  } else {
    return false;
  }

  return !!document.getElementById("ap-container");
}

function initCardknoxCartApplePay() {
  if (
    applePaysettings.merchant_identifier == "" ||
    applePaysettings.merchant_identifier == null ||
    applePaysettings.merchant_identifier.length == 0
  ) {
    jQuery(".applepay-error")
      .html(
        "<div class='woocommerce-error' role='alert'>Please contact support. Failed to initialize Apple Pay. </div>"
      )
      .show();
    return;
  }

  if (!ensureCartApplePayContainer()) {
    return;
  }

  if (typeof ckApplePay === "undefined" || typeof APButtonColor === "undefined") {
    // ifields can finish after our script; retry briefly.
    if (typeof initCardknoxCartApplePay._sdkRetries !== "number") {
      initCardknoxCartApplePay._sdkRetries = 0;
    }
    if (initCardknoxCartApplePay._sdkRetries < 20) {
      initCardknoxCartApplePay._sdkRetries += 1;
      setTimeout(initCardknoxCartApplePay, 150);
      return;
    }
    console.warn("Apple Pay: ifields SDK not ready yet.");
    return;
  }

  initCardknoxCartApplePay._sdkRetries = 0;

  // Must match checkout: SDK resolves "apRequest.*" on window and needs real enums (not null).
  if (!window.apRequest || !window.apRequest.buttonOptions) {
    console.warn("Apple Pay: apRequest not ready.");
    return;
  }
  window.apRequest.buttonOptions.buttonColor = getApButtonColor(applePaysettings);
  window.apRequest.buttonOptions.buttonType = getApButtonType(applePaysettings);

  ckApplePay.enableApplePay({
    initFunction: "apRequest.initAP",
    amountField: "amount",
  });
}

jQuery(document.body).on("updated_cart_totals", function () {
  jQuery.ajax({
    url: applePaysettings.ajax_url,
    type: "POST",
    data: {
      action: "update_cart_total",
    },
    success: function (response) {
      if (response.success) {
        cartTotal.total = response.data.total;
      }
    },
  });
  initCardknoxCartApplePay();
});
/* END PLGN-245 (TICKET RELATED) */

let lastSelectedShippingMethod = "";

// Same as checkout cardknox-apple-pay.js — must be on window for ifields initFunction paths.
window.apRequest = {
  buttonOptions: {
    buttonContainer: "ap-container",
    buttonColor: typeof APButtonColor !== "undefined" ? getApButtonColor(applePaysettings) : null,
    buttonType: typeof APButtonType !== "undefined" ? getApButtonType(applePaysettings) : null,
  },
  totalAmount: null,
  taxAmt: null,
  shippingMethod: null,
  creditType: null,
  getTransactionInfo: function () {
    try {
      const amt = parseFloat(cartTotal.total).toFixed(2);
      return {
        total: {
          type: "final",
          label: "Total",
          amount: amt.toString(),
        },
      };
    } catch (err) {
      console.error("getTransactionInfo error ", exMsg(err));
    }
  },
  onGetTransactionInfo: function () {
    try {
      return this.getTransactionInfo();
    } catch (err) {
      console.error("onGetTransactionInfo error ", exMsg(err));
    }
  },
  onGetShippingMethods: function () {
    return applePaysettings.shippingMethods;
  },
  onShippingContactSelected: function (shippingContact) {
    const self = this;
    return new Promise(function (resolve, reject) {
      try {
        logDebug({
          label: "shippingContact",
          data: JSON.stringify(shippingContact),
        });

        const hasShippingApplepay = shippingContact?.administrativeArea;

        let taxAmt = 0.1;
        const newShippingMethods = applePaysettings.shippingMethods;

        let resp = self.getTransactionInfo(taxAmt, newShippingMethods[0]);
        resp.shippingMethods = newShippingMethods;
        if (hasShippingApplepay && shippingContact.administrativeArea == "HI") {
          resp.error = {
            code: APErrorCode.addressUnserviceable,
            contactField: APErrorContactField.administrativeArea,
            message: "Shipping is not available for the given address",
          };
        }
        resolve(resp);
      } catch (err) {
        logError("onShippingContactSelected error.", err);
        reject({ errors: [err] });
      }
    });
  },
  onShippingMethodSelected: function (shippingMethod) {
    const self = this;
    return new Promise(function (resolve, reject) {
      try {
        logDebug({
          label: "shippingMethod",
          data: JSON.stringify(shippingMethod),
        });
        lastSelectedShippingMethod = shippingMethod;
        const resp = self.getTransactionInfo(null, shippingMethod);
        resolve(resp);
      } catch (err) {
        logError("onShippingMethodSelected error.", err);
        reject({ errors: [err] });
      }
    });
  },
  onPaymentMethodSelected: function (paymentMethod) {
    const self = this;
    return new Promise((resolve, reject) => {
      try {
        console.log("paymentMethod", JSON.stringify(paymentMethod));
        const resp = self.getTransactionInfo(null, null, paymentMethod.type);
        resolve(resp);
      } catch (err) {        
        console.error("onPaymentMethodSelected error.", exMsg(err));
        reject({ errors: [err] });
      }
    });
  },
  validateQuickApplePayMerchant: function () {
    return new Promise((resolve, reject) => {
      try {
        let xhrQuick = new XMLHttpRequest();
        xhrQuick.open("POST", "https://api.cardknox.com/applepay/validate");
        xhrQuick.onload = function () {
          if (this.status >= 200 && this.status < 300) {
            console.log(
              "validateQuickApplePayMerchant",
              JSON.stringify(xhrQuick.response)
            );
            resolve(xhrQuick.response);
          } else {
            console.error(
              "validateQuickApplePayMerchant",
              JSON.stringify(xhrQuick.response),
              this.status
            );
            reject({
              status: this.status,
              statusText: xhrQuick.response,
            });
          }
        };
        xhrQuick.onerror = function () {
          console.error(
            "validateQuickApplePayMerchant",
            xhrQuick.statusText,
            this.status
          );
          reject({
            status: this.status,
            statusText: xhrQuick.statusText,
          });
        };
        xhrQuick.setRequestHeader("Content-Type", "application/json");
        xhrQuick.send();
      } catch (err) {
        setTimeout(function () {
          console.log("getApplePaySession error: " + exMsg(err));
        }, 100);
      }
    });
  },
  onValidateMerchant: function () {
    return new Promise((resolve, reject) => {
      try {
        this.validateQuickApplePayMerchant()
          .then((response) => {
            try {
              resolve(response);
            } catch (err) {
              console.error("validateQuickApplePayMerchant exception.", JSON.stringify(err));
              reject(err);
            }
          })
          .catch((err) => {
            console.error("validateQuickApplePayMerchant error.", JSON.stringify(err));
            reject(err);
          });
      } catch (err) {
        console.error("onValidateMerchant error.", JSON.stringify(err));
        reject(err);
      }
    });
  },  
  authorize: function (applePayload, totalAmount) {
    let appToken = applePayload.token.paymentData.data;
    if (appToken) {
      let xcardnum = btoa(JSON.stringify(applePayload.token.paymentData));
      jQuery("#applePaytoken").val(xcardnum);

      let billingFirstName = applePayload.billingContact.givenName;
      let billingLastName = applePayload.billingContact.familyName;

      let billingAddress = applePayload.billingContact.addressLines
        .filter(function (line) {
          return line; // This will remove any falsy values: undefined, null, "", 0, false, NaN
        })
        .join(" ");

      let billingState = applePayload.billingContact.administrativeArea;
      let billingCountry = applePayload.billingContact.countryCode;
      let billingPostcode = applePayload.billingContact.postalCode;
      let billingCity = applePayload.billingContact.locality;

      let shippingFirstName = applePayload.shippingContact.givenName;
      let shippingLastName = applePayload.shippingContact.familyName;
      let shippingEmail = applePayload.shippingContact.emailAddress;
      let shippingTelephone = applePayload.shippingContact.phoneNumber;
      shippingTelephone = shippingTelephone.substring(
        shippingTelephone.indexOf(" ") + 1
      );

      let shippingAddress = applePayload.shippingContact.addressLines
        .filter(function (line) {
          return line; // This will remove any falsy values: undefined, null, "", 0, false, NaN
        })
        .join(" ");

      let shippingState = applePayload.shippingContact.administrativeArea;
      let shippingCountry = applePayload.shippingContact.countryCode;
      let shippingPostcode = applePayload.shippingContact.postalCode;
      let shippingCity = applePayload.shippingContact.locality;

      let billingContact = {
        firstName: billingFirstName,
        lastName: billingLastName,
        emailAddress: shippingEmail,
        phoneNumber: shippingTelephone,
        address: billingAddress,
        administrativeArea: billingState,
        country: billingCountry,
        postcode: billingPostcode,
        city: billingCity,
      };

      let shippingContact = {
        firstName: shippingFirstName,
        lastName: shippingLastName,
        emailAddress: shippingEmail,
        phoneNumber: shippingTelephone,
        address: shippingAddress,
        administrativeArea: shippingState,
        country: shippingCountry,
        postcode: shippingPostcode,
        city: shippingCity,
      };

      applePaycreateWooCommerceOrder(
        xcardnum,
        totalAmount,
        billingContact,
        shippingContact,
        lastSelectedShippingMethod
      );
    }
  },
  onPaymentAuthorize: function (applePayload) {
    const amtAppleQuick = parseFloat(cartTotal.total).toFixed(2);

    return new Promise((resolve, reject) => {
        this.authorize(applePayload, amtAppleQuick.toString())
            .then((response) => {
                try {
                    console.log(response);
                    const respQuick = JSON.parse(response);
                    if (!respQuick) {
                        throw new Error("Invalid response: " + response);
                    }
                    if (respQuick.xError) {
                        throw respQuick;
                    }
                    resolve(response);
                } catch (err) {
                    reject(err);
                }
            })
            .catch((err) => {
                console.error("authorizeAPay error.", JSON.stringify(err));
                apRequest.handleAPError(err);
                reject(err);
            });
    });
  },
  handleAPError: function (err) {
    if (err?.xRefNum) {
      setAPPayload("There was a problem with your order:(" + err.xRefNum + ")");
    } else {
      setAPPayload("There was a problem with your order:" + exMsg(err));
    }
  },
  initAP: function () {
    return {
      buttonOptions: this.buttonOptions,
      merchantIdentifier: applePaysettings.merchant_identifier,
      requiredBillingContactFields: ["postalAddress", "name", "phone", "email"],
      requiredShippingContactFields: [
        "postalAddress",
        "name",
        "phone",
        "email",
      ],
      onGetTransactionInfo: "apRequest.onGetTransactionInfo",
      onGetShippingMethods: "apRequest.onGetShippingMethods",
      onPaymentMethodSelected: "apRequest.onPaymentMethodSelected",
      onShippingContactSelected: "apRequest.onShippingContactSelected",
      onShippingMethodSelected: "apRequest.onShippingMethodSelected",
      onValidateMerchant: "apRequest.onValidateMerchant",
      onPaymentAuthorize: "apRequest.onPaymentAuthorize",
      onPaymentComplete: "apRequest.onPaymentComplete",
      onAPButtonLoaded: "apRequest.apButtonLoaded",
      isDebug: true,
    };
  },
  isSupportedApplePay: function () {
    return !!window.ApplePaySession && ApplePaySession.canMakePayments();
  },
  apButtonLoaded: function (resp) {
    if (!resp) return;
    if (resp.status === iStatus.success) {
      showHide(this.buttonOptions.buttonContainer, true);
    } else if (resp.reason) {
      jQuery(".applepay-error")
        .html("<div class='woocommerce-error'>" + resp.reason + "</div>")
        .show();
      console.log(resp.reason);
    }

    if (!this.isSupportedApplePay()) {
      jQuery(".woocommerce-checkout .payment_method_cardknox-applepay").hide();
    } else {
      jQuery(".woocommerce-checkout .payment_method_cardknox-applepay").show();
    }
  },
};

function setAPPayload(value) {
  console.log(value);
}

function showHide(elem, toShow) {
  if (typeof elem === "string") {
    elem = document.getElementById(elem);
  }
  if (elem) {
    toShow ? elem.classList.remove("hidden") : elem.classList.add("hidden");
  }
}

function getAmount() {
  let totals = applePaysettings.total;
  return parseFloat(totals).toFixed(2);
}

/*Start PLGN-245 (TICKET RELATED) */
// Init after DOM is ready so cart buttons (.wc-proceed-to-checkout) exist.
jQuery(function () {
  initCardknoxCartApplePay();
});

var cardknoxApayOrderSubmitting = false;
function applePaycreateWooCommerceOrder(
  token,
  amount,
  billingContact,
  shippingContact,
  lastSelectedShippingMethod
) {
  // Prevent duplicate AJAX / double order creation from cart quick checkout.
  if (cardknoxApayOrderSubmitting) {
    return;
  }
  cardknoxApayOrderSubmitting = true;

  jQuery.ajax({
    url: applePaysettings.ajax_url,
    type: "POST",
    data: {
      action: "applepay_cardknox_create_order",
      apple_pay_token: token,
      amount: amount,
      billingContact: JSON.stringify(billingContact),
      shippingContact: JSON.stringify(shippingContact),
      selectedShipping: JSON.stringify(lastSelectedShippingMethod),
      security: applePaysettings.create_order_nonce, // Include nonce
    },
    success: function (response) {
      if (response.success) {
        window.location.href = response.data.redirect_url;
      } else {
        cardknoxApayOrderSubmitting = false;
        jQuery(".applepay-error")
          .html("<div> " + response.data + " </div>")
          .show();
        setTimeout(function () {
          jQuery(".applepay-error").html("").hide();
        }, 3000);
      }
    },
    error: function (error) {
      cardknoxApayOrderSubmitting = false;
      console.log(error); // Log the full error response
      jQuery(".applepay-error")
        .html("<div> " + error.responseText + " </div>")
        .show();
      setTimeout(function () {
        jQuery(".applepay-error").html("").hide();
      }, 3000);
    },
  });
}
/* END PLGN-245 (TICKET RELATED) */
