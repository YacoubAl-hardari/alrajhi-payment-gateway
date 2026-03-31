<?php

namespace AlRajhi\PaymentGateway\Support;

class TransactionStatusResolver
{
    public function normalize(mixed $status): ?string
    {
        if ($status === null) {
            return null;
        }

        $normalizedStatus = strtoupper(trim((string) $status));

        return $normalizedStatus !== '' ? $normalizedStatus : null;
    }

    public function isSuccessful(?string $normalizedStatus): bool
    {
        return in_array($normalizedStatus, ['SUCCESS', 'APPROVED', 'CAPTURED', 'PAID', 'VOIDED'], true);
    }

    public function isFailure(?string $normalizedStatus): bool
    {
        return in_array($normalizedStatus, ['FAILED', 'DECLINED', 'ERROR', 'NOT CAPTURED', 'NOT_CAPTURED'], true);
    }

    public function isCancelled(?string $normalizedStatus): bool
    {
        return in_array($normalizedStatus, ['CANCELLED', 'CANCELED'], true);
    }

    public function isCaptured(?string $normalizedStatus): bool
    {
        return $normalizedStatus === 'CAPTURED';
    }

    public function isAuthorized(?string $normalizedStatus): bool
    {
        return in_array($normalizedStatus, ['AUTHORIZED', 'AUTHORISED', 'APPROVED'], true);
    }

    public function isPending(?string $normalizedStatus): bool
    {
        return in_array($normalizedStatus, ['PENDING', 'PROCESSING', 'IN_PROGRESS'], true);
    }

    public function isVoided(?string $normalizedStatus): bool
    {
        return $normalizedStatus === 'VOIDED';
    }
}
