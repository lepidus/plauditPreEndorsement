<?php

namespace APP\plugins\generic\plauditPreEndorsement\classes\tasks;

use PKP\scheduledTask\ScheduledTask;
use APP\core\Application;
use APP\submission\Submission;
use Illuminate\Support\Facades\DB;
use APP\plugins\generic\plauditPreEndorsement\classes\EndorsementService;
use APP\plugins\generic\plauditPreEndorsement\classes\facades\Repo;
use APP\plugins\generic\plauditPreEndorsement\classes\endorsement\Endorsement;

class CheckEndorsements extends ScheduledTask
{
    public function executeActions()
    {
        $context = Application::get()->getRequest()->getContext();
        $endorsements = Repo::endorsement()->getCollector()
            ->filterByContextIds([$context->getId()])
            ->filterByStatus([Endorsement::STATUS_NOT_CONFIRMED])
            ->getMany()
            ->toArray();

        foreach ($endorsements as $endorsement) {
            $authorsEmails = $this->getPublicationAuthorsEmails($endorsement->getPublicationId());

            if (in_array($endorsement->getEmail(), $authorsEmails)) {
                Repo::endorsement()->delete($endorsement);
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
}
