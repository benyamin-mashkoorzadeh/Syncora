<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

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
        Model::shouldBeStrict(! app()->isProduction());

        Password::defaults(fn (): Password => Password::min(8)->mixedCase()->numbers());

        RateLimiter::for('login', function (Request $request): Limit {
            $key = Str::transliterate(Str::lower($request->string('email')).'|'.$request->ip());

            return Limit::perMinute(5)->by($key);
        });

        RateLimiter::for('demo-login', fn (Request $request): Limit => Limit::perMinute(20)
            ->by($request->ip()));

        RateLimiter::for('password-reset', function (Request $request): Limit {
            $key = Str::transliterate(Str::lower($request->string('email')).'|'.$request->ip());

            return Limit::perMinute(3)->by($key);
        });

        RateLimiter::for('verification', fn (Request $request): Limit => Limit::perMinute(3)
            ->by(($request->user()?->getAuthIdentifier() ?? 'guest').'|'.$request->ip()));

        ResetPassword::createUrlUsing(fn (object $notifiable, string $token): string => rtrim((string) config('app.frontend_url'), '/').'/reset-password?'.http_build_query([
            'token' => $token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]));

        VerifyEmail::createUrlUsing(function (object $notifiable): string {
            $id = $notifiable->getKey();
            $hash = sha1($notifiable->getEmailForVerification());
            $signedUrl = URL::temporarySignedRoute(
                'api.v1.auth.verification.verify',
                now()->addMinutes((int) config('auth.verification.expire', 60)),
                ['id' => $id, 'hash' => $hash],
                absolute: false,
            );
            parse_str((string) parse_url($signedUrl, PHP_URL_QUERY), $signatureQuery);

            return rtrim((string) config('app.frontend_url'), '/').'/verify-email?'.http_build_query([
                'id' => $id,
                'hash' => $hash,
                'expires' => $signatureQuery['expires'],
                'signature' => $signatureQuery['signature'],
            ]);
        });
    }
}
