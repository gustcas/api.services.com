<?php

namespace App\Providers;

use App\Guards\PassportTokenGuard;
use Illuminate\Auth\RequestGuard;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Auth;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\PassportUserProvider;
use Laravel\Passport\TokenRepository;
use League\OAuth2\Server\ResourceServer;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [];

    public function boot()
    {
        $this->registerPolicies();

        Auth::resolved(function ($auth) {
            $auth->extend('passport', function ($app, $name, array $config) {
                return tap(new RequestGuard(function ($request) use ($config, $app) {
                    return (new PassportTokenGuard(
                        $app->make(ResourceServer::class),
                        new PassportUserProvider(Auth::createUserProvider($config['provider']), $config['provider']),
                        $app->make(TokenRepository::class),
                        $app->make(ClientRepository::class),
                        $app->make('encrypter')
                    ))->user($request);
                }, $app['request']), function ($guard) use ($app) {
                    $app->refresh('request', $guard, 'setRequest');
                });
            });
        });
    }
}
