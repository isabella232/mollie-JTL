<?php
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/JTLMollie.php';

if(!defined('MOLLIE_KLARNA_MAX_EXPIRY_LIMIT')){
    define('MOLLIE_KLARNA_MAX_EXPIRY_LIMIT', 28);
}

class JTLMollieKlarnaSliceIt extends JTLMollie
{
    const MAX_EXPIRY_DAYS = MOLLIE_KLARNA_MAX_EXPIRY_LIMIT;

    const ALLOW_PAYMENT_BEFORE_ORDER = true;

    const ALLOW_AUTO_STORNO = true;

    const METHOD = \Mollie\Api\Types\PaymentMethod::KLARNA_SLICE_IT;
}
