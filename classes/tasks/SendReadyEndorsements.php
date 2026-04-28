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
        $contextDao = Application::getContextDAO();
        $contexts = $contextDao->getAll(true);

        while ($context = $contexts->next()) {
            $this->sendReadyEndorsementsFromContext($plugin, $context->getId());
        }

        return true;
    }

    private function sendReadyEndorsementsFromContext($plugin, $contextId)
    {
        $readyEndorsements = Repo::endorsement()->getCollector()
            ->filterByContextIds([$contextId])
            ->filterByStatus([Endorsement::STATUS_CONFIRMED])
            ->getMany()
            ->toArray();

        $endorsementService = new EndorsementService($contextId, $plugin);
        foreach ($readyEndorsements as $endorsement) {
            $submissionStatus = $this->getEndorsementSubmissionStatus($endorsement);
            if (is_null($submissionStatus) || $submissionStatus != Submission::STATUS_PUBLISHED) {
                continue;
            }

            $endorsementService->sendEndorsement($endorsement, true);
        }
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
