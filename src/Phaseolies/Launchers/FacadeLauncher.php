<?php

namespace Phaseolies\Launchers;

use Phaseolies\Support\Validation\Sanitizer;
use Phaseolies\Support\UrlGenerator;
use Phaseolies\Support\ViteManager;
use Phaseolies\Support\StringService;
use Phaseolies\Support\Storage\StorageFileService;
use Phaseolies\Support\Storage\Contracts\StorageInterface;
use Phaseolies\Signals\SignalBus;
use Phaseolies\Support\Session;
use Phaseolies\Session\Contracts\SessionInterface;
use Phaseolies\Support\Mail\MailService;
use Phaseolies\Support\Mail\Contracts\MailInterface;
use Phaseolies\Support\ExecutionService;
use Phaseolies\Support\LoggerService;
use Phaseolies\Support\Encryption;
use Phaseolies\Support\Contracts\EncryptionInterface;
use Phaseolies\Support\CookieJar;
use Phaseolies\Http\Support\RequestAbortion;
use Phaseolies\Http\Contracts\RequestAbortionInterface;
use Phaseolies\Http\Response\RedirectResponse;
use Phaseolies\Http\Response;
use Phaseolies\Database\Migration\Schema;
use Phaseolies\Database\Database;
use Phaseolies\Console\Schedule\SchedulePool;
use Phaseolies\Config\Config;
use Phaseolies\Auth\Security\PasswordHashing;
use Phaseolies\Auth\Security\Contracts\HashingInterface;
use Phaseolies\Application;
use Phaseolies\Auth\ActorManager;

class FacadeLauncher extends ServiceLauncher
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // Bind the 'config' service to a singleton instance of the Config class.
        // This allows the application to access configuration settings globally.
        $this->app->singleton('config', Config::class);

        // Bind the session contract to a singleton implementation and retain
        // the existing 'session' service name as an alias.
        $this->app->singleton(SessionInterface::class, Session::class);
        $this->app->alias(SessionInterface::class, 'session');

        // Bind the 'response' service to a singleton instance of the Response class.
        // This is used to generate HTTP responses.
        $this->app->singleton('response', Response::class);

        // Bind the hashing contract to a singleton implementation and retain
        // the existing 'hash' service name as an alias.
        $this->app->singleton(HashingInterface::class, PasswordHashing::class);
        $this->app->alias(HashingInterface::class, 'hash');

        // Bind the 'auth' service to a singleton instance of the GuardManager class.
        // This handles user authentication and authorization.
        $this->app->singleton('auth', ActorManager::class);

        // Bind the encryption contract to a singleton implementation and retain
        // the existing 'crypt' service name as an alias.
        $this->app->singleton(EncryptionInterface::class, Encryption::class);
        $this->app->alias(EncryptionInterface::class, 'crypt');

        // Bind the 'redirect' service to a singleton instance of the RedirectResponse class.
        // This is used to generate HTTP redirect responses.
        $this->app->singleton('redirect', RedirectResponse::class);

        // Bind the request abortion contract to a singleton implementation and retain
        // the existing 'abort' service name as an alias. This is used to abort requests
        // and return error responses.
        $this->app->singleton(RequestAbortionInterface::class, RequestAbortion::class);
        $this->app->alias(RequestAbortionInterface::class, 'abort');

        // Bind the 'mail' service to a singleton instance of the MailService class.
        // This handles sending emails.
        $this->app->singleton(MailInterface::class, MailService::class);
        $this->app->alias(MailInterface::class, 'mail');

        // Bind the 'url' service to a singleton instance of the UrlGenerator class.
        // This generates URLs for the application, using the base URL from the environment
        // or configuration.
        $this->app->singleton('url', function () {
            return new UrlGenerator(env('APP_URL') ?? config('app.url'));
        });

        // Bind the StorageInterface contract to a singleton StorageFileService and
        // keep the legacy 'storage' key as an alias of it, so both resolve to the
        // same instance. This handles file uploads.
        $this->app->singleton(StorageInterface::class, StorageFileService::class);
        $this->app->alias(StorageInterface::class, 'storage');

        // Bind the 'vite' service to a singleton instance of the Vite manager.
        // This resolves dev-server and built frontend assets.
        $this->app->singleton('vite', ViteManager::class);

        // Bind the 'log' service to a singleton instance of the Logger class.
        // This handles user define log.
        $this->app->singleton('log', LoggerService::class);

        // Bind the 'execution' service: a thin accessor over the container's
        // active execution context (request, console command, worker job).
        $this->app->singleton('execution', function () {
            return new ExecutionService($this->app);
        });

        // Bind the 'sanitize' service to a singleton instance of the Sanitizer class.
        // This handles user requested form data.
        $this->app->singleton('sanitize', function () {
            return new Sanitizer([], []);
        });

        // Bind the 'str' service to a singleton instance of the StringService class.
        // This handles string related helper jobs
        $this->app->singleton('str', StringService::class);

        // Bind the 'cookie' service to a singleton instance of the CookieJar class.
        // This handles cookie related jobs
        $this->app->singleton('cookie', CookieJar::class);

        // Bind the 'db' service to a singleton instance of the Database class.
        // This handles db related jobs
        $this->app->singleton('db', Database::class);

        // Bind the 'signal' service to a singleton instance of the SignalBus class.
        // This handles signals that can wait for a database transaction to settle
        $this->app->singleton('signal', SignalBus::class);

        // Bind the 'app' service to a singleton instance of the Application class.
        // This handles app related jobs
        $this->app->singleton('app', Application::class);

        // Bind the 'schema' service to a singleton instance of the Schema class.
        // This handles database schema related jobs
        $this->app->singleton('schema', Schema::class);

        // Bind the 'pool' service to a singleton instance of the SchedulePool class.
        // This handles pool console related jobs
        $this->app->singleton('pool', SchedulePool::class);
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function launch()
    {
        //
    }
}
