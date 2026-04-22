<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSlothOptionsTable extends Migration
{
    public function up(): void
    {
        Schema::create('options', function (Blueprint $table) {
            $table->increments('option_id');
            $table->string('option_name');
            $table->longText('option_value');
            $table->string('autoload')->default('yes');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('options');
    }
}