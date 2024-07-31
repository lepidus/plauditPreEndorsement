<?php

namespace APP\plugins\generic\plauditPreEndorsement\classes\migration\upgrade;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use PKP\install\DowngradeNotSupportedException;

class MoveLegacyEndorsementsToEndorsementsTable extends Migration
{
    public function up(): void
    {
        $endorsementSettings = $this->getEndorsementsFromPublicationSettings();

        if (!empty($endorsementSettings)) {
            $legacyEndorsements = $this->getLegacyEndorsements($endorsementSettings);
            $this->moveToEndorsementsTable($legacyEndorsements);
            $this->deleteLegacyEndorsements();
        }
    }

    public function down(): void
    {
        throw new DowngradeNotSupportedException();
    }

    private function getEndorsementsFromPublicationSettings()
    {
        return DB::table('publication_settings')
            ->whereIn('setting_name', [
                'endorserName',
                'endorserEmail',
                'endorsementStatus',
                'endorserOrcid',
                'endorserEmailToken',
                'endorserEmailCount'
            ])
            ->get();
    }

    private function getLegacyEndorsements($endorsementSettings)
    {
        $legacyEndorsements = [];
        foreach ($endorsementSettings as $endorsementSetting) {
            $publicationId = $endorsementSetting->publication_id;
            $legacyEndorsements[$publicationId][$endorsementSetting->setting_name] = $endorsementSetting->setting_value;
        }
        return $legacyEndorsements;
    }

    private function moveToEndorsementsTable($legacyEndorsements)
    {
        foreach ($legacyEndorsements as $publicationId => $settings) {
            $submissionId = DB::table('publications')->where('publication_id', $publicationId)->value('submission_id');
            $contextId = DB::table('submissions')->where('submission_id', $submissionId)->value('context_id');

            DB::table('endorsements')->insert([
                'context_id' => $contextId,
                'publication_id' => $publicationId,
                'name' => $settings['endorserName'] ?? '',
                'email' => $settings['endorserEmail'] ?? '',
                'status' => $settings['endorsementStatus'] ?? null,
                'orcid' => $settings['endorserOrcid'] ?? '',
                'email_token' => $settings['endorserEmailToken'] ?? '',
                'email_count' => $settings['endorserEmailCount'] ?? 0,
            ]);
        }
    }

    private function deleteLegacyEndorsements()
    {
        DB::table('publication_settings')
            ->whereIn('setting_name', [
                'endorserName',
                'endorserEmail',
                'endorsementStatus',
                'endorserOrcid',
                'endorserEmailToken',
                'endorserEmailCount'
            ])->delete();
    }
}
