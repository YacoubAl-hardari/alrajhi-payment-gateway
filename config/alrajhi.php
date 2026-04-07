<?php

return [
    'environment' => env('ALRAJHI_ENVIRONMENT', 'sandbox'),

    'credentials' => [
        'tranportal_id' => env('ALRAJHI_TRANPORTAL_ID'),
        'tranportal_password' => env('ALRAJHI_TRANPORTAL_PASSWORD'),
        'resource_key' => env('ALRAJHI_RESOURCE_KEY'),
    ],

    'endpoints' => [
        'sandbox' => [
            'base_url' => env('ALRAJHI_BASE_URL', 'https://securepayments.alrajhibank.com.sa'),
            'payment_hosted' => '/pg/payment/hosted.htm',
            'payment_token' => '/pg/payment/tranportal.htm',
            'bin_check' => '/pg/payment/bincheck.htm',
        ],
        'production' => [
            'base_url' => env('ALRAJHI_BASE_URL', 'https://digitalpayments.neoleap.com.sa'),
            'payment_hosted' => '/pg/payment/hosted.htm',
            'payment_token' => '/pg/payment/tranportal.htm',
            'bin_check' => '/pg/payment/bincheck.htm',
        ],
    ],

    'encryption' => [
        'algorithm' => 'AES-256-CBC',
        'iv' => 'PGKEYENCDECIVSPC',
        'url_encode_before_encrypt' => env('ALRAJHI_URL_ENCODE_BEFORE_ENCRYPT', false),
        'url_decode_after_decrypt' => env('ALRAJHI_URL_DECODE_AFTER_DECRYPT', false),
        'retry_without_url_encoding_on_invalid_trandata' => env('ALRAJHI_RETRY_RAW_TRANDATA_ON_INVALID', true),
    ],

    'currency' => [
        'default' => '682',
        'supported' => ['682'],
    ],

    'webhook' => [
        'secret' => env('ALRAJHI_WEBHOOK_SECRET', null),
        'timeout' => 30,
    ],

    'callbacks' => [
        'response_url' => env('ALRAJHI_RESPONSE_URL'),
        'error_url' => env('ALRAJHI_ERROR_URL'),
    ],

    'response' => [
        'strict_mode' => env('ALRAJHI_STRICT_RESPONSE_MODE', false),
        'accept_plain_query_response' => env('ALRAJHI_ACCEPT_QUERY_RESPONSE', true),
        'accept_direct_callback_fields' => env('ALRAJHI_ACCEPT_DIRECT_CALLBACK_FIELDS', true),
        'success_statuses' => [
            '1',
            'success',
            'approved',
            'captured',
            'processing',
            'voided',
        ],
    ],

    'errors' => [
        'prefer_catalog_message' => env('ALRAJHI_PREFER_CATALOG_MESSAGE', true),
        'include_official_message' => env('ALRAJHI_INCLUDE_OFFICIAL_MESSAGE', true),
    ],

    'udf' => [
        'auto_fill_defaults' => env('ALRAJHI_UDF_AUTO_FILL_DEFAULTS', false),
        'capture_auto_set_udf7_r' => env('ALRAJHI_CAPTURE_AUTO_SET_UDF7_R', true),
    ],
];
