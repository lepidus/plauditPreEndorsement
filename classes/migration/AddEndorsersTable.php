<?php

namespace APP\plugins\generic\plauditPreEndorsement\classes\migration;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PKP\install\DowngradeNotSupportedException;
use APP\plugins\generic\plauditPreEndorsement\classes\migration\upgrade\MoveDeprecatedEndorsementsToEndorsersTable;

class addEndorsementsTable extends Migration
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

        $upgradeMigration = new MoveDeprecatedEndorsementsToEndorsersTable();
        $upgradeMigration->up();
    }

    public function down(): void
    {
        throw new DowngradeNotSupportedException();
    }
}
