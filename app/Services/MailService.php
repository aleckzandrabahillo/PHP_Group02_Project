<?php

namespace App\Services;

final class MailService
{
    public function sendOtp(string $email, string $code, string $purpose): void
    {
        $subject = $purpose === 'activation' ? 'Activate your Avela account' : 'Your Avela sign-in code';
        $body = $purpose === 'activation'
            ? "Your Avela activation code is {$code}. It expires soon."
            : "Your Avela sign-in code is {$code}. It expires soon.";

        $driver = (string) env('MAIL_DRIVER', 'log');
        if ($driver === 'mail') {
            $headers = 'From: ' . env('MAIL_FROM_NAME', 'Avela') . ' <' . env('MAIL_FROM_ADDRESS', 'no-reply@avela.local') . ">\r\n";
            if (!@mail($email, $subject, $body, $headers)) {
                throw new \RuntimeException('The server could not send the OTP email.');
            }
            return;
        }

        $file = dirname(__DIR__, 2) . '/storage/logs/dev_mail.log';
        $line = sprintf("[%s] TO: %s | PURPOSE: %s | OTP: %s | %s\n", date('c'), $email, $purpose, $code, $subject);
        if (@file_put_contents($file, $line, FILE_APPEND | LOCK_EX) === false) {
            throw new \RuntimeException('Avela could not write the local OTP mail log. Check storage/logs permissions.');
        }
    }
}
