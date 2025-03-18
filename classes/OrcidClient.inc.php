<?php

class OrcidClient
{
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
            'client_id' => $this->plugin->getSetting($this->contextId, 'orcidClientId'),
            'client_secret' => $this->plugin->getSetting($this->contextId, 'orcidClientSecret'),
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

    public function requestOrcid(string $code)
    {
        $httpClient = Application::get()->getHttpClient();

        $tokenUrl = $this->plugin->getSetting($this->contextId, 'orcidAPIPath') . 'oauth/token';
        $requestHeaders = ['Accept' => 'application/json'];
        $requestData = [
            'client_id' => $this->plugin->getSetting($this->contextId, 'orcidClientId'),
            'client_secret' => $this->plugin->getSetting($this->contextId, 'orcidClientSecret'),
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
}
