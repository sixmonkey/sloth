<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSlothTermRelationshipsTable extends Migration
{
    public function up(): void
    {
        Schema::create('term_relationships', function (Blueprint $table) {
            $table->bigInteger('object_id')->unsigned();
            $table->bigInteger('term_taxonomy_id');
            $table->integer('term_order')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('term_relationships');
    }
}