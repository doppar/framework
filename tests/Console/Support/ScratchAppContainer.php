<?php

namespace Tests\Console\Support;

use Tests\Support\MockContainer;

/**
 * A container whose base and storage paths point at a scratch directory, so
 * code that calls base_path() / storage_path() works on it.
 */
final class ScratchAppContainer extends MockContainer
{
    private string $root = '';

    public function setRoot(string $root): self
    {
        $this->root = $root;
        $this->basePath = $root;

        return $this;
    }

    public function storagePath(string $path = ''): string
    {
        $base = $this->root . DIRECTORY_SEPARATOR . 'storage';

        return $path === '' ? $base : $base . DIRECTORY_SEPARATOR . ltrim($path, '/\\');
    }
}
