let subTotal = {
    total: parseFloat(googlePaysettings.total).toFixed(2)
};
jQuery(document.body).on("updated_cart_totals", function () {

    jQuery.ajax({
        url: googlePaysettings.ajax_url,
        type: 'POST',
        data: {
            action: 'update_cart_total'
        },
        success: function(response) {
            if (response.success) {
                // Update the global variable with the new total
                subTotal.total = response.data.total;
            }
        }
    });

  if (
    googlePaysettings.merchant_name == "" ||
    googlePaysettings.merchant_name == null ||
    googlePaysettings.merchant_name.length == 0
  ) {
    jQuery(".googlepay-error")
      .html(
        "<div class='woocommerce-error' role='alert'>Please contact support. Failed to initialize Google Pay. </div>"
      )
      .show();
  } else {
    ckGooglePay.enableGooglePay({
      initFunction: "gpRequest.initGP",
      amountField: "amount",
    });
  }
});

ckGooglePay.enableGooglePay({
    initFunction: "gpRequest.initGP",
    amountField: "amount",
});

//Google Pay
window.gpRequest = {
  merchantInfo: {
    merchantName: googlePaysettings.merchant_name,
  },
  buttonOptions: {
    buttonColor: googlePaysettings.button_style
      ? googlePaysettings.button_style
      : "default",
    buttonSizeMode: GPButtonSizeMode.fill,
  },
  billingParams: {
    phoneNumberRequired: true,
    emailRequired: true,
    billingAddressRequired: true,
    billingAddressFormat: GPBillingAddressFormat.full,
  },
  shippingParams: {
    phoneNumberRequired: true,
    emailRequired: true,
    onGetShippingCosts: function (shippingData) {
      logDebug({
        label: "onGetShippingCosts",
        data: googlePaysettings.shippingData,
      });
      return googlePaysettings.shippingCosts;
    },
    onGetShippingOptions: function (shippingData) {
      logDebug({
        label: "onGetShippingOptions",
        data: shippingData,
      });

      const hasShippingGpay = shippingData?.shippingAddress;
      
      if (
        hasShippingGpay &&
        shippingData.shippingAddress.administrativeArea == "HI"
      ) {
        return {
          error: {
            reason: "SHIPPING_ADDRESS_UNSERVICEABLE",
            message: "This shipping option is invalid for the given address",
            intent: "SHIPPING_ADDRESS",
          },
        };
      }
      let selectedOptionid = "free_shipping";
      if (
        hasShippingGpay &&
        shippingData.shippingOptionData.id !== "shipping_option_unselected"
      ) {
        selectedOptionid = shippingData.shippingOptionData.id;
      }
      return {
        defaultSelectedOptionId: selectedOptionid,
        shippingOptions: googlePaysettings.shippingMethods,
      };
    },
  },
  onGetTransactionInfo: function (shippingData) {
    logDebug({
      label: "onGetTransactionInfo",
      data: shippingData,
    });

    const amtQuick = parseFloat(subTotal.total).toFixed(2);
    let countryCodeQuick = null;

    if (
      jQuery("#billing_country").val() !== null &&
      jQuery("#billing_country").val() !== undefined
    ) {
      countryCodeQuick = jQuery("#billing_country").val();
    } else {
      countryCodeQuick = "US";
    }
    return {
      displayItems: [
        {
          label: "Subtotal",
          type: "SUBTOTAL",
          price: amtQuick.toString(),
        },
      ],
      countryCode: countryCodeQuick,
      currencyCode: googlePaysettings.currencyCode,
      totalPriceStatus: "FINAL",
      totalPrice: amt.toString(),
      totalPriceLabel: "Total",
    };
  },
  onBeforeProcessPayment: function () {
    return new Promise(function (resolve, reject) {
      try {
        resolve(iStatus.success);
      } catch (err) {
        reject(err);
      }
    });
  },
  onProcessPayment: function (paymentResponse) {
    paymentResponse = JSON.parse(JSON.stringify(paymentResponse));

    let xAmount = paymentResponse.transactionInfo.totalPrice;

    // Remove country code from telephone
    let telephone = paymentResponse.paymentData.paymentMethodData.info.billingAddress.phoneNumber;
    telephone = telephone.substring(telephone.indexOf(" ") + 1);

    let shippingOptionData = paymentResponse.paymentData.shippingOptionData;

    if (xAmount <= 0) {
      jQuery(".gpay-error")
        .html(
          "<div> Payment is not authorized. Amount must be greater than 0 </div>"
        )
        .show();
      setTimeout(function () {
        jQuery(".gpay-error").html("").hide();
      }, 3000);
      throw new Error(
        "Payment is not authorized. Amount must be greater than 0"
      );
    } else {
      let token = btoa(
        paymentResponse.paymentData.paymentMethodData.tokenizationData.token
      );

      createWooCommerceOrder(token, xAmount, telephone, paymentResponse.paymentData.email, paymentResponse.paymentData.shippingAddress, shippingOptionData);
    }
  },
  onPaymentCanceled: function (respCanceled) {
    jQuery(".gpay-error").html("<div> Payment was canceled </div>").show();
    setTimeout(function () {
      jQuery(".gpay-error").html("").hide();
    }, 3000);
  },
  handleResponse: function (resp) {
    const respObjQuick = JSON.parse(resp);
    if (respObjQuick) {
      if (respObjQuick.xError) {
        setTimeout(function () {
          alert(`There was a problem with your order (${respObjQuick.xRefNum})!`);
        }, 500);
      } else
        setTimeout(function () {
          alert(`Thank you for your order (${respObjQuick.xRefNum})!`);
        }, 500);
    }
  },
  getGPEnvironment: function () {
    return googlePaysettings.environment
      ? googlePaysettings.environment
      : "TEST";
  },
  initGP: function () {
    return {
      merchantInfo: this.merchantInfo,
      buttonOptions: this.buttonOptions,
      environment: this.getGPEnvironment(),
      billingParameters: this.billingParams,
      shippingParameters: {
        emailRequired: this.shippingParams.emailRequired,
        onGetShippingCosts: "gpRequest.shippingParams.onGetShippingCosts",
        onGetShippingOptions: "gpRequest.shippingParams.onGetShippingOptions",
      },
      onGetTransactionInfo: "gpRequest.onGetTransactionInfo",
      onBeforeProcessPayment: "gpRequest.onBeforeProcessPayment",
      onProcessPayment: "gpRequest.onProcessPayment",
      onPaymentCanceled: "gpRequest.onPaymentCanceled",
      onGPButtonLoaded: "gpRequest.gpButtonLoaded",
      isDebug: isDebugEnv,
    };
  },
  gpButtonLoaded: function (quickResp) {
    if (!quickResp) return;
    if (quickResp.status === iStatus.success) {
      showHide("divGpay", true);
    } else if (quickResp.reason) {
      logDebug({
        label: "gpButtonLoaded",
        data: quickResp.reason,
      });
    }
  },
};

function setGPPayload(value) {
  document.getElementById("gp-payload").value = value;
  showHide("divGPPayload", value);
  if (value) {
    divGPPayload.scrollIntoView(false);
  }
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
    let totals = subTotal.total;
    return parseFloat(totals).toFixed(2);
}


function createWooCommerceOrder(token, amount, telephone, email, shippingAddress, shippingOptionData) {
    jQuery.ajax({
        url: googlePaysettings.ajax_url,
        type: 'POST',
        data: {
            action: 'cardknox_create_order',
            google_pay_token: token,
            amount: amount,
            phone: telephone,
            email: email,
            shippingAddress: JSON.stringify(shippingAddress),
            shippingOptionData: JSON.stringify(shippingOptionData),
            security: googlePaysettings.create_order_nonce  // Include nonce
        },
        success: function (response) {            
            if (response.success) {
                window.location.href = response.data.redirect_url;
            } else {
                jQuery(".gpay-error").html("<div> " + response.data + " </div>").show();
                setTimeout(function () {
                    jQuery(".gpay-error").html("").hide();
                }, 3000);
            }
        },
        error: function (error) {
            console.log(error);  // Log the full error response
            jQuery(".gpay-error").html("<div> " + error.responseText + " </div>").show();
            setTimeout(function () {
                jQuery(".gpay-error").html("").hide();
            }, 3000);
        }
    });    
}