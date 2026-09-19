<?php

namespace App\Providers;

use App\Models\User;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Izinkan akses dokumentasi API publik
        Gate::define('viewApiDocs', function (?User $user) {
            return true;
        });

        // Paksa skema HTTPS jika di lingkungan produksi
        if ($this->app->environment('production') || config('app.env') === 'production') {
            URL::forceScheme('https');
        }

        // Dokumentasikan skema autentikasi HMAC pada OpenAPI / Scramble
        Scramble::afterOpenApiGenerated(function (OpenApi $openApi) {
            $openApi->secure(
                SecurityScheme::apiKey('header', 'X-API-KEY')
                    ->as('ApiKeyAuth')
                    ->setDescription('Identifier Client / ID API Key'),
                SecurityScheme::apiKey('header', 'X-TIMESTAMP')
                    ->as('TimestampAuth')
                    ->setDescription('Unix Epoch Timestamp integer dalam detik (toleransi waktu ±300s)'),
                SecurityScheme::apiKey('header', 'X-SIGNATURE')
                    ->as('SignatureAuth')
                    ->setDescription('Signature HMAC-SHA256: hash_hmac("sha256", "METHOD&PATH&TIMESTAMP&PAYLOAD", secretKey)')
            );
        });
    }
}
