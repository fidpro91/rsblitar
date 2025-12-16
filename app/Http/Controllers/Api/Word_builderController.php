<?php

namespace App\Http\Controllers\Api;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpWord\TemplateProcessor;

class Word_builderController
{
    public function generate_word(Request $request)
    {
        try {
            if ($request->berkas == 'resume_medis') {
                $fileWord = $this->set_resume_medis($request);
            }elseif ($request->berkas == 'resep') {
                $fileWord = $this->set_resep($request);
            }else {
                throw new \Exception("Template DOCX belum di setting",201);
            }
            return $fileWord; 
        } catch (\Exception $e) {
            return [
                'code'      => ($e->getCode() == 0 ? 501:$e->getCode()),   
                'message'   => $e->getMessage().' line '.$e->getLine().' File '.$e->getFile(), 
            ];
        }
    }

    private function set_resume_medis($request) {
        $this->validasi_resumeMedis($request);

        $templatePath = storage_path('app/template/resume_medis.docx');
        if (!file_exists($templatePath)) {
            throw new \Exception("Template DOCX tidak ditemukan: {$templatePath}",205);
        }
        $template = new TemplateProcessor($templatePath);
        // dataPasien
        $dataPasien = collect($request->data['pasien']);
        // Isi placeholder dengan value dummy
        foreach ($dataPasien as $key => $value) {
            $template->setValue($key, $value);
        }

        $dataDiagnosa = [];
        $dataDiagnosa = collect($request->data['diagnosa'])
        ->map(function ($d, $i) {
            return [
                "diag.no"         => $i + 1,
                "diag.nama"       => $d['diagnosa_name'],
                "diag.kode_im"    => $d['kode_im'],
                "diag.kode_icd10" => $d['icd10'],
                "diag.kasus"      => $d['kasus'],
                "diag.poli"       => $d['poli_name'],
                "diag.dokter"     => $d['dokter'],
            ];
        })
        ->toArray();
        $template->cloneRowAndSetValues('diag.no', $dataDiagnosa);

        $dataTindakan = [];
        if ($request->data['tindakan']) {
            $dataTindakan = collect($request->data['tindakan'])
            ->map(function ($d, $i) {
                return [
                    "tind.no"               => $i + 1,
                    "tind.nama"             => $d['nama'],
                    "tind.kode_im"          => $d['kode_im'],
                    "tind.kode_icd9"        => $d['kode_icd9'],
                    "tind.dokter"           => $d['dokter'],
                ];
            })
            ->toArray();
            $template->cloneRowAndSetValues('tind.no', $dataTindakan);
        }

        if (is_array($request->data['pemeriksaan'])) {
            $dataPemeriksaan = collect($request->data['pemeriksaan'])
            ->map(fn($v) => "- " . $v)
            ->implode("\n");
            $template->setValue('pemeriksaan', $dataPemeriksaan);
        }else {
            $dataPemeriksaan = $request->data['pemeriksaan'];
        }

        if (is_array($request->data['terapi'])) {
            $dataTerapi = collect($request->data['terapi'])->implode(", ");
            $template->setValue('terapi', $dataTerapi);
        }else {
            $dataTerapi = $request->data['terapi'];
        }

        $template->setValue("nama_dokter", $request->nama);
        $template->setValue("tanggal", Carbon::now()->translatedFormat('d F Y'));
        // Lokasi output DOCX
        $nameFile = "tmp_$request->berkas".$request->id_berkas.$request->visit_id.".docx";
        // $outputPath = storage_path('app/public/'.$nameFile);
        $outputPath = "/mnt/docxfile/$nameFile";
        $template->saveAs($outputPath);

        return [
            "code"      => 200,
            "message"   => "OK",
            "file"      => $nameFile,
            "location"  => $outputPath
        ];
    }

    private function set_resep($request) {
        $this->validasi_resep($request);

        $templatePath = storage_path('app/template/resep.docx');
        if (!file_exists($templatePath)) {
            throw new \Exception("Template DOCX tidak ditemukan: {$templatePath}",205);
        }
        $template = new TemplateProcessor($templatePath);
        // dataPasien
        $dataPasien = collect($request->data['pasien']);
        // Isi placeholder dengan value dummy
        foreach ($dataPasien as $key => $value) {
            $template->setValue($key, $value);
        }

        // data resep
        $dataResep = collect($request->data['resep']);
        // Isi placeholder dengan value dummy
        foreach ($dataResep as $key => $value) {
            $template->setValue($key, $value);
        }

        if (is_array($request->data['listObat'])) {
            $dataObat = collect($request->data['listObat'])
            ->implode("\n");
            $template->setValue('listObat', $dataObat);
        }else {
            $template->setValue('listObat', $request->data['listObat']);
        }

        if (is_array($request->data['listRacikan'])) {
            $dataRacikan = collect($request->data['listRacikan'])->implode("\n");
            $template->setValue('obatRacikan', $dataRacikan);
        }else {
            $template->setValue('obatRacikan', $request->data['listRacikan']);
        }

        $template->setValue("namaDokter", $request->namaDokter);
        $template->setValue("tanggal", Carbon::now()->translatedFormat('d F Y'));
        // Lokasi output DOCX
        $nameFile = "tmp_$request->berkas".$request->id_berkas.$request->visit_id.".docx";
        // $outputPath = storage_path('app/public/'.$nameFile);
        $outputPath = "/mnt/docxfile/$nameFile";
        $template->saveAs($outputPath);

        return [
            "code"      => 200,
            "message"   => "OK",
            "file"      => $nameFile,
            "location"  => $outputPath
        ];
    }

    private function validasi_resumeMedis($request)
    {
        try {
            $request->validate([
                // Validasi pasien
                'data.pasien'                       => 'required|array',
                'data.pasien.rm'                    => 'required|string',
                'data.pasien.sep'                   => 'nullable',
                'data.pasien.nama_pasien'           => 'required|string',
                'data.pasien.nik'                   => 'required|string',
                'data.pasien.total_biaya'           => 'required|string',
                'data.pasien.jenis_perawatan'       => 'required|string',
                'data.pasien.tgl_masuk'             => 'required|date',
                'data.pasien.tgl_keluar'            => 'required|date',
                'data.pasien.jumlah_hari'           => 'required|string',
                'data.pasien.tgl_lahir'             => 'required|date',
                'data.pasien.usia'                  => 'nullable',
                'data.pasien.usia_detail'           => 'nullable',
                'data.pasien.berat_lahir'           => 'nullable',
                'data.pasien.jenis_kelamin'         => 'required|string',
                'data.pasien.anamnesa'              => 'nullable',
                'data.pasien.cara_pulang'           => 'required|string',

                // diagnosa
                'data.diagnosa'                     => 'required|array|min:1',
                'data.diagnosa.*.diagnosa_name'     => 'required|string',
                'data.diagnosa.*.kode_im'           => 'required|string',
                'data.diagnosa.*.icd10'             => 'required|string',
                'data.diagnosa.*.kasus'             => 'required|string',
                'data.diagnosa.*.poli_name'         => 'required|string',
                'data.diagnosa.*.dokter'            => 'required|string',

                'data.pemeriksaan'                  => 'nullable',
                'data.terapi'                       => 'nullable',
            ]);

        } catch (ValidationException $e) {
            $errorMessage = $e->validator->errors()->first();
            throw new \Exception("Validasi gagal: " . $errorMessage,206);
        }
    }

    private function validasi_resep($request)
    {
        try {
            $request->validate([
                'data.pasien'                       => 'required|array',
                'data.pasien.norm'                  => 'required|string',
                'data.pasien.namaPasien'            => 'required|string',
                'data.pasien.nikPasien'             => 'required|string',
                'data.pasien.tanggalLahir'          => 'required|date',
                'data.pasien.alamatPasien'          => 'nullable',
                'data.pasien.beratBadan'            => 'nullable',

                'data.resep'                        => 'required|array',
                'data.resep.dokterPelayanan'        => 'required|string',
                'data.resep.tanggalResep'           => 'required|date',
                'data.resep.ruang'                  => 'required|string',
                'data.resep.riwayatAlergi'          => 'required|string',
                'data.resep.noResep'                => 'required|string',

                'data.pemeriksaan'                  => 'nullable',
                'data.terapi'                       => 'nullable',
            ]);

        } catch (ValidationException $e) {
            $errorMessage = $e->validator->errors()->first();
            throw new \Exception("Validasi gagal: " . $errorMessage,206);
        }
    }
    
}
