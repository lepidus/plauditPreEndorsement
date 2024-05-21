<?php

namespace APP\plugins\generic\plauditPreEndorsement\controllers\grid;

use PKP\controllers\grid\GridCellProvider;
use APP\plugins\generic\plauditPreEndorsement\classes\Endorsement;

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
                return array(
                    'label' => $this->getEndorsementStatusSuffix($element['endorsementStatus']),
                    'badgeClass' => $this->getEndorsementStatusBadge($element['endorsementStatus'])
                );
        }
    }

    private function getEndorsementStatusSuffix(int $endorsementStatus): string
    {
        $mapStatusToSuffix = [
            Endorsement::STATUS_NOT_CONFIRMED => 'NotConfirmed',
            Endorsement::STATUS_CONFIRMED => 'Confirmed',
            Endorsement::STATUS_DENIED => 'Denied',
            Endorsement::STATUS_COMPLETED => 'Completed',
            Endorsement::STATUS_COULDNT_COMPLETE => 'CouldntComplete'
        ];

        return $mapStatusToSuffix[$endorsementStatus] ?? "";
    }

    private function getEndorsementStatusBadge(int $endorsementStatus): string
    {
        $mapStatusToSuffix = [
            Endorsement::STATUS_NOT_CONFIRMED => 'endorsementStatusCustomBadge',
            Endorsement::STATUS_CONFIRMED => 'pkpBadge pkpBadge--isPrimary',
            Endorsement::STATUS_DENIED => 'pkpBadge pkpBadge--isWarnable',
            Endorsement::STATUS_COMPLETED => 'pkpBadge pkpBadge--isSuccess',
            Endorsement::STATUS_COULDNT_COMPLETE => 'pkpBadge pkpBadge--isWarnable'
        ];

        return $mapStatusToSuffix[$endorsementStatus] ?? "";
    }
}
