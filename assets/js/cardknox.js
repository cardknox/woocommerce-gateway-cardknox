/*
 Copyright © 2018 Cardknox Development Inc. All rights reserved.

 This program is free software: you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation, either version 3 of the License, or
 (at your option) any later version.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
/* global wc_cardknox_params */

jQuery(function ($) {
  "use strict";

  /* Open and close for legacy class */
  $("form.checkout, form#order_review").on(
    "change",
    'input[name="wc-cardknox-payment-token"]',
    function () {
      if (
        "new" ===
        $(
          '.cardknox-legacy-payment-fields input[name="wc-cardknox-payment-token"]:checked'
        ).val()
      ) {
        $(".cardknox-legacy-payment-fields #cardknox-payment-data").slideDown(
          200
        );
      } else {
        $(".cardknox-legacy-payment-fields #cardknox-payment-data").slideUp(
          200
        );
      }
    }
  );

  function handle3DSResults(
    actionCode,
    xCavv,
    xEciFlag,
    xRefNum,
    xAuthenticateStatus,
    xSignatureVerification
  ) {
    // alert("submitting verify form with verification results");

    let url = "https://x1.cardknox.com/verify";
    var postData = {
      xKey: wc_cardknox_params.key,
      xRefNum: xRefNum,
      xCavv: xCavv,
      xEci: xEciFlag,
      x3dsAuthenticationStatus: xAuthenticateStatus,
      x3dsSignatureVerificationStatus: xSignatureVerification,
      x3dsActionCode: actionCode,
      x3dsError: ck3DS.error,
      xVersion: wc_cardknox_params.xVersion,
      xSoftwareName: wc_cardknox_params.xSoftwareName,
      xSoftwareVersion: wc_cardknox_params.xSoftwareVersion,
      xAllowDuplicate: 1,
    };

    $.ajax({
      method: "POST",
      url: url,
      data: postData,
    })
      .done(function (resp) {
        // handle the server response
        console.log(resp);
        if (resp.Status == "S") {
          // handle success, eg. show receipt
        } else {
          // handle error
        }
      })
      .fail(function (xhr, status, err) {
        // handle a failure
        var errorMessage = xhr.status + ": " + xhr.statusText;
        console.log(errorMessage);
      });
  }

  function urlEncodedToJson(data) {
    return JSON.parse(
      '{"' +
        decodeURI(data)
          .replace(/"/g, '\\"')
          .replace(/&/g, '","')
          .replace(/=/g, '":"') +
        '"}'
    );
  }
  /**
   * Object to handle Cardknox payment forms.
   */
  var wc_cardknox_form = {
    /**
     * Initialize event handlers and UI state.
     */
    init: function () {
      this.onIfieldloaded();
      // checkout page
      if ($("form.woocommerce-checkout").length) {
        this.form = $("form.woocommerce-checkout");
      }

      $("form.woocommerce-checkout")
        .on("checkout_place_order_cardknox", this.onSubmit)
        .on("change", this.reset);

      // pay order page
      if ($("form#order_review").length) {
        this.form = $("form#order_review");
      }

      $("form#order_review").on("submit", this.onSubmit);

      // add payment method page
      if ($("form#add_payment_method").length) {
        this.form = $("form#add_payment_method");
      }

      $("form#add_payment_method").on("submit", this.onSubmit);

      $(document)
        .on("change", "#wc-cardknox-cc-form :input", this.reset)
        .on("cardknoxError", this.onError)
        .on("checkout_error", this.reset);
    },

    isCardknoxChosen: function () {
      return (
        $("#payment_method_cardknox").is(":checked") &&
        (!$('input[name="wc-cardknox-payment-token"]:checked').length ||
          "new" === $('input[name="wc-cardknox-payment-token"]:checked').val())
      );
    },

    hasExp: function () {
      return 0 < $("input.xExp").length;
    },

    block: function () {
      wc_cardknox_form.form.block({
        message: null,
        overlayCSS: {
          background: "#fff",
          opacity: 0.6,
        },
      });
    },

    unblock: function () {
      wc_cardknox_form.form.unblock();
    },

    onError: function (e, responseObject) {
      console.log("onError");
      var message = responseObject;
      $(".wc-cardknox-error, .xExp").remove();
      $("#ifieldsError")
        .closest("p")
        .before(
          '<ul class="woocommerce_error woocommerce-error wc-cardknox-error"><li>' +
            message +
            "</li></ul>"
        );
      wc_cardknox_form.unblock();
    },

    onSubmit: function (e) {
      //debugger;
      console.log("onSubmit");
      // wc_cardknox_form.form.validate_field();
      if (wc_cardknox_form.isCardknoxChosen() && !wc_cardknox_form.hasExp()) {
        e.preventDefault();
        wc_cardknox_form.block();
        getTokens(
          function () {
            //onSuccess
            //perform your own validation here...
            if (document.getElementsByName("xCardNum")[0].value === "") {
              $(document).trigger("cardknoxError", "Card Number Required");
              return false;
            }
            if (document.getElementsByName("xCVV")[0].value === "") {
              $(document).trigger("cardknoxError", "CVV Required");
              return false;
            }

            if (wc_cardknox_params.enable_3ds == "yes") {
              $("#x3dsReferenceId").val(ck3DS.referenceId);
              $("#x3dsInitializeStatus").val(ck3DS.initializeStatus);
            }

            // console.log("Success");
            wc_cardknox_form.onCardknoxResponse();
          },
          function () {
            //onError
            console.log("error");
            $(document).trigger(
              "cardknoxError",
              document.getElementById("ifieldsError").textContent
            );
            return false;
          },
          //30 second timeout
          30000
        );
        return false;
      }
      return true;
    },

    onCardknoxResponse: function () {
      var xExp = document
        .getElementById("cardknox-card-expiry")
        .value.replace(/\s|\//g, "");
      if (xExp.length != 4) {
        $(document).trigger("cardknoxError", "Invalid expiration date");
        return false;
      }

      // Extract the month and year from the expiration date
      let month = parseInt(xExp.substr(0, 2));
      let year = parseInt(xExp.substr(2));

      // Validate the expiration month and year
      let currentDate = new Date();
      let currentYear = currentDate.getFullYear() % 100; // Get the last two digits of the current year
      let currentMonth = currentDate.getMonth() + 1; // January is month 0 in JavaScript

      if (
        year < currentYear ||
        (year === currentYear && month < currentMonth)
      ) {
        $(document).trigger(
          "cardknoxError",
          "Expiration must be in the future"
        );
        return false;
      }
      wc_cardknox_form.form.append(
        "<input type='hidden' class='xExp' id='xExp' name='xExp' value='" +
          xExp +
          "'/>"
      );
      wc_cardknox_form.form.submit();
    },

    reset: function () {
      $("#cardknox-card-cvc, #cardknox-card-number").val("");
      $(".xExp").remove();
    },

    onIfieldloaded: function () {
      enableLogging();
      setAccount(wc_cardknox_params.key, "wordpress", "0.1.2");

      var card_style = {
        outline: "none",
        border: "0",
        "border-left-color": "rgb(67, 69, 75)",
        padding: "0.6180469716em",
        width: "225px",
        height: "auto",
        "background-color": wc_cardknox_params.bgcolor,
        "font-weight": "inherit",
      };
      var cvv_style = {
        outline: "none",
        border: "0",
        "border-left-color": "rgb(67, 69, 75)",
        padding: "0.6180469716em",
        width: "106px",
        height: "auto",
        "background-color": wc_cardknox_params.bgcolor,
        "font-weight": "inherit",
      };
      setIfieldStyle("card-number", card_style);
      setIfieldStyle("cvv", cvv_style);

      /*
       * [Optional]
       * Use enableAutoFormatting(separator) to automatically format the card number field making it easier to read
       * The function contains an optional parameter to set the separator used between the card number chunks (Default is a single space)
       */
      enableAutoFormatting();

      /*
       * [Optional]
       * Use addIfieldCallback(event, callback) to set callbacks for when the event is triggered inside the ifield
       * The callback function receives a single parameter with data about the state of the ifields
       * The data returned can be seen by using alert(JSON.stringify(data));
       * The available events are ['input', 'click', 'focus', 'dblclick', 'change', 'blur', 'keypress', 'issuerupdated']
       * ('issuerupdated' is fired when the CVV ifield is updated with card issuer)
       *
       * The below example shows a use case for this, where you want to visually alert the user regarding the validity of the card number, cvv and ach ifields
       * Cvv styling should be updated on 'issuerupdated' event also as validity will change based on issuer
       */
      addIfieldCallback("input", function (data) {
        if (data.ifieldValueChanged) {
          setIfieldStyle(
            "card-number",
            data.cardNumberFormattedLength <= 0
              ? defaultStyle
              : data.cardNumberIsValid
              ? validStyle
              : invalidStyle
          );
          if (data.lastIfieldChanged === "cvv") {
            setIfieldStyle(
              "cvv",
              data.issuer === "unknown" || data.cvvLength <= 0
                ? defaultStyle
                : data.cvvIsValid
                ? validStyle
                : invalidStyle
            );
          } else if (data.lastIfieldChanged === "card-number") {
            if (data.issuer === "unknown" || data.cvvLength <= 0) {
              setIfieldStyle("cvv", defaultStyle);
            } else if (data.issuer === "amex") {
              setIfieldStyle(
                "cvv",
                data.cvvLength === 4 ? validStyle : invalidStyle
              );
            } else {
              setIfieldStyle(
                "cvv",
                data.cvvLength === 3 ? validStyle : invalidStyle
              );
            }
          } else if (data.lastIfieldChanged === "ach") {
            setIfieldStyle(
              "ach",
              data.achLength === 0
                ? defaultStyle
                : data.achIsValid
                ? validStyle
                : invalidStyle
            );
          }
        }
      });

      addIfieldCallback("issuerupdated", function (data) {
        setIfieldStyle(
          "cvv",
          data.issuer === "unknown" || data.cvvLength <= 0
            ? defaultStyle
            : data.cvvIsValid
            ? validStyle
            : invalidStyle
        );
      });

      if (wc_cardknox_params.enable_3ds == "yes") {
        enable3DS(wc_cardknox_params.threeds_env, handle3DSResults);
      } else {
        enable3DS(wc_cardknox_params.threeds_env, null);
      }
    },
  };

  wc_cardknox_form.init();
});

jQuery(document).ready(function () {
  // Listen for the 'updated_checkout' event triggered by WooCommerce
  jQuery(document.body).on("updated_checkout", function () {
    jQuery("#ap-container").hide();
    var paymentMethodSelect = 'input[name="payment_method"]'; // Replace with your payment method selector
    var placeOrderButton = 'button[name="woocommerce_checkout_place_order"]'; // Replace with your "Place Order" button selector

    // Initial check on page load
    hidePlaceOrderButton();

    // Check on payment method change
    jQuery(document).on("change", paymentMethodSelect, function () {
      hidePlaceOrderButton();
    });

    // Function to hide/show "Place Order" button
    function hidePlaceOrderButton() {
      var selectedPaymentMethod = jQuery(
        paymentMethodSelect + ":checked"
      ).val();

      if (selectedPaymentMethod === "cardknox-applepay") {
        // Replace 'your_payment_method_slug' with your payment method value
        jQuery(placeOrderButton).hide();
        jQuery("div#divGpay").hide();
        jQuery("#ap-container").show();
        jQuery(".applepay-error").show();
      } else if (selectedPaymentMethod === "cardknox-googlepay") {
        // Replace 'your_payment_method_slug' with your payment method value
        jQuery(placeOrderButton).hide();
        jQuery("div#divGpay").show();
        jQuery("#ap-container").hide();
        jQuery(".applepay-error").hide();
      } else {
        jQuery(placeOrderButton).show();
        jQuery("div#divGpay").hide();
        jQuery("#ap-container").hide();
        jQuery(".applepay-error").hide();
      }
    }
  });
});
