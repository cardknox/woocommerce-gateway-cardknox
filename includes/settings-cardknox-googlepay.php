<?php
if (!defined('ABSPATH')) {
    exit;
}

global $woocommerce;
$countries_obj   = new WC_Countries();
$countries   = $countries_obj->__get('countries');

$textType = 'text';
$selectType = 'select';
$googlePrefix = 'Google Pay';
$keyPrefix = 'googlepay';
$keyTitle = 'title';
$keyType = 'type';
$keyOptions = 'options';
$keyDefault = 'default';
$keyDescTip = 'desc_tip';
$buttonPrefix = 'Google Pay Button';

global $woocommerce;
$countries_obj   = new WC_Countries();
$countries   = $countries_obj->__get('countries');

$wc_cardknox_google_pay_settings = array(
    $keyPrefix . '_enabled' => array(
        $keyTitle       => __('Enabled', 'woocommerce-gateway-cardknox'),
        $keyType        => $selectType,
        $keyOptions     => array(
            'yes'    => __('Yes', 'woocommerce-gateway-cardknox'),
            'no'     => __('No', 'woocommerce-gateway-cardknox'),
        ),
    ),
    $keyPrefix . '_title' => array(
        $keyTitle       => __('Title', 'woocommerce-gateway-cardknox'),
        $keyType        => $textType,
        $keyDefault     => __('Google Pay', 'woocommerce-gateway-cardknox'),
    ),
    $keyPrefix . '_merchant_name' => array(
        $keyTitle       => __('Merchant Name', 'woocommerce-gateway-cardknox'),
        $keyType        => $textType,
        $keyDefault     => __('Example Merchant', 'woocommerce-gateway-cardknox'),
    ),
    $keyPrefix . '_environment' => array(
        $keyTitle       => __('Environment', 'woocommerce-gateway-cardknox'),
        $keyType        => $selectType,
        $keyOptions     => array(
            'TEST'          => __('Test', 'woocommerce-gateway-cardknox'),
            'PRODUCTION'    => __('Production', 'woocommerce-gateway-cardknox'),
        ),
    ),
    $keyPrefix . '_button_style' => array(
        $keyTitle       => __($buttonPrefix . ' Style', 'woocommerce-gateway-cardknox'),
        $keyType        => $selectType,
        $keyOptions     => array(
            'black'          => __('Black', 'woocommerce-gateway-cardknox'),
            'white'          => __('White', 'woocommerce-gateway-cardknox'),
            //'whiteOutline'   => __('WhiteOutline', 'woocommerce-gateway-cardknox'),
        ),
    ),
    $keyPrefix . '_capture' => array(
        $keyTitle       => __($googlePrefix . ' Capture', 'woocommerce-gateway-cardknox'),
        'label'       => __('Capture charge immediately', 'woocommerce-gateway-cardknox'),
        $keyType        => 'checkbox',
        'id'          => 'google_cardknox_capture',
        'description' => __(
            'If the transaction is not immediately captured for Apple Pay, it will require capturing at a later time.',
            'woocommerce-gateway-cardknox'
        ),
        $keyDefault     => 'yes',
        $keyDescTip    => true,
    ),
    $keyPrefix . '_auth_only_order_status' => array(
        $keyTitle       => __('Authorize Only Order Status', 'woocommerce-gateway-cardknox'),
        $keyType        => $selectType,
        'class'       => 'wc-enhanced-select',
        'id'          => 'apple_cardknox_authonly_status',
        'description' => __(
            'Defines the intended order status after an authorization-only transaction for Apple Pay.',
            'woocommerce-gateway-cardknox'
        ),
        $keyDefault     => 'on-hold',
        $keyDescTip    => true,
        $keyOptions     => array(
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
    $keyPrefix . '_applicable_countries' => array(
        $keyTitle       => __('Payment From Applicable Countries', 'woocommerce-gateway-cardknox'),
        $keyType        => $selectType,
        $keyOptions     => array(
            '0'      => __('All Allowed Countries', 'woocommerce-gateway-cardknox'),
            '1'      => __('Specific Countries', 'woocommerce-gateway-cardknox'),
        ),
    ),
    $keyPrefix . '_specific_countries' => array(
        $keyTitle       => __('Payment From Specific Countries', 'woocommerce-gateway-cardknox'),
        $keyType        => 'multiselect',
        $keyOptions     => $countries
    ),
);

$GLOBALS["wc_cardknox_google_pay_settings"] = $wc_cardknox_google_pay_settings;
