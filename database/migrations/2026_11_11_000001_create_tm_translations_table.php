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
        // The tables can outlive the migration record when they sit in another database.
        if (Schema::connection($this->getConnection())->hasTable('tm_translations')) {
            return;
        }

        Schema::connection($this->getConnection())->create('tm_translations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('app', 16);
            $table->string('locale', 16)->collation('utf8mb4_bin');
            // Binary collation: keys that differ only by case or accents are different keys.
            $table->string('group', 100)->collation('utf8mb4_bin');
            $table->string('key', 255)->collation('utf8mb4_bin');
            $table->text('value')->nullable();
            // The text in version control before the first change that isn't committed yet.
            $table->text('base_value')->nullable();
            $table->string('status', 16)->default('saved');
            $table->boolean('ai')->default(false);
            $table->unsignedBigInteger('changed_by')->nullable();
            $table->string('changed_by_name')->nullable();
            $table->timestamp('changed_at')->nullable();
            $table->timestamps();

            $table->unique(['app', 'locale', 'group', 'key'], 'tm_translations_key_unique');
            $table->index(['app', 'status'], 'tm_translations_status_index');
        });
    }

    public function down()
    {
        Schema::connection($this->getConnection())->dropIfExists('tm_translations');
    }

};
