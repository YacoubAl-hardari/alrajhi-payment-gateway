<?php

namespace AlRajhi\PaymentGateway\Helpers;

use AlRajhi\PaymentGateway\Exceptions\EncryptionException;
use Illuminate\Support\Facades\Log;

class EncryptionHelper
{
    protected string $resourceKey;

    protected string $iv;

    protected string $algorithm;

    public function __construct()
    {
        $base = implode('', ['al', 'rajhi']);
        $this->resourceKey = (string) config($base . '.credentials.resource_key', '');
        $this->iv = (string) config($base . '.encryption.iv', 'PGKEYENCDECIVSPC');
        $this->algorithm = (string) config($base . '.encryption.algorithm', 'AES-256-CBC');
    }

    public function encrypt(string $data): string
    {
        try {
            $encodedData = urlencode($data);

            $encryptedRaw = openssl_encrypt(
                $encodedData,
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

    public function decrypt(string $encryptedHex): string
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

            return urldecode($decrypted);
        } catch (\Throwable $e) {
            Log::error('Decryption error', [
                'message' => $e->getMessage(),
            ]);

            throw new EncryptionException($e->getMessage());
        }
    }
}
