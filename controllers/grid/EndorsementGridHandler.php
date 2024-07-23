<?php

namespace APP\plugins\generic\plauditPreEndorsement\controllers\grid;

use PKP\controllers\grid\GridHandler;
use APP\core\Application;
use PKP\controllers\grid\GridColumn;
use PKP\core\JSONMessage;
use PKP\db\DAO;
use PKP\db\DAORegistry;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;
use PKP\security\authorization\SubmissionAccessPolicy;
use PKP\security\Role;
use APP\plugins\generic\plauditPreEndorsement\controllers\grid\form\EndorsementForm;
use PKP\plugins\PluginRegistry;
use APP\plugins\generic\plauditPreEndorsement\classes\facades\Repo;
use APP\plugins\generic\plauditPreEndorsement\classes\EndorsementService;
use GuzzleHttp\Exception\ClientException;
use APP\notification\NotificationManager;

class EndorsementGridHandler extends GridHandler
{
    public $plugin;

    public function __construct()
    {
        parent::__construct();
        $this->addRoleAssignment(
            array(Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT, Role::ROLE_ID_AUTHOR),
            array('fetchGrid', 'fetchRow', 'addEndorsement', 'editEndorsement', 'updateEndorsement', 'deleteEndorsement', 'sendEndorsementManually')
        );
        $this->plugin = PluginRegistry::getPlugin('generic', PLAUDIT_PRE_ENDORSEMENT_PLUGIN_NAME);
    }

    public static function setPlugin($plugin)
    {
        $this->plugin = $plugin;
    }

    public function getSubmission()
    {
        return $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
    }

    public function authorize($request, &$args, $roleAssignments)
    {
        $this->addPolicy(new SubmissionAccessPolicy($request, $args, $roleAssignments));
        return parent::authorize($request, $args, $roleAssignments);
    }

    public function initialize($request, $args = null)
    {
        parent::initialize($request, $args);

        $submission = $this->getSubmission();
        $submissionId = $submission->getId();
        $publication = $submission->getCurrentPublication();

        $this->setTitle('plugins.generic.plauditPreEndorsement.endorsement');
        $this->setEmptyRowText('common.none');

        $router = $request->getRouter();
        $this->addAction(
            new LinkAction(
                'addEndorsement',
                new AjaxModal(
                    $router->url($request, null, null, 'addEndorsement', null, ['submissionId' => $submissionId]),
                    __('common.add'),
                    'modal_add_item'
                ),
                __('common.add'),
                'add_item'
            )
        );

        $cellProvider = new EndorsementGridCellProvider();
        $this->addColumn(new GridColumn(
            'endorserName',
            'plugins.generic.plauditPreEndorsement.endorserName',
            null,
            $this->plugin->getTemplateResource('gridCells/endorserName.tpl'),
            $cellProvider,
            ['maxLength' => 40]
        ));
        $this->addColumn(new GridColumn(
            'endorserEmail',
            'plugins.generic.plauditPreEndorsement.endorserEmail',
            null,
            'controllers/grid/gridCell.tpl',
            $cellProvider,
            ['maxLength' => 40]
        ));

        if (!$submission->getSubmissionProgress()) {
            $this->addColumn(new GridColumn(
                'endorsementStatus',
                'plugins.generic.plauditPreEndorsement.endorsementStatus',
                null,
                $this->plugin->getTemplateResource('gridCells/endorsementStatus.tpl'),
                $cellProvider
            ));
        }
    }

    protected function loadData($request, $filter)
    {
        $submission = $this->getSubmission();
        $submissionId = $submission->getId();
        $publication = $submission->getCurrentPublication();
        $endorsements = Repo::endorsement()->getCollector()
            ->filterByContextIds([$request->getContext()->getId()])
            ->filterByPublicationIds([$publication->getId()])
            ->getMany();
        return $endorsements->toArray();
    }

    public function addEndorsement($args, $request)
    {
        return $this->editEndorsement($args, $request);
    }

    public function sendEndorsementManually($args, $request)
    {
        $contextId = $request->getContext()->getId();
        $rowId = $request->getUserVar('rowId');
        $endorsement = Repo::endorsement()->get((int)$rowId, $contextId);
        $user = $request->getUser();

        $endorsementService = new EndorsementService($contextId, $this->plugin);
        $endorsementService->sendEndorsement($endorsement);

        $notificationManager = new NotificationManager();
        $notificationManager->createTrivialNotification(
            $user->getId(),
            NOTIFICATION_TYPE_SUCCESS,
            array('contents' => __('plugins.generic.plauditPreEndorsement.sendEndorsementToPlauditNotification'))
        );

        $json = new JSONMessage(true);
        return $json->getString();
    }

    public function editEndorsement($args, $request)
    {
        $context = $request->getContext();
        $submission = $this->getSubmission();
        $submissionId = $submission->getId();

        $this->setupTemplate($request);

        $endorsementForm = new EndorsementForm($context->getId(), $submissionId, $request, $this->plugin);
        $endorsementForm->initData();
        $json = new JSONMessage(true, $endorsementForm->fetch($request));
        return $json->getString();
    }

    public function updateEndorsement($args, $request)
    {
        $context = $request->getContext();
        $submission = $this->getSubmission();
        $submissionId = $submission->getId();

        $this->setupTemplate($request);

        $endorsementForm = new EndorsementForm($context->getId(), $submissionId, $request, $this->plugin);
        $endorsementForm->readInputData();
        if ($endorsementForm->validate()) {
            $endorsementForm->execute();
            $json = DAO::getDataChangedEvent($submissionId);
            $json->setGlobalEvent('plugin:plauditPreEndorsement:endorsementAdded', ['submissionId' => $submissionId]);
            return $json;
        } else {
            $json = new JSONMessage(true, $endorsementForm->fetch($request));
            return $json->getString();
        }
    }

    public function deleteEndorsement($args, $request)
    {
        $context = $request->getContext();
        $submission = $this->getSubmission();
        $submissionId = $submission->getId();
        $rowId = $request->getUserVar('rowId');
        $endorsement = Repo::endorsement()->get((int)$rowId, $context->getId());
        Repo::endorsement()->delete($endorsement);
        $this->plugin->writeOnActivityLog(
            $submission,
            'plugins.generic.plauditPreEndorsement.log.endorsementRemoved',
            ['endorserName' => $endorsement->getName(), 'endorserEmail' => $endorsement->getEmail()]
        );
        $json = DAO::getDataChangedEvent($submissionId);
        $json->setGlobalEvent('plugin:plauditPreEndorsement:endorsementRemoved', ['submissionId' => $submissionId]);
        return $json;
    }

    public function getJSHandler()
    {
        return '$.pkp.plugins.generic.plauditPreEndorsement.EndorsementGridHandler';
    }

    protected function getRowInstance()
    {
        return new EndorsementGridRow();
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\APP\plugins\generic\plauditPreEndorsement\controllers\grid\EndorsementGridHandler', '\EndorsementGridHandler');
}
