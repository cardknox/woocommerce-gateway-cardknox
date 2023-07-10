<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

return apply_filters( 'wc_cardknox_apple_settings',
    array(
        'applepay_enabled' => array(
            'title'       => __( 'Enabled', 'woocommerce-gateway-cardknox' ),
            'type'        => 'select',
            'options'     => array(
                'yes'    => __( 'Yes', 'woocommerce-gateway-cardknox' ),
                'no'     => __( 'No', 'woocommerce-gateway-cardknox' ),
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
            'type'        => 'select',
            'options'     => array(
                'TEST'          => __( 'Test', 'woocommerce-gateway-cardknox' ),
                'PRODUCTION'    => __( 'Production', 'woocommerce-gateway-cardknox' ),
            ),
        ),
        'applepay_button_style' => array(
            'title'       => __( 'Apple Pay Button Style', 'woocommerce-gateway-cardknox' ),
            'type'        => 'select',
            'options'     => array(
                'black'          => __( 'Black', 'woocommerce-gateway-cardknox' ),
                'white'          => __( 'White', 'woocommerce-gateway-cardknox' ),
                'whiteOutline'   => __( 'WhiteOutline', 'woocommerce-gateway-cardknox' ),
            ),
        ),
        'applepay_button_type' => array(
            'title'       => __( 'Apple Pay Button Type', 'woocommerce-gateway-cardknox' ),
            'type'        => 'select',
            'options'     => array(
                'buy'            => __( 'Buy', 'woocommerce-gateway-cardknox' ),
                'pay'            => __( 'Pay', 'woocommerce-gateway-cardknox' ),
                'plain'          => __( 'Plain', 'woocommerce-gateway-cardknox' ),
                'order'          => __( 'Order', 'woocommerce-gateway-cardknox' ),
                'donate'         => __( 'Donate', 'woocommerce-gateway-cardknox' ),
                'continue'       => __( 'Continue', 'woocommerce-gateway-cardknox' ),
                'checkout '      => __( 'Checkout', 'woocommerce-gateway-cardknox' ),
            ),
        ),
        'applepay_capture' => array(
			'title'       => __( 'Apple Pay Capture', 'woocommerce-gateway-cardknox' ),
			'label'       => __( 'Capture charge immediately', 'woocommerce-gateway-cardknox' ),
			'type'        => 'checkbox',
			'id'          => 'apple_cardknox_capture',
			'description' => __(
				'If the transaction is not immediately captured for Apple Pay, it will require capturing at a later.',
				'woocommerce-gateway-cardknox'
			),
			'default'     => 'yes',
			'desc_tip'    => true,
		),
        'applepay_auth_only_order_status' => array(
            'title'       => __( 'Authorize Only Order Status', 'woocommerce-gateway-cardknox' ),
            'type'        => 'select',
            'class'       => 'wc-enhanced-select',
            'id'          => 'apple_cardknox_authonly_status',
            'description' => __(
                'Defines the intended order status after an authorization-only transaction for Apple Pay.',
                'woocommerce-gateway-cardknox'
            ),
            'default'     => 'on-hold',
            'desc_tip'    => true,
            'options'     => array(
				'on-hold'        => __(
					'Set order status to on-hold when payment is authorized',
					'woocommerce-gateway-cardknox'
				),
				'processing' => __(
					'Set order status to processing when payment is authorized',
					'woocommerce-gateway-cardknox'
				),
            ),
        ),
        'applepay_applicable_countries' => array(
            'title'       => __( 'Payment From Applicable Countries', 'woocommerce-gateway-cardknox' ),
            'type'        => 'select',
            'options'     => array(
                '0'      => __( 'All Allowed Countries', 'woocommerce-gateway-cardknox' ),
                '1'      => __( 'Specific Countries', 'woocommerce-gateway-cardknox' ),
            ),
        ),
        'applepay_specific_countries' => array(
            'title'       => __( 'Payment From Specific Countries', 'woocommerce-gateway-cardknox' ),
            'type'        => 'multiselect',
            'options'     => WC()->countries->get_countries()
        ),
    )
);
