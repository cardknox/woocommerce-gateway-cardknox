<?php
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
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Gateway_Cardknox_Saved_Cards class.
 */
class WC_Gateway_Cardknox_Saved_Cards {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'wp', array( $this, 'delete_card' ) );
		add_action( 'woocommerce_after_my_account', array( $this, 'output' ) );
		add_action( 'wp', array( $this, 'default_card' ) );
	}

	/**
	 * Display saved cards
	 */
	public function output() {
		if ( ! is_user_logged_in() ) {
			return;
		}

		$cardknox_customer = new WC_Cardknox_Customer( get_current_user_id() );
		$cardknox_cards    = $cardknox_customer->get_cards();
		$default_card    = $cardknox_customer->get_default_card();

		if ( $cardknox_cards ) {
			wc_get_template( 'saved-cards.php', array( 'cards' => $cardknox_cards, 'default_card' => $default_card ), 'woocommerce-gateway-cardknox/', untrailingslashit( plugin_dir_path( WC_CARDKNOX_MAIN_FILE ) ) . '/includes/legacy/templates/' );
		}
	}

	/**
	 * Delete a card
	 */
//	public function delete_card() {
//		if ( ! isset( $_POST['cardknox_delete_card'] ) || ! is_account_page() ) {
//			return;
//		}
//
//		$cardknox_customer    = new WC_Cardknox_Customer( get_current_user_id() );
//		$cardknox_customer_id = $cardknox_customer->get_id();
//		$delete_card        = sanitize_text_field( $_POST['cardknox_delete_card'] );
//
//		if ( ! is_user_logged_in() || ! $cardknox_customer_id || ! wp_verify_nonce( $_POST['_wpnonce'], "cardknox_del_card" ) ) {
//			wp_die( __( 'Unable to make default card, please try again', 'woocommerce-gateway-cardknox' ) );
//		}
//
//		if ( ! $cardknox_customer->delete_card( $delete_card ) ) {
//			wc_add_notice( __( 'Unable to delete card.', 'woocommerce-gateway-cardknox' ), 'error' );
//		} else {
//			wc_add_notice( __( 'Card deleted.', 'woocommerce-gateway-cardknox' ), 'success' );
//		}
//	}

}
new WC_Gateway_Cardknox_Saved_Cards();
