<?php

namespace Phaseolies\Console\Support;

trait InteractsWithFrontendScaffoldState
{
    /**
     * Build a new frontend scaffold state payload.
     *
     * @param array<string, mixed> $metadata
     * @return array<string, mixed>
     */
    protected function newFrontendScaffoldState(array $metadata = []): array
    {
        return [
            'version' => 1,
            'installed_at' => date('c'),
            'metadata' => $metadata,
            'files' => [],
            'directories' => [],
        ];
    }

    /**
     * Get the manifest path used to track frontend scaffolding changes.
     *
     * @return string
     */
    protected function frontendScaffoldStatePath(): string
    {
        return storage_path('framework/frontend-scaffold.json');
    }

    /**
     * Get the backup directory used to restore overwritten files.
     *
     * @return string
     */
    protected function frontendScaffoldBackupPath(): string
    {
        return storage_path('framework/frontend-backups');
    }

    /**
     * Load the current frontend scaffold state manifest.
     *
     * @return array<string, mixed>|null
     */
    protected function loadFrontendScaffoldState(): ?array
    {
        $path = $this->frontendScaffoldStatePath();

        if (!is_file($path)) {
            return null;
        }

        $state = json_decode((string) file_get_contents($path), true);

        return is_array($state) ? $state : null;
    }

    /**
     * Persist the frontend scaffold state manifest.
     *
     * @param array<string, mixed> $state
     * @return void
     */
    protected function saveFrontendScaffoldState(array $state): void
    {
        $path = $this->frontendScaffoldStatePath();
        $directory = dirname($path);

        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        file_put_contents($path, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
    }

    /**
     * Remove the persisted frontend scaffold state manifest.
     *
     * @return void
     */
    protected function forgetFrontendScaffoldState(): void
    {
        $path = $this->frontendScaffoldStatePath();

        if (is_file($path)) {
            unlink($path);
        }
    }

    /**
     * Record a file mutation so it can be restored later.
     *
     * @param array<string, mixed> $state
     * @param string $path
     * @return void
     */
    protected function rememberFrontendFileMutation(array &$state, string $path): void
    {
        if (isset($state['files'][$path])) {
            return;
        }

        $entry = [
            'existed_before' => is_file($path),
            'backup' => null,
        ];

        if ($entry['existed_before']) {
            $entry['backup'] = $this->backupFrontendFile($path);
        }

        $state['files'][$path] = $entry;
    }

    /**
     * Record a directory lifecycle so uninstall can optionally clean it up.
     *
     * @param array<string, mixed> $state
     * @param string $path
     * @return void
     */
    protected function rememberFrontendDirectoryLifecycle(array &$state, string $path): void
    {
        if (isset($state['directories'][$path])) {
            return;
        }

        $state['directories'][$path] = [
            'existed_before' => is_dir($path),
        ];
    }

    /**
     * Backup a file before it is overwritten.
     *
     * @param string $path
     * @return string
     */
    protected function backupFrontendFile(string $path): string
    {
        $backupDirectory = $this->frontendScaffoldBackupPath();

        if (!is_dir($backupDirectory)) {
            mkdir($backupDirectory, 0777, true);
        }

        $backupPath = $backupDirectory . DIRECTORY_SEPARATOR . sha1($path) . '.bak';
        copy($path, $backupPath);

        return $backupPath;
    }

    /**
     * Restore all tracked files and directories to their previous state.
     *
     * @param array<string, mixed> $state
     * @param bool $removeNodeModules
     * @return void
     */
    protected function restoreFrontendScaffoldState(array $state, bool $removeNodeModules = false): void
    {
        foreach (array_reverse(array_keys($state['files'] ?? [])) as $path) {
            $entry = $state['files'][$path];

            if (!empty($entry['backup']) && is_file($entry['backup'])) {
                $directory = dirname($path);
                if (!is_dir($directory)) {
                    mkdir($directory, 0777, true);
                }

                copy($entry['backup'], $path);
                continue;
            }

            if (is_file($path)) {
                unlink($path);
            }
        }

        foreach (array_reverse(array_keys($state['directories'] ?? [])) as $path) {
            $entry = $state['directories'][$path];

            if (($entry['existed_before'] ?? true) === true || !is_dir($path)) {
                continue;
            }

            if ($path === base_path('node_modules') && !$removeNodeModules) {
                continue;
            }

            $this->deleteDirectoryRecursively($path);
        }

        $this->cleanupFrontendDirectories();
    }

    /**
     * Remove empty client/build directories left after uninstall.
     *
     * @return void
     */
    protected function cleanupFrontendDirectories(): void
    {
        foreach ([
            client_path('js'),
            client_path('css'),
            client_path(),
            base_path('client/js'),
            base_path('client/css'),
            base_path('client'),
            public_path('build'),
        ] as $directory) {
            if (is_dir($directory) && $this->isDirectoryEmpty($directory)) {
                rmdir($directory);
            }
        }
    }

    /**
     * Determine whether a directory has no files left inside it.
     *
     * @param string $directory
     * @return bool
     */
    protected function isDirectoryEmpty(string $directory): bool
    {
        $items = scandir($directory);

        return $items !== false && count($items) === 2;
    }

    /**
     * Delete a directory tree recursively.
     *
     * @param string $directory
     * @return void
     */
    protected function deleteDirectoryRecursively(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $items = scandir($directory) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $item;

            if (is_dir($path)) {
                $this->deleteDirectoryRecursively($path);
            } else {
                unlink($path);
            }
        }

        rmdir($directory);
    }
}
