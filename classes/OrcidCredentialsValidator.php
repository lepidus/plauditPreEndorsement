<?php

namespace APP\plugins\generic\plauditPreEndorsement\classes;

class OrcidCredentialsValidator
{
    public function validateClientId($str): bool
    {
        $valid = false;
        if (preg_match('/^APP-[\da-zA-Z]{16}|(\d{4}-){3,}\d{3}[\dX]/', $str) == 1) {
            $valid = true;
        }
        return $valid;
    }

    public function validateClientSecret($str): bool
    {
        $valid = false;
        if (preg_match('/^(\d|-|[a-f]){36,64}/', $str) == 1) {
            $valid = true;
        }
        return $valid;
    }

    public function validateApiPath($apiPath): bool
    {
        $allowedApiPaths = [
            OrcidClient::ORCID_API_URL_PUBLIC,
            OrcidClient::ORCID_API_URL_PUBLIC_SANDBOX,
            OrcidClient::ORCID_API_URL_MEMBER,
            OrcidClient::ORCID_API_URL_MEMBER_SANDBOX,
        ];

        return is_string($apiPath) && in_array($apiPath, $allowedApiPaths, true);
    }

    public static function hasCredentials($plugin, $contextId): bool
    {
        $hasClientId = !empty($plugin->getSetting($contextId, 'orcidClientId'));
        $hasClientSecret = !empty($plugin->getSetting($contextId, 'orcidClientSecret'));
        return $hasClientId && $hasClientSecret;
    }
}
