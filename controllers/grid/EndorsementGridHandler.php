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

class EndorsementGridHandler extends GridHandler
{
    public static $plugin;

    public function __construct()
    {
        parent::__construct();
        $this->addRoleAssignment(
            array(Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT, Role::ROLE_ID_AUTHOR),
            array('fetchGrid', 'fetchRow', 'addEndorser', 'editEndorser', 'updateEndorser', 'deleteEndorser')
        );
    }

    public static function setPlugin($plugin)
    {
        self::$plugin = $plugin;
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
        $this->setEmptyRowText('plugins.generic.funding.noneCreated');

        $gridData = $publication->getData('endorsers');

        $this->setGridDataElements($gridData);

        $router = $request->getRouter();
        $this->addAction(
            new LinkAction(
                'addEndorser',
                new AjaxModal(
                    $router->url($request, null, null, 'addEndorser', null, ['submissionId' => $submissionId]),
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
            'controllers/grid/gridCell.tpl',
            $cellProvider
        ));
        $this->addColumn(new GridColumn(
            'endorserEmail',
            'plugins.generic.plauditPreEndorsement.endorserEmail',
            null,
            'controllers/grid/gridCell.tpl',
            $cellProvider
        ));
    }

    public function addEndorser($args, $request)
    {
        return $this->editEndorser($args, $request);
    }

    public function editEndorser($args, $request)
    {
        $context = $request->getContext();
        $submission = $this->getSubmission();
        $submissionId = $submission->getId();

        $this->setupTemplate($request);

        $endorserForm = new EndorsementForm($context->getId(), $submissionId);
        $endorserForm->initData();
        $json = new JSONMessage(true, $endorserForm->fetch($request));
        return $json->getString();
    }

    public function updateEndorser($args, $request)
    {
        $context = $request->getContext();
        $submission = $this->getSubmission();
        $submissionId = $submission->getId();

        $this->setupTemplate($request);

        $endorserForm = new EndorsementForm($context->getId(), $submissionId);
        $endorserForm->readInputData();
        if ($endorserForm->validate()) {
            $endorserForm->execute();
            $json = DAO::getDataChangedEvent($submissionId);
            $json->setGlobalEvent('plugin:funding:added', ['contextId' => $contextId]);
            return $json;
        } else {
            $json = new JSONMessage(true, $endorserForm->fetch($request));
            return $json->getString();
        }
    }

    public function getJSHandler()
    {
        return '$.pkp.plugins.generic.plauditPreEndorsement.EndorsementGridHandler';
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\APP\plugins\generic\plauditPreEndorsement\controllers\grid\EndorsementGridHandler', '\EndorsementGridHandler');
}
