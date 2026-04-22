<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSlothTermmetaTable extends Migration
{
    public function up(): void
    {
        Schema::create('termmeta', function (Blueprint $table) {
            $table->increments('meta_id');
            $table->bigInteger('term_id')->unsigned();
            $table->string('meta_key');
            $table->longText('meta_value');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('termmeta');
    }
}