<?php

namespace APP\plugins\generic\plauditPreEndorsement\classes;

use APP\core\Application;
use APP\plugins\generic\plauditPreEndorsement\classes\EndorsementStatus;

class PlauditClient
{
    private const PLAUDIT_API_URL = 'https://plaudit.pub/api/v1/endorsements';

    public function filterOrcidNumbers(string $orcid): string
    {
        preg_match("~\d{4}-\d{4}-\d{4}-\d{3}(\d|X)~", $orcid, $matches);
        return $matches[0];
    }

    public function requestEndorsementCreation($endorsement, $publication, $secretKey)
    {
        $httpClient = Application::get()->getHttpClient();
        $headers = ['Content-Type' => 'application/json'];

        $orcid = $this->filterOrcidNumbers($endorsement->getOrcid());
        $postData = [
            'secret_key' => $secretKey,
            'orcid' => $orcid,
            'doi' => $publication->getDoi()
        ];

        $response = $httpClient->request(
            'POST',
            self::PLAUDIT_API_URL,
            [
                'headers' => $headers,
                'json' => $postData,
            ]
        );

        return $response;
    }

    public function getEndorsementStatusByResponse($response, $publication, $endorsement)
    {
        if ($response->getStatusCode() == 200) {
            $body = json_decode($response->getBody()->getContents(), true);

            $endorsementData = $body['endorsements'][0];
            $responseDoi = $endorsementData['doi'];
            $responseOrcid = $endorsementData['orcid'];
            $publicationDoi = strtolower($publication->getDoi());
            $endorsementOrcid = $this->filterOrcidNumbers($endorsement->getOrcid());

            if ($responseDoi ==  $publicationDoi && $responseOrcid == $endorsementOrcid) {
                return EndorsementStatus::COMPLETED;
            }
        }

        return EndorsementStatus::COULDNT_COMPLETE;
    }
}
