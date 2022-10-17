<?php

import('classes.handler.Handler');

class PlauditPreEndorsementHandler extends Handler
{
    public function updateEndorserEmail($args, $request)
    {
        $submissionId = $args['submissionId'];
        $endorserEmail = $args['endorserEmail'];
        
        $submission = DAORegistry::getDAO('SubmissionDAO')->getById($submissionId);
        $publication = $submission->getCurrentPublication();

        $publication->setData('endorserEmail', $endorserEmail);
        $publicationDao = DAORegistry::getDAO('PublicationDAO');
        $publicationDao->updateObject($publication);

        return http_response_code(200);
    }
}