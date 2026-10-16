<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class MfaCipher
{
    private function key(): string
    {
        $key = base64_decode((string) env('MFA_ENCRYPTION_KEY', ''), true);
        if (!extension_loaded('openssl') || $key === false || strlen($key) !== 32) {
            throw new RuntimeException('Authenticator enrollment needs a private 32-byte encryption key.');
        }
        return $key;
    }

    public function configured(): bool
    {
        try { $this->key(); return true; } catch (RuntimeException) { return false; }
    }

    public function encrypt(int $userId, #[\SensitiveParameter] string $secret): string
    {
        $nonce = random_bytes(12); $tag = '';
        $encrypted = openssl_encrypt($secret, 'aes-256-gcm', $this->key(), OPENSSL_RAW_DATA, $nonce, $tag, 'avela:mfa:' . $userId, 16);
        if ($encrypted === false || strlen($tag) !== 16) throw new RuntimeException('Authenticator encryption unavailable.');
        return 'v1:' . base64_encode($nonce . $tag . $encrypted);
    }

    public function decrypt(int $userId, #[\SensitiveParameter] string $encrypted): string
    {
        $payload = str_starts_with($encrypted, 'v1:') ? base64_decode(substr($encrypted, 3), true) : false;
        if ($payload === false || strlen($payload) < 29) throw new RuntimeException('Authenticator configuration unavailable.');
        $secret = openssl_decrypt(substr($payload, 28), 'aes-256-gcm', $this->key(), OPENSSL_RAW_DATA,
            substr($payload, 0, 12), substr($payload, 12, 16), 'avela:mfa:' . $userId);
        if ($secret === false) throw new RuntimeException('Authenticator configuration unavailable.');
        return $secret;
    }

    public function recoveryHash(int $userId, #[\SensitiveParameter] string $code): string
    {
        return hash_hmac('sha256', 'avela:recovery:' . $userId . ':' . $code, $this->key());
    }
}
