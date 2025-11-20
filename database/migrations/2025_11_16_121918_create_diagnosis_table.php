<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDiagnosisTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('diagnosis', function (Blueprint $table) {
            $table->id(); // id int4, primary key
            $table->string('visit_id')->nullable(); // ID dari SatuSehat
            $table->uuid('uuid')->nullable(); // UUID diagnosis
            $table->integer('rank')->nullable(); // urutan diagnosis
            $table->string('code')->nullable(); // kode diagnosis
            $table->string('dx_name')->nullable(); // nama diagnosis
            $table->timestamps(); // created_at & updated_at
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('diagnosis');
    }
}
