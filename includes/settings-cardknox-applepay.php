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

return apply_filters( 'wc_cardknox_apple_settings',
	array(
		'applepay_enabled' => array(
			'title'       => __( 'Enabled', 'woocommerce-gateway-cardknox' ),
			'type'     	  => 'select',
			'options'  => array(
				'yes'    => __( 'Yes', 'woocommerce-gateway-cardknox' ),
				'no'	   => __( 'No', 'woocommerce-gateway-cardknox' ),
			),
		),
		'applepay_title' => array(
			'title'       => __( 'Title', 'woocommerce-gateway-cardknox' ),
			'type'        => 'text',
			'default'     => __( 'Apple Pay', 'woocommerce-gateway-cardknox' ),
		),
		'applepay_merchant_identifier' => array(
			'title'       => __( 'Merchant Identifier', 'woocommerce-gateway-cardknox' ),
			'type'        => 'text',
			'default'     => __( 'merchant.cardknox.com', 'woocommerce-gateway-cardknox' ),
		),
		'applepay_environment' => array(
			'title'       => __( 'Environment', 'woocommerce-gateway-cardknox' ),
			'type'     	  => 'select',
			'options'  => array(
				'TEST'    		 => __( 'Test', 'woocommerce-gateway-cardknox' ),
				'PRODUCTION'	 => __( 'Production', 'woocommerce-gateway-cardknox' ),
			),
		),
		'applepay_button_style' => array(
			'title'       => __( 'Apple Pay Button Style', 'woocommerce-gateway-cardknox' ),
			'type'     	  => 'select',
			'options'  => array(
				'black'    		 => __( 'Black', 'woocommerce-gateway-cardknox' ),
				'white'	 		 => __( 'White', 'woocommerce-gateway-cardknox' ),
				'whiteOutline'	 => __( 'WhiteOutline', 'woocommerce-gateway-cardknox' ),
			),
		),
		'applepay_button_type' => array(
			'title'       => __( 'Apple Pay Button Type', 'woocommerce-gateway-cardknox' ),
			'type'     	  => 'select',
			'options'  => array(
				'buy'    		 => __( 'Buy', 'woocommerce-gateway-cardknox' ),
				'pay'	 		 => __( 'Pay', 'woocommerce-gateway-cardknox' ),
				'plain'	 => __( 'Plain', 'woocommerce-gateway-cardknox' ),
				'order'	 => __( 'Order', 'woocommerce-gateway-cardknox' ),
				'donate'	 => __( 'Donate', 'woocommerce-gateway-cardknox' ),
				'continue'	 => __( 'Continue', 'woocommerce-gateway-cardknox' ),
				'checkout '	 => __( 'Checkout', 'woocommerce-gateway-cardknox' ),
			),
		),
		'applepay_capture' => array(
			'title'       => __( 'Apple Pay Capture', 'woocommerce-gateway-cardknox' ),
			'label'       => __( 'Capture charge immediately', 'woocommerce-gateway-cardknox' ),
			'type'        => 'checkbox',
			'id'       	  => 'cardknox_capture',
			'description' => __( 'Whether or not to immediately capture the transaction. When unchecked, the transaction will need to be captured later for applePay.', 'woocommerce-gateway-cardknox' ),
			'default'     => 'yes',
			'desc_tip'    => true,
		),
		'applepay_auth_only_order_status' => array(
			'title'       => __( 'Authorize Only Order Status', 'woocommerce-gateway-cardknox' ),
			'type'     => 'select',
			'class'    => 'wc-enhanced-select',
			'id'       => 'cardknox_authonly_status',
			'description' => __( 'Configures what the order status should be changed to after a authorize only transaction for applePay.', 'woocommerce-gateway-cardknox' ),
			'default'     => 'on-hold',
			'desc_tip'    => true,
			'options'  => array(
				'on-hold'        => __( 'Set order status to on-hold when payment is authorized', 'woocommerce-gateway-cardknox' ),
				'processing' => __( 'set order status to processing when payment is authorized', 'woocommerce-gateway-cardknox' ),
			),
		),
		'applepay_applicable_countries' => array(
			'title'       => __( 'Payment From Applicable Countries', 'woocommerce-gateway-cardknox' ),
			'type'     	  => 'select',
			'options'  => array(
				'0'    	=> __( 'All Allowed Countries', 'woocommerce-gateway-cardknox' ),
				'1'	 	=> __( 'Specific Countries', 'woocommerce-gateway-cardknox' ),
			),
		),
		'applepay_specific_countries' => array(
			'title'       => __( 'Payment From Specific Countries', 'woocommerce-gateway-cardknox' ),
			'type'     	  => 'multiselect',
			'options'  	  => WC()->countries->get_countries()
		),
	)
);
