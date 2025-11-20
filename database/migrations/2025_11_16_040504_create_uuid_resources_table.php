<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUuidResourcesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('uuid_resources', function (Blueprint $table) {
            $table->id();

            $table->string('visit_id');              
            $table->uuid('local_uuid');             
            $table->string('resource_type');         
            $table->uuid('remote_uuid')->nullable(); 

            $table->timestamps();
        });
    }
    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('uuid_resources');
    }
}
