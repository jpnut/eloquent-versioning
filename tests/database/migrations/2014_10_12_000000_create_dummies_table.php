<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDummiesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('dummies', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('version')->unsigned()->nullable();
            $table->string('name')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('dummy_versions', function (Blueprint $table) {
            $table->integer('parent_id')->unsigned();
            $table->integer('version')->unsigned();
            $table->string('email')->nullable();
            $table->string('city')->nullable();
            $table->timestamp('created_at');
            $table->integer('role_id')->unsigned()->nullable();

            $table->primary(['parent_id', 'version']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dummies');
        Schema::dropIfExists('dummy_versions');
    }
}
