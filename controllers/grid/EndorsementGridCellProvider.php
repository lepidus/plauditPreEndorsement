<?php

namespace APP\plugins\generic\plauditPreEndorsement\controllers\grid;

use PKP\controllers\grid\GridCellProvider;
use APP\plugins\generic\plauditPreEndorsement\classes\endorsement\Endorsement;

class EndorsementGridCellProvider extends GridCellProvider
{
    public function getTemplateVarsFromRowColumn($row, $column)
    {
        $element = $row->getData();
        switch ($column->getId()) {
            case 'endorserName':
                return array('label' => $element->getName(), 'orcid' => $element->getOrcid());
            case 'endorserEmail':
                return array('label' => $element->getEmail(), 'emailCount' => $element->getEmailCount());
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
            Endorsement::STATUS_NOT_CONFIRMED => __('plugins.generic.plauditPreEndorsement.endorsementNotConfirmed'),
            Endorsement::STATUS_CONFIRMED => __('plugins.generic.plauditPreEndorsement.endorsementConfirmed'),
            Endorsement::STATUS_DENIED => __('plugins.generic.plauditPreEndorsement.endorsementDenied'),
            Endorsement::STATUS_COMPLETED => __('plugins.generic.plauditPreEndorsement.endorsementCompleted'),
            Endorsement::STATUS_COULDNT_COMPLETE => __('plugins.generic.plauditPreEndorsement.endorsementCouldntComplete')
        ];

        return $mapStatusToSuffix[$endorsementStatus] ?? "";
    }

    private function getEndorsementStatusBadge(?int $endorsementStatus): string
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
