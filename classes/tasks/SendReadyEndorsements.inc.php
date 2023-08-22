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
            $doiIsDeposited = $this->doiIsDeposited($publication->getData('pub-id::doi'));

            if($doiIsDeposited) {
                $plugin->sendEndorsementToPlaudit($publication);
            }
        }

        return true;
    }

    private function doiIsDeposited(string $doi): bool
    {
        $doiUrl = "https://doi.org/".$doi;
        $headers = get_headers($doiUrl);
        $statusCode = intval(substr($headers[0], 9, 3));

        $HTTP_STATUS_FOUND = 302;

        if(!empty($doi) and $statusCode == $HTTP_STATUS_FOUND) {
            return true;
        }

        return false;
    }
}
