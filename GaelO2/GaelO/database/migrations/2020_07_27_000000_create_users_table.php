<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('lastname');
            $table->string('firstname');
            $table->string('username')->unique();
            $table->string('email')->unique()->nullable(false);
            $table->string('password')->nullable(false);
            $table->string('password_previous1');
            $table->string('password_previous2');
            $table->string('password_temporary');
            $table->string('phone');
            $table->dateTime('last_password_update')->nullable(false);
            $table->dateTime('creation_date')->nullable(false);
            $table->dateTime('last_connexion');
            $table->set('status', ['Blocked','Deactivated','Unconfirmed','Activated'])->default('Unconfirmed')->nullable(false);
            $table->integer('attempts')->default(0)->nullable(false);
            $table->boolean('administrator')->default(false)->nullable(false);
            $table->integer('center_code')->nullable(false);
            $table->string('job_name')->nullable(false);
            $table->string('orthanc_address');
            $table->string('orthanc_login');
            $table->string('orthanc_password');
            //SK rememberToken sert a CSRF, peut etre pas utile si JWT a documenter
            $table->rememberToken();
            $table->timestamps();

            $table->foreign('center_code')->references('code')->on('centers');
            $table->foreign('job_name')->references('name')->on('jobs');

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('users');
    }
}
