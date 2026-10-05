<?php

declare(strict_types=1);

namespace App\Services;

final class RecaptchaService
{
    public function verify(string $response): bool
    {
        if ($response === '') {
            return false;
        }

        $secret = (string) env('RECAPTCHA_SECRET_KEY', '');

        if ($secret === '') {
            return false;
        }

        $postData = http_build_query([
            'secret' => $secret,
            'response' => $response,
            'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
        ]);

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => $postData,
                'timeout' => 10,
            ],
        ]);

        $result = @file_get_contents(
            'https://www.google.com/recaptcha/api/siteverify',
            false,
            $context
        );

        if ($result === false) {
            return false;
        }

        $data = json_decode($result, true);

        return is_array($data) && ($data['success'] ?? false) === true;
    }
}