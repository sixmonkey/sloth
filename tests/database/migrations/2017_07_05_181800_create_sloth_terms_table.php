<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSlothTermsTable extends Migration
{
    public function up(): void
    {
        Schema::create('terms', function (Blueprint $table) {
            $table->increments('term_id');
            $table->string('name');
            $table->string('slug');
            $table->bigInteger('term_group');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('terms');
    }
}