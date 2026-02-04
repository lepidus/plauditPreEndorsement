<?php

namespace APP\plugins\generic\plauditPreEndorsement\classes\migration;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PKP\install\DowngradeNotSupportedException;

class EndorsementSchemaMigration extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('endorsements')) {
            Schema::create('endorsements', function (Blueprint $table) {
                $table->bigInteger('endorsement_id')->autoIncrement();
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

                $table->unique(['context_id', 'publication_id', 'email'], 'endorsement_pkey');
            });
        }

        if (!Schema::hasTable('endorsement_settings')) {
            Schema::create('endorsement_settings', function (Blueprint $table) {
                $table->bigIncrements('endorsement_setting_id');
                $table->bigInteger('endorsement_id');
                $table->string('locale', 14)->default('');
                $table->string('setting_name', 255);
                $table->longText('setting_value')->nullable();

                $table->foreign('endorsement_id')
                    ->references('endorsement_id')
                    ->on('endorsements')
                    ->onDelete('cascade');
                $table->index(['endorsement_id'], 'endorsement_settings_id');
                $table->unique(['endorsement_id', 'locale', 'setting_name'], 'endorsement_settings_pkey');
            });
        }
    }

    public function down(): void
    {
        throw new DowngradeNotSupportedException();
    }
}
