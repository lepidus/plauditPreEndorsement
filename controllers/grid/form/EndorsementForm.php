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

    public function __construct($contextId, $submissionId)
    {
        $plugin = PluginRegistry::getPlugin('generic', 'plauditpreendorsementplugin');
        parent::__construct($plugin->getTemplateResource('addEndorsement.tpl'));

        $this->contextId = $contextId;
        $this->submissionId = $submissionId;

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
        $templateMgr->assign('submissionId', $this->submissionId);
        return parent::fetch($request);
    }

    public function execute(...$functionArgs)
    {
        $submission = Repo::submission()->get($this->submissionId);
        $publication = $submission->getCurrentPublication();
        $endorsers = $publication->getData('endorsers') ?? array();
        $endorser = ['name' => $this->getData('endorserName'), 'email' => $this->getData('endorserEmail')];
        $endorsers[] = $endorser;
        Repo::publication()->edit($publication, ['endorsers' => $endorsers]);
    }
}
