<?php

declare(strict_types=1);
namespace App\Services;

use PHPMailer\PHPMailer\PHPMailer;

final class MailService
{
    public function sendOtp(string $email, #[\SensitiveParameter] string $code, string $purpose): void
    {
        if (!preg_match('/^[0-9]{6}$/D', $code) || !in_array($purpose, ['activation','login','profile_update'], true)) throw new \InvalidArgumentException('Invalid verification message.');
        $subject = match ($purpose) {
            'activation' => 'Activate your Avela account',
            'profile_update' => 'Confirm your Avela profile update',
            default => 'Your Avela sign-in code',
        };
        $this->deliver($email, $subject, "Your Avela verification code is {$code}. It expires in " . max(1, (int) env('OTP_EXPIRY_MINUTES', 5)) . ' minutes. If you did not request it, ignore this message.', $purpose, $code);
    }

    public function sendActivation(string $email, #[\SensitiveParameter] string $link, #[\SensitiveParameter] string $code): void
    {
        $this->deliver($email, 'Activate your Avela account',
            "Open this link and choose Activate account:\n{$link}\n\nThe link expires in 30 minutes and works once. You can instead enter the six-digit code {$code} in your pending Avela activation session. The code expires in " . max(1, (int) env('OTP_EXPIRY_MINUTES', 5)) . " minutes. Either method activates the same account. If you did not register, ignore this message.",
            'activation_link', $code);
    }

    public function sendOrderConfirmation(#[\SensitiveParameter] array $order, array $items): void
    {
        $id=OrderReference::display($order);
        $lines=['Your Avela order '.$id.' is confirmed.',''];
        foreach($items as $item)$lines[]=$item['quantity'].' × '.$item['product_name_snapshot'].' — PHP '.number_format((float)$item['unit_price_snapshot'],2);
        $subtotal=0;foreach($items as $item)$subtotal+=(int)$item['quantity']*(int)round((float)$item['unit_price_snapshot']*100);
        $lines[]='';$lines[]='Subtotal: PHP '.ShippingService::decimal($subtotal);
        if(!empty($order['discount']))$lines[]='Discount ('.$order['discount']['code_snapshot'].'): -PHP '.ShippingService::decimal((int)$order['discount']['amount_cents']);
        $lines[]='Shipping: PHP '.number_format((float)($order['shipping_fee']??0),2);$lines[]='Total: PHP '.number_format((float)$order['total'],2);
        $lines[]=($order['payment_method']??'cod')==='maya_sandbox'
            ? 'Debit/Credit Card payment confirmed for this test checkout. No real money was charged.'
            : 'Cash on delivery. This confirmation is not a payment receipt.';
        $lines[]='Sign in to your Avela account to view order updates.';
        $this->deliver($order['contact_email_snapshot'],'Avela order '.$id.' confirmation',implode("\n",$lines),'order_confirmation','');
    }

    /** Strict transport configuration; no SMTP debugging or certificate-validation overrides. */
    public function smtpMailer(): PHPMailer
    {
        $host = (string) env('MAIL_HOST', '');
        $mode = (string) env('MAIL_ENCRYPTION', 'tls');
        $port = filter_var(env('MAIL_PORT', 587), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]);
        $username = (string) env('MAIL_USERNAME', '');
        $password = (string) env('MAIL_PASSWORD', '');
        $from = (string) env('MAIL_FROM_ADDRESS', $username);
        if ($host === '' || strlen($host) > 253 || !filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)
            || preg_match('/[\\s;:\/\\\\]/', $host) || !$port || !in_array($mode, ['tls','ssl'], true)
            || $username === '' || $password === '' || !PHPMailer::validateAddress($from)) {
            throw new MailDeliveryException('Email delivery is not configured correctly. Contact an Administrator.');
        }
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $host;
        $mail->SMTPAuth = true;
        $mail->Username = $username;
        $mail->Password = $password;
        $mail->SMTPSecure = $mode === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->SMTPAutoTLS = true;
        $mail->Port = $port;
        $mail->Timeout = 10;
        $mail->Timelimit = 15;
        $mail->SMTPDebug = 0;
        $mail->Debugoutput = static function (): void {};
        $mail->SMTPOptions = ['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'allow_self_signed' => false, 'peer_name' => $host]];
        $mail->CharSet = PHPMailer::CHARSET_UTF8;
        $mail->setFrom($from, (string) env('MAIL_FROM_NAME', 'Avela'));
        return $mail;
    }

    private function deliver(string $email, string $subject, #[\SensitiveParameter] string $body, string $purpose, #[\SensitiveParameter] string $code): void
    {
        if (!PHPMailer::validateAddress($email)) throw new MailDeliveryException('The verification email address is invalid.');
        $driver = (string) env('MAIL_DRIVER', 'smtp');
        if ($driver === 'smtp') {
            try {
                $mail = $this->smtpMailer();
                $mail->addAddress($email);
                $mail->Subject = $subject;
                $mail->Body = $body;
                $mail->isHTML(false);
                $mail->send();
                return;
            } catch (\Throwable) {
                // Never pass provider diagnostics, credentials, message bodies or codes to logs/responses.
                throw new MailDeliveryException('The verification email could not be sent. Please try again later.');
            }
        }
        if ($driver !== 'log' || env('APP_ENV', 'production') !== 'development') throw new MailDeliveryException('Configure secure email delivery. Local test mail is unavailable in production.');
        $file = dirname(__DIR__, 2) . '/storage/logs/dev_mail.log';
        if (in_array($purpose, ['activation_link','order_confirmation'], true)) {
            // A private delivery artifact, never a token in general application/access logs.
            $dir = realpath((string) env('MAIL_DEV_OUTBOX_DIRECTORY', ''));
            $root = str_replace('\\', '/', (string) realpath(dirname(__DIR__, 2)));
            $normal = str_replace('\\', '/', (string) $dir);
            if (!$dir || !is_writable($dir) || str_starts_with(strtolower($normal . '/'), strtolower($root . '/'))) throw new MailDeliveryException('Configure a private development outbox outside the project before testing activation links.');
            if (PHP_OS_FAMILY !== 'Windows' && (fileperms($dir) & 0077) !== 0) throw new MailDeliveryException('The development outbox must be private.');
            $delivery = $dir . '/' . ($purpose === 'activation_link' ? 'activation' : 'order') . '-' . bin2hex(random_bytes(16)) . '.eml';
            if (@file_put_contents($delivery, "To: {$email}\nSubject: {$subject}\n\n{$body}\n", LOCK_EX) === false) throw new MailDeliveryException('The local development message could not be saved.');
            @chmod($delivery, 0600);
            $line = '[' . date('c') . "] TO: {$email} | PURPOSE: {$purpose} | Private outbox delivery prepared\n";
        } else {
            $line = '[' . date('c') . "] TO: {$email} | PURPOSE: {$purpose} | OTP: {$code} | {$subject}\n";
        }
        if (@file_put_contents($file, $line, FILE_APPEND | LOCK_EX) === false) {
            if (isset($delivery)) @unlink($delivery);
            throw new MailDeliveryException('Avela could not record local test delivery. Check private storage permissions.');
        }
    }
}
