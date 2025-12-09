<?php

namespace APP\plugins\generic\plauditPreEndorsement\classes\tasks;

use PKP\scheduledTask\ScheduledTask;
use PKP\plugins\PluginRegistry;
use APP\core\Application;
use APP\submission\Submission;
use Illuminate\Support\Facades\DB;
use APP\plugins\generic\plauditPreEndorsement\classes\EndorsementService;
use APP\plugins\generic\plauditPreEndorsement\classes\facades\Repo;
use APP\plugins\generic\plauditPreEndorsement\classes\endorsement\Endorsement;

class SendReadyEndorsements extends ScheduledTask
{
    public function executeActions()
    {
        PluginRegistry::loadCategory('generic');
        $plugin = PluginRegistry::getPlugin('generic', 'plauditpreendorsementplugin');
        $context = Application::get()->getRequest()->getContext();
        $readyEndorsements = Repo::endorsement()->getCollector()
            ->filterByContextIds([$context->getId()])
            ->filterByStatus([Endorsement::STATUS_CONFIRMED])
            ->getMany()
            ->toArray();

        foreach ($readyEndorsements as $endorsement) {
            $submissionStatus = $this->getEndorsementSubmissionStatus($endorsement);
            if (is_null($submissionStatus) || $submissionStatus != Submission::STATUS_PUBLISHED) {
                continue;
            }

            $endorsementService = new EndorsementService($context->getId(), $plugin);
            $endorsementService->sendEndorsement($endorsement, true);
        }

        return true;
    }

    private function getEndorsementSubmissionStatus($endorsement)
    {
        $row = DB::table('publications AS p')
            ->leftJoin('submissions AS s', 'p.submission_id', '=', 's.submission_id')
            ->where('p.publication_id', $endorsement->getPublicationId())
            ->select('s.status')
            ->first();
        return $row ? $row->status : null;
    }
}
