<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\User;
use DomainException;
use PDO;

/** Operator-only SMTP check. No activation/login proof is created or consumed. */
final class MailTransportCheck
{
    public function run(string $actor, bool $send, bool $toSender = false): array
    {
        if (env('MAIL_DRIVER', 'smtp') !== 'smtp') {
            throw new DomainException('SMTP is not selected. Local log delivery is not real email delivery.');
        }
        $user = (new User())->findByIdentifier($actor);
        $this->eligible($user);
        (new MailService())->smtpMailer(); // Strict configuration validation; no connection or send.
        if (!$send) {
            return ['mode'=>'dry-run', 'configuration_valid'=>true, 'network_contacted'=>false,
                'message_sent'=>false, 'inbox_receipt'=>'not verified'];
        }
        return Database::transaction(function (PDO $pdo) use ($user,$toSender): array {
            $current = (new User())->findById((int)$user['id'], true);
            $this->eligible($current);
            $mail = (new MailService())->smtpMailer();
            try {
                $mail->addAddress($toSender ? $mail->From : $current['email']);
                $mail->Subject = 'Avela SMTP configuration check';
                $mail->Body = 'This is a requested Avela email-transport check. It is not an activation or sign-in message. '
                    . 'No verification code was issued and no account settings were changed. '
                    . 'Receiving this message confirms receipt in this mailbox; ignore it if you did not request the check.';
                $mail->isHTML(false);
                $mail->send();
            } catch (\Throwable) {
                throw new MailDeliveryException('The SMTP check could not send. Check provider access and private configuration; certificate checks remain required.');
            }
            AuditService::record($pdo, (int)$current['id'], 'mail.transport_test', 'mail', null,
                ['result'=>'provider accepted; inbox receipt unverified','destination'=>$toSender?'configured sender':'operator']);
            // Provider acceptance cannot establish inbox receipt, nor be recalled if commit fails.
            return ['mode'=>'send', 'configuration_valid'=>true, 'network_contacted'=>true,
                'message_sent'=>true, 'inbox_receipt'=>'owner must verify in the authorized mailbox'];
        });
    }

    private function eligible(?array $user): void
    {
        if (!$user || !array_key_exists('lock_requires_admin', $user) || $user['role'] !== 'admin'
            || !User::canAuthenticate($user) || !filter_var($user['email'], FILTER_VALIDATE_EMAIL)) {
            throw new DomainException('An active, unlocked Administrator and reviewed lock schema are required.');
        }
    }
}
