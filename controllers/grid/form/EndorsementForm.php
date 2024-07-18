<?php

namespace APP\plugins\generic\plauditPreEndorsement\controllers\grid\form;

use APP\template\TemplateManager;
use PKP\db\DAORegistry;
use PKP\form\Form;
use PKP\form\validation\FormValidator;
use PKP\form\validation\FormValidatorCSRF;
use PKP\form\validation\FormValidatorPost;
use PKP\plugins\PluginRegistry;
use APP\plugins\generic\plauditPreEndorsement\classes\facades\Repo;

class EndorsementForm extends Form
{
    public $contextId;
    public $submissionId;
    private $request;
    private $plugin;

    public function __construct($contextId, $submissionId, $request = null, $plugin = null)
    {
        $this->contextId = $contextId;
        $this->submissionId = $submissionId;
        $this->request = $request ?? null;
        $this->plugin = $plugin;

        $this->addCheck(new FormValidatorPost($this));
        $this->addCheck(new FormValidatorCSRF($this));
        parent::__construct($plugin->getTemplateResource('addEndorsement.tpl'));
    }

    public function initData()
    {
        $this->setData('submissionId', $this->submissionId);
    }

    public function readInputData()
    {
        $this->readUserVars(array('endorserEmail', 'endorserName'));
    }

    public function fetch($request, $template = null, $display = false)
    {
        $templateMgr = TemplateManager::getManager();
        $rowId = $this->request->getUserVar('rowId');
        if ($rowId) {
            $endorsement = Repo::endorsement()->get($rowId, $this->contextId);
            $templateMgr->assign('endorserName', $endorsement->getName());
            $templateMgr->assign('endorserEmail', $endorsement->getEmail());
        }
        $templateMgr->assign('rowId', $rowId);
        $templateMgr->assign('submissionId', $this->submissionId);
        return parent::fetch($request);
    }

    public function execute(...$functionArgs)
    {
        $rowId = $this->request->getUserVar('rowId');
        $submission = Repo::submission()->get($this->submissionId);
        $publication = $submission->getCurrentPublication();

        if ($rowId) {
            $endorsement = Repo::endorsement()->get((int)$rowId, $this->contextId);
            $params = [
                'name' => $this->getData('endorserName'),
                'email' => $this->getData('endorserEmail')
            ];
            Repo::endorsement()->edit($endorsement, $params);
        } else {
            $params = [
                'contextId' => $this->contextId,
                'name' => $this->getData('endorserName'),
                'email' => $this->getData('endorserEmail'),
                'publicationId' => $publication->getId(),
            ];
            $endorsement = Repo::endorsement()->newDataObject($params);
            Repo::endorsement()->add($endorsement);
            $this->plugin->sendEmailToEndorser($publication, $endorsement);
        }
    }
}
