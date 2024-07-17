<?php

namespace APP\plugins\generic\plauditPreEndorsement\controllers\grid;

use PKP\controllers\grid\GridCellProvider;
use APP\plugins\generic\plauditPreEndorsement\classes\EndorsementStatus;

class EndorsementGridCellProvider extends GridCellProvider
{
    public function getTemplateVarsFromRowColumn($row, $column)
    {
        $element = $row->getData();
        switch ($column->getId()) {
            case 'endorserName':
                return array('label' => $element->getName(), 'orcid' => $element->getOrcid());
            case 'endorserEmail':
                return array('label' => $element->getEmail());
            case 'endorsementStatus':
                return array(
                    'label' => $this->getEndorsementStatusSuffix($element->getStatus()),
                    'badgeClass' => $this->getEndorsementStatusBadge($element->getStatus())
                );
        }
    }

    private function getEndorsementStatusSuffix(?int $endorsementStatus): string
    {
        $mapStatusToSuffix = [
            EndorsementStatus::STATUS_NOT_CONFIRMED => __('plugins.generic.plauditPreEndorsement.endorsementNotConfirmed'),
            EndorsementStatus::STATUS_CONFIRMED => __('plugins.generic.plauditPreEndorsement.endorsementConfirmed'),
            EndorsementStatus::STATUS_DENIED => __('plugins.generic.plauditPreEndorsement.endorsementDenied'),
            EndorsementStatus::STATUS_COMPLETED => __('plugins.generic.plauditPreEndorsement.endorsementCompleted'),
            EndorsementStatus::STATUS_COULDNT_COMPLETE => __('plugins.generic.plauditPreEndorsement.endorsementCouldntComplete')
        ];

        return $mapStatusToSuffix[$endorsementStatus] ?? "";
    }

    private function getEndorsementStatusBadge(?int $endorsementStatus): string
    {
        $mapStatusToSuffix = [
            EndorsementStatus::STATUS_NOT_CONFIRMED => 'endorsementStatusCustomBadge',
            EndorsementStatus::STATUS_CONFIRMED => 'pkpBadge pkpBadge--isPrimary',
            EndorsementStatus::STATUS_DENIED => 'pkpBadge pkpBadge--isWarnable',
            EndorsementStatus::STATUS_COMPLETED => 'pkpBadge pkpBadge--isSuccess',
            EndorsementStatus::STATUS_COULDNT_COMPLETE => 'pkpBadge pkpBadge--isWarnable'
        ];

        return $mapStatusToSuffix[$endorsementStatus] ?? "";
    }
}
