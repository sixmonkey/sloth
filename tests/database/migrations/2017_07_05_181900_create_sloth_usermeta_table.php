<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSlothUsermetaTable extends Migration
{
    public function up(): void
    {
        Schema::create('usermeta', function (Blueprint $table) {
            $table->increments('umeta_id');
            $table->bigInteger('user_id')->unsigned();
            $table->string('meta_key');
            $table->longText('meta_value');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usermeta');
    }
}