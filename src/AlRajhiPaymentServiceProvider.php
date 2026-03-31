<?php

namespace AlRajhi\PaymentGateway;

use AlRajhi\PaymentGateway\Contracts\ArrayValueResolverContract;
use AlRajhi\PaymentGateway\Contracts\GatewayBodyParserContract;
use AlRajhi\PaymentGateway\Contracts\PaymentPayloadBuilderContract;
use AlRajhi\PaymentGateway\Http\Clients\PaymentGatewayClient;
use AlRajhi\PaymentGateway\Support\ArrayValueResolver;
use AlRajhi\PaymentGateway\Support\GatewayBodyParser;
use AlRajhi\PaymentGateway\Support\PaymentPayloadBuilder;
use Illuminate\Support\ServiceProvider;

class AlRajhiPaymentServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/alrajhi.php' => config_path('alrajhi.php'),
        ], 'alrajhi-config');

        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/alrajhi.php', 'alrajhi');

        $this->app->bind(ArrayValueResolverContract::class, ArrayValueResolver::class);
        $this->app->bind(GatewayBodyParserContract::class, GatewayBodyParser::class);
        $this->app->bind(PaymentPayloadBuilderContract::class, PaymentPayloadBuilder::class);

        $this->app->singleton('alrajhi-payment', function ($app) {
            return new AlRajhiPaymentManager($app->make(PaymentGatewayClient::class));
        });
    }
}
