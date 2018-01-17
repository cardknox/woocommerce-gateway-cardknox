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

return apply_filters( 'wc_cardknox_settings',
	array(
		'enabled' => array(
			'title'       => __( 'Enable/Disable', 'woocommerce-gateway-cardknox' ),
			'label'       => __( 'Enable Cardknox', 'woocommerce-gateway-cardknox' ),
			'type'        => 'checkbox',
			'description' => '',
			'default'     => 'no',
		),
		'title' => array(
			'title'       => __( 'Title', 'woocommerce-gateway-cardknox' ),
			'type'        => 'text',
			'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce-gateway-cardknox' ),
			'default'     => __( 'Credit Card', 'woocommerce-gateway-cardknox' ),
			'desc_tip'    => true,
		),
		'description' => array(
			'title'       => __( 'Description', 'woocommerce-gateway-cardknox' ),
			'type'        => 'text',
			'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce-gateway-cardknox' ),
			'default'     => __( 'Pay with your credit card.', 'woocommerce-gateway-cardknox' ),
			'desc_tip'    => true,
		),
		'token_key' => array(
			'title'       => __( 'Cardknox Token Key', 'woocommerce-gateway-cardknox' ),
			'type'        => 'text',
			'description' => __( 'Get your API keys from your cardknox account.', 'woocommerce-gateway-cardknox' ),
			'default'     => '',
			'desc_tip'    => true,
		),
		'transaction_key' => array(
			'title'       => __( 'Cardknox Transaction Key', 'woocommerce-gateway-cardknox' ),
			'type'        => 'text',
			'description' => __( 'Get your API keys from your cardknox account.', 'woocommerce-gateway-cardknox' ),
			'default'     => '',
			'desc_tip'    => true,
		),
		'capture' => array(
			'title'       => __( 'Capture', 'woocommerce-gateway-cardknox' ),
			'label'       => __( 'Capture charge immediately', 'woocommerce-gateway-cardknox' ),
			'type'        => 'checkbox',
			'description' => __( 'Whether or not to immediately capture the charge. When unchecked, the charge issues an authorization and will need to be captured later. Uncaptured charges expire in 7 days.', 'woocommerce-gateway-cardknox' ),
			'default'     => 'yes',
			'desc_tip'    => true,
		),
		'saved_cards' => array(
			'title'       => __( 'Saved Cards', 'woocommerce-gateway-cardknox' ),
			'label'       => __( 'Enable Payment via Saved Cards', 'woocommerce-gateway-cardknox' ),
			'type'        => 'checkbox',
			'description' => __( 'If enabled, users will be able to pay with a saved card during checkout. Card details are saved on Cardknox servers, not on your store.', 'woocommerce-gateway-cardknox' ),
			'default'     => 'no',
			'desc_tip'    => true,
		),
		'logging' => array(
			'title'       => __( 'Logging', 'woocommerce-gateway-cardknox' ),
			'label'       => __( 'Log debug messages', 'woocommerce-gateway-cardknox' ),
			'type'        => 'checkbox',
			'description' => __( 'Save debug messages to the WooCommerce System Status log.', 'woocommerce-gateway-cardknox' ),
			'default'     => 'no',
			'desc_tip'    => true,
		),
	)
);
