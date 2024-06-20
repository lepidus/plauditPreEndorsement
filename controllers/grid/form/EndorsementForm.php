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
use APP\plugins\generic\plauditPreEndorsement\classes\endorser\Repository as EndorserRepository;

class EndorsementForm extends Form
{
    public $contextId;
    public $submissionId;
    private $request;
    private $plugin;
    private $endorserRepository;

    public function __construct($contextId, $submissionId, $request = null, $plugin = null)
    {
        $this->contextId = $contextId;
        $this->submissionId = $submissionId;
        $this->request = $request ?? null;
        $this->plugin = $plugin;
        $this->endorserRepository = app(EndorserRepository::class);

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
            $endorser = $this->endorserRepository->get($rowId, $this->contextId);
            $templateMgr->assign('endorserName', $endorser->getName());
            $templateMgr->assign('endorserEmail', $endorser->getEmail());
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
            $endorser = $this->endorserRepository->get((int)$rowId, $this->contextId);
            $params = [
                'name' => $this->getData('endorserName'),
                'email' => $this->getData('endorserEmail')
            ];
            $this->endorserRepository->edit($endorser, $params);
        } else {
            $params = [
                'contextId' => $this->contextId,
                'name' => $this->getData('endorserName'),
                'email' => $this->getData('endorserEmail'),
                'publicationId' => $publication->getId(),
            ];
            $endorser = $this->endorserRepository->newDataObject($params);
            $this->endorserRepository->add($endorser);
            // $this->plugin->sendEmailToEndorser($publication, $endorser);
        }
    }
}
