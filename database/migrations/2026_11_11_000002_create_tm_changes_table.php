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
        if (Schema::connection($this->getConnection())->hasTable('tm_changes')) {
            return;
        }

        Schema::connection($this->getConnection())->create('tm_changes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('app', 16);
            $table->string('locale', 16)->collation('utf8mb4_bin');
            $table->string('group', 100)->collation('utf8mb4_bin');
            $table->string('key', 255)->collation('utf8mb4_bin');
            $table->string('action', 16);
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            // The name is stored too: the users table can be replaced by a dump.
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_name')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['app', 'locale', 'group', 'key'], 'tm_changes_key_index');
            $table->index(['app', 'created_at'], 'tm_changes_time_index');
        });
    }

    public function down()
    {
        Schema::connection($this->getConnection())->dropIfExists('tm_changes');
    }

};
