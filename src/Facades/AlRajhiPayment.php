<?php

namespace AlRajhi\PaymentGateway\Facades;

use Illuminate\Support\Facades\Facade;

class AlRajhiPayment extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'alrajhi-payment';
    }
}
