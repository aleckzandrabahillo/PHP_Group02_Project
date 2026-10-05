<?php

namespace App\Services;

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

final class MailService
{
    public function sendOtp(string $email, string $code, string $purpose): void
    {
        $subject = match ($purpose) {
            'activation' => 'Activate your Avela account',
            'profile_update' => 'Confirm your Avela profile update',
            default => 'Your Avela sign-in code',
        };

        $body = match ($purpose) {
            'activation' => "Your Avela activation code is {$code}. It expires soon.",
            'profile_update' => "Your Avela profile update code is {$code}. It expires soon.",
            default => "Your Avela sign-in code is {$code}. It expires soon.",
        };

        $driver = (string) env('MAIL_DRIVER', 'log');

        if ($driver === 'smtp') {
            $mail = new PHPMailer(true);

            try {
                $mail->isSMTP();
                $mail->Host = (string) env('MAIL_HOST', 'smtp.gmail.com');
                $mail->SMTPAuth = true;
                $mail->Username = (string) env('MAIL_USERNAME', '');
                $mail->Password = (string) env('MAIL_PASSWORD', '');
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = (int) env('MAIL_PORT', 587);

                $mail->setFrom(
                    (string) env('MAIL_FROM_ADDRESS', env('MAIL_USERNAME', '')),
                    (string) env('MAIL_FROM_NAME', 'Avela')
                );

                $mail->addAddress($email);
                $mail->Subject = $subject;
                $mail->Body = $body;
                $mail->isHTML(false);

                $mail->send();
                return;
            } catch (Exception $e) {
                throw new \RuntimeException(
                    'The server could not send the OTP email.'
                );
            }
        }

        $file = dirname(__DIR__, 2) . '/storage/logs/dev_mail.log';
        $line = sprintf(
            "[%s] TO: %s | PURPOSE: %s | OTP: %s | %s\n",
            date('c'),
            $email,
            $purpose,
            $code,
            $subject
        );

        if (
            @file_put_contents(
                $file,
                $line,
                FILE_APPEND | LOCK_EX
            ) === false
        ) {
            throw new \RuntimeException(
                'Avela could not write the local OTP mail log. Check storage/logs permissions.'
            );
        }
    }
}
