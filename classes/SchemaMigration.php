<?php

namespace APP\plugins\generic\plauditPreEndorsement\classes;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class SchemaMigration extends Migration
{
    public function up(): void
    {
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
}
