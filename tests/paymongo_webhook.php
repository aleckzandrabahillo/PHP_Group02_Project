<?php
declare(strict_types=1);

/**
 * Offline checks for PayMongo webhook signature verification, redirect safety and the
 * payment-method gate. No database or network needed:
 *
 *   php tests/paymongo_webhook.php
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once dirname(__DIR__) . '/bootstrap.php';

use App\Core\CheckoutValidator;
use App\Core\Env;
use App\Core\Response;
use App\Services\PayMongoService;
use App\Services\PaymentService;

$failures = 0;
function check(string $name, bool $ok): void
{
    global $failures;
    echo ($ok ? 'PASS: ' : 'FAIL: ') . $name . PHP_EOL;
    if (!$ok) { $failures++; }
}

/** Override settings for this run (Env::get prefers values loaded from .env over $_ENV). */
function setEnv(array $values): void
{
    $file = tempnam(sys_get_temp_dir(), 'avela_env');
    file_put_contents($file, implode("\n", array_map(fn ($k, $v) => $k . '=' . $v, array_keys($values), $values)) . "\n");
    Env::load($file);
    unlink($file);
}

$secret = 'whsk_unit_test_secret';
setEnv(['PAYMONGO_WEBHOOK_SECRET' => $secret, 'PAYMONGO_SECRET_KEY' => 'sk_test_unit_test_key', 'PAYMONGO_ENABLED' => 'true']);

$gateway = new PayMongoService();
$body = '{"data":{"id":"evt_1","type":"event","attributes":{"type":"checkout_session.payment.paid"}}}';
$time = (string) time();
$good = hash_hmac('sha256', $time . '.' . $body, $secret);

check('valid test-mode signature is accepted', $gateway->verifyWebhookSignature($body, "t=$time,te=$good,li="));
check('live signature is ignored with a test key', !$gateway->verifyWebhookSignature($body, "t=$time,te=,li=$good"));
check('tampered body is rejected', !$gateway->verifyWebhookSignature($body . ' ', "t=$time,te=$good,li="));
check('wrong secret is rejected', !$gateway->verifyWebhookSignature($body, "t=$time,te=" . hash_hmac('sha256', $time . '.' . $body, 'whsk_other') . ',li='));
check('timestamp is part of the signature', !$gateway->verifyWebhookSignature($body, 't=' . ((int) $time + 1) . ",te=$good,li="));
check('missing header is rejected', !$gateway->verifyWebhookSignature($body, ''));
check('garbage header is rejected', !$gateway->verifyWebhookSignature($body, 'nonsense'));

setEnv(['PAYMONGO_SECRET_KEY' => 'sk_live_unit_test_key']);
check('live key pairs with the li signature', $gateway->verifyWebhookSignature($body, "t=$time,te=,li=$good"));
check('test signature is ignored with a live key', !$gateway->verifyWebhookSignature($body, "t=$time,te=$good,li="));

check(
    'test key accepts livemode false',
    (function () {
        setEnv(['PAYMONGO_SECRET_KEY' => 'sk_test_unit_test_key']);
        return PaymentService::webhookModeMatches(['livemode' => false]);
    })()
);

check(
    'live key accepts livemode true',
    (function () {
        setEnv(['PAYMONGO_SECRET_KEY' => 'sk_live_unit_test_key']);
        return PaymentService::webhookModeMatches(['livemode' => true]);
    })()
);

check(
    'missing livemode is rejected',
    !PaymentService::webhookModeMatches([])
);

check(
    'string livemode is rejected',
    !PaymentService::webhookModeMatches(['livemode' => 'false'])
);

check(
    'test key rejects live-mode event',
    (function () {
        setEnv(['PAYMONGO_SECRET_KEY' => 'sk_test_unit_test_key']);
        return !PaymentService::webhookModeMatches(['livemode' => true]);
    })()
);


check(
    'test key accepts test-mode session',
    (function () {
        setEnv(['PAYMONGO_SECRET_KEY' => 'sk_test_unit_test_key']);
        return PaymentService::sessionModeMatches([
            'attributes' => ['livemode' => false],
        ]);
    })()
);

check(
    'live key accepts live-mode session',
    (function () {
        setEnv(['PAYMONGO_SECRET_KEY' => 'sk_live_unit_test_key']);
        return PaymentService::sessionModeMatches([
            'attributes' => ['livemode' => true],
        ]);
    })()
);

check(
    'missing session attributes are rejected',
    !PaymentService::sessionModeMatches([])
);

check(
    'missing session livemode is rejected',
    !PaymentService::sessionModeMatches([
        'attributes' => [],
    ])
);

check(
    'mismatched session mode is rejected',
    (function () {
        setEnv(['PAYMONGO_SECRET_KEY' => 'sk_test_unit_test_key']);
        return !PaymentService::sessionModeMatches([
            'attributes' => ['livemode' => true],
        ]);
    })()
);


setEnv(['PAYMONGO_WEBHOOK_SECRET' => '']);
check('no webhook secret configured rejects everything', !$gateway->verifyWebhookSignature($body, "t=$time,te=$good,li=$good"));

setEnv(['PAYMONGO_ENABLED' => 'false']);
check('PAYMONGO_ENABLED=false hides online payment', !PayMongoService::isConfigured());
setEnv(['PAYMONGO_ENABLED' => 'true', 'PAYMONGO_SECRET_KEY' => 'sk_test_your_key_here']);
check('placeholder key is not "configured"', !PayMongoService::isConfigured());
setEnv(['PAYMONGO_SECRET_KEY' => 'sk_test_realistic_key_value']);
check('real-looking key with the flag on is configured', PayMongoService::isConfigured());

$form = ['full_name' => 'Ana Cruz', 'contact_no' => '09171234567', 'delivery_address' => '1 Rizal St, Paranaque'];
check('cod-only validation still rejects paymongo', isset(CheckoutValidator::validate($form + ['payment_method' => 'paymongo'])['errors']['payment_method']));
check('paymongo accepted when allowed', !isset(CheckoutValidator::validate($form + ['payment_method' => 'paymongo'], ['cod', 'paymongo'])['errors']['payment_method']));
check('unknown method rejected even when online is allowed', isset(CheckoutValidator::validate($form + ['payment_method' => 'bitcoin'], ['cod', 'paymongo'])['errors']['payment_method']));

foreach (['http://checkout.paymongo.com/cs_x', 'https://evil.example/paymongo.com', 'https://paymongo.com.evil.example/x', "https://checkout.paymongo.com/x\r\nSet-Cookie: a=b", 'https://user:pw@checkout.paymongo.com/x'] as $bad) {
    $blocked = false;
    try { Response::redirectExternal($bad); } catch (RuntimeException) { $blocked = true; }
    check('redirect blocked: ' . substr(str_replace(["\r", "\n"], ' ', $bad), 0, 50), $blocked);
}

echo $failures === 0 ? "\nAll checks passed.\n" : "\n$failures check(s) failed.\n";
exit($failures === 0 ? 0 : 1);
