<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateServiceRequestTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('service_request', function (Blueprint $table) {
            $table->id(); // id int4
            $table->timestamp('tgl_permintaan')->nullable(); // tanggal permintaan
            $table->integer('visit_id')->nullable(); // visit_id
            $table->string('code_periksa')->nullable(); // kode pemeriksaan
            $table->string('nama_pemeriksaan')->nullable(); // nama pemeriksaan
            $table->text('hasil')->nullable(); // hasil pemeriksaan
            $table->string('nilai_kritis')->nullable(); // nilai kritis
            $table->text('nilai_normal_text')->nullable(); // nilai normal text
            $table->string('nilai_normal_bawah')->nullable(); // nilai normal bawah
            $table->string('nilai_normal_atas')->nullable(); // nilai normal atas
            $table->string('dokter_pemeriksa')->nullable(); // dokter pemeriksa
            $table->string('kode_pemeriksa')->nullable(); // nomor dokter pemeriksa
            $table->string('dokter_pengirim')->nullable(); // dokter pengirim
            $table->string('kode_pengirim')->nullable(); // nomor dokter pengirim
            $table->timestamp('tanggal_selesai')->nullable(); // tanggal otorisasi
            $table->text('satuan')->nullable(); // satuan
            $table->text('uuid_service_req')->nullable(); // UUID service request
            $table->text('uuid_specimen')->nullable(); // UUID specimen
            $table->text('uuid_obs')->nullable(); // UUID observation
            $table->text('uuid_diagnostic_report')->nullable(); // UUID diagnostic report
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
        Schema::dropIfExists('service_request');
    }
}
