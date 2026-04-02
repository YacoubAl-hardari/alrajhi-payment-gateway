<?php

namespace AlRajhi\PaymentGateway\Helpers;

use AlRajhi\PaymentGateway\Exceptions\EncryptionException;
use Illuminate\Support\Facades\Log;

class EncryptionHelper
{
    protected string $resourceKey;

    protected string $iv;

    protected string $algorithm;

    protected bool $urlEncodeBeforeEncrypt;

    protected bool $urlDecodeAfterDecrypt;

    public function __construct()
    {
        $base = implode('', ['al', 'rajhi']);
        $this->resourceKey = (string) config($base . '.credentials.resource_key', '');
        $this->iv = (string) config($base . '.encryption.iv', 'PGKEYENCDECIVSPC');
        $this->algorithm = (string) config($base . '.encryption.algorithm', 'AES-256-CBC');
        $this->urlEncodeBeforeEncrypt = $this->toBool(config($base . '.encryption.url_encode_before_encrypt', true));
        $this->urlDecodeAfterDecrypt = $this->toBool(config($base . '.encryption.url_decode_after_decrypt', true));
    }

    public function encrypt(string $data, ?bool $urlEncodeBeforeEncrypt = null): string
    {
        try {
            $shouldUrlEncode = $urlEncodeBeforeEncrypt ?? $this->urlEncodeBeforeEncrypt;
            $plainText = $shouldUrlEncode ? urlencode($data) : $data;

            $encryptedRaw = openssl_encrypt(
                $plainText,
                $this->algorithm,
                $this->resourceKey,
                OPENSSL_RAW_DATA,
                $this->iv
            );

            if ($encryptedRaw === false) {
                throw new EncryptionException('Encryption failed: ' . (openssl_error_string() ?: 'unknown error'));
            }

            return strtoupper(bin2hex($encryptedRaw));
        } catch (\Throwable $e) {
            Log::error('Encryption error', [
                'message' => $e->getMessage(),
            ]);

            throw new EncryptionException($e->getMessage());
        }
    }

    public function decrypt(string $encryptedHex, ?bool $urlDecodeAfterDecrypt = null): string
    {
        try {
            $encryptedRaw = hex2bin($encryptedHex);

            if ($encryptedRaw === false) {
                throw new EncryptionException('Invalid encrypted hex payload');
            }

            $decrypted = openssl_decrypt(
                $encryptedRaw,
                $this->algorithm,
                $this->resourceKey,
                OPENSSL_RAW_DATA,
                $this->iv
            );

            if ($decrypted === false) {
                throw new EncryptionException('Decryption failed: ' . (openssl_error_string() ?: 'unknown error'));
            }

            $shouldUrlDecode = $urlDecodeAfterDecrypt ?? $this->urlDecodeAfterDecrypt;

            return $shouldUrlDecode ? urldecode($decrypted) : $decrypted;
        } catch (\Throwable $e) {
            Log::error('Decryption error', [
                'message' => $e->getMessage(),
            ]);

            throw new EncryptionException($e->getMessage());
        }
    }

    public function usesUrlEncodingBeforeEncrypt(): bool
    {
        return $this->urlEncodeBeforeEncrypt;
    }

    protected function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $normalized = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

        return $normalized ?? false;
    }
}
