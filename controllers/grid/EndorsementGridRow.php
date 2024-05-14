<?php

namespace APP\plugins\generic\plauditPreEndorsement\controllers\grid;

use PKP\controllers\grid\GridRow;

class EndorsementGridRow extends GridRow
{
    public $_readOnly;

    public function __construct($readOnly = false)
    {
        $this->_readOnly = $readOnly;
        parent::__construct();
    }

    public function initialize($request, $template = null)
    {
        parent::initialize($request, $template);
        $funderId = $this->getId();
        $submissionId = $request->getUserVar('submissionId');

        if (!empty($funderId) && !$this->isReadOnly()) {
            $router = $request->getRouter();

            import('lib.pkp.classes.linkAction.request.AjaxModal');
            $this->addAction(
                new LinkAction(
                    'editFunderItem',
                    new AjaxModal(
                        $router->url($request, null, null, 'editFunder', null, array('funderId' => $funderId, 'submissionId' => $submissionId)),
                        __('grid.action.edit'),
                        'modal_edit',
                        true
                    ),
                    __('grid.action.edit'),
                    'edit'
                )
            );

            import('lib.pkp.classes.linkAction.request.RemoteActionConfirmationModal');
            $this->addAction(
                new LinkAction(
                    'delete',
                    new RemoteActionConfirmationModal(
                        $request->getSession(),
                        __('common.confirmDelete'),
                        __('grid.action.delete'),
                        $router->url($request, null, null, 'deleteFunder', null, array('funderId' => $funderId, 'submissionId' => $submissionId)),
                        'modal_delete'
                    ),
                    __('grid.action.delete'),
                    'delete'
                )
            );
        }
    }

    public function isReadOnly()
    {
        return $this->_readOnly;
    }
}
