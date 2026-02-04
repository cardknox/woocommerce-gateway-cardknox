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
$fileType = 'file';

$wc_cardknox_apple_pay_settings = array(

    $keyPrefix . '_certificate' => array(
        'title'    => __('Apple Certificate', 'woocommerce-gateway-cardknox'),
        'type'     => $fileType,
        'css'      => 'position: absolute;z-index: 999;min-height: 44px;opacity: 1;padding-left: 160px;top: 30px; width: 700px;',
        'custom_attributes' => array(
            'class' => 'custom-file-upload',
        ),
        'description' => '
            <div class="upload-btn-wrapper">
                <button class="btn" style=" border: 1px solid #58b5d8; color: #58b5d8; padding: 8px 10px; font-weight: 600; border-radius: 4px; cursor: pointer;">Choose Certificate</button>
            </div>
        ',
    ),    
    $keyPrefix . '_enabled' => array(
        $keyTitle       => __('Enabled', 'woocommerce-gateway-cardknox'),
        $keyType        => $selectType,
        $keyOptions     => array(
            'no'     => __('No', 'woocommerce-gateway-cardknox'),
            'yes'    => __('Yes', 'woocommerce-gateway-cardknox'),
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
