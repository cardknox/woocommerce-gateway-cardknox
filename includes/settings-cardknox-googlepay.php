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
            'no'     => __('No', 'woocommerce-gateway-cardknox'),
            'yes'    => __('Yes', 'woocommerce-gateway-cardknox'),
        ),
        $gkeyDefault => 'no',
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
);

$GLOBALS["wc_cardknox_google_pay_settings"] = $wc_cardknox_google_pay_settings;
