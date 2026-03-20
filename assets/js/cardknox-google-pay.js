jQuery(document.body).on("updated_checkout", function () {
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
    //phoneNumberRequired: true,
    emailRequired: true,
    billingAddressRequired: false,
  },
  shippingParams: {
    shippingAddressRequired: false,
  },
  onGetTransactionInfo: function (shippingData) {
    const amt = getAmount();
    let countryCode = null;
    if (
      jQuery("#billing_country").val() !== null &&
      jQuery("#billing_country").val() !== undefined
    ) {
      countryCode = jQuery("#billing_country").val();
    } else {
      countryCode = "US";
    }
    return {
      displayItems: [
        {
          label: "Subtotal",
          type: "SUBTOTAL",
          price: amt.toString(),
        },
      ],
      countryCode: countryCode,
      currencyCode: googlePaysettings.currencyCode,
      totalPriceStatus: "FINAL",
      totalPrice: amt.toString(),
      totalPriceLabel: "Total",
    };
  },
  onBeforeProcessPayment: function () {
    return new Promise(function (resolve, reject) {
      try {
        //Do some validation here
        resolve(iStatus.success);
      } catch (err) {
        reject(err);
      }
    });
  },
  onProcessPayment: function (paymentResponse) {
    paymentResponse = JSON.parse(JSON.stringify(paymentResponse));

    let xAmount = paymentResponse.transactionInfo.totalPrice;
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
      jQuery("#googlePaytoken").val(token);
      jQuery("#place_order").trigger("click");
    }
  },
  onPaymentCanceled: function (respCanceled) {
    jQuery(".gpay-error").html("<div> Payment was canceled </div>").show();
    setTimeout(function () {
      jQuery(".gpay-error").html("").hide();
    }, 3000);
  },
  handleResponse: function (resp) {
    const respObj = JSON.parse(resp);
    if (respObj) {
      if (respObj.xError) {
        setTimeout(function () {
          alert(`There was a problem with your order (${respObj.xRefNum})!`);
        }, 500);
      } else
        setTimeout(function () {
          alert(`Thank you for your order (${respObj.xRefNum})!`);
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
      shippingParameters: this.shippingParams,
      onGetTransactionInfo: "gpRequest.onGetTransactionInfo",
      onBeforeProcessPayment: "gpRequest.onBeforeProcessPayment",
      onProcessPayment: "gpRequest.onProcessPayment",
      onPaymentCanceled: "gpRequest.onPaymentCanceled",
      onGPButtonLoaded: "gpRequest.gpButtonLoaded",
      isDebug: isDebugEnv,
    };
  },
  gpButtonLoaded: function (resp) {
    if (!resp) return;
    if (resp.status === iStatus.success) {
      showHide("divGpay", true);
    } else if (resp.reason) {
      logDebug({
        label: "gpButtonLoaded",
        data: resp.reason,
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

function getAmount() {
  let totals = googlePaysettings.total;
  return parseFloat(totals).toFixed(2);
}
