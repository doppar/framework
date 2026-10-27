<?php

namespace Phaseolies\Support\Storage;

use Phaseolies\Support\Storage\Contracts\StorageInterface;

class StorageFileService implements StorageInterface
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
     * @param string $name
     * @return IFileSystem
     */
    public function disk(string $name): IFileSystem
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
     * @return string|null
     */
    public function getDiskPath(string $disk): ?string
    {
        return config("filesystem.disks.{$disk}.root");
    }
}
