<?php

namespace Phaseolies\Support\Storage;

use Phaseolies\Support\File;

interface IFileSystem
{
    /**
     * Store a file at the given path.
     *
     * @param string $path
     * @param File $file
     * @param string|null $fileName
     * @return bool
     */
    public function store(string $path, File $file, ?string $fileName = null): bool;

    /**
     * Get the filesystem base path.
     *
     * @return string
     */
    public function storageBasePath(): string;

    /**
     * Get the absolute path for a stored file.
     *
     * @param string $path
     * @return string|null
     */
    public function get(string $path): ?string;

    /**
     * Read the contents of a stored file.
     *
     * @param string $path
     * @return string
     */
    public function content(string $path): string;

    /**
     * Delete one or more stored files.
     *
     * @param string|array $path
     * @return bool
     */
    public function delete(string|array $path): bool;
}
