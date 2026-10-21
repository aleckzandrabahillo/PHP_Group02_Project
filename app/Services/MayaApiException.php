<?php
declare(strict_types=1);
namespace App\Services;

/** Safe diagnostic fields only; never retain provider bodies, credentials or buyer/card data. */
final class MayaApiException extends \RuntimeException
{
    public readonly ?string $providerCode;

    public function __construct(public readonly int $httpStatus, mixed $providerCode = null)
    {
        $this->providerCode = is_string($providerCode) && preg_match('/^PY[0-9]{4}$/D', $providerCode) === 1 ? $providerCode : null;
        parent::__construct('Maya Sandbox could not complete the request.', $httpStatus);
    }
}
