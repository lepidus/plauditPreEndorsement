<?php

namespace APP\plugins\generic\plauditPreEndorsement\controllers\grid;

use PKP\controllers\grid\GridRow;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;
use PKP\linkAction\request\RemoteActionConfirmationModal;
use APP\plugins\generic\plauditPreEndorsement\classes\EndorsementStatus;
use PKP\plugins\PluginRegistry;
use APP\facades\Repo;
use APP\submission\Submission;

class EndorsementGridRow extends GridRow
{
    public $plugin;

    public function __construct()
    {
        parent::__construct();
        $this->plugin = PluginRegistry::getPlugin('generic', PLAUDIT_PRE_ENDORSEMENT_PLUGIN_NAME);
    }

    public function initialize($request, $template = null)
    {
        parent::initialize($request, $template);
        $submissionId = $request->getUserVar('submissionId');
        $submission = Repo::submission()->get($submissionId);
        $publication = $submission->getCurrentPublication();

        $router = $request->getRouter();

        $element = & $this->getData();

        $rowId = $this->getId();

        $canSendEndorsementManually = $publication->getData('status') == Submission::STATUS_PUBLISHED
            && !$this->plugin->userAccessingIsAuthor($submission)
            && (
                $element->getStatus() == Endorsement::STATUS_CONFIRMED ||
                $element->getStatus() == Endorsement::STATUS_COULDNT_COMPLETE
            );

        if ($canSendEndorsementManually) {
            $this->addAction(
                new LinkAction(
                    'sendEndorsementManually',
                    new RemoteActionConfirmationModal(
                        $request->getSession(),
                        __('plugins.generic.plauditPreEndorsement.sendEndorsementToPlauditConfirmationMessage'),
                        __('plugins.generic.plauditPreEndorsement.sendEndorsementToPlaudit'),
                        $router->url(
                            $request,
                            null,
                            null,
                            'sendEndorsementManually',
                            null,
                            array('submissionId' => $submissionId, 'rowId' => $rowId)
                        ),
                        'modal_delete'
                    ),
                    __('plugins.generic.plauditPreEndorsement.sendEndorsementToPlaudit'),
                    'sendToPlaudit'
                )
            );
        }

        $this->addAction(
            new LinkAction(
                'editEndorserItem',
                new AjaxModal(
                    $router->url(
                        $request,
                        null,
                        null,
                        'editEndorser',
                        null,
                        array('submissionId' => $submissionId, 'rowId' => $rowId)
                    ),
                    __('grid.action.edit'),
                    'modal_edit',
                    true
                ),
                __('grid.action.edit'),
                'edit'
            )
        );
        $this->addAction(
            new LinkAction(
                'delete',
                new RemoteActionConfirmationModal(
                    $request->getSession(),
                    __('common.confirmDelete'),
                    __('grid.action.delete'),
                    $router->url(
                        $request,
                        null,
                        null,
                        'deleteEndorser',
                        null,
                        array('submissionId' => $submissionId, 'rowId' => $rowId)
                    ),
                    'modal_delete'
                ),
                __('grid.action.delete'),
                'delete'
            )
        );
    }
}
