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

    onIfieldloaded: function () {
      enableLogging();
      setAccount(wc_cardknox_params.key, "wordpress", "0.1.2");
      setupCardStyles();
      enableAutoFormatting();
      addIfieldCallback("input", handleInput);
      addIfieldCallback("issuerupdated", handleIssuerUpdate);
    },

    setupCardStyles: function () {
      var card_style = getCardStyle();
      var cvv_style = getCvvStyle();
      setIfieldStyle("card-number", card_style);
      setIfieldStyle("cvv", cvv_style);
    },

    getCardStyle: function () {
      return {
        outline: "none",
        border: "0",
        "border-left-color": "rgb(67, 69, 75)",
        padding: "0.6180469716em",
        width: "225px",
        height: "auto",
        "background-color": wc_cardknox_params.bgcolor,
        "font-weight": "inherit",
      };
    },

    getCvvStyle: function () {
      return {
        outline: "none",
        border: "0",
        "border-left-color": "rgb(67, 69, 75)",
        padding: "0.6180469716em",
        width: "106px",
        height: "auto",
        "background-color": wc_cardknox_params.bgcolor,
        "font-weight": "inherit",
      };
    },

    handleInput: function (data) {
      if (data.ifieldValueChanged) {
        updateCardNumberStyle(data);
        updateCvvStyle(data);
        updateAchStyle(data);
      }
    },

    updateCardNumberStyle: function (data) {
      setIfieldStyle(
        "card-number",
        data.cardNumberFormattedLength <= 0
          ? defaultStyle
          : data.cardNumberIsValid
          ? validStyle
          : invalidStyle
      );
    },

    updateCvvStyle: function (data) {
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
        // update cvv style based on card number changes
        updateCvvStyleFromCardNumber(data);
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
    },

    updateCvvStyleFromCardNumber: function (data) {
      if (data.issuer === "unknown" || data.cvvLength <= 0) {
        setIfieldStyle("cvv", defaultStyle);
      } else if (data.issuer === "amex") {
        setIfieldStyle("cvv", data.cvvLength === 4 ? validStyle : invalidStyle);
      } else {
        setIfieldStyle("cvv", data.cvvLength === 3 ? validStyle : invalidStyle);
      }
    },

    updateAchStyle: function (data) {
      setIfieldStyle(
        "ach",
        data.achLength === 0
          ? defaultStyle
          : data.achIsValid
          ? validStyle
          : invalidStyle
      );
    },

    handleIssuerUpdate: function (data) {
      updateCvvStyle(data);
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
        jQuery(placeOrderButton).hide();
        jQuery("div#divGpay").hide();
        jQuery("#ap-container").show();
        jQuery(".applepay-error").show();
      } else if (selectedPaymentMethod === "cardknox-googlepay") {
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
