<?php

namespace APP\plugins\generic\plauditPreEndorsement\classes\tasks;

use PKP\scheduledTask\ScheduledTask;
use PKP\plugins\PluginRegistry;
use APP\core\Application;
use APP\plugins\generic\plauditPreEndorsement\classes\EndorsementService;
use APP\plugins\generic\plauditPreEndorsement\classes\facades\Repo;
use APP\plugins\generic\plauditPreEndorsement\classes\EndorsementStatus;

class SendReadyEndorsements extends ScheduledTask
{
    public function executeActions()
    {
        PluginRegistry::loadCategory('generic');
        $plugin = PluginRegistry::getPlugin('generic', 'plauditpreendorsementplugin');
        $context = Application::get()->getRequest()->getContext();
        $readyEndorsements = Repo::endorsement()->getCollector()
            ->filterByContextIds([$context->getId()])
            ->filterByStatus([EndorsementStatus::CONFIRMED])
            ->getMany()
            ->toArray();

        foreach($readyEndorsements as $endorsement) {
            $endorsementService = new EndorsementService($context->getId(), $plugin);
            $endorsementService->sendEndorsement($endorsement, true);
        }

        return true;
    }
}
