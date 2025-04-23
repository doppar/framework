<?php

namespace Phaseolies\Support\Storage;

use Phaseolies\Support\Storage\LocalFileSystem;
use Phaseolies\Support\Storage\DiskNotFoundException;

class StorageFileService
{
    /**
     * The array of resolved filesystem drivers.
     *
     * @var array
     */
    protected $disks = [];

    /**
     * Get a filesystem instance.
     *
     * @param  string|null  $name
     * @return \Phaseolies\Support\Storage\IFileSystem
     */
    public function disk($name = null)
    {
        $path = $this->getDiskPath($name);

        return match ($name) {
            'local' => app(LocalFileSystem::class, [$path]),
            'public' => app(PublicFileSystem::class, [$path]),
            default => throw new DiskNotFoundException("Unsupported {$name}"),
        };
    }

    /**
     * Return Storage Base Path
     *
     * @param string $disk
     * @return string
     */
    public function getDiskPath(string $disk): string
    {
        $path = config("filesystem.disks.{$disk}.root");

        return $path;
    }
}
