<?php

namespace Phaseolies\Support;

use Phaseolies\Support\Storage\PublicFileSystem;
use Phaseolies\Support\Storage\FileNotFoundException;
use Phaseolies\Support\Facades\Storage;

class File extends \SplFileInfo
{
    protected array $file;

    /**
     * Constructor that initializes the File object with the given file data.
     *
     * @param array $file The uploaded file's data from the $_FILES array.
     */
    public function __construct(\SplFileInfo|string|array $file, bool $checkPath = true)
    {
        if (is_string($file)) {
            if ($checkPath && !is_file($file)) {
                throw new FileNotFoundException("File not found: " . $file);
            }

            $this->file = [
                'name' => basename($file),
                'type' => mime_content_type($file),
                'tmp_name' => $file,
                'error' => UPLOAD_ERR_OK,
                'size' => filesize($file),
            ];
            parent::__construct($file);
        } else {
            $this->file = $file;
        }
    }

    /**
     * Gets the original name of the uploaded file.
     *
     * @return string The name of the file.
     */
    public function getClientOriginalName(): string
    {
        return $this->file['name'] ?? '';
    }

    /**
     * Gets the temporary path where the file is stored on the server.
     *
     * @return string The temporary file path.
     */
    public function getClientOriginalPath(): string
    {
        return $this->file['tmp_name'] ?? '';
    }

    /**
     * Gets the MIME type of the uploaded file.
     *
     * @return string The MIME type of the file (e.g., "image/jpeg").
     */
    public function getClientOriginalType(): string
    {
        return $this->file['type'] ?? '';
    }

    /**
     * Gets the size of the uploaded file in bytes.
     *
     * @return int The file size in bytes.
     */
    public function getClientOriginalSize(): int
    {
        return $this->file['size'] ?? 0;
    }

    /**
     * Gets the extension of the original file.
     *
     * @return string The file extension (e.g., "jpg", "png").
     */
    public function getClientOriginalExtension(): string
    {
        $name = $this->getClientOriginalName();
        $extension = pathinfo($name, PATHINFO_EXTENSION);
        return strtolower($extension);
    }

    /**
     * Generate a unique name for the uploaded file.
     *
     * @param string|null $extension Optional extension to use. If null, the original extension is used.
     * @return string The unique filename.
     */
    public function generateUniqueName(?string $extension = null): string
    {
        $extension = $extension ?? $this->getClientOriginalExtension();
        return uniqid() . '.' . $extension;
    }

    /**
     * Checks if the uploaded file is of a specific MIME type.
     *
     * @param string|array $mimeType The MIME type(s) to check against.
     * @return bool True if the file's MIME type matches, false otherwise.
     */
    public function isMimeType(string|array $mimeType): bool
    {
        $fileMimeType = $this->getClientOriginalType();

        if (is_array($mimeType)) {
            return in_array($fileMimeType, $mimeType);
        }

        return $fileMimeType === $mimeType;
    }

    /**
     * Check if the uploaded file is an image.
     *
     * @return bool True if the file is an image, false otherwise.
     */
    public function isImage(): bool
    {
        return strpos($this->getClientOriginalType(), 'image/') === 0;
    }

    /**
     * Check if the uploaded file is a video.
     *
     * @return bool True if the file is a video, false otherwise.
     */
    public function isVideo(): bool
    {
        return strpos($this->getClientOriginalType(), 'video/') === 0;
    }

    /**
     * Check if the uploaded file is a document.
     *
     * @return bool True if the file is a document, false otherwise.
     */
    public function isDocument(): bool
    {
        $documentMimes = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'text/plain',
            'text/csv'
        ];

        return $this->isMimeType($documentMimes);
    }

    /**
     * Moves the uploaded file to a new location.
     *
     * @param string $destination The destination path to move the file to.
     * @param string|null $fileName Optional filename to use. If null, the original filename is used.
     * @return bool True if the file was moved successfully, false otherwise.
     */
    public function move(string $destination, ?string $fileName = null): bool
    {
        if (!$this->isValid()) {
            return false;
        }

        $fileName = $fileName ?? $this->getClientOriginalName();
        $destinationPath = rtrim($destination, '/') . '/' . $fileName;

        if (move_uploaded_file($this->getClientOriginalPath(), $destinationPath)) {
            return true;
        }

        return false;
    }

    /**
     * Get the file's mime type by using the fileinfo extension.
     *
     * @return string|false The file's mime type or false on failure.
     */
    public function getMimeTypeByFileInfo(): string|false
    {
        if (!$this->isValid()) {
            return false;
        }
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $this->getClientOriginalPath());
        finfo_close($finfo);

        return $mime;
    }

    /**
     * Gets the error code of the uploaded file.
     *
     * @return int The error code of the file upload process.
     */
    public function getError(): int
    {
        return $this->file['error'] ?? 0;
    }

    /**
     * Checks if the file was uploaded successfully.
     *
     * @return bool True if the file upload was successful, otherwise false.
     */
    public function isValid(): bool
    {
        return $this->getError() === UPLOAD_ERR_OK;
    }

    /**
     * Store image in public or private file system
     *
     * @param string $path
     * @param string $options
     * @return boolean
     */
    public function store(string $path, string $options = ''): bool
    {
        if (is_null($options) || empty($options)) {
            $options = 'public';
        }

        $relativePath = Storage::getDiskPath($options);

        return app(PublicFileSystem::class, [$relativePath])->store($path, $this);
    }

    /**
     * StoreAs function store the file with filename
     *
     * @param callable $callback
     * @param string $path
     * @param string $fileName
     * @return boolean
     */
    public function storeAs(callable $callback, string $path, string $fileName = ''): bool
    {
        if (! is_callable($callback)) {
            return false;
        }

        $shouldStore = $callback($this);

        if (! $shouldStore) {
            return false;
        }

        $relativePath = Storage::getDiskPath('public');

        return app(PublicFileSystem::class, [$relativePath, $fileName])->store($path, $this);
    }

    /**
     * Checks if the file is readable.
     *
     * @return bool True if the file is readable, false otherwise.
     */
    public function isReadable(): bool
    {
        return is_readable($this->getClientOriginalPath());
    }

    /**
     * Gets the last modification time of the uploaded file.
     *
     * @return int|false The last modification time as a Unix timestamp, or false on error.
     */
    public function getMTime(): int|false
    {
        if (!$this->isValid()) {
            return false;
        }

        return filemtime($this->getClientOriginalPath());
    }

    /**
     * Automatically sets the Last-Modified header according the file modification date.
     *
     * @return $this
     */
    public function setAutoLastModified(): static
    {
        if ($mtime = $this->getMTime()) {
            // Assuming you have a setLastModified method in your class or a related trait/helper.
            // If not, you will need to implement it according to your application's
            // request/response handling. Here's a placeholder example:
            $this->setLastModifiedHeader($mtime);
        }

        return $this;
    }

    /**
     * Placeholder method to set the Last-Modified header.
     * Replace this with your actual implementation.
     *
     * @param int $timestamp The Unix timestamp of the last modification time.
     */
    protected function setLastModifiedHeader(int $timestamp): void
    {
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s T', $timestamp));
    }
}
