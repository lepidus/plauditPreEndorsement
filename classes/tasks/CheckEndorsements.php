<?php

namespace APP\plugins\generic\plauditPreEndorsement\classes\tasks;

use PKP\scheduledTask\ScheduledTask;
use PKP\plugins\PluginRegistry;
use APP\core\Application;
use Illuminate\Support\Facades\DB;
use APP\plugins\generic\plauditPreEndorsement\classes\facades\Repo;
use APP\plugins\generic\plauditPreEndorsement\classes\endorsement\Endorsement;

class CheckEndorsements extends ScheduledTask
{
    public function executeActions()
    {
        PluginRegistry::loadCategory('generic');
        $plugin = PluginRegistry::getPlugin('generic', 'plauditpreendorsementplugin');
        $context = Application::get()->getRequest()->getContext();
        $endorsements = Repo::endorsement()->getCollector()
            ->filterByContextIds([$context->getId()])
            ->filterByStatus([Endorsement::STATUS_NOT_CONFIRMED])
            ->getMany()
            ->toArray();

        foreach ($endorsements as $endorsement) {
            $authorsEmails = $this->getPublicationAuthorsEmails($endorsement->getPublicationId());

            if (in_array($endorsement->getEmail(), $authorsEmails)) {
                $submissionId = $this->getSubmissionIdByEndorsement($endorsement);

                if (!is_null($submissionId)) {
                    Repo::endorsement()->delete($endorsement);
                    $plugin->writeOnActivityLog(
                        $submissionId,
                        'plugins.generic.plauditPreEndorsement.log.endorsementRemoved.emailFromAuthor',
                        ['email' => $endorsement->getEmail()]
                    );
                }
            }
        }

        return true;
    }

    private function getPublicationAuthorsEmails($publicationId)
    {
        $rows = DB::table('authors AS a')
            ->where('a.publication_id', $publicationId)
            ->select('a.email')
            ->get();

        $emails = [];
        foreach ($rows as $row) {
            $emails[] = $row->email;
        }
        return $emails;
    }

    private function getSubmissionIdByEndorsement($endorsement)
    {
        $publicationId = $endorsement->getPublicationId();
        $row = DB::table('publications AS p')
            ->where('p.publication_id', $publicationId)
            ->select('p.submission_id')
            ->first();

        return $row ? $row->submission_id : null;
    }
}
