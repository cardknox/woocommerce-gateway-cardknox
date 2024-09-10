<?php
if (!defined('ABSPATH')) {
    exit;
}

$gtextType = 'text';
$gselectType = 'select';
$googlePrefix = 'Google Pay';
$gkeyPrefix = 'googlepay';
$gkeyTitle = 'title';
$gkeyType = 'type';
$gkeyOptions = 'options';
$gkeyDefault = 'default';
$gkeyDescTip = 'desc_tip';
$gbuttonPrefix = 'Google Pay Button';

$wc_cardknox_google_pay_settings = array(
    $gkeyPrefix . '_enabled' => array(
        $gkeyTitle       => __('Enabled', 'woocommerce-gateway-cardknox'),
        $gkeyType        => $gselectType,
        $gkeyOptions     => array(
            'yes'    => __('Yes', 'woocommerce-gateway-cardknox'),
            'no'     => __('No', 'woocommerce-gateway-cardknox'),
        ),
    ),
    $gkeyPrefix . '_quickcheckout' => array(
        $gkeyTitle       => __('Disable Quick Checkout to Cart Page', 'woocommerce-gateway-cardknox'),
        $gkeyType        => 'checkbox',
        'description' => __(
            'Disable Quick Checkout to Cart Page',
            'woocommerce-gateway-cardknox'
        ),
        $gkeyDefault    => 'no',
        $gkeyDescTip    => true,
    ),
    $gkeyPrefix . '_title' => array(
        $gkeyTitle       => __('Title', 'woocommerce-gateway-cardknox'),
        $gkeyType        => $gtextType,
        $gkeyDefault     => __('Google Pay', 'woocommerce-gateway-cardknox'),
    ),
    $gkeyPrefix . '_merchant_name' => array(
        $gkeyTitle       => __('Merchant Name', 'woocommerce-gateway-cardknox'),
        $gkeyType        => $gtextType,
        $gkeyDefault     => __('Example Merchant', 'woocommerce-gateway-cardknox'),
    ),
    $gkeyPrefix . '_environment' => array(
        $gkeyTitle       => __('Environment', 'woocommerce-gateway-cardknox'),
        $gkeyType        => $gselectType,
        $gkeyOptions     => array(
            'TEST'          => __('Test', 'woocommerce-gateway-cardknox'),
            'PRODUCTION'    => __('Production', 'woocommerce-gateway-cardknox'),
        ),
    ),
    $gkeyPrefix . '_button_style' => array(
        $gkeyTitle       => __($gbuttonPrefix . 'Style', 'woocommerce-gateway-cardknox'),
        $gkeyType        => $gselectType,
        $gkeyOptions     => array(
            'black'          => __('Black', 'woocommerce-gateway-cardknox'),
            'white'          => __('White', 'woocommerce-gateway-cardknox'),
        ),
    ),
    $gkeyPrefix . '_capture' => array(
        $gkeyTitle       => __($googlePrefix . 'Capture', 'woocommerce-gateway-cardknox'),
        'label'       => __('Capture charge immediately', 'woocommerce-gateway-cardknox'),
        $gkeyType        => 'checkbox',
        'id'          => 'google_cardknox_capture',
        'description' => __(
            'If the transaction is not immediately captured for Google Pay, it will require capturing at a later time.',
            'woocommerce-gateway-cardknox'
        ),
        $gkeyDefault     => 'yes',
        $gkeyDescTip    => true,
    ),
    $gkeyPrefix . '_auth_only_order_status' => array(
        $gkeyTitle       => __('Authorize Only Order Status', 'woocommerce-gateway-cardknox'),
        $gkeyType        => $gselectType,
        'class'       => 'wc-enhanced-select',
        'id'          => 'google_cardknox_authonly_status',
        'description' => __(
            'Defines the intended order status after an authorization-only transaction for Google Pay.',
            'woocommerce-gateway-cardknox'
        ),
        $gkeyDefault     => 'on-hold',
        $gkeyDescTip    => true,
        $gkeyOptions     => array(
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
    $gkeyPrefix . '_applicable_countries' => array(
        $gkeyTitle       => __('Payment From Applicable Countries', 'woocommerce-gateway-cardknox'),
        $gkeyType        => $gselectType,
        $gkeyOptions     => array(
            '0'      => __('All Allowed Countries', 'woocommerce-gateway-cardknox'),
            '1'      => __('Specific Countries', 'woocommerce-gateway-cardknox'),
        ),
    ),
    $gkeyPrefix . '_specific_countries' => array(
        $gkeyTitle       => __('Payment From Specific Countries', 'woocommerce-gateway-cardknox'),
        $gkeyType        => 'multiselect',
        $gkeyOptions     => $countries
    ),
);

$GLOBALS["wc_cardknox_google_pay_settings"] = $wc_cardknox_google_pay_settings;
