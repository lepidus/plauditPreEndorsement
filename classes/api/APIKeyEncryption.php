<?php

namespace APP\plugins\generic\plauditPreEndorsement\classes\api;

use Firebase\JWT\JWT;
use PKP\config\Config;
use Exception;

class APIKeyEncryption
{
    public static function secretConfigExists(): bool
    {
        try {
            self::getSecretFromConfig();
        } catch (Exception $e) {
            return false;
        }
        return true;
    }

    private static function getSecretFromConfig(): string
    {
        $secret = Config::getVar('security', 'api_key_secret');
        if ($secret === "") {
            throw new Exception("A secret must be set in the config file ('api_key_secret') so that keys can be encrypted and decrypted");
        }
        return $secret;
    }

    public static function encryptString(string $plainText): string
    {
        $secret = self::getSecretFromConfig();
        return JWT::encode($plainText, $secret, 'HS256');
    }

    public static function decryptString(string $encryptedText)
    {
        $secret = self::getSecretFromConfig();
        try {
            return JWT::decode($encryptedText, $secret, ['HS256']);
        } catch (Exception $e) {
            if ($e instanceof Firebase\JWT\SignatureInvalidException) {
                throw new Exception(
                    'The `api_key_secret` configuration is not the same as the one used to encrypt the key.',
                    1
                );
            }

            throw $e;
        }
    }
}
