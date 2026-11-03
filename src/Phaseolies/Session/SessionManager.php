<?php

namespace Phaseolies\Session;

use Closure;
use Phaseolies\Session\Contracts\SessionStoreInterface;
use Phaseolies\Session\Exceptions\SessionException;
use Phaseolies\Session\Stores\ArrayStore;
use Phaseolies\Session\Stores\CookieStore;
use Phaseolies\Session\Stores\FileStore;
use Phaseolies\Support\Contracts\EncryptionInterface;

class SessionManager
{
    /**
     * Defaults for every option, so an older config file keeps working.
     *
     * @var array
     */
    protected const DEFAULTS = [
        'driver' => 'file',
        'lifetime' => 120,
        'expire_on_close' => false,
        'files' => null,
        'cookie' => 'doppar_session',
        'path' => '/',
        'domain' => null,
        'secure' => null,
        'http_only' => true,
        'same_site' => 'lax',
        'stateless' => [],
        'lottery' => [2, 100],
        'always_send_xsrf_cookie' => false,
    ];

    /**
     * The resolved store, shared by every session of the process.
     *
     * @var SessionStoreInterface|null
     */
    private static ?SessionStoreInterface $store = null;

    /**
     * Custom drivers registered through extend().
     *
     * @var array<string, Closure>
     */
    private static array $drivers = [];

    /**
     * Prepare the session system for a new application boot.
     *
     * @return void
     */
    public static function initialize(): void
    {
        self::$store = null;
    }

    /**
     * The session configuration with every default filled in.
     *
     * @return array
     */
    public static function config(): array
    {
        $config = self::normalize((array) config('session'));

        $config['files'] ??= base_path('storage/framework/sessions');

        return $config;
    }

    /**
     * Fill in defaults and normalize a session configuration array.
     *
     * @param array $config
     * @return array
     */
    public static function normalize(array $config): array
    {
        $config = array_replace(self::DEFAULTS, $config);

        $config['lifetime'] = max(1, (int) $config['lifetime']);
        $config['cookie'] = self::cookieName((string) $config['cookie']);
        $config['lottery'] = self::lottery($config['lottery']);

        return $config;
    }

    /**
     * Turn the lottery option into [chances, out of].
     *
     * @param mixed $value
     * @return array{0: int, 1: int}
     */
    public static function lottery(mixed $value): array
    {
        if (!is_array($value) || $value === []) {
            return [0, 100];
        }

        $value = array_values($value);
        $total = (int) ($value[1] ?? 100);

        return [max(0, (int) $value[0]), $total > 0 ? $total : 100];
    }

    /**
     * Make a cookie name safe to send and to read back.
     *
     * PHP rewrites dots and spaces in incoming cookie names to underscores, so
     * a name such as "my app.session" is sent under one name and arrives under
     * another, and the session would never be found. A name built from APP_NAME
     * can easily contain either, so normalize it to a portable token.
     *
     * @param string $name
     * @return string
     */
    public static function cookieName(string $name): string
    {
        $name = preg_replace('/[^A-Za-z0-9_\-]+/', '_', trim($name)) ?? '';

        return $name === '' ? self::DEFAULTS['cookie'] : $name;
    }

    /**
     * Get the store sessions are persisted in.
     *
     * @return SessionStoreInterface
     * @throws SessionException
     */
    public static function store(): SessionStoreInterface
    {
        return self::$store ??= self::createStore(self::config());
    }

    /**
     * Replace the store, mainly for tests.
     *
     * @param SessionStoreInterface|null $store
     * @return void
     */
    public static function useStore(?SessionStoreInterface $store): void
    {
        self::$store = $store;
    }

    /**
     * Register a custom session driver.
     *
     * @param string $driver
     * @param Closure(array): SessionStoreInterface $factory
     * @return void
     */
    public static function extend(string $driver, Closure $factory): void
    {
        self::$drivers[$driver] = $factory;
        self::$store = null;
    }

    /**
     * Build the store for the configured driver.
     *
     * @param array $config
     * @return SessionStoreInterface
     * @throws SessionException
     */
    public static function createStore(array $config): SessionStoreInterface
    {
        $driver = (string) $config['driver'];
        $lifetime = $config['lifetime'] * 60;

        if (isset(self::$drivers[$driver])) {
            return (self::$drivers[$driver])($config);
        }

        return match ($driver) {
            'file' => new FileStore($config['files'], $lifetime),
            'cookie' => new CookieStore(app(EncryptionInterface::class), $lifetime),
            'array' => new ArrayStore($lifetime),
            default => throw new SessionException('Unsupported session driver. Supported drivers: file, cookie, array.'),
        };
    }
}
