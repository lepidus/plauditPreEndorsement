<?php

namespace APP\plugins\generic\plauditPreEndorsement\classes\observers\listeners;

use Illuminate\Events\Dispatcher;
use PKP\observers\events\SubmissionSubmitted;
use PKP\plugins\PluginRegistry;

class SendEmailToEndorser
{
    public function subscribe(Dispatcher $events): void
    {
        $events->listen(
            SubmissionSubmitted::class,
            SendEmailToEndorser::class
        );
    }

    public function handle(SubmissionSubmitted $event): void
    {
        $publication = $event->submission->getCurrentPublication();

        if (!empty($publication->getData('endorserEmail'))) {
            $plugin = PluginRegistry::getPlugin('generic', 'plauditpreendorsementplugin');
            $plugin->sendEmailToEndorser($publication);
        }
    }
}
