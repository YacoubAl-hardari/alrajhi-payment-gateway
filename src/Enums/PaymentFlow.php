<?php

namespace AlRajhi\PaymentGateway\Enums;

enum PaymentFlow: string
{
    case BANK_HOSTED = 'bank_hosted';
    case FASTER_CHECKOUT = 'faster_checkout';
    case IFRAME = 'iframe';
    case APPLE_PAY = 'apple_pay';
}
