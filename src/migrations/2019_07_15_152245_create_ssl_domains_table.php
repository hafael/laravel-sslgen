<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSslDomainsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create(config('ssl.domains_table'), function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('account_id');
            $table->string('apex');
            $table->text('aliases');
            $table->dateTime('expiration')->nullable();
            $table->boolean('auto_renewal')->default(0);
            $table->boolean('verified')->default(0);
            $table->dateTime('order_expiration');
            $table->text('order');
            $table->string('status')->default('pending');
            $table->text('keys');
            $table->text('challenges');
            $table->string('webmaster_email');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists(config('ssl.domains_table'));
    }
}
