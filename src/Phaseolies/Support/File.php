<?php

namespace Phaseolies\Support;

use Phaseolies\Support\Storage\PublicFileSystem;
use Phaseolies\Support\Storage\FileNotFoundException;
use Phaseolies\Support\Facades\Storage;

class File extends \SplFileInfo
{   
    /**
     * Represents an uploaded file in a normalized structure.
     *
     * Keys:
     * - name      : (string) The original name of the uploaded file (basename only).
     * - type      : (string) The MIME type of the file (from PHP's mime_content_type()).
     * - tmp_name  : (string) The temporary path where the file is stored.
     * - error     : (int)    The PHP file upload error code (UPLOAD_ERR_* constants).
     * - size      : (int)    The size of the file in bytes.
     *
     * @var array<string, mixed>
     */
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
        } elseif (is_array($file)) {
            $this->file = array_merge([
                'name' => '',
                'type' => '',
                'tmp_name' => '',
                'error' => UPLOAD_ERR_NO_FILE,
                'size' => 0
            ], $file);

            if (!empty($this->file['tmp_name']) && is_string($this->file['tmp_name'])) {
                parent::__construct($this->file['tmp_name']);
            }
        } else {
            $this->file = [
                'name' => $file->getFilename(),
                'type' => mime_content_type($file->getPathname()),
                'tmp_name' => $file->getPathname(),
                'error' => UPLOAD_ERR_OK,
                'size' => $file->getSize(),
            ];
            parent::__construct($file->getPathname());
        }
    }

    /**
     * Gets the original name of the uploaded file.
     *
     * @return string
     */
    public function getClientOriginalName(): string
    {
        return $this->file['name'] ?? '';
    }

    /**
     * Gets the temporary path where the file is stored on the server.
     *
     * @return string
     */
    public function getClientOriginalPath(): string
    {
        return $this->file['tmp_name'] ?? '';
    }

    /**
     * Gets the MIME type of the uploaded file.
     *
     * @return string
     */
    public function getClientOriginalType(): string
    {
        return $this->file['type'] ?? '';
    }

    /**
     * Gets the size of the uploaded file in bytes.
     *
     * @return int
     */
    public function getClientOriginalSize(): int
    {
        return $this->file['size'] ?? 0;
    }

    /**
     * Gets the extension of the original file.
     *
     * @return string
     */
    public function getClientOriginalExtension(): string
    {
        $name = $this->getClientOriginalName();
        $extension = pathinfo($name, PATHINFO_EXTENSION);

        return strtolower($extension);
    }

    /**
     * Generate a unique name for an uploaded file.
     *
     * @return string
     */
    public function generateUniqueName(): string
    {
        return time() . '_' . $this->getClientOriginalName();
    }

    /**
     * Checks if the uploaded file is of a specific MIME type.
     *
     * @param string|array $mimeType The MIME type(s) to check against.
     * @return bool
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
     * @return bool
     */
    public function isImage(): bool
    {
        return strpos($this->getClientOriginalType(), 'image/') === 0;
    }

    /**
     * Check if the uploaded file is a video.
     *
     * @return bool
     */
    public function isVideo(): bool
    {
        return strpos($this->getClientOriginalType(), 'video/') === 0;
    }

    /**
     * Check if the uploaded file is a document.
     *
     * @return bool
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
     * @return bool
     */
    public function move(string $destination, ?string $fileName = null): bool
    {
        if (!$this->isValid()) {
            return false;
        }

        $fileName = $fileName ?? $this->getClientOriginalName();
        $destinationPath = rtrim($destination, '/') . '/' . $fileName;

        if (!is_dir(dirname($destinationPath))) {
            mkdir(dirname($destinationPath), 0777, true);
        }

        return move_uploaded_file($this->getClientOriginalPath(), $destinationPath);
    }

    /**
     * Get the file's mime type by using the fileinfo extension.
     *
     * @return string|false
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
     * @return int
     */
    public function getError(): int
    {
        return $this->file['error'] ?? 0;
    }

    /**
     * Checks if the file was uploaded successfully.
     *
     * @return bool
     */
    public function isValid(): bool
    {
        return $this->getError() === UPLOAD_ERR_OK;
    }

    /**
     * Store image in public or private file system
     *
     * @param string $path
     * @param string $disk
     * @return bool
     */
    public function store(string $path, string $disk = 'public'): bool
    {
        if (is_null($disk) || empty($disk)) {
            $disk = 'public';
        }

        return $this->storeAs($path, $this->generateUniqueName($this->getClientOriginalName()), $disk);
    }

    /**
     * Store the file with filename
     *
     * @param string $path The directory path to store the file
     * @param string $fileName The filename to use
     * @param string $disk The storage disk to use (default: 'public')
     * @param callable|null $callback Optional validation callback
     * @return string|false
     */
    public function storeAs(string $path, string $fileName = '', string $disk = 'public', ?callable $callback = null): string|false
    {
        if (!$this->isValid()) {
            return false;
        }

        // Execute callback validation if provided
        if (is_callable($callback)) {
            $shouldStore = $callback($this);
            if (!$shouldStore) {
                return false;
            }
        }

        $fileName = $fileName ?: $this->generateUniqueName($this->getClientOriginalName());

        $path = trim($path, '/');
        $fileName = trim($fileName, '/');

        $storagePath = Storage::getDiskPath($disk);
        $fullPath = $storagePath . '/' . $path . '/' . $fileName;

        $directory = dirname($fullPath);
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        if (move_uploaded_file($this->getClientOriginalPath(), $fullPath)) {
            return $path . '/' . $fileName;
        }

        return false;
    }

    /**
     * Checks if the file is readable.
     *
     * @return bool
     */
    public function isReadable(): bool
    {
        return is_readable($this->getClientOriginalPath());
    }

    /**
     * Gets the last modification time of the uploaded file.
     *
     * @return int|false
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
            $this->setLastModifiedHeader($mtime);
        }

        return $this;
    }

    /**
     * Placeholder method to set the Last-Modified header.
     *
     * @param int $timestamp
     * @return void
     */
    protected function setLastModifiedHeader(int $timestamp): void
    {
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s T', $timestamp));
    }
}
