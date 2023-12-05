jQuery(document.body).on("updated_checkout", function () {
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

window.apRequest = {
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
      const amt = getAmount();
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
  onPaymentMethodSelected: function (paymentMethod) {
    const self = this;
    return new Promise((resolve, reject) => {
      try {
        console.log("paymentMethod", JSON.stringify(paymentMethod));
        const resp = self.getTransactionInfo(null, null, paymentMethod.type);
        resolve(resp);
      } catch (err) {
        const apErr = {
          code: "-102",
          contactField: "",
          message: exMsg(err),
        };
        console.error("onPaymentMethodSelected error.", exMsg(err));
        reject({ errors: [err] });
      }
    });
  },
  validateApplePayMerchant: function () {
    return new Promise((resolve, reject) => {
      try {
        var xhr = new XMLHttpRequest();
        xhr.open("POST", "https://api.cardknox.com/applepay/validate");
        xhr.onload = function () {
          if (this.status >= 200 && this.status < 300) {
            console.log(
              "validateApplePayMerchant",
              JSON.stringify(xhr.response)
            );
            resolve(xhr.response);
          } else {
            console.error(
              "validateApplePayMerchant",
              JSON.stringify(xhr.response),
              this.status
            );
            reject({
              status: this.status,
              statusText: xhr.response,
            });
          }
        };
        xhr.onerror = function () {
          console.error(
            "validateApplePayMerchant",
            xhr.statusText,
            this.status
          );
          reject({
            status: this.status,
            statusText: xhr.statusText,
          });
        };
        xhr.setRequestHeader("Content-Type", "application/json");
        xhr.send();
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
    console.log(applePayload);
    var appToken = applePayload.token.paymentData.data;
    if (appToken) {
      var xcardnum = btoa(JSON.stringify(applePayload.token.paymentData));
      jQuery("#applePaytoken").val(xcardnum);
      jQuery("#place_order").trigger("click");
    }
  },
  onPaymentAuthorize: function (applePayload) {
    const amt = getAmount();
    return new Promise((resolve, reject) => {
      try {
        this.authorize(applePayload, amt.toString())
          .then((response) => {
            try {
              console.log(response);
              const resp = JSON.parse(response);
              if (!resp) throw "Invalid response: " + response;
              if (resp.xError) {
                throw resp;
              }
              resolve(response);
            } catch (err) {
              throw err;
              // reject(err);
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
    if (err && err.xRefNum) {
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
      onValidateMerchant: "apRequest.onValidateMerchant",
      onPaymentAuthorize: "apRequest.onPaymentAuthorize",
      onPaymentComplete: "apRequest.onPaymentComplete",
      onAPButtonLoaded: "apRequest.apButtonLoaded",
      isDebug: true,
    };
  },
  isSupportedApplePay: function () {
    if (!window.ApplePaySession || !ApplePaySession.canMakePayments()) {
      return false;
    } else {
      return true;
    }
  },
  apButtonLoaded: function (resp) {
    if (!resp) return;
    if (resp.status === iStatus.success) {
      showHide(this.buttonOptions.buttonContainer, true);
      //showHide("lbAPPayload", true);
    } else if (resp.reason) {
      jQuery(".applepay-error")
        .html("<div class='woocommerce-error'>" + resp.reason + "</div>")
        .show();
      console.log(resp.reason);
    }

    if (this.isSupportedApplePay() == false) {
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
  var totals = applePaysettings.total;
  return parseFloat(totals).toFixed(2);
}

function getApButtonColor(applePaysettings) {
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
