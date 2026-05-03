<?php

namespace App\Guards;

use Laravel\Passport\Guards\TokenGuard;
use Nyholm\Psr7\Factory\Psr17Factory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;

class PassportTokenGuard extends TokenGuard
{
    protected function getPsrRequestViaBearerToken($request)
    {
        try {
            $psr = (new PsrHttpFactory(
                new Psr17Factory,
                new Psr17Factory,
                new Psr17Factory,
                new Psr17Factory
            ))->createRequest($request);
        } catch (\RuntimeException $e) {
            // On Windows with php artisan serve, uploaded file tmp_name can be
            // an invalid path. Strip files from the request and retry.
            $bare = $request->duplicate();
            $bare->files->replace([]);

            $psr = (new PsrHttpFactory(
                new Psr17Factory,
                new Psr17Factory,
                new Psr17Factory,
                new Psr17Factory
            ))->createRequest($bare);
        }

        try {
            return $this->server->validateAuthenticatedRequest($psr);
        } catch (\League\OAuth2\Server\Exception\OAuthServerException $e) {
            $request->headers->set('Authorization', '', true);
            app(\Illuminate\Contracts\Debug\ExceptionHandler::class)->report($e);
        }
    }
}
