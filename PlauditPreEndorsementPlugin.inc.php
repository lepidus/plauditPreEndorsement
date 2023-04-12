<?php

/**
 * @file plugins/generic/plaudit/PlauditPreEndorsementPlugin.inc.php
 *
 * Copyright (c) 2022 Lepidus Tecnologia
 * Distributed under the GNU GPL v3. For full terms see LICENSE or https://www.gnu.org/licenses/gpl-3.0.txt.
 *
 * @class PlauditPreEndorsementPlugin
 * @ingroup plugins_generic_plauditPreEndorsement
 * @brief Plaudit Pre-Endorsement Plugin
 */

use GuzzleHttp\Exception\ClientException;

import('lib.pkp.classes.plugins.GenericPlugin');
import('plugins.generic.plauditPreEndorsement.classes.PlauditClient');

define('ENDORSEMENT_ORCID_URL', 'https://orcid.org/');
define('ENDORSEMENT_ORCID_URL_SANDBOX', 'https://sandbox.orcid.org/');
define('ENDORSEMENT_ORCID_API_URL_PUBLIC', 'https://pub.orcid.org/');
define('ENDORSEMENT_ORCID_API_URL_PUBLIC_SANDBOX', 'https://pub.sandbox.orcid.org/');
define('ENDORSEMENT_ORCID_API_URL_MEMBER', 'https://api.orcid.org/');
define('ENDORSEMENT_ORCID_API_URL_MEMBER_SANDBOX', 'https://api.sandbox.orcid.org/');
define('ENDORSEMENT_ORCID_API_SCOPE_PUBLIC', '/authenticate');
define('ENDORSEMENT_ORCID_API_SCOPE_MEMBER', '/activities/update');

define('ENDORSEMENT_STATUS_NOT_CONFIRMED', 0);
define('ENDORSEMENT_STATUS_CONFIRMED', 1);
define('ENDORSEMENT_STATUS_DENIED', 2);
define('ENDORSEMENT_STATUS_COMPLETED', 3);
define('ENDORSEMENT_STATUS_COULDNT_COMPLETE', 4);

class PlauditPreEndorsementPlugin extends GenericPlugin
{
    public const HANDLER_PAGE = 'pre-endorsement-handler';

    public function register($category, $path, $mainContextId = null)
    {
        $success = parent::register($category, $path, $mainContextId);

        if (!Config::getVar('general', 'installed') || defined('RUNNING_UPGRADE')) {
            return true;
        }

        if ($success && $this->getEnabled($mainContextId)) {
            HookRegistry::register('Templates::Submission::SubmissionMetadataForm::AdditionalMetadata', array($this, 'addEndorserFieldsToStep3'));

            HookRegistry::register('submissionsubmitstep3form::readuservars', array($this, 'allowStep3FormToReadOurFields'));
            HookRegistry::register('submissionsubmitstep3form::execute', array($this, 'step3SaveEndorserEmail'));
            HookRegistry::register('submissionsubmitstep4form::execute', array($this, 'step4SendEmailToEndorser'));
            HookRegistry::register('Schema::get::publication', array($this, 'addOurFieldsToPublicationSchema'));
            HookRegistry::register('Template::Workflow::Publication', array($this, 'addEndorserFieldsToWorkflow'));
            HookRegistry::register('Publication::publish', array($this, 'sendEndorsementOnPosting'));
            HookRegistry::register('LoadHandler', array($this, 'setupPlauditPreEndorsementHandler'));
        }

        return $success;
    }

    public function setupPlauditPreEndorsementHandler($hookName, $params)
    {
        $page = $params[0];
        if ($this->getEnabled() && $page == self::HANDLER_PAGE) {
            $this->import('classes/PlauditPreEndorsementHandler');
            define('HANDLER_CLASS', 'PlauditPreEndorsementHandler');
            return true;
        }
        return false;
    }

    public function getDisplayName()
    {
        return __('plugins.generic.plauditPreEndorsement.displayName');
    }

    public function getDescription()
    {
        return __('plugins.generic.plauditPreEndorsement.description');
    }

    public function writeOnActivityLog($submission, $message, $messageParams = array())
    {
        $request = Application::get()->getRequest();
        SubmissionLog::logEvent($request, $submission, SUBMISSION_LOG_METADATA_UPDATE, $message, $messageParams);
    }

    public function addEndorserFieldsToStep3($hookName, $params)
    {
        $smarty = &$params[1];
        $output = &$params[2];

        $submissionId = $smarty->smarty->get_template_vars('submissionId');
        $submission = DAORegistry::getDAO('SubmissionDAO')->getById($submissionId);
        $publication = $submission->getCurrentPublication();

        $smarty->assign('endorserName', $publication->getData('endorserName'));
        $smarty->assign('endorserEmail', $publication->getData('endorserEmail'));

        $output .= $smarty->fetch($this->getTemplateResource('endorserFieldStep3.tpl'));
        return false;
    }

    public function allowStep3FormToReadOurFields($hookName, $params)
    {
        $formFields = &$params[1];
        $ourFields = ['endorserName', 'endorserEmail'];

        $formFields = array_merge($formFields, $ourFields);
    }

    public function step3SaveEndorserEmail($hookName, $params)
    {
        $step3Form = $params[0];
        $publication = $step3Form->submission->getCurrentPublication();
        $endorserName = $step3Form->getData('endorserName');
        $endorserEmail = $step3Form->getData('endorserEmail');

        $publication->setData('endorserName', $endorserName);
        $publication->setData('endorserEmail', $endorserEmail);
        $publicationDao = DAORegistry::getDAO('PublicationDAO');
        $publicationDao->updateObject($publication);
    }

    public function step4SendEmailToEndorser($hookName, $params)
    {
        $step4Form = $params[0];
        $publication = $step4Form->submission->getCurrentPublication();

        if (!empty($publication->getData('endorserEmail'))) {
            $this->sendEmailToEndorser($publication);
        }
    }

    public function addOurFieldsToPublicationSchema($hookName, $params)
    {
        $schema = &$params[0];
        $ourFields = [
            'endorserName' => 'string',
            'endorserEmail' => 'string',
            'endorsementStatus' => 'integer',
            'endorserOrcid' => 'string',
            'endorserEmailToken' => 'string',
            'endorserEmailCount' => 'integer',
        ];

        foreach ($ourFields as $name => $type) {
            $schema->properties->{$name} = (object) [
                'type' => $type,
                'apiSummary' => true,
                'validation' => ['nullable'],
            ];
        }

        return false;
    }

    private function getEndorsementStatusSuffix($endorsementStatus): string
    {
        $mapStatusToSuffix = [
            ENDORSEMENT_STATUS_NOT_CONFIRMED => 'NotConfirmed',
            ENDORSEMENT_STATUS_CONFIRMED => 'Confirmed',
            ENDORSEMENT_STATUS_DENIED => 'Denied',
            ENDORSEMENT_STATUS_COMPLETED => 'Completed',
            ENDORSEMENT_STATUS_COULDNT_COMPLETE => 'CouldntComplete'
        ];

        return $mapStatusToSuffix[$endorsementStatus] ?? "";
    }

    public function addEndorserFieldsToWorkflow($hookName, $params)
    {
        $smarty = &$params[1];
        $output = &$params[2];

        $submission = $smarty->get_template_vars('submission');
        $publication = $submission->getCurrentPublication();

        $request = PKPApplication::get()->getRequest();
        $updateEndorserUrl = $request->getDispatcher()->url($request, ROUTE_PAGE, null, self::HANDLER_PAGE, 'updateEndorser');

        $endorsementStatus = $publication->getData('endorsementStatus');
        $endorsementStatusSuffix = $this->getEndorsementStatusSuffix($endorsementStatus);
        $canEditEndorsement = (is_null($endorsementStatus) || $endorsementStatus == ENDORSEMENT_STATUS_NOT_CONFIRMED || $endorsementStatus == ENDORSEMENT_STATUS_DENIED);
        $canSendEndorsementManually = $publication->getData('status') === STATUS_PUBLISHED
            && !$this->userAccessingIsAuthor($submission)
            && ($endorsementStatus == ENDORSEMENT_STATUS_CONFIRMED || $endorsementStatus == ENDORSEMENT_STATUS_COULDNT_COMPLETE);
        
        $smarty->assign([
            'submissionId' => $submission->getId(),
            'endorserName' => $publication->getData('endorserName'),
            'endorserEmail' => $publication->getData('endorserEmail'),
            'endorserOrcid' => $publication->getData('endorserOrcid'),
            'endorserEmailCount' => $publication->getData('endorserEmailCount'),
            'endorsementStatus' => $endorsementStatus,
            'endorsementStatusSuffix' => $endorsementStatusSuffix,
            'canEditEndorsement' => $canEditEndorsement,
            'canSendEndorsementManually' => $canSendEndorsementManually,
            'updateEndorserUrl' => $updateEndorserUrl,
            'sendEndorsementManuallyUrl' => $request->getDispatcher()->url($request, ROUTE_PAGE, null, self::HANDLER_PAGE, 'sendEndorsementManually')
        ]);

        $output .= sprintf(
            '<tab id="plauditPreEndorsement" label="%s">%s</tab>',
            __('plugins.generic.plauditPreEndorsement.preEndorsement'),
            $smarty->fetch($this->getTemplateResource('endorserFieldWorkflow.tpl'))
        );
    }

    public function sendEndorsementOnPosting($hookName, $params)
    {
        $publication = $params[0];
        $this->sendEndorsementToPlaudit($publication);
    }

    public function sendEndorsementToPlaudit($publication)
    {
        $request = PKPApplication::get()->getRequest();
        $contextId = $request->getContext()->getId();
        $submission = DAORegistry::getDAO('SubmissionDAO')->getById($publication->getData('submissionId'));

        $endorsementStatusOkay = ($publication->getData('endorsementStatus') == ENDORSEMENT_STATUS_CONFIRMED
            || $publication->getData('endorsementStatus') == ENDORSEMENT_STATUS_COULDNT_COMPLETE);
        $publicationHasDoi = !empty($publication->getData('pub-id::doi'));
        $secretKey = $this->getSetting($contextId, 'plauditAPISecret');

        if(!$publicationHasDoi) {
            $this->writeOnActivityLog($submission, 'plugins.generic.plauditPreEndorsement.log.failedEndorsementSending.doi');
            return;
        }
        else if(empty($secretKey)) {
            $this->writeOnActivityLog($submission, 'plugins.generic.plauditPreEndorsement.log.failedEndorsementSending.secretKey');
            return;
        }

        if ($endorsementStatusOkay) {
            $plauditClient = new PlauditClient();

            try {
                $response = $plauditClient->requestEndorsementCreation($publication, $secretKey);
                $newEndorsementStatus = $plauditClient->getEndorsementStatusByResponse($response, $publication);
            } catch (ClientException $exception) {
                $reason = print_r($exception->getResponse()->getBody(false), true);
                $this->writeOnActivityLog($submission, 'plugins.generic.plauditPreEndorsement.log.failedSendingEndorsement', ['reason' => $reason]);
                $newEndorsementStatus = ENDORSEMENT_STATUS_COULDNT_COMPLETE;
            }

            $publication->setData('endorsementStatus', $newEndorsementStatus);
            $publicationDao = DAORegistry::getDAO('PublicationDAO');
            $publicationDao->updateObject($publication);
        }
    }

    public function sendEmailToEndorser($publication, $endorserChanged = false)
    {
        $request = PKPApplication::get()->getRequest();
        $context = $request->getContext();
        $endorserName = $publication->getData('endorserName');
        $endorserEmail = $publication->getData('endorserEmail');

        if (!is_null($context) && !empty($endorserEmail)) {
            $emailTemplate = 'ORCID_REQUEST_ENDORSER_AUTHORIZATION';
            $email = $this->getMailTemplate($emailTemplate, $context);

            $email->setFrom($context->getData('contactEmail'), $context->getData('contactName'));
            $email->setRecipients([['name' => $endorserName, 'email' => $endorserEmail]]);

            $endorserEmailToken = md5(microtime() . $endorserEmail);
            $oauthUrl = $this->buildOAuthUrl(['token' => $endorserEmailToken, 'state' => $publication->getId()]);

            $email->sendWithParams([
                'orcidOauthUrl' => $oauthUrl,
                'endorserName' => htmlspecialchars($endorserName),
                'preprintTitle' => htmlspecialchars($publication->getLocalizedTitle()),
            ]);

            if(is_null($publication->getData('endorserEmailCount')) || $endorserChanged)
                $endorserEmailCount = 0;
            else
                $endorserEmailCount = $publication->getData('endorserEmailCount');

            $publication->setData('endorserEmailToken', $endorserEmailToken);
            $publication->setData('endorsementStatus', ENDORSEMENT_STATUS_NOT_CONFIRMED);
            $publication->setData('endorserEmailCount', $endorserEmailCount + 1);
            $publicationDao = DAORegistry::getDAO('PublicationDAO');
            $publicationDao->updateObject($publication);
            
            $submission = DAORegistry::getDAO('SubmissionDAO')->getById($publication->getData('submissionId'));
            $this->writeOnActivityLog($submission, 'plugins.generic.plauditPreEndorsement.log.sentEmailEndorser', ['endorserName' => $endorserName, 'endorserEmail' => $endorserEmail]);
        }
    }

    public function getInstallEmailTemplatesFile()
    {
        return $this->getPluginPath() . '/emailTemplates.xml';
    }

    private function getMailTemplate($emailKey, $context = null)
    {
        import('lib.pkp.classes.mail.MailTemplate');
        return new MailTemplate($emailKey, null, $context, false);
    }

    public function orcidIsGloballyConfigured()
    {
        $apiUrl = Config::getVar('orcid', 'api_url');
        $clientId = Config::getVar('orcid', 'client_id');
        $clientSecret = Config::getVar('orcid', 'client_secret');
        return isset($apiUrl) && trim($apiUrl) && isset($clientId) && trim($clientId) &&
            isset($clientSecret) && trim($clientSecret);
    }

    public function getActions($request, $actionArgs)
    {
        $router = $request->getRouter();
        import('lib.pkp.classes.linkAction.request.AjaxModal');
        return array_merge(
            array(
                new LinkAction(
                    'settings',
                    new AjaxModal($router->url($request, null, null, 'manage', null, array('verb' => 'settings', 'plugin' => $this->getName(), 'category' => 'generic')), $this->getDisplayName()),
                    __('manager.plugins.settings'),
                    null
                ),
            ),
            parent::getActions($request, $actionArgs)
        );
    }

    public function manage($args, $request)
    {
        $context = $request->getContext();
        $contextId = ($context == null) ? 0 : $context->getId();

        switch ($request->getUserVar('verb')) {
            case 'settings':
                $templateMgr = TemplateManager::getManager();
                $templateMgr->registerPlugin('function', 'plugin_url', array($this, 'smartyPluginUrl'));
                $apiOptions = [
                    ENDORSEMENT_ORCID_API_URL_PUBLIC => 'plugins.generic.plauditPreEndorsement.settings.orcidAPIPath.public',
                    ENDORSEMENT_ORCID_API_URL_PUBLIC_SANDBOX => 'plugins.generic.plauditPreEndorsement.settings.orcidAPIPath.publicSandbox',
                    ENDORSEMENT_ORCID_API_URL_MEMBER => 'plugins.generic.plauditPreEndorsement.settings.orcidAPIPath.member',
                    ENDORSEMENT_ORCID_API_URL_MEMBER_SANDBOX => 'plugins.generic.plauditPreEndorsement.settings.orcidAPIPath.memberSandbox'
                ];
                $templateMgr->assign('orcidApiUrls', $apiOptions);

                $this->import('PlauditPreEndorsementSettingsForm');
                $form = new PlauditPreEndorsementSettingsForm($this, $contextId);
                if ($request->getUserVar('save')) {
                    $form->readInputData();
                    if ($form->validate()) {
                        $form->execute();
                        return new JSONMessage(true);
                    }
                } else {
                    $form->initData();
                }
                return new JSONMessage(true, $form->fetch($request));
        }
        return parent::manage($args, $request);
    }

    public function buildOAuthUrl($redirectParams)
    {
        $request = PKPApplication::get()->getRequest();
        $contextId = $request->getContext()->getId();

        if ($this->isMemberApiEnabled($contextId)) {
            $scope = ENDORSEMENT_ORCID_API_SCOPE_MEMBER;
        } else {
            $scope = ENDORSEMENT_ORCID_API_SCOPE_PUBLIC;
        }

        $redirectUrl = $request->getDispatcher()->url(
            $request,
            ROUTE_PAGE,
            null,
            self::HANDLER_PAGE,
            'orcidVerify',
            null,
            $redirectParams
        );

        return $this->getOauthPath() . 'authorize?' . http_build_query(
            array(
                'client_id' => $this->getSetting($contextId, 'orcidClientId'),
                'response_type' => 'code',
                'scope' => $scope,
                'redirect_uri' => $redirectUrl)
        );
    }

    public function isMemberApiEnabled($contextId)
    {
        $apiUrl = $this->getSetting($contextId, 'orcidAPIPath');
        if ($apiUrl === ENDORSEMENT_ORCID_API_URL_MEMBER || $apiUrl === ENDORSEMENT_ORCID_API_URL_MEMBER_SANDBOX) {
            return true;
        } else {
            return false;
        }
    }

    public function getOauthPath()
    {
        return $this->getOrcidUrl() . 'oauth/';
    }

    public function getOrcidUrl()
    {
        $request = Application::get()->getRequest();
        $context = $request->getContext();
        $contextId = ($context == null) ? 0 : $context->getId();

        $apiPath = $this->getSetting($contextId, 'orcidAPIPath');
        if ($apiPath == ENDORSEMENT_ORCID_API_URL_PUBLIC || $apiPath == ENDORSEMENT_ORCID_API_URL_MEMBER) {
            return ENDORSEMENT_ORCID_URL;
        } else {
            return ENDORSEMENT_ORCID_URL_SANDBOX;
        }
    }

    public function userAccessingIsAuthor($submission): bool
    {
        $currentUser = Application::get()->getRequest()->getUser();
        $currentUserAssignedRoles = array();
        if ($currentUser) {
            $stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO');
            $stageAssignmentsResult = $stageAssignmentDao->getBySubmissionAndUserIdAndStageId($submission->getId(), $currentUser->getId(), $submission->getData('stageId'));
            $userGroupDao = DAORegistry::getDAO('UserGroupDAO');
            while ($stageAssignment = $stageAssignmentsResult->next()) {
                $userGroup = $userGroupDao->getById($stageAssignment->getUserGroupId(), $submission->getData('contextId'));
                $currentUserAssignedRoles[] = (int) $userGroup->getRoleId();
            }
        }

        return $currentUserAssignedRoles[0] == ROLE_ID_AUTHOR;
    }
}
