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
        $gkeyTitle       => __('Enabled', 'woo-cardknox-gateway'),
        $gkeyType        => $gselectType,
        $gkeyOptions     => array(
            'no'     => __('No', 'woo-cardknox-gateway'),
            'yes'    => __('Yes', 'woo-cardknox-gateway'),
        ),
        $gkeyDefault => 'no',
    ),
    $gkeyPrefix . '_quickcheckout' => array(
        $gkeyTitle       => __('Disable Quick Checkout to Cart Page', 'woo-cardknox-gateway'),
        $gkeyType        => 'checkbox',
        'description' => __(
            'Disable Quick Checkout to Cart Page',
            'woo-cardknox-gateway'
        ),
        $gkeyDefault    => 'no',
        $gkeyDescTip    => true,
    ),
    $gkeyPrefix . '_title' => array(
        $gkeyTitle       => __('Title', 'woo-cardknox-gateway'),
        $gkeyType        => $gtextType,
        $gkeyDefault     => __('Google Pay', 'woo-cardknox-gateway'),
    ),
    $gkeyPrefix . '_merchant_name' => array(
        $gkeyTitle       => __('Merchant Name', 'woo-cardknox-gateway'),
        $gkeyType        => $gtextType,
        $gkeyDefault     => __('Example Merchant', 'woo-cardknox-gateway'),
    ),
    $gkeyPrefix . '_environment' => array(
        $gkeyTitle       => __('Environment', 'woo-cardknox-gateway'),
        $gkeyType        => $gselectType,
        $gkeyOptions     => array(
            'TEST'          => __('Test', 'woo-cardknox-gateway'),
            'PRODUCTION'    => __('Production', 'woo-cardknox-gateway'),
        ),
    ),
    $gkeyPrefix . '_button_style' => array(
        $gkeyTitle       => __($gbuttonPrefix . 'Style', 'woo-cardknox-gateway'),
        $gkeyType        => $gselectType,
        $gkeyOptions     => array(
            'black'          => __('Black', 'woo-cardknox-gateway'),
            'white'          => __('White', 'woo-cardknox-gateway'),
        ),
    ),
);

$GLOBALS["wc_cardknox_google_pay_settings"] = $wc_cardknox_google_pay_settings;
