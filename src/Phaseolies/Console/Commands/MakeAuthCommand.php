<?php

namespace Phaseolies\Console\Commands;

use Phaseolies\Console\Schedule\Command;
use RuntimeException;

class MakeAuthCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $name = 'make:auth';

    /**
     * The description of the console command.
     *
     * @var string
     */
    protected $description = 'Generate authentication system';

    /**
     * Execute the console command.
     *
     * @return int
     */
    protected function handle(): int
    {
        return $this->executeWithTiming(function () {
            if ($this->authFilesExist()) {
                $this->displayWarning('Authentication files already exist!');
                $this->line('If you want to regenerate them, please delete these files first:');
                $this->newLine();
                $this->listExistingAuthFiles();
                return Command::FAILURE;
            }

            $this->createDirectories();
            $this->generateControllers();
            $this->generateViews();
            $this->appendRoutes();

            $this->displaySuccess('Authentication scaffolding generated successfully');
            $this->displayInfo('🎉 Generated Files:');
            $this->line('- Controllers: Login, Register, Home, Profile, 2FA');
            $this->line('- Views: Login, Register, Home, Profile, Layout, 2FA');

            return Command::SUCCESS;
        });
    }

    /**
     * Create required directories
     */
    protected function createDirectories(): void
    {
        $paths = [
            base_path('app/Http/Controllers/Auth/'),
            base_path('resources/views/auth/'),
            base_path('resources/views/layouts/'),
        ];

        foreach ($paths as $path) {
            if (!is_dir($path)) {
                mkdir($path, 0755, true);
            }
        }
    }

    /**
     * Generate authentication controllers
     */
    protected function generateControllers(): void
    {
        $this->createFile(
            base_path('app/Http/Controllers/Auth/LoginController.php'),
            $this->getStubContent('LoginController.stub')
        );

        $this->createFile(
            base_path('app/Http/Controllers/Auth/RegisterController.php'),
            $this->getStubContent('RegisterController.stub')
        );

        $this->createFile(
            base_path('app/Http/Controllers/Auth/TwoFactorAuthController.php'),
            $this->getStubContent('TwoFactorAuthController.stub')
        );

        $this->createFile(
            base_path('app/Http/Controllers/HomeController.php'),
            $this->getStubContent('HomeController.stub')
        );

        $this->createFile(
            base_path('app/Http/Controllers/ProfileController.php'),
            $this->getStubContent('ProfileController.stub')
        );
    }

    /**
     * Generate authentication views
     */
    protected function generateViews(): void
    {
        $views = [
            'auth/login.blade.php' => 'auth/login.stub',
            'auth/register.blade.php' => 'auth/register.stub',
            'auth/2fa.blade.php' => 'auth/2fa.stub',
            'layouts/app.blade.php' => 'layouts/app.stub',
            'home.blade.php' => 'home.stub',
            'profile.blade.php' => 'profile.stub'
        ];

        foreach ($views as $destination => $stubFile) {
            $destinationPath = base_path('resources/views/' . $destination);
            $content = $this->getStubContent($stubFile);
            $this->createFile($destinationPath, $content);
        }
    }

    /**
     * Append auth routes to web.php
     */
    protected function appendRoutes(): void
    {
        $routesPath = base_path('routes/web.php');

        if (!file_exists($routesPath)) {
            throw new RuntimeException('Routes file not found: ' . $routesPath);
        }

        $routesContent = $this->getStubContent('routes.stub');

        file_put_contents($routesPath, $routesContent, FILE_APPEND);
    }

    /**
     * Create a file with the given content
     */
    protected function createFile(string $path, string $content): void
    {
        if (!file_exists($path)) {
            file_put_contents($path, $content);
        }
    }

    /**
     * Get the contents of a stub file
     */
    protected function getStubContent(string $stubName): string
    {
        $stubPath = __DIR__ . '/stubs/auth/' . $stubName;

        if (!file_exists($stubPath)) {
            throw new RuntimeException('Stub not found: ' . $stubPath);
        }

        return file_get_contents($stubPath);
    }

    /**
     * Check if any authentication files already exist
     */
    protected function authFilesExist(): bool
    {
        $filesToCheck = [
            base_path('app/Http/Controllers/Auth/LoginController.php'),
            base_path('app/Http/Controllers/Auth/RegisterController.php'),
            base_path('app/Http/Controllers/Auth/TwoFactorAuthController.php'),
            base_path('app/Http/Controllers/HomeController.php'),
            base_path('app/Http/Controllers/ProfileController.php'),
            base_path('resources/views/auth/login.blade.php'),
            base_path('resources/views/auth/2fa.blade.php'),
            base_path('resources/views/auth/register.blade.php'),
            base_path('resources/views/layouts/app.blade.php'),
            base_path('resources/views/home.blade.php'),
            base_path('resources/views/profile.blade.php'),
        ];

        foreach ($filesToCheck as $file) {
            if (file_exists($file)) {
                return true;
            }
        }

        return false;
    }

    /**
     * List existing authentication files
     */
    protected function listExistingAuthFiles(): void
    {
        $files = [
            'Controllers' => [
                base_path('app/Http/Controllers/Auth/LoginController.php'),
                base_path('app/Http/Controllers/Auth/RegisterController.php'),
                base_path('app/Http/Controllers/Auth/TwoFactorAuthController.php'),
                base_path('app/Http/Controllers/HomeController.php'),
                base_path('app/Http/Controllers/ProfileController.php'),
            ],
            'Views' => [
                base_path('resources/views/auth/login.blade.php'),
                base_path('resources/views/auth/register.blade.php'),
                base_path('resources/views/auth/2fa.blade.php'),
                base_path('resources/views/layouts/app.blade.php'),
                base_path('resources/views/home.blade.php'),
                base_path('resources/views/profile.blade.php'),
            ]
        ];

        foreach ($files as $category => $paths) {
            $this->line("<fg=yellow>{$category}:</>");
            foreach ($paths as $path) {
                if (file_exists($path)) {
                    $this->line('- ' . str_replace(base_path(), '', $path));
                }
            }
            $this->newLine();
        }
    }
}
