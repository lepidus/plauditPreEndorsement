<?php

namespace APP\plugins\generic\plauditPreEndorsement\controllers\grid;

use PKP\controllers\grid\GridRow;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;
use PKP\linkAction\request\RemoteActionConfirmationModal;

class EndorsementGridRow extends GridRow
{
    public function __construct()
    {
        parent::__construct();
    }

    public function initialize($request, $template = null)
    {
        parent::initialize($request, $template);
        $submissionId = $request->getUserVar('submissionId');

        $router = $request->getRouter();

        $element = & $this->getData();

        $rowId = $this->getId();

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
