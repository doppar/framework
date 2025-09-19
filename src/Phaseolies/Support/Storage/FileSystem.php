<?php

namespace Phaseolies\Support\Storage;

use Phaseolies\Support\File;

class FileSystem
{
    /**
     * Full File path directory without filename
     *
     * @var string
     */
    protected string $filePath;

    /**
     * Custom FileName
     *
     * @var string
     */
    protected string $fileName;

    public function __construct(string $filePath, string $fileName = '')
    {
        $this->filePath = $filePath;
        $this->fileName = $fileName;
    }

    /**
     * move file in local file system
     *
     * @param string $path
     * @param File $file
     * @param string|null $fileName
     * @return bool
     */
    public function put(string $path, File $file, ?string $fileName = null): bool
    {
        $fileName = $fileName ?? $this->getFileName($file);
        $stream = fopen($file->getClientOriginalPath(), 'rb');

        if (is_resource($stream)) {
            $this->writeStream($path, $fileName, $stream);
            return true;
        }

        return false;
    }

    /**
     * Undocumented function
     *
     * @param File $file
     * @return string
     */
    public function getFileName(File $file): string
    {
        if (is_null($this->fileName) || empty($this->fileName)) {
            return $file->getClientOriginalName();
        }

        $fileType = explode('/', $file->getClientOriginalType())[1];

        return $this->fileName . '.' . $fileType;
    }

    /**
     * Writes data from a stream to a file at the specified path
     *
     * @param string $path The directory path where the file will be saved.
     * @param string $name The name of the file to be created.
     * @param resource $stream The input stream to read data from.
     * @return bool
     */
    public function writeStream(string $path, string $name, $stream): bool
    {
        $realPath = $this->destinationFile($path, $name);

        // Open the output file in write-binary mode
        $outputStream = fopen($realPath, 'wb');

        // Determine the chunk size based on the stream size
        $streamSize = $this->getStreamSize($stream);

        // Use 5 MB chunks for files > 50 MB, otherwise 4 KB
        $chunkSize = ($streamSize > 50 * 1024 * 1024) ? 5 * 1024 * 1024 : 4096;

        while (!feof($stream)) {
            $buffer = fread($stream, $chunkSize);
            fwrite($outputStream, $buffer);
        }

        fclose($stream);
        fclose($outputStream);

        return true;
    }

    /**
     * Retrieves the size of a given stream.
     *
     * @param resource $stream The stream resource to get the size of.
     * @return int The size of the stream in bytes.
     */
    private function getStreamSize($stream)
    {
        $stat = fstat($stream);

        return $stat['size'];
    }

    /**
     * Path is already exist or not
     *
     * @param string $path
     * @return string
     */
    public function isDirectoryExists(string $path): string
    {
        $realPath = $this->storeageBasePath();

        if (! $this->isDirectory($realPath . '/' . trim($path, '/'))) {
            $this->makeDirectory($realPath . '/' . $path, 0755, true);
        }
        return $path;
    }

    /**
     * Determine if the given path is a directory.
     *
     * @param  string  $directory
     * @return bool
     */
    public function isDirectory(string $directory): bool
    {
        return is_dir($directory);
    }

    /**
     * create the destination full path according in os
     *
     * @param string $path
     * @return string
     */
    public function destinationFile(string $path, $fileName): string
    {
        return $this->filePath . '/' . $path . '/' . $fileName;
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
     * Determine if the given path is a file.
     *
     * @param string $file
     * @return bool
     */
    public function isFile($file)
    {
        return is_file($file);
    }

    /**
     * Create a directory.
     *
     * @param string $path
     * @param int $mode
     * @param bool $recursive
     * @param bool $force
     * @return bool
     */
    public function makeDirectory(string $path, $mode = 0755, bool $recursive = false, bool $force = false): bool
    {
        if ($force) {
            return @mkdir($path, $mode, $recursive);
        }

        return mkdir($path, $mode, $recursive);
    }
}
