<?php

namespace Phaseolies\Support\Storage\Contracts;

use Phaseolies\Support\Storage\IFileSystem;

interface StorageInterface
{
    /**
     * Get a filesystem disk by name.
     *
     * @param string $name
     * @return IFileSystem
     */
    public function disk(string $name): IFileSystem;

    /**
     * Get the configured root path for a disk.
     *
     * @param string $disk
     * @return string|null
     */
    public function getDiskPath(string $disk): ?string;
}
