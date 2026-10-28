<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class CreateSatusehatProductionTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // 1. Tabel Riwayat Sinkronisasi SIMRS
        if (!Schema::hasTable('simrs_sync_logs')) {
            Schema::create('simrs_sync_logs', function (Blueprint $table) {
                $table->string('id', 64)->primary();
                $table->string('trigger', 32)->default('manual');
                $table->string('status', 20);
                $table->date('tanggal')->nullable();
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable();
                $table->integer('chunk_size')->default(50);
                $table->integer('limit_data')->nullable();
                $table->boolean('dry_run')->default(false);
                $table->timestamp('started_at')->nullable();
                $table->timestamp('finished_at')->nullable();
                $table->decimal('duration_seconds', 8, 2)->nullable();
                $table->decimal('memory_peak_mb', 8, 2)->nullable();
                $table->jsonb('total_stats')->nullable();
                $table->integer('chunks_processed')->default(0);
                $table->text('error_message')->nullable();
                $table->jsonb('warnings')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['start_date', 'end_date'], 'idx_simrs_sync_logs_dates');
                $table->index('status', 'idx_simrs_sync_logs_status');
            });
        }

        // 2. Tabel Pemetaan Resource ID SATUSEHAT
        if (!Schema::hasTable('satusehat_resource_ids')) {
            Schema::create('satusehat_resource_ids', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('visit_id', 64);
                $table->string('resource_type', 64);
                $table->string('resource_id', 128);
                $table->text('location')->nullable();
                $table->string('status', 32)->nullable();
                $table->timestamps();

                $table->unique(['visit_id', 'resource_type'], 'uq_satusehat_resource_ids_visit_res');
                $table->index('visit_id', 'idx_satusehat_res_ids_visit');
                $table->index('resource_type', 'idx_satusehat_res_ids_type');
            });
        }

        // 3. Tabel Log Pengiriman SATUSEHAT
        if (!Schema::hasTable('satusehat_send_logs')) {
            Schema::create('satusehat_send_logs', function (Blueprint $table) {
                $table->string('id', 64)->primary();
                $table->string('visit_id', 64);
                $table->string('resource_type', 64)->nullable();
                $table->string('status', 32);
                $table->integer('http_code')->nullable();
                $table->string('error_type', 64)->nullable();
                $table->text('message')->nullable();
                $table->integer('created_count')->default(0);
                $table->integer('updated_count')->default(0);
                $table->integer('failed_count')->default(0);
                $table->jsonb('resource_ids')->nullable();
                $table->jsonb('issues')->nullable();
                $table->jsonb('summary')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index('visit_id', 'idx_satusehat_send_logs_visit');
                $table->index('status', 'idx_satusehat_send_logs_status');
                $table->index('resource_type', 'idx_satusehat_send_logs_res');
            });
        }

        // 4. Tabel Audit Trail Perbaikan Data (Repair History)
        if (!Schema::hasTable('satusehat_repair_history')) {
            Schema::create('satusehat_repair_history', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('visit_id', 64);
                $table->string('resource_type', 64);
                $table->string('staging_table', 64);
                $table->string('staging_id', 64);
                $table->string('field_name', 64);
                $table->text('old_value')->nullable();
                $table->text('new_value')->nullable();
                $table->text('reason')->nullable();
                $table->string('repaired_by', 100)->default('Admin SIMRS');
                $table->timestamp('repaired_at')->useCurrent();
                $table->timestamp('created_at')->useCurrent();

                $table->index('visit_id', 'idx_satusehat_repair_hist_visit');
            });
        }

        // 5. Tabel Draft Perbaikan Non-Mutasi
        if (!Schema::hasTable('satusehat_data_drafts')) {
            Schema::create('satusehat_data_drafts', function (Blueprint $table) {
                $table->string('visit_id', 64)->primary();
                $table->jsonb('fields');
                $table->timestamp('updated_at')->useCurrent();
            });
        }

        // 6. Penambahan Kolom Status pada visit_encounter
        if (Schema::hasTable('visit_encounter')) {
            Schema::table('visit_encounter', function (Blueprint $table) {
                if (!Schema::hasColumn('visit_encounter', 'bundle_status')) {
                    $table->string('bundle_status', 32)->default('DRAFT');
                }
                if (!Schema::hasColumn('visit_encounter', 'last_error')) {
                    $table->text('last_error')->nullable();
                }
                if (!Schema::hasColumn('visit_encounter', 'retry_count')) {
                    $table->integer('retry_count')->default(0);
                }
                if (!Schema::hasColumn('visit_encounter', 'uuid_episodeofcare')) {
                    $table->string('uuid_episodeofcare', 64)->nullable();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Safe rollback tanpa merusak data utama
    }
}
