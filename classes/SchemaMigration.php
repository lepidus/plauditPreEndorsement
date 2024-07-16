<?php

namespace APP\plugins\generic\plauditPreEndorsement\classes;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PKP\install\DowngradeNotSupportedException;

class SchemaMigration extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('endorsers')) {
            Schema::create('endorsers', function (Blueprint $table) {
                $table->bigInteger('endorser_id')->autoIncrement();
                $table->bigInteger('context_id');
                $table->bigInteger('publication_id');
                $table->string('name');
                $table->string('email');
                $table->integer('status')->nullable();
                $table->string('orcid')->nullable();
                $table->string('email_token')->nullable();
                $table->integer('email_count')->default(0);

                $table->foreign('context_id')
                    ->references('server_id')
                    ->on('servers')
                    ->onDelete('cascade');
                $table->index(['context_id'], 'endorsers_context_id');

                $table->foreign('publication_id')
                    ->references('publication_id')
                    ->on('publications')
                    ->onDelete('cascade');
                $table->index(['context_id'], 'endorsers_publication_id');

                $table->unique(['context_id', 'publication_id', 'email'], 'endorser_pkey');
            });
        }

        $publicationSettings = DB::table('publication_settings')
            ->whereIn('setting_name', [
                'endorserName',
                'endorserEmail',
                'endorsementStatus',
                'endorserOrcid',
                'endorserEmailToken',
                'endorserEmailCount'
            ])
            ->get();

        $endorsements = [];
        foreach ($publicationSettings as $setting) {
            $endorsements[$setting->publication_id][$setting->setting_name] = $setting->setting_value;
        }

        foreach ($endorsements as $publication_id => $data) {
            $context_id = DB::table('publications')
                ->where('publication_id', $publication_id)
                ->value('context_id');

            DB::table('endorsers')->insert([
                'context_id' => $context_id,
                'publication_id' => $publication_id,
                'name' => $data['endorserName'] ?? '',
                'email' => $data['endorserEmail'] ?? '',
                'status' => $data['endorsementStatus'] ?? null,
                'orcid' => $data['endorserOrcid'] ?? '',
                'email_token' => $data['endorserEmailToken'] ?? '',
                'email_count' => $data['endorserEmailCount'] ?? 0,
            ]);
        }
    }

    public function down(): void
    {
        throw new DowngradeNotSupportedException();
    }
}
