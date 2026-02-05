<?php

namespace APP\plugins\generic\plauditPreEndorsement\classes\tasks;

use PKP\scheduledTask\ScheduledTask;
use PKP\plugins\PluginRegistry;
use APP\core\Application;
use APP\submission\Submission;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use APP\plugins\generic\plauditPreEndorsement\classes\facades\Repo;
use APP\plugins\generic\plauditPreEndorsement\classes\EndorsementService;
use APP\plugins\generic\plauditPreEndorsement\classes\endorsement\Endorsement;
use APP\plugins\generic\plauditPreEndorsement\classes\mail\builders\OrcidWithoutWorksEmailBuilder;

class CheckEndorsements extends ScheduledTask
{
    private const ORCID_REQUEST_INTERVAL_DAYS = 3;

    public function executeActions()
    {
        PluginRegistry::loadCategory('generic');
        $plugin = PluginRegistry::getPlugin('generic', 'plauditpreendorsementplugin');
        $context = Application::get()->getRequest()->getContext();
        $endorsements = Repo::endorsement()->getCollector()
            ->filterByContextIds([$context->getId()])
            ->filterByStatus([Endorsement::STATUS_NOT_CONFIRMED, Endorsement::STATUS_CONFIRMED])
            ->getMany()
            ->toArray();

        foreach ($endorsements as $endorsement) {
            $authorsEmails = $this->getPublicationAuthorsEmails($endorsement->getPublicationId());
            if (in_array($endorsement->getEmail(), $authorsEmails)) {
                $submissionId = $this->getSubmissionIdByEndorsement($endorsement);

                Repo::endorsement()->delete($endorsement);
                $plugin->writeOnActivityLog(
                    $submissionId,
                    'plugins.generic.plauditPreEndorsement.log.endorsementRemoved.emailFromAuthor',
                    ['email' => $endorsement->getEmail()]
                );
            }

            if ($endorsement->getStatus() == Endorsement::STATUS_CONFIRMED) {
                $this->checkEndorsementOrcid($endorsement, $context, $plugin);
            }

            if ($endorsement->getStatus() == Endorsement::STATUS_NOT_CONFIRMED) {
                $this->checkOrcidRequestMessage($endorsement);
            }
        }

        return true;
    }

    private function checkEndorsementOrcid($endorsement, $context, $plugin)
    {
        $endorsementService = new EndorsementService($context->getId(), $plugin);
        $publication = Repo::publication()->get($endorsement->getPublicationId());
        $validationMessage = $endorsementService->validateEndorsementSending($endorsement, $publication);

        if ($validationMessage == 'plugins.generic.plauditPreEndorsement.log.endorsementRemoved.orcidFromAuthor') {
            $submissionId = $this->getSubmissionIdByEndorsement($endorsement);

            Repo::endorsement()->delete($endorsement);
            $plugin->writeOnActivityLog(
                $submissionId,
                'plugins.generic.plauditPreEndorsement.log.endorsementRemoved.orcidFromAuthor',
                ['orcid' => $endorsement->getOrcid()]
            );
        }
    }

    private function checkOrcidRequestMessage($endorsement)
    {
        $today = (new \DateTime())->format('Y-m-d');

        $submissionData = $this->getSubmissionDataByEndorsement($endorsement);
        if (is_null($submissionData)) {
            return;
        }

        $submissionStatus = (int) $submissionData['status'];
        $dateSubmitted = (new \DateTime($submissionData['date_submitted']))->format('Y-m-d');
        if ($submissionStatus != Submission::STATUS_QUEUED || $dateSubmitted == $today) {
            return;
        }

        $lastEmailDate = $endorsement->getLastEmailDate();
        if (!empty($lastEmailDate)) {
            $daysSinceLastEmail = (int) (new \DateTime($lastEmailDate))
                ->diff(new \DateTime($today))
                ->format('%a');

            if ($daysSinceLastEmail < self::ORCID_REQUEST_INTERVAL_DAYS) {
                return;
            }
        }


        $publication = Repo::publication()->get($endorsement->getPublicationId());
        $submission = Repo::submission()->get($publication->getData('submissionId'));

        $email = (new OrcidWithoutWorksEmailBuilder())
            ->setEndorsement($endorsement)
            ->setPublication($publication)
            ->buildEmailParams()
            ->build(['submission' => $submission]);

        Mail::send($email);

        $endorsement->setLastEmailDate($today);
        Repo::endorsement()->edit($endorsement, []);
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

    private function getSubmissionDataByEndorsement($endorsement)
    {
        $publicationId = $endorsement->getPublicationId();
        $row = DB::table('publications AS p')
            ->leftJoin('submissions AS s', 's.submission_id', '=', 'p.submission_id')
            ->where('p.publication_id', $publicationId)
            ->select('s.status', 's.date_submitted')
            ->first();

        return $row ? get_object_vars($row) : null;
    }
}
