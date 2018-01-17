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
jQuery( function( $ ) {
	'use strict';

	/**
	 * Object to handle Cardknox admin functions.
	 */
	var wc_cardknox_admin = {

		getSecretKey: function() {
			return $( '#woocommerce_cardknox_transaction_key' ).val();
		},

		/**
		 * Initialize.
		 */
		init: function() {

			// Validate the keys to make sure it is matching live with live field.
			$( '#woocommerce_cardknox_test_transaction_key, #woocommerce_cardknox_test_token_key' ).on( 'input', function() {
				var value = $( this ).val();

				if ( value.indexOf( '_live_' ) >= 0 ) {
					$( this ).css( 'border-color', 'red' ).after( '<span class="description cardknox-error-description" style="color:red; display:block;">' + wc_cardknox_admin_params.localized_messages.not_valid_test_key_msg + '</span>' );
				} else {
					$( this ).css( 'border-color', '' );
					$( '.cardknox-error-description', $( this ).parent() ).remove();
				}
			}).trigger( 'input' );
		}
	};

	wc_cardknox_admin.init();
});
