<?php

namespace App\Http\Controllers\Api;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpWord\TemplateProcessor;

class Word_builderController
{
    public function generate_word($request)
    {
        try {
            if ($request->berkas == 'resume_medis') {
                $fileWord = $this->set_resume_medis($request);
            } elseif ($request->berkas == 'resep') {
                $fileWord = $this->set_resep($request);
            } elseif ($request->berkas == 'pemeriksaan_fisik') {
                $fileWord = $this->set_pemeriksaan_fisik($request);
            } elseif ($request->berkas == 'resume_medis_ri') {
                $fileWord = $this->set_resume_medis_ri($request);
            } else {
                throw new \Exception("Template DOCX belum di setting", 201);
            }
            return $fileWord;
        } catch (\Exception $e) {
            return [
                'code'      => ($e->getCode() == 0 ? 501 : $e->getCode()),
                'message'   => $e->getMessage() . ' line ' . $e->getLine() . ' File ' . $e->getFile(),
            ];
        }
    }

    private function set_resume_medis($request)
    {
        $this->validasi_resumeMedis($request);

        $templatePath = storage_path('app/template/resume_medis.docx');
        if (!file_exists($templatePath)) {
            throw new \Exception("Template DOCX tidak ditemukan: {$templatePath}", 205);
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
        } else {
            $template->setValue('tind.no', '');
            $template->setValue('tind.nama', '');
            $template->setValue('tind.kode_im', '');
            $template->setValue('tind.kode_icd9', '');
            $template->setValue('tind.dokter', '');
        }

        if (is_array($request->data['pemeriksaan'])) {
            $dataPemeriksaan = collect($request->data['pemeriksaan'])
                ->map(fn($v) => "- " . $v)
                ->implode("\n");
        } else {
            $dataPemeriksaan = $request->data['pemeriksaan'];
        }
        $template->setValue('pemeriksaan', $dataPemeriksaan);

        if (is_array($request->data['terapi'])) {
            $dataTerapi = collect($request->data['terapi'])->implode(", ");
        } else {
            $dataTerapi = $request->data['terapi'];
        }
        $template->setValue('terapi', $dataTerapi);

        $template->setValue("nama_dokter", $request->nama);
        $template->setValue("tanggal", Carbon::now()->translatedFormat('d F Y'));
        // Lokasi output DOCX
        $nameFile = "tmp_$request->berkas" . $request->id_berkas . $request->visit_id . ".docx";
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

    private function set_pemeriksaan_fisik($request)
    {
        // $this->validasi_resumeMedis($request);
        $templatePath = storage_path('app/template/pemeriksaan_fisik.docx');
        if (!file_exists($templatePath)) {
            throw new \Exception("Template DOCX tidak ditemukan: {$templatePath}", 205);
        }
        $template = new TemplateProcessor($templatePath);
        // dataPasien
        $dataPasien = collect($request->data['pasien']);
        foreach ($dataPasien as $key => $value) {
            $template->setValue($key, $value);
        }

        // pemeriksaan fisik
        $dataPemeriksaan = collect($request->data['pemeriksaan_fisik'])->map(fn($v) => $this->xmlSafe($v))->toArray();
        foreach ($dataPemeriksaan as $key => $value) {
            $template->setValue($key, $value);
        }

        // kondisi keluar
        $kondisiKeluar = collect($request->data['kondisiKeluar'])->map(fn($v) => $this->xmlSafe($v))->toArray();
        foreach ($kondisiKeluar as $key => $value) {
            $template->setValue($key, $value);
        }

        //gambar fisik
        $gambarPemeriksaan = $request->data['gambarPemeriksaan']['image'];
        if (!str_starts_with($gambarPemeriksaan, 'data:image')) {
            throw new \Exception("Format gambar tidak valid", 205);
        }
        $imageData = base64_decode(
            preg_replace('#^data:image/\w+;base64,#i', '', $gambarPemeriksaan)
        );
        $tempPath = storage_path('app/temp/gambar.png');
        file_put_contents($tempPath, $imageData);
        // $tempPath = public_path('anatomi.png');
        $template->setImageValue('gambarPemeriksaan', [
            'path'      => $tempPath,
            'width'     => (!empty($request->data['gambarPemeriksaan']['width']) ? $request->data['gambarPemeriksaan']['width'] : 1000),
            'height'    => (!empty($request->data['gambarPemeriksaan']['height']) ? $request->data['gambarPemeriksaan']['height'] : 500),
            'ratio' => true
        ]);

        $dataDiagnosa = [];
        $dataDiagnosa = collect($request->data['diagnosa'])
            ->map(function ($d) {
                return [
                    "diag.prioritas"        => $d['prioritas'],
                    "diag.diagnosaKerja"    => $d['diagnosa_kerja'],
                    "diag.diagnosaBanding"  => $d['diagnosa_banding'],
                ];
            })
            ->toArray();
        $template->cloneRowAndSetValues('diag.prioritas', $dataDiagnosa);

        $template->setValue("nama_dokter", $request->namaDokter);
        $template->setValue("tanggal", Carbon::now()->translatedFormat('d F Y'));
        // Lokasi output DOCX
        $nameFile = "tmp_$request->berkas" . $request->id_berkas . $request->visit_id . time() . ".docx";
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

    private function set_resep($request)
    {
        $this->validasi_resep($request);

        $templatePath = storage_path('app/template/resep.docx');
        if (!file_exists($templatePath)) {
            throw new \Exception("Template DOCX tidak ditemukan: {$templatePath}", 205);
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
        } else {
            $template->setValue('listObat', $request->data['listObat']);
        }

        if (is_array($request->data['listRacikan'])) {
            $dataRacikan = collect($request->data['listRacikan'])->implode("\n");
            $template->setValue('obatRacikan', $dataRacikan);
        } else {
            $template->setValue('obatRacikan', $request->data['listRacikan']);
        }

        $template->setValue("namaDokter", $request->namaDokter);
        $template->setValue("tanggal", Carbon::now()->translatedFormat('d F Y'));
        // Lokasi output DOCX
        $nameFile = "tmp_$request->berkas" . $request->id_berkas . $request->visit_id . ".docx";
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
            throw new \Exception("Validasi gagal: " . $errorMessage, 206);
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
            throw new \Exception("Validasi gagal: " . $errorMessage, 206);
        }
    }

    private function set_resume_medis_ri($request)
    {
        $this->validasi_resumeMedisRI($request);

        $templatePath = storage_path('app/template/resume_medis_ri.docx');
        if (!file_exists($templatePath)) {
            throw new \Exception("Template DOCX tidak ditemukan: {$templatePath}", 205);
        }

        $tempTemplate = $this->prepareTemplate($templatePath);

        try {
            $template = new TemplateProcessor($tempTemplate);

            $data = $request->data;
            $pasien = $data['pasien'] ?? [];
            $tandaVital = $data['tanda_vital'] ?? [];

            // 1. Data Pasien Header
            $template->setValue('nama_pasien', $this->xmlSafe($pasien['nama_pasien'] ?? '-'));
            $template->setValue('rm', $this->xmlSafe($pasien['rm'] ?? '-'));
            $template->setValue('tgl_lahir', $this->xmlSafe($pasien['tgl_lahir'] ?? '-'));
            $template->setValue('nik', $this->xmlSafe($pasien['nik'] ?? '-'));

            // 2. Data Pelayanan Rawat Inap
            $tglMasuk = $data['tglmasuk'] ?? $data['tgl_masuk'] ?? $pasien['tgl_masuk'] ?? '-';
            $template->setValue('tglmasuk', $this->xmlSafe($tglMasuk));

            $indikasi = $data['indikasi'] ?? $data['indikasi_dirawat'] ?? $pasien['anamnesa'] ?? '-';
            $template->setValue('indikasi', $this->xmlSafe($indikasi));

            // Pemeriksaan Fisik
            $pemeriksaanFisik = $data['pemeriksaanfisik'] ?? $data['pemeriksaan_fisik'] ?? '-';
            if (is_array($pemeriksaanFisik)) {
                $pemeriksaanFisik = collect($pemeriksaanFisik)->implode("\n");
            }
            $template->setValue('pemeriksaanfisik', $this->xmlSafe($pemeriksaanFisik));

            // Diagnosa
            $diagnosa = $data['diagnosa'] ?? [];
            if (is_array($diagnosa)) {
                $dataDiagnosa = collect($diagnosa)->map(function ($d, $i) {
                    if (is_array($d)) {
                        $nama = $d['diagnosa_name'] ?? $d['nama'] ?? '';
                        $icd10 = $d['icd10'] ?? $d['kode_icd10'] ?? '';
                        return ($i + 1) . ". " . $nama . ($icd10 ? " ({$icd10})" : "");
                    }
                    return ($i + 1) . ". " . $d;
                })->implode("\n");
            } else {
                $dataDiagnosa = $diagnosa;
            }
            $template->setValue('diagnosa', $this->xmlSafe($dataDiagnosa ?: '-'));

            // Tindakan
            $tindakan = $data['tindakan'] ?? [];
            if (is_array($tindakan)) {
                $dataTindakan = collect($tindakan)->map(function ($t, $i) {
                    if (is_array($t)) {
                        $nama = $t['nama'] ?? $t['tindakan_name'] ?? '';
                        $icd9 = $t['kode_icd9'] ?? $t['icd9'] ?? '';
                        return ($i + 1) . ". " . $nama . ($icd9 ? " ({$icd9})" : "");
                    }
                    return ($i + 1) . ". " . $t;
                })->implode("\n");
            } else {
                $dataTindakan = $tindakan;
            }
            $template->setValue('tindakan', $this->xmlSafe($dataTindakan ?: '-'));

            // Konsultasi
            $konsultasi = $data['konsultasi'] ?? '-';
            if (is_array($konsultasi)) {
                $konsultasi = collect($konsultasi)->implode("\n");
            }
            $template->setValue('konsultasi', $this->xmlSafe($konsultasi ?: '-'));

            // Alergi
            $alergi = $data['alergi'] ?? '-';
            if (is_array($alergi)) {
                $alergi = collect($alergi)->implode(", ");
            }
            $template->setValue('alergi', $this->xmlSafe($alergi ?: '-'));

            // Hasil Laboratorium & Radiologi
            $hasilLab = $data['hasillab'] ?? $data['hasil_lab'] ?? null;
            $hasilRad = $data['hasilrad'] ?? $data['hasil_rad'] ?? $data['hasil_radiologi'] ?? null;

            if ($hasilLab === null && !empty($data['pemeriksaan']) && is_array($data['pemeriksaan'])) {
                $labItems = collect($data['pemeriksaan'])->filter(fn($p) => stripos($p, '[laboratorium]') !== false);
                $hasilLab = $labItems->isNotEmpty() ? $labItems->map(fn($v) => "- " . trim(str_ireplace('[laboratorium]', '', $v)))->implode("\n") : '-';
            }
            if (is_array($hasilLab)) {
                $hasilLab = collect($hasilLab)->map(fn($v) => "- " . $v)->implode("\n");
            }
            $template->setValue('hasillab', $this->xmlSafe($hasilLab ?: '-'));

            if ($hasilRad === null && !empty($data['pemeriksaan']) && is_array($data['pemeriksaan'])) {
                $radItems = collect($data['pemeriksaan'])->filter(fn($p) => stripos($p, '[radiologi]') !== false);
                $hasilRad = $radItems->isNotEmpty() ? $radItems->map(fn($v) => "- " . trim(str_ireplace('[radiologi]', '', $v)))->implode("\n") : '-';
            }
            if (is_array($hasilRad)) {
                $hasilRad = collect($hasilRad)->map(fn($v) => "- " . $v)->implode("\n");
            }
            $template->setValue('hasilrad', $this->xmlSafe($hasilRad ?: '-'));

            // 3. Keadaan Umum & Tanda Vital
            $keadaan = $data['keadaan'] ?? $data['keadaan_umum'] ?? $tandaVital['keadaan'] ?? $tandaVital['keadaan_umum'] ?? '-';
            $sistol = $data['sistol'] ?? $tandaVital['sistol'] ?? '-';
            $diastol = $data['diastol'] ?? $tandaVital['diastol'] ?? '-';
            $nadi = $data['nadi'] ?? $tandaVital['nadi'] ?? '-';
            $rr = $data['rr'] ?? $tandaVital['rr'] ?? $tandaVital['respirasi'] ?? '-';
            $suhu = $data['suhu'] ?? $tandaVital['suhu'] ?? '-';

            $template->setValue('keadaan', $this->xmlSafe($keadaan));
            $template->setValue('sistol', $this->xmlSafe($sistol));
            $template->setValue('diastol', $this->xmlSafe($diastol));
            $template->setValue('nadi', $this->xmlSafe($nadi));
            $template->setValue('rr', $this->xmlSafe($rr));
            $template->setValue('suhu', $this->xmlSafe($suhu));

            // 4. Cara Keluar
            $caraKeluar = $data['carakeluar'] ?? $data['cara_keluar'] ?? $pasien['cara_pulang'] ?? '-';
            $template->setValue('carakeluar', $this->xmlSafe($caraKeluar));

            // 5. Tanggal & DPJP
            $tanggal = !empty($data['tanggal']) ? $data['tanggal'] : Carbon::now()->translatedFormat('d F Y');
            $template->setValue('tanggal', $this->xmlSafe($tanggal));

            $dpjpName = $data['dpjp_name'] ?? $request->nama ?? $request->nama_dokter ?? '-';
            $template->setValue('dpjp_name', $this->xmlSafe($dpjpName));

            // Lokasi output DOCX
            $nameFile = "tmp_$request->berkas" . $request->id_berkas . $request->visit_id . ".docx";
            $outputDir = '/mnt/docxfile';
            // $outputDir = storage_path('app/public/' . $nameFile);
            if (!file_exists($outputDir)) {
                @mkdir($outputDir, 0777, true);
            }
            $outputPath = "$outputDir/$nameFile";
            $template->saveAs($outputPath);

            return [
                "code"      => 200,
                "message"   => "OK",
                "file"      => $nameFile,
                "location"  => $outputPath
            ];
        } finally {
            if (file_exists($tempTemplate)) {
                @unlink($tempTemplate);
            }
        }
    }

    private function prepareTemplate($templatePath)
    {
        if (!file_exists($templatePath)) {
            throw new \Exception("Template DOCX tidak ditemukan: {$templatePath}", 205);
        }

        $tempDir = storage_path('app/temp');
        if (!file_exists($tempDir)) {
            @mkdir($tempDir, 0777, true);
        }
        $tempFile = $tempDir . '/tmp_tpl_' . uniqid() . '.docx';
        copy($templatePath, $tempFile);

        $zip = new \ZipArchive();
        if ($zip->open($tempFile) === true) {
            $xml = $zip->getFromName('word/document.xml');
            if ($xml) {
                $vars = [
                    'tglmasuk',
                    'indikasi',
                    'pemeriksaanfisik',
                    'diagnosa',
                    'tindakan',
                    'konsultasi',
                    'alergi',
                    'hasillab',
                    'hasilrad',
                    'keadaan',
                    'sistol',
                    'diastol',
                    'nadi',
                    'rr',
                    'suhu',
                    'carakeluar',
                    'dpjp_name',
                    'tanggal',
                    'nama_pasien',
                    'rm',
                    'tgl_lahir',
                    'nik',
                ];

                $dom = new \DOMDocument();
                $prevErrors = libxml_use_internal_errors(true);
                if ($dom->loadXML($xml)) {
                    $xpath = new \DOMXPath($dom);
                    $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

                    $paragraphs = $xpath->query('//w:p');
                    $modified = false;
                    foreach ($paragraphs as $p) {
                        $pText = '';
                        $tNodes = $xpath->query('.//w:t', $p);
                        foreach ($tNodes as $t) {
                            $pText .= $t->nodeValue;
                        }

                        $hasVar = false;
                        foreach ($vars as $v) {
                            if (strpos($pText, '$' . $v) !== false && strpos($pText, '${' . $v . '}') === false) {
                                $hasVar = true;
                                break;
                            }
                        }

                        if ($hasVar) {
                            $newText = $pText;
                            foreach ($vars as $v) {
                                $newText = preg_replace('/(?<!\{)\$' . preg_quote($v, '/') . '(?!})/', '${' . $v . '}', $newText);
                            }
                            $first = true;
                            foreach ($tNodes as $t) {
                                if ($first) {
                                    $t->nodeValue = $newText;
                                    $first = false;
                                } else {
                                    $t->nodeValue = '';
                                }
                            }
                            $modified = true;
                        }
                    }

                    if ($modified) {
                        $zip->addFromString('word/document.xml', $dom->saveXML());
                    }
                }
                libxml_use_internal_errors($prevErrors);
            }
            $zip->close();
        }

        return $tempFile;
    }

    private function validasi_resumeMedisRI($request)
    {
        try {
            $request->validate([
                'data.pasien'                   => 'required|array',
                'data.pasien.rm'                => 'required|string',
                'data.pasien.nama_pasien'       => 'required|string',
                'data.pasien.nik'               => 'required|string',
                'data.pasien.tgl_lahir'         => 'required|string',

                'data.tglmasuk'                 => 'nullable|string',
                'data.indikasi'                 => 'nullable|string',
                'data.pemeriksaanfisik'         => 'nullable',
                'data.diagnosa'                 => 'nullable',
                'data.tindakan'                 => 'nullable',
                'data.konsultasi'               => 'nullable',
                'data.alergi'                   => 'nullable',
                'data.hasillab'                 => 'nullable',
                'data.hasilrad'                 => 'nullable',
                'data.carakeluar'               => 'nullable|string',

                'data.keadaan'                  => 'nullable|string',
                'data.sistol'                   => 'nullable',
                'data.diastol'                  => 'nullable',
                'data.nadi'                     => 'nullable',
                'data.rr'                       => 'nullable',
                'data.suhu'                     => 'nullable',
                'data.tanda_vital'              => 'nullable|array',
                'data.dpjp_name'                => 'nullable|string',
            ]);
        } catch (ValidationException $e) {
            $errorMessage = $e->validator->errors()->first();
            throw new \Exception("Validasi gagal: " . $errorMessage, 206);
        }
    }

    private function xmlSafe($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
