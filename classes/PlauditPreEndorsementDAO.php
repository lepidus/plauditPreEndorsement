<?php

namespace APP\plugins\generic\plauditPreEndorsement\classes;

use PKP\db\DAO;
use Illuminate\Support\Facades\DB;
use APP\facades\Repo;
use APP\submission\Submission;
use APP\plugins\generic\plauditPreEndorsement\classes\Endorsement;

class PlauditPreEndorsementDAO extends DAO
{
    public function getPublicationsWithEndorsementReadyToSend(int $contextId): array
    {
        $result = DB::table('submissions as sub')
            ->leftJoin('publications as pub', 'sub.current_publication_id', '=', 'pub.publication_id')
            ->leftJoin('publication_settings as ps', 'pub.publication_id', '=', 'ps.publication_id')
            ->where('ps.setting_name', 'endorsementStatus')
            ->where('ps.setting_value', Endorsement::STATUS_CONFIRMED)
            ->where('sub.status', Submission::STATUS_PUBLISHED)
            ->where('sub.context_id', $contextId)
            ->select('pub.publication_id')
            ->get();

        $publications = [];

        foreach ($result as $row) {
            $row = (array) $row;
            $publications[] = Repo::publication()->get($row['publication_id']);
        }

        return $publications;

    }
}
