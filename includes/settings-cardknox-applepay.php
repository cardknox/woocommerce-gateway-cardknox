<?php
if (!defined('ABSPATH')) {
    exit;
}

$textType = 'text';
$selectType = 'select';
$applePrefix = 'Apple Pay';
$keyPrefix = 'applepay';
$keyTitle = 'title';
$keyType = 'type';
$keyOptions = 'options';
$keyDefault = 'default';
$keyDescTip = 'desc_tip';
$buttonPrefix = 'Apple Pay Button';

$wc_cardknox_apple_pay_settings = array(
    $keyPrefix . '_enabled' => array(
        $keyTitle       => __('Enabled', 'woocommerce-gateway-cardknox'),
        $keyType        => $selectType,
        $keyOptions     => array(
            'yes'    => __('Yes', 'woocommerce-gateway-cardknox'),
            'no'     => __('No', 'woocommerce-gateway-cardknox'),
        ),
    ),
    $keyPrefix . '_quickcheckout' => array(
        $keyTitle       => __('Disable Quick Checkout to Cart Page', 'woocommerce-gateway-cardknox'),
        $keyType        => 'checkbox',
        'description' => __(
            'Disable Quick Checkout to Cart Page',
            'woocommerce-gateway-cardknox'
        ),
        $keyDefault    => 'no',
        $keyDescTip    => true,
    ),
    $keyPrefix . '_title' => array(
        $keyTitle       => __('Title', 'woocommerce-gateway-cardknox'),
        $keyType        => $textType,
        $keyDefault     => __('Apple Pay', 'woocommerce-gateway-cardknox'),
    ),
    $keyPrefix . '_merchant_identifier' => array(
        $keyTitle       => __('Merchant Identifier', 'woocommerce-gateway-cardknox'),
        $keyType        => $textType,
        $keyDefault     => __('merchant.cardknox.com', 'woocommerce-gateway-cardknox'),
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
            'whiteOutline'   => __('WhiteOutline', 'woocommerce-gateway-cardknox'),
        ),
    ),
    $keyPrefix . '_button_type' => array(
        $keyTitle       => __($buttonPrefix . ' Type', 'woocommerce-gateway-cardknox'),
        $keyType        => $selectType,
        $keyOptions     => array(
            'buy'            => __('Buy', 'woocommerce-gateway-cardknox'),
            'pay'            => __('Pay', 'woocommerce-gateway-cardknox'),
            'plain'          => __('Plain', 'woocommerce-gateway-cardknox'),
            'order'          => __('Order', 'woocommerce-gateway-cardknox'),
            'donate'         => __('Donate', 'woocommerce-gateway-cardknox'),
            'continue'       => __('Continue', 'woocommerce-gateway-cardknox'),
            'checkout '      => __('Checkout', 'woocommerce-gateway-cardknox'),
        ),
    )
);

$GLOBALS["wc_cardknox_apple_pay_settings"] = $wc_cardknox_apple_pay_settings;
