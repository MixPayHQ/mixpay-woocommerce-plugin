
<?php

$cache_key              = 'mixpay:settlement_asset_lists';
$settlement_asset_lists = wp_cache_get($cache_key, 'mixpay');

$this->form_fields = apply_filters( 'wc_offline_form_fields', [

    'enabled' => [
        'title'   => __('Enable/Disable', 'wc-mixpay-gateway'),
        'type'    => 'checkbox',
        'label'   => __('Enable MixPay Payment', 'wc-mixpay-gateway'),
        'default' => 'yes',
    ],
    'title' => [
        'title'       => __('Title', 'wc-mixpay-gateway'),
        'type'        => 'text',
        'description' => __('This controls the title which the user sees during checkout.', 'wc-mixpaypayment-gateway'),
        'default'     => __('MixPay Payment', 'wc-mixpay-gateway'),
    ],
    'description' => [
        'title'       => __('Description', 'wc-mixpay-gateway'),
        'type'        => 'textarea',
        'description' => __('This controls the description which the user sees during checkout.', 'wc-mixpay-gateway'),
        'default'     => __('Expand your payment options with MixPay! BTC, ETH, LTC and many more: pay with anything you like!', 'wc-mixpay-gateway'),
    ],
    'payee_uuid' => [
        'title'       => __('Payee Uuid ', 'wc-mixpay-gateway'),
        'type'        => 'text',
        'description' => __('This controls the assets payee uuid.', 'wc-mixpay-gateway'),
    ],
    'settlement_asset_id' => [
        'title'       => __('Settlement Asset ', 'wc-mixpay-gateway'),
        'type'        => 'select',
        'description' => __('This controls the assets received by the merchant.', 'wc-mixpay-gateway'),
        "options"     => $settlement_asset_lists,
    ],
    'instructions' => [
        'title'       => __( 'Instructions', 'wc-gateway-offline' ),
        'type'        => 'textarea',
        'description' => __( '', 'wc-gateway-offline' ),
        'default'     => '',
    ],
    'domain' => [
        'title'       => __('Domain', 'wc-mixpay-gateway'),
        'type'        => 'text',
        'description' => __('Please enter a woocommerce shop domain', 'wc-mixpay-gateway'),
    ],
    'invoice_prefix' => [
        'title'       => __('Invoice Prefix', 'wc-mixpay-gateway'),
        'type'        => 'text',
        'description' => __('Please enter a prefix for your invoice numbers. If you use your nowpayments.io account for multiple stores ensure this prefix is unique.', 'wc-mixpaypayment-gateway'),
        'default'     => 'WC-WORDPRESS-',
    ],
    'debug' => [
        'title'       => __('Debug', 'wc-mixpay-gateway'),
        'type'        => 'checkbox',
        'default'     => 'false',
        'description' => __('(this will Slow down website performance) Send post data to debug', 'wc-mixpay-gateway'),
    ]
] );
