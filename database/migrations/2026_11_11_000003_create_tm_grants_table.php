<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function getConnection()
    {
        return config('translation-manager.connection');
    }

    public function up()
    {
        if (Schema::connection($this->getConnection())->hasTable('tm_grants')) {
            return;
        }

        // What a translator may edit. Shared by every application that uses the tables.
        Schema::connection($this->getConnection())->create('tm_grants', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->unique();
            $table->json('locales');
            $table->json('content_groups');
            $table->string('reference_locale', 16)->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::connection($this->getConnection())->dropIfExists('tm_grants');
    }

};
