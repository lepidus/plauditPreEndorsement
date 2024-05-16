<?php

namespace APP\plugins\generic\plauditPreEndorsement\controllers\grid\form;

use APP\template\TemplateManager;
use PKP\db\DAORegistry;
use PKP\form\Form;
use PKP\form\validation\FormValidator;
use PKP\form\validation\FormValidatorCSRF;
use PKP\form\validation\FormValidatorPost;
use PKP\plugins\PluginRegistry;
use APP\facades\Repo;

class EndorsementForm extends Form
{
    public $contextId;
    public $submissionId;
    private $request;

    public function __construct($contextId, $submissionId, $request = null)
    {
        $plugin = PluginRegistry::getPlugin('generic', 'plauditpreendorsementplugin');
        parent::__construct($plugin->getTemplateResource('addEndorsement.tpl'));

        $this->contextId = $contextId;
        $this->submissionId = $submissionId;
        $this->request = $request ?? null;

        $this->addCheck(new FormValidatorPost($this));
        $this->addCheck(new FormValidatorCSRF($this));
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
        $element = $this->request->getUserVar('element');
        if ($element) {
            $templateMgr->assign('rowId', $this->request->getUserVar('rowId'));
            $templateMgr->assign('endorserName', $element[0]);
            $templateMgr->assign('endorserEmail', $element[1]);
        }
        $templateMgr->assign('submissionId', $this->submissionId);
        return parent::fetch($request);
    }

    public function execute(...$functionArgs)
    {
        $rowId = $this->request->getUserVar('rowId');
        $submission = Repo::submission()->get($this->submissionId);
        $publication = $submission->getCurrentPublication();
        $endorsers = $publication->getData('endorsers') ?? array();

        if (isset($rowId) && is_numeric($rowId)) {
            $endorsers[$rowId] = ['name' => $this->getData('endorserName'), 'email' => $this->getData('endorserEmail')];
            Repo::publication()->edit($publication, ['endorsers' => $endorsers]);
        } else {
            $endorser = ['name' => $this->getData('endorserName'), 'email' => $this->getData('endorserEmail')];
            $endorsers[] = $endorser;
            Repo::publication()->edit($publication, ['endorsers' => $endorsers]);
        }
    }
}
