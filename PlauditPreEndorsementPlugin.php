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
use APP\pages\submission\SubmissionHandler;
use PKP\log\event\PKPSubmissionEventLogEntry;
use PKP\db\DAORegistry;
use PKP\core\Core;
use APP\plugins\generic\plauditPreEndorsement\classes\facades\Repo;
use PKP\security\Role;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use APP\plugins\generic\plauditPreEndorsement\classes\settings\Actions;
use APP\plugins\generic\plauditPreEndorsement\classes\settings\Manage;
use APP\plugins\generic\plauditPreEndorsement\classes\OrcidClient;
use APP\plugins\generic\plauditPreEndorsement\classes\endorsement\Endorsement;
use APP\plugins\generic\plauditPreEndorsement\classes\mail\builders\OrcidRequestMailBuilder;
use APP\plugins\generic\plauditPreEndorsement\classes\mail\mailables\OrcidRequestEndorserAuthorization;
use APP\plugins\generic\plauditPreEndorsement\classes\observers\listeners\SendEmailToEndorser;
use APP\plugins\generic\plauditPreEndorsement\classes\SchemaBuilder;
use APP\plugins\generic\plauditPreEndorsement\classes\migration\EndorsementSchemaMigration;
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
            Hook::add('Template::SubmissionWizard::Section', [$this, 'addToSubmissionWizardTemplate']);
            Hook::add('Template::SubmissionWizard::Section::Review', [$this, 'addToReviewSubmissionWizardTemplate']);
            Hook::add('Schema::get::endorsement', [$this, 'addEndorsementSchema']);

            Hook::add('Template::Workflow::Publication', [$this, 'addEndorsementFieldsToWorkflow']);
            Hook::add('LoadHandler', [$this, 'setupPreEndorsementHandler']);
            Hook::add('AcronPlugin::parseCronTab', [$this, 'addEndorsementTasksToCrontab']);
            Hook::add('LoadComponentHandler', [$this, 'setupGridHandler']);

            $templateMgr = TemplateManager::getManager();
            $request = Application::get()->getRequest();

            $templateMgr->addJavaScript(
                'EndorsementGridHandler',
                $request->getBaseUrl() . '/' . $this->getPluginPath() . '/js/EndorsementGridHandler.js',
                ['contexts' => 'backend']
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

    public function getInstallMigration(): Migration
    {
        return new EndorsementSchemaMigration();
    }

    public function getInstallEmailTemplatesFile()
    {
        return $this->getPluginPath() . '/emailTemplates.xml';
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

    public function writeOnActivityLog($submissionId, $message, $messageParams = [])
    {
        $eventLogData = [
            'assocType' => Application::ASSOC_TYPE_SUBMISSION,
            'assocId' => $submissionId,
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
        $output = &$params[2];

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

    public function addEndorsementSchema(string $hookName, array $params): bool
    {
        $schema = &$params[0];
        $schema = SchemaBuilder::get('endorsement');
        return true;
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

    public function addEndorsementFieldsToWorkflow($hookName, $params)
    {
        $smarty = &$params[1];
        $output = &$params[2];

        $submission = $smarty->getTemplateVars('submission');
        $publications = $submission->getData('publications');
        $publicationIds = [];

        if (!empty($publications)) {
            foreach ($publications as $publication) {
                $publicationIds[] = $publication->getId();
            }
        }
        $request = Application::get()->getRequest();

        $countEndorsers = Repo::endorsement()->getCollector()
            ->filterByContextIds([$request->getContext()->getId()])
            ->filterByPublicationIds($publicationIds)
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
        $orcidRequestMailBuilder = new OrcidRequestMailBuilder();
        $email = $orcidRequestMailBuilder
            ->setEndorsement($endorsement)
            ->setPublication($publication)
            ->buildEmailParams()
            ->build(['endorsementChanged' => $endorsementChanged]);

        Mail::send($email);

        $this->writeOnActivityLog(
            $publication->getData('submissionId'),
            'plugins.generic.plauditPreEndorsement.log.sentEmailEndorser',
            ['endorserName' => $endorsement->getName(), 'endorserEmail' => $endorsement->getEmail()]
        );
    }

    public function getActions($request, $actionArgs)
    {
        $actions = new Actions($this);
        return $actions->execute($request, $actionArgs, parent::getActions($request, $actionArgs));
    }

    public function manage($args, $request)
    {
        $manage = new Manage($this);
        return $manage->execute($args, $request);
    }

    public function userAccessingIsAuthor($submission): bool
    {
        $currentUser = Application::get()->getRequest()->getUser();
        $currentUserAssignedRoles = [];
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
        $component = &$params[0];
        if ($component == 'plugins.generic.plauditPreEndorsement.controllers.grid.EndorsementGridHandler') {
            define('PLAUDIT_PRE_ENDORSEMENT_PLUGIN_NAME', $this->getName());
            return true;
        }
        return false;
    }
}
