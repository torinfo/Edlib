<?php

namespace App\Providers;

use App\Apis\AuthApiService;
use App\Apis\ResourceApiService;
use App\ContentVersion;
use App\H5POption;
use App\Http\Middleware\RequestId;
use App\Http\Middleware\TrimStrings;
use App\Libraries\ContentAuthorStorage;
use App\Libraries\H5P\Helper\H5POptionsCache;
use App\Listeners\ResourceEventHandler;
use App\Observers\ContentVersionsObserver;
use App\Observers\H5POptionObserver;
use Cerpus\EdlibResourceKit\Oauth1\Credentials;
use Cerpus\EdlibResourceKit\Oauth1\CredentialStoreInterface;
use Illuminate\Http\Request;
use Illuminate\Log\Logger;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Paginator::useBootstrapThree();

        H5POption::observe(H5POptionObserver::class);
        ContentVersion::observe(ContentVersionsObserver::class);

        TrimStrings::skipWhen(fn (Request $request) => $request->has('lti_message_type'));
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(CredentialStoreInterface::class, fn () => new Credentials(
            config('app.consumer-key'),
            config('app.consumer-secret'),
        ));

        $this->app->singleton(H5POptionsCache::class, function () {
            return new H5POptionsCache();
        });

        $this->app->singleton(ContentAuthorStorage::class);

        $this->app->bind(
            ResourceApiService::class,
            function ($app) {
                return new ResourceApiService();
            }
        );

        $this->app->bind(
            AuthApiService::class,
            function ($app) {
                return new AuthApiService();
            }
        );

        $this->app->when(RequestId::class)
            ->needs(Logger::class)
            ->give(fn () => Log::channel());

        $this->app->when(ResourceEventHandler::class)
            ->needs('$enableEdlib2')
            ->giveConfig('app.enable-edlib2');
    }
}
