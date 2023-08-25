<?php

import('lib.pkp.classes.scheduledTask.ScheduledTask');
import('plugins.generic.plauditPreEndorsement.classes.PlauditPreEndorsementDAO');

class SendReadyEndorsements extends ScheduledTask
{
    public function executeActions()
    {
        PluginRegistry::loadCategory('generic');
        $plugin = PluginRegistry::getPlugin('generic', 'plauditpreendorsementplugin');
        $preEndorsementDao = new PlauditPreEndorsementDAO();
        $context = Application::get()->getRequest()->getContext();

        $readyPublications = $preEndorsementDao->getPublicationsWithEndorsementReadyToSend($context->getId());

        foreach($readyPublications as $publication) {
            $plugin->sendEndorsementToPlaudit($publication);
        }

        return true;
    }
}
