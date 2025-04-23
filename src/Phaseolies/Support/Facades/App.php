<?php

namespace Phaseolies\Support\Facades;

/**
 * @method static \Phaseolies\Application langPath($path = ''): string
 * @method static \Phaseolies\Application setBasePath(string $basePath): self
 * @method static \Phaseolies\Application getBasePath(): string
 * @method static \Phaseolies\Application resourcesPath($path = ''): string
 * @method static \Phaseolies\Application bootstrapPath($path = ''): string
 * @method static \Phaseolies\Application databasePath($path = ''): string
 * @method static \Phaseolies\Application publicPath($path = ''): string
 * @method static \Phaseolies\Application storagePath($path = ''): string
 * @method static \Phaseolies\Application appPath(): string
 * @method static \Phaseolies\Application basePath(): string
 * @method static \Phaseolies\Application configPath($path = ''): string
 * @method static \Phaseolies\Application runningInConsole(): bool
 * @method static \Phaseolies\Application hasBeenBootstrapped(): bool
 * @method static \Phaseolies\Application isBooted(): bool
 * @method static \Phaseolies\Application make($abstract, array $parameters = [])
 * @method static \Phaseolies\Application getLocale(): string
 * @method static \Phaseolies\Application currentLocale(): string
 * @method static \Phaseolies\Application getFallbackLocale(): string
 * @method static \Phaseolies\Application setLocale($locale): void
 * @method static \Phaseolies\Application setFallbackLocale($fallbackLocale)
 * @method static \Phaseolies\Application isLocale($locale): bool
 * @see \Phaseolies\Application
 */

use Phaseolies\Facade\BaseFacade;

class App extends BaseFacade
{
    protected static function getFacadeAccessor()
    {
        return 'app';
    }
}
