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
        'title'    => __('Apple Certificate', 'woo-cardknox-gateway'),
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
        $keyTitle       => __('Enabled', 'woo-cardknox-gateway'),
        $keyType        => $selectType,
        $keyOptions     => array(
            'no'     => __('No', 'woo-cardknox-gateway'),
            'yes'    => __('Yes', 'woo-cardknox-gateway'),
        ),
    ),
    $keyPrefix . '_quickcheckout' => array(
        $keyTitle       => __('Disable Quick Checkout to Cart Page', 'woo-cardknox-gateway'),
        $keyType        => 'checkbox',
        'description' => __(
            'Disable Quick Checkout to Cart Page',
            'woo-cardknox-gateway'
        ),
        $keyDefault    => 'no',
        $keyDescTip    => true,
    ),
    $keyPrefix . '_title' => array(
        $keyTitle       => __('Title', 'woo-cardknox-gateway'),
        $keyType        => $textType,
        $keyDefault     => __('Apple Pay', 'woo-cardknox-gateway'),
    ),
    $keyPrefix . '_merchant_identifier' => array(
        $keyTitle       => __('Merchant Identifier', 'woo-cardknox-gateway'),
        $keyType        => $textType,
        $keyDefault     => __('merchant.cardknox.com', 'woo-cardknox-gateway'),
    ),
    $keyPrefix . '_environment' => array(
        $keyTitle       => __('Environment', 'woo-cardknox-gateway'),
        $keyType        => $selectType,
        $keyOptions     => array(
            'TEST'          => __('Test', 'woo-cardknox-gateway'),
            'PRODUCTION'    => __('Production', 'woo-cardknox-gateway'),
        ),
    ),
    $keyPrefix . '_button_style' => array(
        $keyTitle       => __($buttonPrefix . ' Style', 'woo-cardknox-gateway'),
        $keyType        => $selectType,
        $keyOptions     => array(
            'black'          => __('Black', 'woo-cardknox-gateway'),
            'white'          => __('White', 'woo-cardknox-gateway'),
            'whiteOutline'   => __('WhiteOutline', 'woo-cardknox-gateway'),
        ),
    ),
    $keyPrefix . '_button_type' => array(
        $keyTitle       => __($buttonPrefix . ' Type', 'woo-cardknox-gateway'),
        $keyType        => $selectType,
        $keyOptions     => array(
            'buy'            => __('Buy', 'woo-cardknox-gateway'),
            'pay'            => __('Pay', 'woo-cardknox-gateway'),
            'plain'          => __('Plain', 'woo-cardknox-gateway'),
            'order'          => __('Order', 'woo-cardknox-gateway'),
            'donate'         => __('Donate', 'woo-cardknox-gateway'),
            'continue'       => __('Continue', 'woo-cardknox-gateway'),
            'checkout '      => __('Checkout', 'woo-cardknox-gateway'),
        ),
    )
);

$GLOBALS["wc_cardknox_apple_pay_settings"] = $wc_cardknox_apple_pay_settings;
