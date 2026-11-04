let subTotal = {
    total: parseFloat(googlePaysettings.total).toFixed(2),
    subtotal: null,
    shipping_total: null,
    tax_total: null,
};

let lastGooglePayShippingAddress = null;

/**
 * Ask the server for cart totals + shipping rates for the wallet address.
 * Pass billingAddress when available so tax-based-on-billing stores are correct.
 */
function fetchGooglePayWalletTotals(address, shippingMethodId, billingAddress) {
  const data = {
    action: "cardknox_wallet_calculate_totals",
    security: googlePaysettings.create_order_nonce,
    address: JSON.stringify(address || {}),
    shipping_method: shippingMethodId || "",
  };
  if (billingAddress && (billingAddress.countryCode || billingAddress.country)) {
    data.billing_address = JSON.stringify(billingAddress);
  }

  return new Promise(function (resolve, reject) {
    jQuery.ajax({
      url: googlePaysettings.ajax_url,
      type: "POST",
      data: data,
      success: function (response) {
        if (response && response.success && response.data) {
          resolve(response.data);
        } else {
          reject(
            (response && response.data) ||
              "Unable to calculate shipping for this address."
          );
        }
      },
      error: function (xhr) {
        reject(xhr.responseText || "Unable to calculate shipping.");
      },
    });
  });
}

function applyGooglePayTotalsData(data) {
  if (!data) {
    return;
  }
  subTotal.total = parseFloat(data.total).toFixed(2);
  subTotal.subtotal = data.subtotal;
  subTotal.shipping_total = data.shipping_total;
  subTotal.tax_total = data.tax_total;
  if (data.shipping_methods_google && data.shipping_methods_google.length) {
    googlePaysettings.shippingMethods = data.shipping_methods_google;
  }
  if (data.shipping_costs_google) {
    googlePaysettings.shippingCosts = data.shipping_costs_google;
  }
}

function buildGooglePayTransactionInfo(countryCode) {
  const amtQuick = parseFloat(subTotal.total).toFixed(2);
  const displayItems = [];

  if (subTotal.subtotal != null) {
    displayItems.push({
      label: "Subtotal",
      type: "SUBTOTAL",
      price: parseFloat(subTotal.subtotal).toFixed(2),
    });
  } else {
    displayItems.push({
      label: "Subtotal",
      type: "SUBTOTAL",
      price: amtQuick.toString(),
    });
  }

  if (subTotal.shipping_total != null && parseFloat(subTotal.shipping_total) > 0) {
    displayItems.push({
      label: "Shipping",
      type: "LINE_ITEM",
      price: parseFloat(subTotal.shipping_total).toFixed(2),
    });
  }

  if (subTotal.tax_total != null && parseFloat(subTotal.tax_total) > 0) {
    displayItems.push({
      label: "Tax",
      type: "TAX",
      price: parseFloat(subTotal.tax_total).toFixed(2),
    });
  }

  return {
    displayItems: displayItems,
    countryCode: countryCode || "US",
    currencyCode: googlePaysettings.currencyCode,
    totalPriceStatus: "FINAL",
    totalPrice: amtQuick.toString(),
    totalPriceLabel: "Total",
  };
}

function initCardknoxCartGooglePay() {
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
    return;
  }
  ckGooglePay.enableGooglePay({
    initFunction: "gpRequest.initGP",
    amountField: "amount",
  });
}

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
                subTotal.subtotal = null;
                subTotal.shipping_total = null;
                subTotal.tax_total = null;
            }
        }
    });
    // Re-init after cart totals refresh (do not call enableGooglePay on page load twice).
    initCardknoxCartGooglePay();
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
        data: googlePaysettings.shippingCosts,
      });
      return googlePaysettings.shippingCosts || {};
    },
    onGetShippingOptions: function (shippingData) {
      logDebug({
        label: "onGetShippingOptions",
        data: shippingData,
      });

      const hasShippingGpay = shippingData?.shippingAddress;
      const firstMethodId =
        (googlePaysettings.shippingMethods[0] &&
          googlePaysettings.shippingMethods[0].id) ||
        "";
      if (!hasShippingGpay) {
        return {
          defaultSelectedOptionId: firstMethodId,
          shippingOptions: googlePaysettings.shippingMethods,
        };
      }

      lastGooglePayShippingAddress = shippingData.shippingAddress;
      let selectedOptionid = firstMethodId;
      if (
        shippingData.shippingOptionData &&
        shippingData.shippingOptionData.id !== "shipping_option_unselected"
      ) {
        selectedOptionid = shippingData.shippingOptionData.id;
      }

      return fetchGooglePayWalletTotals(
        shippingData.shippingAddress,
        selectedOptionid
      )
        .then(function (data) {
          applyGooglePayTotalsData(data);
          const defaultId =
            data.chosen_shipping_method ||
            (data.shipping_methods_google[0] &&
              data.shipping_methods_google[0].id) ||
            selectedOptionid;
          return {
            defaultSelectedOptionId: defaultId,
            shippingOptions: googlePaysettings.shippingMethods,
          };
        })
        .catch(function (err) {
          return {
            error: {
              reason: "SHIPPING_ADDRESS_UNSERVICEABLE",
              message:
                typeof err === "string"
                  ? err
                  : "This shipping option is invalid for the given address",
              intent: "SHIPPING_ADDRESS",
            },
          };
        });
    },
  },
  onGetTransactionInfo: function (shippingData) {
    logDebug({
      label: "onGetTransactionInfo",
      data: shippingData,
    });

    let countryCodeQuick = "US";
    if (
      jQuery("#billing_country").val() !== null &&
      jQuery("#billing_country").val() !== undefined
    ) {
      countryCodeQuick = jQuery("#billing_country").val();
    } else if (shippingData?.shippingAddress?.countryCode) {
      countryCodeQuick = shippingData.shippingAddress.countryCode;
    }

    const hasAddress = shippingData?.shippingAddress;
    if (!hasAddress) {
      return buildGooglePayTransactionInfo(countryCodeQuick);
    }

    lastGooglePayShippingAddress = shippingData.shippingAddress;
    let selectedOptionid = "";
    if (
      shippingData.shippingOptionData &&
      shippingData.shippingOptionData.id !== "shipping_option_unselected"
    ) {
      selectedOptionid = shippingData.shippingOptionData.id;
    }

    return fetchGooglePayWalletTotals(
      shippingData.shippingAddress,
      selectedOptionid
    )
      .then(function (data) {
        applyGooglePayTotalsData(data);
        return buildGooglePayTransactionInfo(
          shippingData.shippingAddress.countryCode || countryCodeQuick
        );
      })
      .catch(function () {
        return buildGooglePayTransactionInfo(countryCodeQuick);
      });
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

    let billingAddress =
      paymentResponse.paymentData.paymentMethodData.info.billingAddress || null;

    // Prefer billing phone for AVS / order contact; fall back to shipping.
    let telephone =
      (billingAddress && billingAddress.phoneNumber) ||
      (paymentResponse.paymentData.shippingAddress &&
        paymentResponse.paymentData.shippingAddress.phoneNumber) ||
      "";
    if (telephone && telephone.indexOf(" ") !== -1) {
      telephone = telephone.substring(telephone.indexOf(" ") + 1);
    }

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

      const shippingAddress = paymentResponse.paymentData.shippingAddress;
      const selectedOptionId =
        (shippingOptionData && shippingOptionData.id) || "";

      // Recalculate with real billing before charge (tax-based-on-billing).
      fetchGooglePayWalletTotals(shippingAddress, selectedOptionId, billingAddress)
        .then(function (data) {
          applyGooglePayTotalsData(data);
          xAmount = subTotal.total;
        })
        .catch(function () {
          // Keep sheet amount if recalc fails; server still validates.
        })
        .then(function () {
          createWooCommerceOrder(
            token,
            xAmount,
            telephone,
            paymentResponse.paymentData.email,
            shippingAddress,
            shippingOptionData,
            billingAddress
          );
        });
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


// Init once after gpRequest is defined (fixes load-order + avoids double enableGooglePay).
initCardknoxCartGooglePay();

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


var cardknoxGpayOrderSubmitting = false;
function createWooCommerceOrder(token, amount, telephone, email, shippingAddress, shippingOptionData, billingAddress) {
    // Prevent duplicate AJAX / double order creation from cart quick checkout.
    if (cardknoxGpayOrderSubmitting) {
        return;
    }
    cardknoxGpayOrderSubmitting = true;

    jQuery.ajax({
        url: googlePaysettings.ajax_url,
        type: 'POST',
        data: {
            action: 'cardknox_create_order',
            google_pay_token: token,
            amount: amount,
            phone: telephone,
            email: email,
            billingAddress: JSON.stringify(billingAddress || {}),
            shippingAddress: JSON.stringify(shippingAddress),
            shippingOptionData: JSON.stringify(shippingOptionData),
            security: googlePaysettings.create_order_nonce  // Include nonce
        },
        success: function (response) {            
            if (response.success) {
                window.location.href = response.data.redirect_url;
            } else {
                cardknoxGpayOrderSubmitting = false;
                jQuery(".gpay-error").html("<div> " + response.data + " </div>").show();
                setTimeout(function () {
                    jQuery(".gpay-error").html("").hide();
                }, 3000);
            }
        },
        error: function (error) {
            cardknoxGpayOrderSubmitting = false;
            console.log(error);  // Log the full error response
            jQuery(".gpay-error").html("<div> " + error.responseText + " </div>").show();
            setTimeout(function () {
                jQuery(".gpay-error").html("").hide();
            }, 3000);
        }
    });    
}