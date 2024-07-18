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
use APP\plugins\generic\plauditPreEndorsement\classes\facades\Repo;
use PKP\security\Role;
use PKP\core\JSONMessage;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use APP\plugins\generic\plauditPreEndorsement\classes\OrcidClient;
use APP\plugins\generic\plauditPreEndorsement\classes\EndorsementStatus;
use APP\plugins\generic\plauditPreEndorsement\classes\components\forms\EndorsementForm;
use APP\plugins\generic\plauditPreEndorsement\PlauditPreEndorsementSettingsForm;
use APP\plugins\generic\plauditPreEndorsement\classes\mail\mailables\OrcidRequestEndorserAuthorization;
use APP\plugins\generic\plauditPreEndorsement\classes\observers\listeners\SendEmailToEndorser;
use APP\plugins\generic\plauditPreEndorsement\classes\SchemaBuilder;
use APP\plugins\generic\plauditPreEndorsement\classes\migration\addEndorsementsTable;
use Illuminate\Database\Migrations\Migration;

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

            Hook::add('TemplateManager::display', [$this, 'addToSubmissionWizardSteps']);
            Hook::add('Template::SubmissionWizard::Section', array($this, 'addToSubmissionWizardTemplate'));
            Hook::add('Template::SubmissionWizard::Section::Review', [$this, 'addToReviewSubmissionWizardTemplate']);
            Hook::add('Schema::get::endorsement', [$this, 'addEndorsementSchema']);
            Hook::add('Submission::validateSubmit', [$this, 'validateEndorsement']);

            Hook::add('Template::Workflow::Publication', [$this, 'addEndorsementFieldsToWorkflow']);
            Hook::add('LoadHandler', [$this, 'setupPreEndorsementHandler']);
            Hook::add('AcronPlugin::parseCronTab', [$this, 'addEndorsementTasksToCrontab']);
            Hook::add('LoadComponentHandler', [$this, 'setupGridHandler']);

            $templateMgr = TemplateManager::getManager();
            $request = Application::get()->getRequest();

            $templateMgr->addJavaScript(
                'EndorsementGridHandler',
                $request->getBaseUrl() . '/' . $this->getPluginPath() . '/js/EndorsementGridHandler.js',
                array('contexts' => 'backend')
            );
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

    public function addToSubmissionWizardSteps($hookName, $params)
    {
        $request = Application::get()->getRequest();

        if ($request->getRequestedPage() !== 'submission' || $request->getRequestedOp() == 'saved') {
            return;
        }

        $submission = $request
            ->getRouter()
            ->getHandler()
            ->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);

        if (!$submission || !$submission->getData('submissionProgress')) {
            return;
        }

        $templateMgr = $params[0];

        $steps = $templateMgr->getState('steps');
        $steps = array_map(function ($step) {
            if ($step['id'] === 'details') {
                $step['sections'][] = [
                    'id' => 'plauditPreEndorsement',
                    'name' => __('plugins.generic.plauditPreEndorsement.endorsement'),
                    'description' => __('plugins.generic.plauditPreEndorsement.endorsement.description'),
                    'type' => SubmissionHandler::SECTION_TYPE_TEMPLATE,
                ];
            }
            return $step;
        }, $steps);

        $templateMgr->setState([
            'steps' => $steps,
        ]);

        return false;
    }

    public function addToSubmissionWizardTemplate($hookName, $params)
    {
        $smarty = $params[1];
        $output = & $params[2];

        $output .= sprintf(
            '<template v-else-if="section.id === \'plauditPreEndorsement\'">%s</template>',
            $smarty->fetch($this->getTemplateResource('endorsementComponent.tpl'))
        );

        return false;
    }

    public function addToReviewSubmissionWizardTemplate($hookName, $params)
    {
        $step = $params[0]['step'];
        $templateMgr = $params[1];
        $output = &$params[2];

        if ($step == 'details') {
            $output .= $templateMgr->fetch($this->getTemplateResource('endorsementComponent.tpl'));
        }

        return false;
    }

    public function validateEndorsement($hookName, $params)
    {
        $errors = &$params[0];
        $submission = $params[1];
        $publication = $submission->getCurrentPublication();
        $endorsementEmail = $publication->getData('endorserEmail');

        if ($endorsementEmail) {
            if (!$this->inputIsEmail($endorsementEmail)) {
                $errors['endorsement']  = [__("plugins.generic.plauditPreEndorsement.endorsementEmailInvalid")];
            } else {
                foreach ($publication->getData('authors') as $author) {
                    if ($author->getData('email') == $endorsementEmail) {
                        $errors['endorsement'] = [__("plugins.generic.plauditPreEndorsement.endorsementFromAuthor")];
                    }
                }
            }
        }

        return false;
    }

    public function addEndorsementSchema(string $hookName, array $params): bool
    {
        $schema = &$params[0];
        $schema = SchemaBuilder::get('endorsement');
        return true;
    }

    public function getInstallMigration(): Migration
    {
        return new addEndorsementsTable();
    }


    private function getEndorsementStatusSuffix($endorsementStatus): string
    {
        $mapStatusToSuffix = [
            EndorsementStatus::NOT_CONFIRMED => 'NotConfirmed',
            EndorsementStatus::CONFIRMED => 'Confirmed',
            EndorsementStatus::DENIED => 'Denied',
            EndorsementStatus::COMPLETED => 'Completed',
            EndorsementStatus::COULDNT_COMPLETE => 'CouldntComplete'
        ];

        return $mapStatusToSuffix[$endorsementStatus] ?? "";
    }

    public function addEndorsementFieldsToWorkflow($hookName, $params)
    {
        $smarty = &$params[1];
        $output = &$params[2];

        $submission = $smarty->getTemplateVars('submission');
        $publication = $submission->getCurrentPublication();
        $request = Application::get()->getRequest();

        $countEndorsers = Repo::endorsement()->getCollector()
            ->filterByContextIds([$request->getContext()->getId()])
            ->filterByPublicationIds([$publication->getId()])
            ->getCount();

        $tabBadge = (empty($countEndorsers) ? 'badge="0"' : 'badge=' . $countEndorsers);
        $output .= sprintf(
            '<tab id="plauditPreEndorsement" %s label="%s">%s</tab>',
            $tabBadge,
            __('plugins.generic.plauditPreEndorsement.preEndorsement'),
            $smarty->fetch($this->getTemplateResource('endorsementComponent.tpl'))
        );
    }

    public function sendEmailToEndorser($publication, $endorsement, $endorsementChanged = false)
    {
        $request = Application::get()->getRequest();
        $context = $request->getContext();
        $endorsementName = $endorsement->getName();
        $endorsementEmail = $endorsement->getEmail();

        if (!is_null($context) && !empty($endorsementEmail)) {
            $submission = Repo::submission()->get($publication->getData('submissionId'));
            $emailTemplate = Repo::emailTemplate()->getByKey(
                $context->getId(),
                'ORCID_REQUEST_ENDORSER_AUTHORIZATION'
            );

            $endorsementEmailToken = md5(microtime() . $endorsementEmail);
            $orcidClient = new OrcidClient($this, $context->getId());
            $oauthUrl = $orcidClient->buildOAuthUrl(
                [
                    'token' => $endorsementEmailToken,
                    'state' => $publication->getId(),
                    'endorsementId' => $endorsement->getId()
                ]
            );
            $emailParams = [
                'orcidOauthUrl' => $oauthUrl,
                'endorserName' => htmlspecialchars($endorsementName),
            ];

            $email = new OrcidRequestEndorserAuthorization($context, $submission, $emailParams);
            $email->from($context->getData('contactEmail'), $context->getData('contactName'));
            $email->to([['name' => $endorsementName, 'email' => $endorsementEmail]]);
            $email->subject($emailTemplate->getLocalizedData('subject'));
            $email->body($emailTemplate->getLocalizedData('body'));

            Mail::send($email);

            if (is_null($endorsement->getEmailCount()) || $endorsementChanged) {
                $endorsementEmailCount = 0;
            } else {
                $endorsementEmailCount = $endorsement->getEmailCount();
            }

            $endorsement->setEmailToken($endorsementEmailToken);
            $endorsement->setStatus(EndorsementStatus::NOT_CONFIRMED);
            $endorsement->setEmailCount($endorsementEmailCount + 1);

            Repo::endorsement()->edit($endorsement, []);

            $this->writeOnActivityLog($submission, 'plugins.generic.plauditPreEndorsement.log.sentEmailEndorser', ['endorserName' => $endorsementName, 'endorserEmail' => $endorsementEmail]);
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

    public function setupGridHandler($hookName, $params)
    {
        $component = & $params[0];
        if ($component == 'plugins.generic.plauditPreEndorsement.controllers.grid.EndorsementGridHandler') {
            define('PLAUDIT_PRE_ENDORSEMENT_PLUGIN_NAME', $this->getName());
            return true;
        }
        return false;
    }
}
