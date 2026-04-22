<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSlothCommentmetaTable extends Migration
{
    public function up(): void
    {
        Schema::create('commentmeta', function (Blueprint $table) {
            $table->increments('meta_id');
            $table->bigInteger('comment_id')->unsigned();
            $table->string('meta_key');
            $table->longText('meta_value');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commentmeta');
    }
}