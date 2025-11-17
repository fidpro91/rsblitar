<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVisitEncounterTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('services.visit_encounter', function (Blueprint $table) {
            $table->id(); // id int4, auto increment
            $table->integer('visit_id')->nullable();
            $table->bigInteger('no_ktp')->nullable();
            $table->string('px_norm')->nullable();
            $table->string('px_name')->nullable();
            $table->string('unit_name')->nullable();
            $table->text('idunitsatset')->nullable();
            $table->string('dpjp_name')->nullable();
            $table->string('kode_dokter')->nullable();
            $table->timestamp('tgl_kunjung')->nullable();
            $table->timestamp('tgl_dilayani')->nullable();
            $table->timestamp('tgl_selesai_dilayani')->nullable();
            $table->timestamp('tgl_pulang')->nullable();
            $table->string('tipe_kunjungan')->nullable();
            $table->boolean('is_send')->default(false);
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
        Schema::dropIfExists('services.visit_encounter');
    }
}
