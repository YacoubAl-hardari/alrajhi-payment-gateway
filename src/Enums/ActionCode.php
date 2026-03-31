<?php

namespace AlRajhi\PaymentGateway\Enums;

enum ActionCode: string
{
    case PURCHASE = '1';
    case AUTHORIZE = '4';
}
