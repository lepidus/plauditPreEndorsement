<?php

/**
 * @file plugins/generic/plauditPreEndorsement/PlauditPreEndorsementPlugin.inc.php
 *
 * Copyright (c) 2022 - 2026 Lepidus Tecnologia
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
use PKP\log\event\PKPSubmissionEventLogEntry;
use PKP\core\Core;
use APP\plugins\generic\plauditPreEndorsement\classes\facades\Repo;
use PKP\security\Role;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use APP\plugins\generic\plauditPreEndorsement\classes\settings\Actions;
use APP\plugins\generic\plauditPreEndorsement\classes\settings\Manage;
use APP\plugins\generic\plauditPreEndorsement\classes\endorsement\Endorsement;
use APP\plugins\generic\plauditPreEndorsement\classes\mail\builders\OrcidRequestEmailBuilder;
use APP\plugins\generic\plauditPreEndorsement\classes\observers\listeners\SendEmailToEndorser;
use APP\plugins\generic\plauditPreEndorsement\classes\SchemaBuilder;
use APP\plugins\generic\plauditPreEndorsement\classes\migration\EndorsementSchemaMigration;
use APP\plugins\generic\plauditPreEndorsement\classes\tasks\CheckEndorsements;
use APP\plugins\generic\plauditPreEndorsement\classes\tasks\SendReadyEndorsements;
use Illuminate\Database\Migrations\Migration;
use PKP\plugins\interfaces\HasTaskScheduler;
use PKP\scheduledTask\PKPScheduler;
use PKP\stageAssignment\StageAssignment;
use PKP\core\PKPBaseController;
use PKP\handler\APIHandler;
use Illuminate\Http\Request as IlluminateRequest;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;

class PlauditPreEndorsementPlugin extends GenericPlugin implements HasTaskScheduler
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
            Hook::add('Schema::get::endorsement', [$this, 'addEndorsementSchema']);

            Hook::add('LoadHandler', [$this, 'setupPreEndorsementHandler']);

            $this->addEndorsementApiRoutes();

            $request = Application::get()->getRequest();
            $templateMgr = TemplateManager::getManager($request);

            $templateMgr->addJavaScript(
                'PlauditPreEndorsement',
                $request->getBaseUrl() . '/' . $this->getPluginPath() . '/public/build/build.iife.js',
                [
                    'inline' => false,
                    'contexts' => ['backend'],
                    'priority' => TemplateManager::STYLE_SEQUENCE_LAST,
                ]
            );

            $templateMgr->addStyleSheet(
                'plauditPreEndorsementStyles',
                $request->getBaseUrl() . '/' . $this->getPluginPath() . '/public/build/build.css',
                ['contexts' => ['backend']]
            );
        }

        return $success;
    }

    public function registerSchedules(PKPScheduler $scheduler): void
    {
        $scheduler->addSchedule(new CheckEndorsements())
            ->everyFourMinutes()
            ->name(CheckEndorsements::class)
            ->withoutOverlapping();

        $scheduler->addSchedule(new SendReadyEndorsements())
            ->everyFiveMinutes()
            ->name(SendReadyEndorsements::class)
            ->withoutOverlapping();
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
        $page = & $params[0];
        $handler = & $params[3];
        if ($this->getEnabled() && $page == self::HANDLER_PAGE) {
            $handler = new \APP\plugins\generic\plauditPreEndorsement\classes\PlauditPreEndorsementHandler();
            return true;
        }
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
        $steps = array_map(function ($step) use ($submission) {
            if ($step['id'] === 'details') {
                $step['sections'][] = [
                    'id' => 'plauditPreEndorsement',
                    'name' => __('plugins.generic.plauditPreEndorsement.endorsement'),
                    'description' => __('plugins.generic.plauditPreEndorsement.endorsement.description'),
                    'component' => 'EndorsementWizardSection',
                    'props' => ['submissionId' => $submission->getId()],
                ];
            }
            return $step;
        }, $steps);

        $templateMgr->setState([
            'steps' => $steps,
        ]);

        $reviewSteps = $templateMgr->getTemplateVars('reviewSteps') ?: [];
        $reviewSteps[] = [
            'id' => 'plauditPreEndorsementReview',
            'component' => 'EndorsementWizardReview',
            'props' => [
                'submissionId' => $submission->getId(),
                'title' => __('plugins.generic.plauditPreEndorsement.endorsement'),
            ],
        ];
        $templateMgr->assign('reviewSteps', $reviewSteps);

        return false;
    }

    public function addEndorsementSchema(string $hookName, array $params): bool
    {
        $schema = &$params[0];
        $schema = SchemaBuilder::get('endorsement');
        return true;
    }

    public function sendEmailToEndorser($publication, $endorsement, $endorsementChanged = false)
    {
        $orcidRequestEmailBuilder = new OrcidRequestEmailBuilder();
        $email = $orcidRequestEmailBuilder
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
        if (!$currentUser) {
            return false;
        }

        return StageAssignment::withSubmissionIds([$submission->getId()])
            ->withRoleIds([Role::ROLE_ID_AUTHOR])
            ->withUserId($currentUser->getId())
            ->get()
            ->isNotEmpty();
    }

    private function addEndorsementApiRoutes(): void
    {
        Hook::add('Dispatcher::dispatch', function (string $hookName, array $params): bool {
            $request = $params[0];
            $router = $request->getRouter();

            if (!($router instanceof \PKP\core\APIRouter)) {
                return Hook::CONTINUE;
            }

            if (!str_contains($request->getRequestPath(), 'api/v1/endorsements')) {
                return Hook::CONTINUE;
            }

            $handler = new APIHandler(
                new \APP\plugins\generic\plauditPreEndorsement\classes\api\v1\EndorsementController()
            );
            $router->setHandler($handler);
            $handler->runRoutes();
            exit;
        });
    }
}
