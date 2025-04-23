<?php

namespace Phaseolies\Support\Storage;

use Phaseolies\Support\File;

class LocalFileSystem extends FileSystem implements IFileSystem
{
    /**
     * Store file as user defined path
     *
     * @param string $path
     * @param File $file
     * @param string|null $fileName
     * @return bool
     */
    public function store(string $path, File $file, ?string $fileName = null): bool
    {
        return $this->put($this->isDirectoryExists($path), $file, $fileName);
    }

    /**
     * return project base path in os
     *
     * @return string
     */
    public function storeageBasePath(): string
    {
        return $this->filePath;
    }

    /**
     * Get the contents of a file.
     *
     * @param string $path
     * @return string|null The file path, or null if the file does not exist.
     */
    public function get(string $path): ?string
    {
        $fullPath = $this->filePath . '/' . $path;

        if ($this->isFile($fullPath)) {
            return $fullPath;
        }

        return null;
    }

    /**
     * Get the contents of a file.
     *
     * @param  string  $path
     * @return string
     *
     * @throws \Phaseolies\Support\Storage\FileNotFoundException
     */
    public function content($path)
    {
        $fullPath = $this->filePath . '/' . $path;

        if ($this->isFile($fullPath)) {
            $file = new \SplFileObject($fullPath, 'r');
            $contents = '';
            while (!$file->eof()) {
                $contents .= $file->fgets();
            }
            return $contents;
        }

        throw new FileNotFoundException("File does not exist at path {$fullPath}.");
    }

    /**
     * Delete one or more files.
     *
     * @param string|array $path The path(s) to the file(s) to delete.
     * @return bool True if all files were successfully deleted, false otherwise.
     */
    public function delete(string|array $path): bool
    {
        $success = true;

        foreach ((array) $path as $filePath) {
            $fullPath = $this->filePath . '/' . $filePath;

            if (file_exists($fullPath)) {
                if (!unlink($fullPath)) {
                    $success = false;
                }
            } else {
                $success = false;
            }
        }

        return $success;
    }
}
