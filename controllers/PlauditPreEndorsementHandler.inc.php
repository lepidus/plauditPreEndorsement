<?php

import('classes.handler.Handler');

class PlauditPreEndorsementHandler extends Handler
{
    public function updateEndorser($args, $request)
    {
        $submissionId = $args['submissionId'];
        $endorserName = $args['endorserName'];
        $endorserEmail = $args['endorserEmail'];
        
        $submission = DAORegistry::getDAO('SubmissionDAO')->getById($submissionId);
        $publication = $submission->getCurrentPublication();

        $publication->setData('endorserName', $endorserName);
        $publication->setData('endorserEmail', $endorserEmail);
        $publicationDao = DAORegistry::getDAO('PublicationDAO');
        $publicationDao->updateObject($publication);

        return http_response_code(200);
    }
}