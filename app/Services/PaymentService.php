<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Env;
use InvalidArgumentException;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Online payment lifecycle for orders paid through PayMongo hosted checkout.
 *
 * Order flow:
 *   1. CheckoutService::placeOnline saves the order (payment_status=pending, stock reserved).
 *   2. start() creates the PayMongo checkout session and returns its URL.
 *   3. The customer pays on PayMongo. Payment is confirmed by whichever arrives first:
 *        - the checkout_session.payment.paid webhook (handleWebhook), or
 *        - reconcile(), which asks PayMongo directly (used on the return page and order page,
 *          so it also works on localhost where PayMongo cannot reach a webhook).
 *      Both end in applySession(), which is idempotent and checks the paid amount.
 *   4. Unpaid orders are closed by closeUnpaid()/sweepStale(): the session is expired at PayMongo,
 *      the order is cancelled and its stock returned.
 *
 * The browser redirect is never trusted as proof of payment.
 */
final class PaymentService
{
    public function __construct(
        private readonly PayMongoService $gateway = new PayMongoService(),
        private readonly ?PDO $connection = null,
    ) {}

        /**
     * Check whether a webhook event's mode matches the configured PayMongo key.
     */
    public static function webhookModeMatches(array $attributes): bool
    {
        $liveKey = str_starts_with(
            (string) Env::get('PAYMONGO_SECRET_KEY', ''),
            'sk_live_'
        );

        return array_key_exists('livemode', $attributes)
            && is_bool($attributes['livemode'])
            && $attributes['livemode'] === $liveKey;
    }


    /**
     * Check whether a retrieved checkout session matches the configured key mode.
     */
    public static function sessionModeMatches(array $session): bool
    {
        $attributes = $session['attributes'] ?? null;

        if (!is_array($attributes)) {
            return false;
        }

        $liveKey = str_starts_with(
            (string) Env::get('PAYMONGO_SECRET_KEY', ''),
            'sk_live_'
        );

        return array_key_exists('livemode', $attributes)
            && is_bool($attributes['livemode'])
            && $attributes['livemode'] === $liveKey;
    }


    /**
     * Open the PayMongo checkout for a just-placed order and return the hosted checkout URL.
     * If PayMongo cannot be reached the order is cancelled, stock returned and the bag restored,
     * then the original error is rethrown.
     *
     * @param int[] $cartIds carts converted by this order, reactivated if payment cannot start
     */
    public function start(int $orderId, int $userId, array $cartIds): string
    {
        try {
            $order = $this->loadOrder($orderId, $userId);
            if (
                $order === null
                || $order['payment_method'] !== 'paymongo'
                || $order['payment_status'] !== 'pending'
                || !empty($order['paymongo_checkout_session_id'])
            ) {
                throw new RuntimeException('Order is not ready for online payment.');
            }

            $session = $this->gateway->createCheckoutSession(
                $this->lineItems($orderId),
                $this->returnUrl('/payments/return', $orderId),
                $this->returnUrl('/payments/cancelled', $orderId),
                self::reference($orderId),
                'AVELA order #' . $orderId
            );

            $stmt = $this->pdo()->prepare(
                'UPDATE orders SET paymongo_checkout_session_id = ?, updated_at = NOW()
                 WHERE id = ? AND paymongo_checkout_session_id IS NULL'
            );
           
            $stmt->execute([$session['id'], $orderId]);
            if ($stmt->rowCount() !== 1) {
                 throw new RuntimeException('Could not attach the PayMongo checkout session to the order.');
                 } return $session['checkout_url'];

        } catch (Throwable $e) {
            $this->log('start failed for order ' . $orderId . ': ' . $e->getMessage());
            try {
                $this->cancelUnpaid($orderId, 'failed', $cartIds, $userId);
            } catch (Throwable $inner) {
                $this->log('cleanup failed for order ' . $orderId . ': ' . $inner->getMessage());
            }
            throw $e;
        }
    }

    /**
     * Hosted checkout URL for a customer who left without paying ("Pay now" on the order page).
     * Null when the order no longer awaits payment.
     */
    public function checkoutUrlFor(int $orderId, int $userId): ?string
    {
        if ($this->reconcile($orderId, $userId) !== 'pending') {
            return null;
        }

        $order = $this->loadOrder($orderId, $userId);
        $sessionId = $order['paymongo_checkout_session_id'] ?? null;

        if ($order === null || $order['status'] !== 'pending' || !is_string($sessionId) || $sessionId === '') {
            return null;
        }

        $url = $this->gateway->retrieveCheckoutSession($sessionId)['attributes']['checkout_url'] ?? null;

        return is_string($url) ? $url : null;
    }

    /**
     * Ask PayMongo whether a pending order has been paid and record the result.
     *
     * @return string the order's payment_status afterwards (or 'missing')
     */
    public function reconcile(int $orderId, ?int $userId = null): string
    {
        $order = $this->loadOrder($orderId, $userId);
        if ($order === null) {
            return 'missing';
        }

        $status = (string) ($order['payment_status'] ?? 'not_required');
        $sessionId = $order['paymongo_checkout_session_id'] ?? null;

        if (
            ($order['payment_method'] ?? '') !== 'paymongo'
            || $status !== 'pending'
            || !is_string($sessionId)
            || $sessionId === ''
        ) {
            return $status;
        }

        $this->applySession($orderId, $this->gateway->retrieveCheckoutSession($sessionId));

        $fresh = $this->loadOrder($orderId);

        return (string) ($fresh['payment_status'] ?? $status);
    }

    /**
     * Close an unpaid online order: confirm with PayMongo that it is unpaid, expire the session,
     * cancel the order and return its stock.
     *
     * @return string 'paid' | 'cancelled' | 'pending' (could not be closed) | 'missing'
     * @throws RuntimeException when PayMongo is temporarily unreachable
     */
    public function closeUnpaid(int $orderId, ?int $userId = null): string
    {
        $order = $this->loadOrder($orderId, $userId);
        if ($order === null) {
            return 'missing';
        }
        if (($order['payment_method'] ?? '') !== 'paymongo') {
            return 'pending';
        }
        if (($order['payment_status'] ?? '') !== 'pending') {
            return $order['payment_status'] === 'paid' ? 'paid' : 'cancelled';
        }

        $sessionId = $order['paymongo_checkout_session_id'] ?? null;
        if (is_string($sessionId) && $sessionId !== '') {
            if ($this->reconcile($orderId) === 'paid') {
                return 'paid';
            }
            try {
                $this->gateway->expireCheckoutSession($sessionId);
            } catch (RuntimeException $e) {
                // Network failure or PayMongo outage: try again later instead of guessing.
                if ($e->getCode() === 0 || $e->getCode() >= 500) {
                    throw $e;
                }
                // A 4xx usually means the session is already closed or just got paid: re-check.
                if ($this->reconcile($orderId) === 'paid') {
                    return 'paid';
                }
            }
        }

        return $this->cancelUnpaid($orderId, 'expired') ? 'cancelled' : 'pending';
    }

    /**
     * Close online orders left unpaid for $minutes. Run from the command line on a schedule.
     *
     * @return array{checked: int, paid: int, cancelled: int, errors: int}
     */
    public function sweepStale(int $minutes, int $limit = 100): array
    {
        $minutes = max(5, $minutes);
        $limit = max(1, min(500, $limit));
        $ids = $this->pdo()->query(
            "SELECT id FROM orders
             WHERE payment_method = 'paymongo' AND payment_status = 'pending'
               AND status IN ('pending','cancelled')
               AND created_at < (NOW() - INTERVAL " . (int) $minutes . " MINUTE)
             ORDER BY id LIMIT " . (int) $limit
        )->fetchAll(PDO::FETCH_COLUMN);

        $result = ['checked' => 0, 'paid' => 0, 'cancelled' => 0, 'errors' => 0];
        foreach ($ids as $id) {
            $result['checked']++;
            try {
                $state = $this->closeUnpaid((int) $id);
                if ($state === 'paid') {
                    $result['paid']++;
                } elseif ($state === 'cancelled') {
                    $result['cancelled']++;
                } else {
                    $result['errors']++;
                }
            } catch (Throwable $e) {
                $result['errors']++;
                $this->log('sweep failed for order ' . $id . ': ' . $e->getMessage());
            }
        }

        return $result;
    }

    /**
     * Handle a PayMongo webhook request.
     *
     * @return array{0: int, 1: string} HTTP status and a short plain-text body. Anything other than
     *         2xx makes PayMongo retry, so unrelated events are acknowledged with 200.
     */
    public function handleWebhook(string $rawBody, string $signatureHeader): array
    {
        if (!$this->gateway->verifyWebhookSignature($rawBody, $signatureHeader)) {
            return [400, 'invalid signature'];
        }

        $event = json_decode($rawBody, true);
        $attributes = is_array($event) ? ($event['data']['attributes'] ?? null) : null;
        if (!is_array($attributes)) {
            return [400, 'invalid payload'];
        }

        // Test events never touch a live shop and vice versa.
        
        if (!self::webhookModeMatches($attributes)) {
            return [200, 'ignored (missing or mismatched mode)'];
            }

        if (($attributes['type'] ?? '') !== 'checkout_session.payment.paid') {
            return [200, 'ignored'];
        }

        $session = $attributes['data'] ?? null;
        $sessionId = is_array($session) ? ($session['id'] ?? null) : null;
        if (!is_string($sessionId) || preg_match('/^cs_[A-Za-z0-9_-]+$/D', $sessionId) !== 1) {
            return [400, 'invalid session'];
        }

        $stmt = $this->pdo()->prepare('SELECT id FROM orders WHERE paymongo_checkout_session_id = ?');
        $stmt->execute([$sessionId]);
        $orderId = $stmt->fetchColumn();
        if ($orderId === false) {
            return [200, 'ignored (unknown session)'];
        }

        $this->applySession((int) $orderId, $session);

        return [200, 'ok'];
    }

    /**
     * Record payment for an order if the given PayMongo checkout session shows it was paid in full.
     * Idempotent: webhook retries, the return page and the sweeper may all call this.
     *
     * @param array<string, mixed> $session checkout session resource ['id' => ..., 'attributes' => [...]]
     * @return bool true when the order is paid
     */
    private function applySession(int $orderId, array $session): bool
    {
        return (bool) $this->tx(function (PDO $pdo) use ($orderId, $session): bool {
            $stmt = $pdo->prepare(
                'SELECT id, total, status, payment_method, payment_status, paymongo_checkout_session_id
                 FROM orders WHERE id = ? FOR UPDATE'
            );
            $stmt->execute([$orderId]);
            $order = $stmt->fetch();
            
            if (!$order || $order['payment_method'] !== 'paymongo') {
                return false;
            }

            if (!self::sessionModeMatches($session)) {
                $this->log('missing or mismatched session mode on order ' . $orderId);
                return false;
            }

            if ($order['payment_status'] === 'paid') {
                return true;
            }
            if (
                $order['payment_status'] !== 'pending'
                || ($session['id'] ?? null) !== $order['paymongo_checkout_session_id']
            ) {
                return false;
            }
            
            $attributes = is_array($session['attributes'] ?? null)
            ? $session['attributes']
             : [];

            $reference = $attributes['reference_number'] ?? null;

            if (
                !is_string($reference)
                || $reference !== self::reference($orderId)
            ) {
                $this->log('missing or mismatched reference on order ' . $orderId);
                return false;
            }

            $payment = null;
            foreach ((array) ($attributes['payments'] ?? []) as $candidate) {
                $fields = is_array($candidate) ? ($candidate['attributes'] ?? $candidate) : [];
                if (is_array($fields) && ($fields['status'] ?? '') === 'paid') {
                    $payment = $fields;
                    break;
                }
            }
            if ($payment === null) {
                return false;
            }

            $expected = self::cents((string) $order['total']);
            $amount = $payment['amount'] ?? null;
            $currency = $payment['currency'] ?? null;

            if (
                !is_int($amount)
                || $amount !== $expected
                || !is_string($currency)
                || strtoupper($currency) !== 'PHP'
            ) {
                // Never mark an order paid for incomplete or mismatched payment data.
                $this->log('missing or mismatched amount/currency on order ' . $orderId);
                return false;
            }

            $intent = $payment['payment_intent_id'] ?? ($attributes['payment_intent'] ?? null);
            $intentId = is_array($intent) ? ($intent['id'] ?? null) : $intent;
            if (!is_string($intentId) || preg_match('/^pi_[A-Za-z0-9_-]+$/D', $intentId) !== 1) {
                $intentId = null;
            }

            $pdo->prepare(
                "UPDATE orders SET payment_status = 'paid', paid_at = NOW(),
                        paymongo_payment_intent_id = COALESCE(?, paymongo_payment_intent_id), updated_at = NOW()
                 WHERE id = ?"
            )->execute([$intentId, $orderId]);

            $cancelled = $order['status'] === 'cancelled';
            AuditService::record(
                $pdo,
                null,
                $cancelled ? 'payment.paid_after_cancel' : 'payment.paid',
                'order',
                $orderId,
                ['amount_centavos' => $expected, 'refund_needed' => $cancelled]
            );

            return true;
        });
    }

    /**
     * Cancel an order whose online payment did not happen, returning its stock.
     * An order an administrator already cancelled only gets its payment status closed
     * (administrator cancellation does not touch stock).
     *
     * @param 'failed'|'expired' $paymentStatus
     * @param int[] $cartIds converted carts to reactivate (checkout could not start payment)
     */
    public function cancelUnpaid(int $orderId, string $paymentStatus, array $cartIds = [], ?int $actorId = null): bool
    {
        if (!in_array($paymentStatus, ['failed', 'expired'], true)) {
            throw new InvalidArgumentException('Unknown payment status.');
        }

        return (bool) $this->tx(function (PDO $pdo) use ($orderId, $paymentStatus, $cartIds, $actorId): bool {
            $stmt = $pdo->prepare(
                'SELECT id, user_id, status, payment_method, payment_status FROM orders WHERE id = ? FOR UPDATE'
            );
            $stmt->execute([$orderId]);
            $order = $stmt->fetch();

            if (
                !$order
                || $order['payment_method'] !== 'paymongo'
                || $order['payment_status'] !== 'pending'
                || !in_array($order['status'], ['pending', 'cancelled'], true)
            ) {
                return false;
            }

            if ($order['status'] === 'pending') {
                $items = $pdo->prepare(
                    'SELECT product_id, quantity FROM order_items
                     WHERE order_id = ? AND product_id IS NOT NULL ORDER BY product_id'
                );
                $items->execute([$orderId]);
                $restock = $pdo->prepare('UPDATE products SET stock_qty = stock_qty + ?, updated_at = NOW() WHERE id = ?');
                foreach ($items->fetchAll() as $item) {
                    $restock->execute([(int) $item['quantity'], (int) $item['product_id']]);
                }
                $pdo->prepare("UPDATE orders SET status = 'cancelled', payment_status = ?, updated_at = NOW() WHERE id = ?")
                    ->execute([$paymentStatus, $orderId]);
            } else {
                $pdo->prepare('UPDATE orders SET payment_status = ?, updated_at = NOW() WHERE id = ?')
                    ->execute([$paymentStatus, $orderId]);
            }

            $cartIds = array_values(array_filter(array_map('intval', $cartIds), fn (int $id): bool => $id > 0));
            if ($cartIds) {
                $marks = implode(',', array_fill(0, count($cartIds), '?'));
                $pdo->prepare("UPDATE carts SET status = 'active', updated_at = NOW() WHERE user_id = ? AND status = 'converted' AND id IN ($marks)")
                    ->execute([(int) $order['user_id'], ...$cartIds]);
            }

            AuditService::record($pdo, $actorId, 'payment.' . $paymentStatus, 'order', $orderId, ['stock_returned' => $order['status'] === 'pending']);

            return true;
        });
    }

    private static function reference(int $orderId): string
    {
        return 'AVELA-' . $orderId;
    }

    /** Order total or unit price ("123.45") to integer centavos. */
    private static function cents(string $value): int
    {
        if (preg_match('/^([0-9]{1,8})\.([0-9]{2})$/D', $value, $parts) !== 1) {
            throw new RuntimeException('Invalid order amount.');
        }

        return (int) $parts[1] * 100 + (int) $parts[2];
    }

    /** @return array<int, array{name: string, amount: int, quantity: int, currency: string}> */
    private function lineItems(int $orderId): array
    {
        $stmt = $this->pdo()->prepare(
            'SELECT product_name_snapshot, quantity, unit_price_snapshot FROM order_items WHERE order_id = ? ORDER BY id'
        );
        $stmt->execute([$orderId]);

        $items = [];
        foreach ($stmt->fetchAll() as $row) {
            $items[] = [
                'name' => (string) $row['product_name_snapshot'],
                'amount' => self::cents((string) $row['unit_price_snapshot']),
                'quantity' => (int) $row['quantity'],
                'currency' => 'PHP',
            ];
        }

        return $items;
    }

    /** APP_URL must be the public address of the site (include any sub-folder, no trailing slash). */
    private function returnUrl(string $path, int $orderId): string
    {
        return rtrim((string) Env::get('APP_URL', ''), '/') . $path . '?id=' . $orderId;
    }

    private function loadOrder(int $orderId, ?int $userId = null): ?array
    {
        $stmt = $this->pdo()->prepare(
            'SELECT * FROM orders WHERE id = ?' . ($userId !== null ? ' AND user_id = ?' : '')
        );
        $stmt->execute($userId !== null ? [$orderId, $userId] : [$orderId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    private function pdo(): PDO
    {
        return $this->connection ?? Database::connection();
    }

    private function tx(callable $operation): mixed
    {
        $pdo = $this->pdo();
        $pdo->beginTransaction();
        try {
            $result = $operation($pdo);
            $pdo->commit();

            return $result;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /** Operational notes only: ids and error text, never request bodies, keys or customer details. */
    private function log(string $message): void
    {
        $dir = dirname(__DIR__, 2) . '/storage/logs';
        if (is_dir($dir)) {
            @file_put_contents($dir . '/payments.log', sprintf("[%s] %s\n", date('c'), $message), FILE_APPEND | LOCK_EX);
        }
    }
}
