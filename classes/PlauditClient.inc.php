<?php

import('plugins.generic.plauditPreEndorsement.PlauditPreEndorsementPlugin');

define('PLAUDIT_API_URL', 'https://scielo-preprints-review.plaudit.pub/api/v1/endorsements');

class PlauditClient
{
    private function filterOrcidNumbers(string $orcid): string
    {
        preg_match("~\d{4}-\d{4}-\d{4}-\d{3}(\d|X|x)~", $orcid, $matches);
        return strtolower($matches[0]);
    }

    public function requestEndorsementCreation($publication, $secretKey)
    {
        $httpClient = Application::get()->getHttpClient();
        $headers = ['Content-Type' => 'application/json'];

        $orcid = $this->filterOrcidNumbers($publication->getData('endorserOrcid'));
        $postData = [
            'secret_key' => $secretKey,
            'orcid' => $orcid,
            'doi' => $publication->getData('pub-id::doi')
        ];

        $response = $httpClient->request(
            'POST',
            PLAUDIT_API_URL,
            [
                'headers' => $headers,
                'json' => $postData,
            ]
        );

        return $response;
    }

    public function getEndorsementStatusByResponse($response, $publication)
    {
        if ($response->getStatusCode() == 200) {
            $body = json_decode($response->getBody()->getContents(), true);

            $endorsementData = $body['endorsements'][0];
            $responseDoi = $endorsementData['doi'];
            $responseOrcid = $endorsementData['orcid'];
            $publicationDoi = strtolower($publication->getData('pub-id::doi'));
            $publicationOrcid = $this->filterOrcidNumbers($publication->getData('endorserOrcid'));

            if ($responseDoi ==  $publicationDoi && $responseOrcid == $publicationOrcid) {
                return ENDORSEMENT_STATUS_COMPLETED;
            }
        }

        return ENDORSEMENT_STATUS_COULDNT_COMPLETE;
    }
}
