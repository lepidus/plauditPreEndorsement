<?php

namespace APP\plugins\generic\plauditPreEndorsement\classes\observers\listeners;

use Illuminate\Events\Dispatcher;
use PKP\observers\events\SubmissionSubmitted;
use PKP\plugins\PluginRegistry;
use APP\plugins\generic\plauditPreEndorsement\classes\facades\Repo;

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
        $contextId = $event->context->getId();
        $endorsements = Repo::endorsement()->getCollector()
            ->filterByContextIds([$contextId])
            ->filterByPublicationIds([$publication->getId()])
            ->getMany()
            ->toArray();

        $plugin = PluginRegistry::getPlugin('generic', 'plauditpreendorsementplugin');

        foreach ($endorsements as $endorsement) {
            $plugin->sendEmailToEndorser($publication, $endorsement);
        }
    }
}
