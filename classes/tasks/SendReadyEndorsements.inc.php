<?php

import('lib.pkp.classes.scheduledTask.ScheduledTask');
import('plugins.generic.plauditPreEndorsement.classes.PlauditPreEndorsementDAO');
import('plugins.generic.plauditPreEndorsement.classes.EndorsementService');

class SendReadyEndorsements extends ScheduledTask
{
    public function executeActions()
    {
        PluginRegistry::loadCategory('generic');
        $plugin = PluginRegistry::getPlugin('generic', 'plauditpreendorsementplugin');
        $preEndorsementDao = new PlauditPreEndorsementDAO();
        $context = Application::get()->getRequest()->getContext();

        $readyPublications = $preEndorsementDao->getPublicationsWithEndorsementReadyToSend($context->getId());

        foreach ($readyPublications as $publication) {
            $endorsementService = new EndorsementService($context->getId(), $plugin);
            $endorsementService->sendEndorsement($publication, true);
        }

        return true;
    }
}
