<?php

namespace App\Services;

use InvalidArgumentException;
use RuntimeException;

final class ProductImageService
{
    public const MAX_BYTES = ImageUploadPolicy::MAX_BYTES;
    private const DIRECTORY = 'uploads/products/';

    public function store(mixed $file): ?string
    {
        if ($file === null) return null;
        if (!is_array($file) || !isset($file['error']) || !is_int($file['error'])) {
            throw new InvalidArgumentException('Choose a valid product image.');
        }
        if ($file['error'] === UPLOAD_ERR_NO_FILE) return null;
        if (in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
            throw new InvalidArgumentException('Product images must be ' . ImageUploadPolicy::label() . ' or smaller.');
        }
        if ($file['error'] !== UPLOAD_ERR_OK || !is_string($file['tmp_name'] ?? null)
            || !is_string($file['name'] ?? null) || !is_uploaded_file($file['tmp_name'])) {
            throw new InvalidArgumentException('The image upload failed. Choose the file again.');
        }
        $size = filesize($file['tmp_name']);
        if ($size === false || $size < 1 || $size > ImageUploadPolicy::maxBytes()) {
            throw new InvalidArgumentException('Product images must be nonempty and ' . ImageUploadPolicy::label() . ' or smaller.');
        }
        $extension = strtolower(pathinfo(str_replace('\\', '/', $file['name']), PATHINFO_EXTENSION));
        $types = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
        $info = @getimagesize($file['tmp_name']);
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        if (!isset($types[$extension]) || $mime !== $types[$extension] || ($info['mime'] ?? null) !== $mime) {
            throw new InvalidArgumentException('Choose a genuine JPEG, PNG or WebP image with a matching extension.');
        }
        if ($info[0] < 1 || $info[1] < 1 || $info[0] > 6000 || $info[1] > 6000 || $info[0] * $info[1] > 16000000) {
            throw new InvalidArgumentException('Choose an image within 6000 pixels per side and 16 megapixels.');
        }
        $directory = dirname(__DIR__, 2) . '/public/' . self::DIRECTORY;
        // The tracked folder policy prevents script handling; do not silently upload without it.
        if (!is_dir($directory) || !is_file($directory . '.htaccess') || !is_writable($directory) || is_link(rtrim($directory, '/'))) {
            throw new RuntimeException('Product image storage is unavailable.');
        }
        $path = self::DIRECTORY . bin2hex(random_bytes(16)) . '.' . ($extension === 'jpeg' ? 'jpg' : $extension);
        ImageUploadPolicy::store($file['tmp_name'], dirname(__DIR__, 2) . '/public/' . $path, $mime);
        return $path;
    }

    public function discard(?string $path): bool
    {
        // Only generated files in this service's directory can be removed; preserve legacy assets.
        if ($path === null || !preg_match('~^uploads/products/[a-f0-9]{32}\.(jpg|png|webp)$~D', $path)) return true;
        $directory = realpath(dirname(__DIR__, 2) . '/public/' . self::DIRECTORY);
        $file = dirname(__DIR__, 2) . '/public/' . $path;
        if (!file_exists($file)) return true;
        if (is_link($file) || realpath(dirname($file)) !== $directory) return false;
        return @unlink($file);
    }
}
