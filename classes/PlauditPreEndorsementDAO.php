<?php

use Illuminate\Database\Capsule\Manager as Capsule;

import('lib.pkp.classes.db.DAO');

class PlauditPreEndorsementDAO extends DAO
{
    public function getPublicationsWithEndorsementReadyToSend(int $contextId): array
    {
        $result = Capsule::table('submissions as sub')
            ->leftJoin('publications as pub', 'sub.current_publication_id', '=', 'pub.publication_id')
            ->leftJoin('publication_settings as ps', 'pub.publication_id', '=', 'ps.publication_id')
            ->where('ps.setting_name', 'endorsementStatus')
            ->where('ps.setting_value', ENDORSEMENT_STATUS_CONFIRMED)
            ->where('sub.status', STATUS_PUBLISHED)
            ->where('sub.context_id', $contextId)
            ->select('pub.publication_id')
            ->get();

        $publications = [];
        $publicationDao = DAORegistry::getDAO('PublicationDAO');

        foreach ($result as $row) {
            $row = (array) $row;
            $publications[] = $publicationDao->getById($row['publication_id']);
        }

        return $publications;

    }
}
