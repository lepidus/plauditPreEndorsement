<?php

namespace APP\plugins\generic\plauditPreEndorsement\classes\tasks;

use PKP\scheduledTask\ScheduledTask;
use PKP\plugins\PluginRegistry;
use APP\core\Application;
use APP\plugins\generic\plauditPreEndorsement\classes\EndorsementService;
use APP\plugins\generic\plauditPreEndorsement\classes\endorser\Repository as EndorserRepository;
use APP\plugins\generic\plauditPreEndorsement\classes\EndorsementStatus;

class SendReadyEndorsements extends ScheduledTask
{
    public function executeActions()
    {
        PluginRegistry::loadCategory('generic');
        $plugin = PluginRegistry::getPlugin('generic', 'plauditpreendorsementplugin');
        $context = Application::get()->getRequest()->getContext();
        $endorserRepository = app(EndorserRepository::class);
        $readyEndorsers = $repository->getCollector()
            ->filterByContextIds([$context->getId()])
            ->filterByStatus([EndorsementStatus::STATUS_CONFIRMED])
            ->getMany()
            ->toArray();

        foreach($readyEndorsers as $endorser) {
            $endorsementService = new EndorsementService($context->getId(), $plugin);
            $endorsementService->sendEndorsement($endorser, true);
        }

        return true;
    }
}
