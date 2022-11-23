<?php

import('plugins.generic.plauditPreEndorsement.PlauditPreEndorsementPlugin');

class PlauditClient
{
    public function getEndorsementStatusByResponse($response, $publication)
    {
        if ($response->getStatusCode() == 200) {
            $body = json_decode($response->getBody(), true);

            $endorsementData = $body['endorsements'][0];
            $responseDoi = $endorsementData['doi'];
            $responseOrcid = $endorsementData['orcid'];
            
            if ($responseDoi == $publication->getData('pub-id::doi') && $responseOrcid == $publication->getData('endorserOrcid')) {
                return ENDORSEMENT_STATUS_COMPLETED;
            }
        }

        return ENDORSEMENT_STATUS_COULDNT_COMPLETE;
    }
}
