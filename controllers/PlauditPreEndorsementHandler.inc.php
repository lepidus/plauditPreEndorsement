<?php

import('classes.handler.Handler');
import('plugins.generic.plauditPreEndorsement.PlauditPreEndorsementPlugin');

define('AUTH_SUCCESS', 'success');
define('AUTH_INVALID_TOKEN', 'invalid_token');
define('AUTH_ACCESS_DENIED', 'access_denied');


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

        $plugin = new PlauditPreEndorsementPlugin();
        $plugin->sendEmailToEndorser($publication);

        return http_response_code(200);
    }

    public function orcidVerify($args, $request)
    {
        $publicationDao = DAORegistry::getDAO('PublicationDAO');
        $publication = $publicationDao->getById($request->getUserVar('state'));

        $templateMgr = TemplateManager::getManager($request);
        $plugin = PluginRegistry::getPlugin('generic', 'plauditpreendorsementplugin');
		$templatePath = $plugin->getTemplateResource('orcidVerify.tpl');

        $statusAuth = $this->getStatusAuthentication($publication, $request);
        if($statusAuth == AUTH_SUCCESS){
            $publication->setData('confirmedEndorsement', true);
            $publicationDao->updateObject($publication);
            $templateMgr->assign('verifySuccess', true);
        }
        else if($statusAuth == AUTH_INVALID_TOKEN) {
            $templateMgr->assign('invalidToken', true);
        }
        else {
            $templateMgr->assign('denied', true);
        }

        $templateMgr->display($templatePath);
    }

    public function getStatusAuthentication($publication, $request)
    {
        if ($request->getUserVar('token') != $publication->getData('endorserEmailToken')) {
			return AUTH_INVALID_TOKEN;
		}
        else if($request->getUserVar('error') == 'access_denied'){
            return AUTH_ACCESS_DENIED;
        }
        else{
            return AUTH_SUCCESS;
        }
    }
}