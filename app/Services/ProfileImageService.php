<?php

declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;
use RuntimeException;

final class ProfileImageService
{
    public function store(mixed $file): string
    {
        if (!is_array($file) || !is_int($file['error'] ?? null)) {
            throw new InvalidArgumentException('Choose a valid profile photograph.');
        }
        if (in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
            throw new InvalidArgumentException('Profile photographs must be 2 MB or smaller.');
        }
        if ($file['error'] !== UPLOAD_ERR_OK || !is_string($file['tmp_name'] ?? null)
            || !is_string($file['name'] ?? null) || !is_uploaded_file($file['tmp_name'])) {
            throw new InvalidArgumentException('Choose the photograph again; the upload did not complete.');
        }
        $size = filesize($file['tmp_name']);
        if ($size === false || $size < 1 || $size > 2 * 1024 * 1024) {
            throw new InvalidArgumentException('Profile photographs must be nonempty and 2 MB or smaller.');
        }
        $extension = strtolower(pathinfo(str_replace('\\', '/', $file['name']), PATHINFO_EXTENSION));
        $types = ['jpg'=>'image/jpeg', 'jpeg'=>'image/jpeg', 'png'=>'image/png', 'webp'=>'image/webp'];
        $info = @getimagesize($file['tmp_name']);
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        if (!isset($types[$extension]) || $mime !== $types[$extension] || ($info['mime'] ?? null) !== $mime) {
            throw new InvalidArgumentException('Choose a genuine JPEG, PNG or WebP photograph with a matching extension.');
        }
        if ($info[0] < 1 || $info[1] < 1 || $info[0] > 6000 || $info[1] > 6000 || $info[0] * $info[1] > 16000000) {
            throw new InvalidArgumentException('Choose a photograph within 6000 pixels per side and 16 megapixels.');
        }
        $directory = dirname(__DIR__, 2) . '/public/uploads/profile/';
        if (!is_dir($directory) || !is_file($directory . '.htaccess') || !is_writable($directory) || is_link(rtrim($directory, '/'))) {
            throw new RuntimeException('Profile image storage is unavailable.');
        }
        $path = 'uploads/profile/' . bin2hex(random_bytes(16)) . '.' . ($extension === 'jpeg' ? 'jpg' : $extension);
        if (!move_uploaded_file($file['tmp_name'], dirname(__DIR__, 2) . '/public/' . $path)) {
            throw new RuntimeException('Profile image storage is unavailable.');
        }
        return $path;
    }

    public function discard(?string $path): bool
    {
        if ($path === null || !preg_match('~^uploads/profile/[a-f0-9]{32}\.(jpg|png|webp)$~D', $path)) return true;
        $directory = realpath(dirname(__DIR__, 2) . '/public/uploads/profile');
        $file = dirname(__DIR__, 2) . '/public/' . $path;
        if (!file_exists($file)) return true;
        if (!$directory || is_link($file) || realpath(dirname($file)) !== $directory) return false;
        return @unlink($file);
    }
}
