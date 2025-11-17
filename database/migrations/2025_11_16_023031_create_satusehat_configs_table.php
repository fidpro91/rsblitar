<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSatusehatConfigsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('satusehat_configs', function (Blueprint $table) {
            $table->id();
            $table->string('kode')->unique(); // contoh: STAGING, PROD
            $table->string('client_key');
            $table->string('secret_key');
            $table->string('url'); // base URL OAuth/FHIR
            $table->string('kode_organization'); // ORG123456
            $table->enum('tipe', ['staging', 'production', 'local']);
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
        Schema::dropIfExists('satusehat_configs');
    }
}
