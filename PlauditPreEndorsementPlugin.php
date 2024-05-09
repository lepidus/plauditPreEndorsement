<?php

/**
 * @file plugins/generic/plaudit/PlauditPreEndorsementPlugin.inc.php
 *
 * Copyright (c) 2022 - 2024 Lepidus Tecnologia
 * Distributed under the GNU GPL v3. For full terms see LICENSE or https://www.gnu.org/licenses/gpl-3.0.txt.
 *
 * @class PlauditPreEndorsementPlugin
 * @ingroup plugins_generic_plauditPreEndorsement
 * @brief Plaudit Pre-Endorsement Plugin
 */

namespace APP\plugins\generic\plauditPreEndorsement;

use PKP\plugins\GenericPlugin;
use APP\core\Application;
use PKP\plugins\Hook;
use APP\template\TemplateManager;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;
use APP\pages\submission\SubmissionHandler;
use PKP\log\event\PKPSubmissionEventLogEntry;
use PKP\db\DAORegistry;
use PKP\core\Core;
use APP\facades\Repo;
use PKP\security\Role;
use PKP\core\JSONMessage;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use APP\plugins\generic\plauditPreEndorsement\classes\OrcidClient;
use APP\plugins\generic\plauditPreEndorsement\classes\Endorsement;
use APP\plugins\generic\plauditPreEndorsement\classes\components\forms\EndorsementForm;
use APP\plugins\generic\plauditPreEndorsement\PlauditPreEndorsementSettingsForm;
use APP\plugins\generic\plauditPreEndorsement\classes\mail\mailables\OrcidRequestEndorserAuthorization;
use APP\plugins\generic\plauditPreEndorsement\classes\observers\listeners\SendEmailToEndorser;
use APP\plugins\generic\plauditPreEndorsement\classes\components\listPanel\EndorsersListPanel;

class PlauditPreEndorsementPlugin extends GenericPlugin
{
    public const HANDLER_PAGE = 'pre-endorsement-handler';

    public function register($category, $path, $mainContextId = null)
    {
        $success = parent::register($category, $path, $mainContextId);

        if (Application::isUnderMaintenance()) {
            return $success;
        }

        if ($success && $this->getEnabled($mainContextId)) {
            Event::subscribe(new SendEmailToEndorser());

            Hook::add('TemplateManager::display', [$this, 'modifySubmissionSteps']);
            Hook::add('TemplateManager::display', [$this, 'addEndorsersListPanel']);
            Hook::add('Schema::get::publication', [$this, 'addOurFieldsToPublicationSchema']);
            Hook::add('Submission::validateSubmit', [$this, 'validateEndorsement']);
            Hook::add('Template::SubmissionWizard::Section::Review', [$this, 'modifyReviewSections']);

            Hook::add('Template::Workflow::Publication', [$this, 'addEndorserFieldsToWorkflow']);
            Hook::add('LoadHandler', [$this, 'setupPreEndorsementHandler']);
            Hook::add('AcronPlugin::parseCronTab', [$this, 'addEndorsementTasksToCrontab']);
        }

        return $success;
    }

    public function getDisplayName()
    {
        return __('plugins.generic.plauditPreEndorsement.displayName');
    }

    public function getDescription()
    {
        return __('plugins.generic.plauditPreEndorsement.description');
    }

    public function setupPreEndorsementHandler($hookName, $params)
    {
        $page = $params[0];
        if ($this->getEnabled() && $page == self::HANDLER_PAGE) {
            define('HANDLER_CLASS', 'APP\plugins\generic\plauditPreEndorsement\classes\PlauditPreEndorsementHandler');
            return true;
        }
        return false;
    }

    public function addEndorsementTasksToCrontab($hookName, $params)
    {
        $taskFilesPath = &$params[0];
        $taskFilesPath[] = $this->getPluginPath() . DIRECTORY_SEPARATOR . 'scheduledTasks.xml';
        return false;
    }

    public function writeOnActivityLog($submission, $message, $messageParams = array())
    {
        $eventLogData = [
            'assocType' => Application::ASSOC_TYPE_SUBMISSION,
            'assocId' => $submission->getId(),
            'eventType' => PKPSubmissionEventLogEntry::SUBMISSION_LOG_METADATA_UPDATE,
            'message' => __($message, $messageParams),
            'isTranslated' => true,
            'dateLogged' => Core::getCurrentDate(),
        ];

        $user = Application::get()->getRequest()->getUser();
        if ($user) {
            $eventLogData['userId'] = $user->getId();
        }

        $eventLog = Repo::eventLog()->newDataObject($eventLogData);
        Repo::eventLog()->add($eventLog);
    }

    public function inputIsEmail(string $input): bool
    {
        return filter_var($input, FILTER_VALIDATE_EMAIL);
    }

    public function modifySubmissionSteps($hookName, $params)
    {
        $request = Application::get()->getRequest();
        $context = $request->getContext();
        $templateMgr = $params[0];

        if ($request->getRequestedPage() != 'submission' || $request->getRequestedOp() == 'saved') {
            return false;
        }

        $submission = $request
            ->getRouter()
            ->getHandler()
            ->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);

        if (!$submission || !$submission->getData('submissionProgress')) {
            return false;
        }

        $publication = $submission->getCurrentPublication();
        $publicationApiUrl = $request->getDispatcher()->url(
            $request,
            Application::ROUTE_API,
            $request->getContext()->getPath(),
            'submissions/' . $submission->getId() . '/publications/' . $publication->getId()
        );
        $endorsementForm = new EndorsementForm(
            $publicationApiUrl,
            $publication,
        );

        $steps = $templateMgr->getState('steps');
        $steps = array_map(function ($step) use ($endorsementForm) {
            if ($step['id'] == 'details') {
                $step['sections'][] = [
                    'id' => 'endorsement',
                    'name' => __('plugins.generic.plauditPreEndorsement.endorsement'),
                    'description' => __('plugins.generic.plauditPreEndorsement.endorsement.description'),
                    'type' => SubmissionHandler::SECTION_TYPE_FORM,
                    'form' => $endorsementForm->getConfig(),
                ];
            }
            return $step;
        }, $steps);

        $templateMgr->setState(['steps' => $steps]);

        return false;
    }

    public function addEndorsersListPanel($hookName, $params)
    {
        $templateMgr = $params[0];
        $request = Application::get()->getRequest();

        $submission = $request
            ->getRouter()
            ->getHandler()
            ->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);

        $listPanel = new EndorsersListPanel(
            'contributors',
            'Endorsers',
            $submission,
            [['title' => 'Yves']]
        );

        $components = $templateMgr->getState('components');
        $components['endorsers'] = $listPanel->getConfig();
        $templateMgr->setState(['components' => $components]);

        return false;
    }

    public function validateEndorsement($hookName, $params)
    {
        $errors = &$params[0];
        $submission = $params[1];
        $publication = $submission->getCurrentPublication();
        $endorserEmail = $publication->getData('endorserEmail');

        if ($endorserEmail) {
            if (!$this->inputIsEmail($endorserEmail)) {
                $errors['endorsement']  = [__("plugins.generic.plauditPreEndorsement.endorsementEmailInvalid")];
            } else {
                foreach ($publication->getData('authors') as $author) {
                    if ($author->getData('email') == $endorserEmail) {
                        $errors['endorsement'] = [__("plugins.generic.plauditPreEndorsement.endorsementFromAuthor")];
                    }
                }
            }
        }

        return false;
    }

    public function modifyReviewSections($hookName, $params)
    {
        $step = $params[0]['step'];
        $templateMgr = $params[1];
        $output = &$params[2];

        if ($step == 'details') {
            $output .= $templateMgr->fetch($this->getTemplateResource('reviewEndorsement.tpl'));
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
            Endorsement::STATUS_NOT_CONFIRMED => 'NotConfirmed',
            Endorsement::STATUS_CONFIRMED => 'Confirmed',
            Endorsement::STATUS_DENIED => 'Denied',
            Endorsement::STATUS_COMPLETED => 'Completed',
            Endorsement::STATUS_COULDNT_COMPLETE => 'CouldntComplete'
        ];

        return $mapStatusToSuffix[$endorsementStatus] ?? "";
    }

    public function addEndorserFieldsToWorkflow($hookName, $params)
    {
        $smarty = &$params[1];
        $output = &$params[2];

        $submission = $smarty->getTemplateVars('submission');
        $publication = $submission->getCurrentPublication();

        $request = Application::get()->getRequest();
        $handlerUrl = $request->getDispatcher()->url($request, Application::ROUTE_PAGE, null, self::HANDLER_PAGE);

        $endorsementStatus = $publication->getData('endorsementStatus');
        $endorsementStatusSuffix = $this->getEndorsementStatusSuffix($endorsementStatus);
        $canEditEndorsement = (is_null($endorsementStatus) || $endorsementStatus == Endorsement::STATUS_NOT_CONFIRMED || $endorsementStatus == Endorsement::STATUS_DENIED);
        $canSendEndorsementManually = $publication->getData('status') == STATUS_PUBLISHED
            && !$this->userAccessingIsAuthor($submission)
            && ($endorsementStatus == Endorsement::STATUS_CONFIRMED || $endorsementStatus == Endorsement::STATUS_COULDNT_COMPLETE);
        $canRemoveEndorsement = !is_null($endorsementStatus) && !$this->userAccessingIsAuthor($submission);
        $smarty->assign([
            'submissionId' => $submission->getId(),
            'endorserName' => $publication->getData('endorserName'),
            'endorserEmail' => $publication->getData('endorserEmail'),
            'endorserOrcid' => $publication->getData('endorserOrcid'),
            'endorserEmailCount' => $publication->getData('endorserEmailCount'),
            'endorsementStatus' => $endorsementStatus,
            'endorsementStatusSuffix' => $endorsementStatusSuffix,
            'canEditEndorsement' => $canEditEndorsement,
            'canRemoveEndorsement' => $canRemoveEndorsement,
            'canSendEndorsementManually' => $canSendEndorsementManually,
            'handlerUrl' => $handlerUrl,
        ]);

        $tabBadge = (is_null($endorsementStatus) ? 'badge="0"' : 'badge="1"');
        $output .= sprintf(
            '<tab id="plauditPreEndorsement" %s label="%s">%s</tab>',
            $tabBadge,
            __('plugins.generic.plauditPreEndorsement.preEndorsement'),
            $smarty->fetch($this->getTemplateResource('endorserFieldWorkflow.tpl'))
        );
    }

    public function sendEmailToEndorser($publication, $endorserChanged = false)
    {
        $request = Application::get()->getRequest();
        $context = $request->getContext();
        $endorserName = $publication->getData('endorserName');
        $endorserEmail = $publication->getData('endorserEmail');

        if (!is_null($context) && !empty($endorserEmail)) {
            $submission = Repo::submission()->get($publication->getData('submissionId'));
            $emailTemplate = Repo::emailTemplate()->getByKey(
                $context->getId(),
                'ORCID_REQUEST_ENDORSER_AUTHORIZATION'
            );

            $endorserEmailToken = md5(microtime() . $endorserEmail);
            $orcidClient = new OrcidClient($this, $context->getId());
            $oauthUrl = $orcidClient->buildOAuthUrl(['token' => $endorserEmailToken, 'state' => $publication->getId()]);
            $emailParams = [
                'orcidOauthUrl' => $oauthUrl,
                'endorserName' => htmlspecialchars($endorserName),
            ];

            $email = new OrcidRequestEndorserAuthorization($context, $submission, $emailParams);
            $email->from($context->getData('contactEmail'), $context->getData('contactName'));
            $email->to([['name' => $endorserName, 'email' => $endorserEmail]]);
            $email->subject($emailTemplate->getLocalizedData('subject'));
            $email->body($emailTemplate->getLocalizedData('body'));

            Mail::send($email);

            if (is_null($publication->getData('endorserEmailCount')) || $endorserChanged) {
                $endorserEmailCount = 0;
            } else {
                $endorserEmailCount = $publication->getData('endorserEmailCount');
            }

            $publication->setData('endorserEmailToken', $endorserEmailToken);
            $publication->setData('endorsementStatus', Endorsement::STATUS_NOT_CONFIRMED);
            $publication->setData('endorserEmailCount', $endorserEmailCount + 1);
            Repo::publication()->edit($publication, []);

            $this->writeOnActivityLog($submission, 'plugins.generic.plauditPreEndorsement.log.sentEmailEndorser', ['endorserName' => $endorserName, 'endorserEmail' => $endorserEmail]);
        }
    }

    public function getInstallEmailTemplatesFile()
    {
        return $this->getPluginPath() . '/emailTemplates.xml';
    }

    public function getActions($request, $actionArgs)
    {
        $router = $request->getRouter();
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
                    OrcidClient::ORCID_API_URL_PUBLIC => 'plugins.generic.plauditPreEndorsement.settings.orcidAPIPath.public',
                    OrcidClient::ORCID_API_URL_PUBLIC_SANDBOX => 'plugins.generic.plauditPreEndorsement.settings.orcidAPIPath.publicSandbox',
                    OrcidClient::ORCID_API_URL_MEMBER => 'plugins.generic.plauditPreEndorsement.settings.orcidAPIPath.member',
                    OrcidClient::ORCID_API_URL_MEMBER_SANDBOX => 'plugins.generic.plauditPreEndorsement.settings.orcidAPIPath.memberSandbox'
                ];
                $templateMgr->assign('orcidApiUrls', $apiOptions);

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

    public function userAccessingIsAuthor($submission): bool
    {
        $currentUser = Application::get()->getRequest()->getUser();
        $currentUserAssignedRoles = array();
        if ($currentUser) {
            $stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO');
            $stageAssignmentsResult = $stageAssignmentDao->getBySubmissionAndUserIdAndStageId($submission->getId(), $currentUser->getId(), $submission->getData('stageId'));

            while ($stageAssignment = $stageAssignmentsResult->next()) {
                $userGroup = Repo::userGroup()->get($stageAssignment->getUserGroupId(), $submission->getData('contextId'));
                $currentUserAssignedRoles[] = (int) $userGroup->getRoleId();
            }
        }

        return $currentUserAssignedRoles[0] == Role::ROLE_ID_AUTHOR;
    }
}
