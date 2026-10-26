jQuery(function ($) {
  "use strict";

  var $form = $("form.checkout, form#order_review");
  var isTokenizing = false;
  var iFieldsReady = false;

  var defaultStyle = {
    border: "1px solid #c3c3c3",
    "font-size": "14px",
    padding: "3px",
    width: "100%",
  };
  var validStyle = {
    border: "1px solid green",
    "font-size": "14px",
    padding: "3px",
    width: "100%",
  };
  var invalidStyle = {
    border: "1px solid red",
    "font-size": "14px",
    padding: "3px",
    width: "100%",
  };

  function getParams() {
    return window.wc_cardknox_ach_params || {};
  }

  function isAchChosen() {
    return $("#payment_method_cardknox-ach").is(":checked");
  }

  function isValidRoutingNumber(routing) {
    if (!/^\d{9}$/.test(routing)) {
      return false;
    }
    var digits = routing.split("").map(Number);
    var checksum =
      3 * (digits[0] + digits[3] + digits[6]) +
      7 * (digits[1] + digits[4] + digits[7]) +
      (digits[2] + digits[5] + digits[8]);
    return checksum % 10 === 0;
  }

  function showFieldError($field, message) {
    $field.addClass("wc-cardknox-ach-field-invalid");
    var $error = $field.siblings(".wc-cardknox-ach-error");
    if (!$error.length) {
      $error = $field.closest(".form-row").find(".wc-cardknox-ach-error").first();
    }
    if ($error.length) {
      $error.text(message).show();
    }
  }

  function clearAllErrors() {
    $(".wc-cardknox-ach-form .input-text").removeClass("wc-cardknox-ach-field-invalid");
    $(".wc-cardknox-ach-error").hide().text("");
    $("#cardknox-ach-ifields-error").text("").hide();
  }

  /**
   * Validate non-sensitive ACH fields (routing, name, consent).
   * Account number is validated via iFields (achIsValid / getTokens).
   */
  function validateAchFields() {
    clearAllErrors();

    if (!isAchChosen()) {
      return true;
    }

    var params = getParams();
    var valid = true;
    var accountType = $('input[name="cardknox_ach_account_type"]:checked').val();
    var nameOnAccount = $.trim($("#cardknox_ach_name_on_account").val() || "");
    var routing = $.trim($("#cardknox_ach_routing_number").val() || "").replace(/\D/g, "");
    var consent = $("#cardknox_ach_consent").is(":checked");

    $("#cardknox_ach_routing_number").val(routing);

    if (!accountType) {
      showFieldError(
        $(".wc-cardknox-ach-account-type").first(),
        params.i18n_account_type || "Please select an account type."
      );
      valid = false;
    }

    if (!nameOnAccount) {
      showFieldError(
        $("#cardknox_ach_name_on_account"),
        params.i18n_name_required || "Name on account is required."
      );
      valid = false;
    }

    if (!isValidRoutingNumber(routing)) {
      showFieldError(
        $("#cardknox_ach_routing_number"),
        params.i18n_routing_invalid || "Please enter a valid 9-digit routing number."
      );
      valid = false;
    }

    if (params.reenter === "yes") {
      var routingConfirm = $.trim($("#cardknox_ach_routing_number_confirm").val() || "").replace(
        /\D/g,
        ""
      );
      $("#cardknox_ach_routing_number_confirm").val(routingConfirm);

      if (routing !== routingConfirm) {
        showFieldError(
          $("#cardknox_ach_routing_number_confirm"),
          params.i18n_routing_mismatch || "Routing numbers do not match."
        );
        valid = false;
      }
    }

    if (!consent) {
      showFieldError(
        $("#cardknox_ach_consent"),
        params.i18n_consent_required ||
          "You must acknowledge and agree to the ACH terms and conditions."
      );
      valid = false;
    }

    return valid;
  }

  function initIFields() {
    var params = getParams();
    if (!params.key || typeof setAccount !== "function") {
      return;
    }

    setAccount(params.key, params.software_name || "Wordpress_WooCommerce", params.software_version || "1.0");

    if (typeof setIfieldStyle === "function") {
      setIfieldStyle("ach", defaultStyle);
    }

    if (typeof addIfieldCallback === "function") {
      addIfieldCallback("input", function (data) {
        if (!data.ifieldValueChanged || data.lastIfieldChanged !== "ach") {
          return;
        }
        if (typeof setIfieldStyle !== "function") {
          return;
        }
        setIfieldStyle(
          "ach",
          data.achLength === 0 ? defaultStyle : data.achIsValid ? validStyle : invalidStyle
        );
      });
    }

    iFieldsReady = true;
  }

  function blockForm() {
    $form.block({
      message: null,
      overlayCSS: {
        background: "#fff",
        opacity: 0.6,
      },
    });
  }

  function unblockForm() {
    $form.unblock();
    isTokenizing = false;
  }

  function showAchTokenError(message) {
    var params = getParams();
    var msg =
      message ||
      params.i18n_account_required ||
      "Account number is required.";
    $("#cardknox-ach-ifields-error").text(msg).show();
    if (typeof setIfieldStyle === "function") {
      setIfieldStyle("ach", invalidStyle);
    }
    unblockForm();
  }

  /**
   * Standard iFields flow: getTokens() → populate xACH SUT → submit checkout.
   */
  function onAchSubmit(e) {
    if (!isAchChosen()) {
      return true;
    }

    if (isTokenizing) {
      return false;
    }

    // Already tokenized for this submit cycle.
    if ($('input[name="xACH"]').val() && $form.data("cardknox-ach-ready") === true) {
      $form.data("cardknox-ach-ready", false);
      return true;
    }

    if (!validateAchFields()) {
      return false;
    }

    if (typeof getTokens !== "function") {
      showAchTokenError(getParams().i18n_ifields_unavailable || "Payment form is not ready. Please refresh and try again.");
      return false;
    }

    if (e && e.preventDefault) {
      e.preventDefault();
    }

    isTokenizing = true;
    blockForm();

    getTokens(
      function () {
        var achToken = $('input[name="xACH"]').val() || "";
        if (!achToken) {
          showAchTokenError();
          return false;
        }

        if (typeof setIfieldStyle === "function") {
          setIfieldStyle("ach", validStyle);
        }

        $form.data("cardknox-ach-ready", true);
        isTokenizing = false;
        $form.unblock();
        $form.submit();
      },
      function () {
        var errEl = document.querySelector(
          '#wc-cardknox-ach-form [data-ifields-id="card-data-error"], #cardknox-ach-ifields-error'
        );
        var errMsg = errEl && errEl.textContent ? errEl.textContent.trim() : "";
        showAchTokenError(errMsg || getParams().i18n_account_invalid || "Please enter a valid account number.");
        return false;
      },
      30000
    );

    return false;
  }

  // Init iFields when ACH method is selected / checkout updates.
  function setupWhenVisible() {
    if (isAchChosen()) {
      initIFields();
    }
  }

  $(document.body).on("updated_checkout", function () {
    setTimeout(setupWhenVisible, 500);
  });

  $(document).on("change", 'input[name="payment_method"]', function () {
    if ($(this).val() === "cardknox-ach") {
      setTimeout(initIFields, 300);
    }
  });

  // Place-order / form submit hook for classic checkout.
  $("form.woocommerce-checkout").on("checkout_place_order_cardknox-ach", onAchSubmit);
  $("form#order_review").on("submit", function (e) {
    if (isAchChosen()) {
      return onAchSubmit(e);
    }
    return true;
  });

  $(document.body).on(
    "blur",
    "#cardknox_ach_routing_number, #cardknox_ach_name_on_account, #cardknox_ach_routing_number_confirm",
    function () {
      if (isAchChosen()) {
        validateAchFields();
      }
    }
  );

  // Initial load
  setTimeout(setupWhenVisible, 800);
});
