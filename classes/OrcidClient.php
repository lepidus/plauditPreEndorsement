<?php

namespace APP\plugins\generic\plauditPreEndorsement\classes;

use APP\core\Application;
use APP\plugins\generic\plauditPreEndorsement\classes\api\APIKeyEncryption;

class OrcidClient
{
    public const ORCID_URL = 'https://orcid.org/';
    public const ORCID_URL_SANDBOX = 'https://sandbox.orcid.org/';
    public const ORCID_API_URL_PUBLIC = 'https://pub.orcid.org/';
    public const ORCID_API_URL_PUBLIC_SANDBOX = 'https://pub.sandbox.orcid.org/';
    public const ORCID_API_URL_MEMBER = 'https://api.orcid.org/';
    public const ORCID_API_URL_MEMBER_SANDBOX = 'https://api.sandbox.orcid.org/';
    public const ORCID_API_SCOPE_PUBLIC = '/authenticate';
    public const ORCID_API_SCOPE_MEMBER = '/activities/update';

    private $plugin;
    private $contextId;

    public function __construct($plugin, $contextId)
    {
        $this->plugin = $plugin;
        $this->contextId = $contextId;
    }

    public function getReadPublicAccessToken(): string
    {
        $httpClient = Application::get()->getHttpClient();

        $tokenUrl = $this->plugin->getSetting($this->contextId, 'orcidAPIPath') . 'oauth/token';
        $requestHeaders = ['Accept' => 'application/json'];
        $requestData = [
            'client_id' => APIKeyEncryption::decryptString($this->plugin->getSetting($this->contextId, 'orcidClientId')),
            'client_secret' => APIKeyEncryption::decryptString($this->plugin->getSetting($this->contextId, 'orcidClientSecret')),
            'grant_type' => 'client_credentials',
            'scope' => '/read-public'
        ];

        $response = $httpClient->request(
            'POST',
            $tokenUrl,
            [
                'headers' => $requestHeaders,
                'form_params' => $requestData,
            ]
        );

        $responseJson = json_decode($response->getBody(), true);
        return $responseJson['access_token'];
    }

    public function getOrcidRecord(string $orcid, string $accessToken): array
    {
        $httpClient = Application::get()->getHttpClient();

        $recordUrl = $this->plugin->getSetting($this->contextId, 'orcidAPIPath') . 'v3.0/' . urlencode($orcid) . '/record';
        $response = $httpClient->request(
            'GET',
            $recordUrl,
            [
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => 'Bearer ' . $accessToken,
                ],
            ]
        );

        return json_decode($response->getBody(), true);
    }

    public function requestOrcid(string $code)
    {
        $httpClient = Application::get()->getHttpClient();

        $tokenUrl = $this->plugin->getSetting($this->contextId, 'orcidAPIPath') . 'oauth/token';
        $requestHeaders = ['Accept' => 'application/json'];
        $requestData = [
            'client_id' => APIKeyEncryption::decryptString($this->plugin->getSetting($this->contextId, 'orcidClientId')),
            'client_secret' => APIKeyEncryption::decryptString($this->plugin->getSetting($this->contextId, 'orcidClientSecret')),
            'grant_type' => 'authorization_code',
            'code' => $code
        ];

        $response = $httpClient->request(
            'POST',
            $tokenUrl,
            [
                'headers' => $requestHeaders,
                'form_params' => $requestData,
            ]
        );

        $responseJson = json_decode($response->getBody(), true);
        return $responseJson['orcid'];
    }

    public function getOrcidWorks(string $orcid, string $accessToken): array
    {
        $httpClient = Application::get()->getHttpClient();

        $worksUrl = $this->plugin->getSetting($this->contextId, 'orcidAPIPath') . 'v3.0/' . urlencode($orcid) . '/works';
        $response = $httpClient->request(
            'GET',
            $worksUrl,
            [
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => 'Bearer ' . $accessToken,
                ],
            ]
        );

        return json_decode($response->getBody(), true);
    }

    public function recordHasWorks(array $worksResponse): bool
    {
        return !empty($worksResponse['group']);
    }

    public function getFullNameFromRecord(array $recordResponse): string
    {
        $givenName = $recordResponse['person']['name']['given-names']['value'];
        $familyName = $recordResponse['person']['name']['family-name']['value'];

        return "$givenName $familyName";
    }

    public function buildOAuthUrl($redirectParams)
    {
        $request = Application::get()->getRequest();

        if ($this->isMemberApiEnabled($this->contextId)) {
            $scope = self::ORCID_API_SCOPE_MEMBER;
        } else {
            $scope = self::ORCID_API_SCOPE_PUBLIC;
        }

        $redirectUrl = $request->getDispatcher()->url(
            $request,
            Application::ROUTE_PAGE,
            null,
            $this->plugin::HANDLER_PAGE,
            'orcidVerify',
            null,
            $redirectParams
        );

        return $this->getOauthPath() . 'authorize?' . http_build_query(
            array(
                'client_id' => APIKeyEncryption::decryptString($this->plugin->getSetting($this->contextId, 'orcidClientId')),
                'response_type' => 'code',
                'scope' => $scope,
                'redirect_uri' => $redirectUrl)
        );
    }

    private function isMemberApiEnabled()
    {
        $apiUrl = $this->plugin->getSetting($this->contextId, 'orcidAPIPath');
        return ($apiUrl == self::ORCID_API_URL_MEMBER || $apiUrl == self::ORCID_API_URL_MEMBER_SANDBOX);
    }

    private function getOauthPath()
    {
        return $this->getOrcidUrl() . 'oauth/';
    }

    private function getOrcidUrl()
    {
        $apiPath = $this->plugin->getSetting($this->contextId, 'orcidAPIPath');
        if ($apiPath == self::ORCID_API_URL_PUBLIC || $apiPath == self::ORCID_API_URL_MEMBER) {
            return self::ORCID_URL;
        } else {
            return self::ORCID_URL_SANDBOX;
        }
    }
}
