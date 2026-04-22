<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSlothTermTaxonomyTable extends Migration
{
    public function up(): void
    {
        Schema::create('term_taxonomy', function (Blueprint $table) {
            $table->increments('term_taxonomy_id');
            $table->bigInteger('term_id')->unsigned();
            $table->string('taxonomy');
            $table->longText('description');
            $table->bigInteger('parent')->unsigned();
            $table->bigInteger('count');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('term_taxonomy');
    }
}