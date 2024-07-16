<?php

namespace APP\plugins\generic\plauditPreEndorsement\classes\migration\upgrade;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use PKP\install\DowngradeNotSupportedException;

class MoveDeprecatedEndorsementsToEndorsersTable extends Migration
{
    public function up(): void
    {
        $endorsementSettings = $this->getEndorsementsFromPublicationSettings();

        if (!empty($endorsementSettings)) {
            $deprecatedEndorsements = $this->getDeprecatedEndorsements($endorsementSettings);
            $this->moveToEndorsersTable($deprecatedEndorsements);
            $this->deleteDeprecatedEndorsements();
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

    private function getDeprecatedEndorsements($endorsementSettings)
    {
        $deprecatedEndorsements = [];
        foreach ($endorsementSettings as $endorsementSetting) {
            $publicationId = $endorsementSetting->publication_id;
            $deprecatedEndorsements[$publicationId][$endorsementSetting->setting_name] = $endorsementSetting->setting_value;
        }
        return $deprecatedEndorsements;
    }

    private function moveToEndorsersTable($deprecatedEndorsements)
    {
        foreach ($deprecatedEndorsements as $publicationId => $settings) {
            $submissionId = DB::table('publications')->where('publication_id', $publicationId)->value('submission_id');
            $contextId = DB::table('submissions')->where('submission_id', $submissionId)->value('context_id');

            DB::table('endorsers')->insert([
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

    private function deleteDeprecatedEndorsements()
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
