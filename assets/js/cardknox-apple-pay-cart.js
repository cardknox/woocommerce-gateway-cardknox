let cartTotal = {
  total: parseFloat(applePaysettings.total).toFixed(2),
};
jQuery(document.body).on("updated_cart_totals", function () {
  jQuery.ajax({
    url: applePaysettings.ajax_url,
    type: "POST",
    data: {
      action: "update_cart_total",
    },
    success: function (response) {
      if (response.success) {
        // Update the global variable with the new total
        cartTotal.total = response.data.total;
      }
    },
  });

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
  } else {
    ckApplePay.enableApplePay({
      initFunction: "apRequest.initAP",
      amountField: "amount",
    });
  }
});

document.addEventListener("DOMContentLoaded", function (event) {
  ckApplePay.enableApplePay({
    initFunction: "apRequest.initAP",
    amountField: "amount",
  });
});

let lastSelectedShippingMethod = "";

const apRequest = {
  buttonOptions: {
    buttonContainer: "ap-container",
    buttonColor: getApButtonColor(applePaysettings),
    buttonType: getApButtonType(applePaysettings),
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
        const hasShipping = shippingContact?.administrativeArea;
        let taxAmt = 0.1;
        const newShippingMethods = applePaysettings.shippingMethods;

        let resp = self.getTransactionInfo(taxAmt, newShippingMethods[0]);
        resp.shippingMethods = newShippingMethods;
        if (hasShipping && shippingContact.administrativeArea == "HI") {
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
  validateApplePayMerchant: function () {
    return new Promise((resolve, reject) => {
      try {
        let xhrQuick = new XMLHttpRequest();
        xhrQuick.open("POST", "https://api.cardknox.com/applepay/validate");
        xhrQuick.onload = function () {
          if (this.status >= 200 && this.status < 300) {
            console.log(
              "validateApplePayMerchant",
              JSON.stringify(xhrQuick.response)
            );
            resolve(xhrQuick.response);
          } else {
            console.error(
              "validateApplePayMerchant",
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
            "validateApplePayMerchant",
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
        this.validateApplePayMerchant()
          .then((response) => {
            try {
              console.log(response);
              resolve(response);
            } catch (err) {
              console.error(
                "validateApplePayMerchant exception.",
                JSON.stringify(err)
              );
              reject(err);
            }
          })
          .catch((err) => {
            console.error(
              "validateApplePayMerchant error.",
              JSON.stringify(err)
            );
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
      try {
        this.authorize(applePayload, amtAppleQuick.toString())
          .then((response) => {
            try {
              console.log(response);
              const resp = JSON.parse(response);
              if (!resp) {
                throw new Error("Invalid response: " + response);
              }            
              if (resp.xError) {
                throw resp;
              }
              resolve(response);
            } catch (err) {
              throw err;
            }
          })
          .catch((err) => {
            console.error("authorizeAPay error.", JSON.stringify(err));
            apRequest.handleAPError(err);
            reject(err);
          });
      } catch (err) {
        console.error("onPaymentAuthorize error.", JSON.stringify(err));
        apRequest.handleAPError(err);
        reject(err);
      }
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

function getApButtonColor(applePaysettings) {
  switch (applePaysettings.button_style) {
    case "white":
      return APButtonColor.white;
    case "whiteOutline":
      return APButtonColor.whiteOutline;
    case "black":
    default:
      return APButtonColor.black;
  }
}

function getApButtonType(applePaysettings) {
  let apButtonType = APButtonType.pay;
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

function applePaycreateWooCommerceOrder(
  token,
  amount,
  billingContact,
  shippingContact,
  lastSelectedShippingMethod
) {
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
        jQuery(".applepay-error")
          .html("<div> " + response.data + " </div>")
          .show();
        setTimeout(function () {
          jQuery(".applepay-error").html("").hide();
        }, 3000);
      }
    },
    error: function (error) {
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
