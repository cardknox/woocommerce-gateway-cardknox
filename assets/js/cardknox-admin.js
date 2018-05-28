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

			$( 'input#woocommerce_cardknox_capture' ).change(function() {
				if ( $( this ).is( ':checked' ) ) {
					$( '#woocommerce_cardknox_auth_only_order_status' ).closest( 'tr' ).hide();
				} else {
					$( '#woocommerce_cardknox_auth_only_order_status' ).closest( 'tr' ).show();
				}
			}).change();
		}


	};

	wc_cardknox_admin.init();
});
