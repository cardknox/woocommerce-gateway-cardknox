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

jQuery( function( $ ) {
    'use strict';

    /* Open and close for legacy class */
    $('form.checkout, form#order_review').on('change', 'input[name="wc-cardknox-payment-token"]', function () {
        if ('new' === $('.cardknox-legacy-payment-fields input[name="wc-cardknox-payment-token"]:checked').val()) {
            $('.cardknox-legacy-payment-fields #cardknox-payment-data').slideDown(200);
        } else {
            $('.cardknox-legacy-payment-fields #cardknox-payment-data').slideUp(200);
        }
    });


    //
    //console.log("add_payment_method");


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
            if ($('form.woocommerce-checkout').length) {
                this.form = $('form.woocommerce-checkout');
            }

            $('form.woocommerce-checkout')
                .on(
                    'checkout_place_order_cardknox',
                    this.onSubmit
                );

            // pay order page
            if ($('form#order_review').length) {
                this.form = $('form#order_review');
            }

            $('form#order_review')
                .on(
                    'submit',
                    this.onSubmit
                );

            // add payment method page
            if ($('form#add_payment_method').length) {
                this.form = $('form#add_payment_method');
            }

            $('form#add_payment_method')
                .on(
                    'submit',
                    this.onSubmit
                );

            $(document)
                .on(
                    'change',
                    '#wc-cardknox-cc-form :input',
                    this.onCCFormChange
                )
                .on(
                    'cardknoxError',
                    this.onError
                )
                .on(
                    'checkout_error',
                    this.clearToken
                );
        },

        isCardknoxChosen: function () {
            return $('#payment_method_cardknox').is(':checked') && ( !$('input[name="wc-cardknox-payment-token"]:checked').length || 'new' === $('input[name="wc-cardknox-payment-token"]:checked').val() );
        },

        hasExp: function () {
            return 0 < $('input.xExp').length;
        },

        block: function () {
            wc_cardknox_form.form.block({
                message: null,
                overlayCSS: {
                    background: '#fff',
                    opacity: 0.6
                }
            });
        },

        unblock: function () {
            wc_cardknox_form.form.unblock();
        },

        onError: function (e, responseObject) {
            console.log('onError');
            var message = responseObject;

            // Customers do not need to know the specifics of the below type of errors
            // therefore return a generic localizable error message.
            //if (
            //	'invalid_request_error' === responseObject.response.error.type ||
            //	'api_connection_error'  === responseObject.response.error.type ||
            //	'api_error'             === responseObject.response.error.type ||
            //	'authentication_error'  === responseObject.response.error.type ||
            //	'rate_limit_error'      === responseObject.response.error.type
            //) {
            //message = wc_cardknox_params.invalid_request_error;
            //}

            //if ( 'card_error' === responseObject.response.error.type && wc_cardknox_params.hasOwnProperty( responseObject.response.error.code ) ) {
            //	message = wc_cardknox_params[ responseObject.response.error.code ];
            //}

            $('.wc-cardknox-error, .xExp').remove();
            $('#cardknox-card-number').closest('p').before('<ul class="woocommerce_error woocommerce-error wc-cardknox-error"><li>' + message + '</li></ul>');
            wc_cardknox_form.unblock();
        },

        onSubmit: function (e) {
            //debugger;
            console.log('onSubmit');
            if (wc_cardknox_form.isCardknoxChosen() && !wc_cardknox_form.hasExp()) {
                e.preventDefault();
                wc_cardknox_form.block();
                setAccount(wc_cardknox_params.key, "wordpress", "1.0.0");
                if (document.getElementById('cardknox-card-number').value === '') {
                    $(document).trigger('cardknoxError', 'card number required');
                }
                setAccount( wc_cardknox_params.key,"wordpress", "0.1.2" );

                getTokens(
                    function () {
                        //onSuccess
                        //perform your own validation here...
                        console.log("Success");
                        wc_cardknox_form.onCardknoxResponse();
                    },
                    function () {
                        //onError
                        console.log("error");
                        wc_cardknox_form.unblock();
                        $(document).trigger('cardknoxError', document.getElementById('ifieldsError').textContent);
                        //$( document ).trigger( 'cardknoxError', document.getElementById('ifieldsError').textContent);
                        return false;
                    },
                    //30 second timeout
                    30000
                );
                return false;
            }
        },

        onCCFormChange: function () {
            $('.wc-cardknox-error, .xExp').remove();
        },

        onCardknoxResponse: function () {
            var expires = $('#cardknox-card-expiry').payment('cardExpiryVal');
            var xExp = expires.month.toString() + expires.year.toString().substr(2, 2);
            console.log('onCardknoxResponse');
            wc_cardknox_form.form.append("<input type='hidden' class='xExp' name='xExp' value='" + xExp + "'/>");
            wc_cardknox_form.form.submit();

        },

        onIfieldloaded: function () {
            //var interval = setInterval(function () {
            //    if (wc_cardknox_form.isCardknoxChosen()) {
                    //alert("loaded");
                    var card_style = {
                        border: '0',
                        'border-left-color': 'rgb(67, 69, 75)',
                        'font-size': '19.8px',
                        padding: '12.25px',
                        width: '225px',
                        height: '32px',
                        'background-color': 'rgb(242, 242, 242)',
                        'font-weight': '400'
                    };

                    var cvv_style = {
                        border: '0',
                        'border-left-color': 'rgb(67, 69, 75)',
                        'font-size': '19.8px',
                        padding: '12.25px',
                        width: '106px',
                        height: '32px',
                        'background-color': 'rgb(242, 242, 242)',
                        'font-weight': '400'
                    };

                    setIfieldStyle('card-number', card_style);
                    setIfieldStyle('cvv', cvv_style);
                    //clearInterval(interval);
        }
                //Check what you want here
            //}, 500);



    };

    wc_cardknox_form.init();
});

