<?php

namespace APP\plugins\generic\plauditPreEndorsement\controllers\grid;

use PKP\controllers\grid\GridCellProvider;

class EndorsementGridCellProvider extends GridCellProvider
{
    public function getTemplateVarsFromRowColumn($row, $column)
    {
        $element = $row->getData();
        switch ($column->getId()) {
            case 'endorserName':
                return array('label' => $element['name']);
            case 'endorserEmail':
                return array('label' => $element['email']);
            case 'endorsementStatus':
                return array('label' => $element['endorsementStatus']);
        }
    }
}
