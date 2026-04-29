<?php

namespace App\Http\Controllers;

use App\Jobs\SendTteJob;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\Html;
use PhpOffice\PhpWord\TemplateProcessor;

class CobaController extends Controller
{
    public function cetakResume(Request $request)
    {
        // contoh dummy data
        $data = [
            'nama' => 'Budi Santoso',
            'no_rm' => '20250101',
            'nik' => '3572010101700001',
            'tgl_lahir' => '10 Januari 1980',
            'jk' => 'Laki-laki',
            'alamat' => 'Jl. Mawar No. 10',

            'tgl_masuk' => '2025-02-01',
            'tgl_keluar' => '2025-02-05',
            'unit' => 'Ruang Rawat Inap Melati',
            'dokter' => 'Dr. Andi Wijaya, Sp.PD',
            'diagnosa_masuk' => 'Demam Tinggi',
            'diagnosa_akhir' => 'Demam Berdarah Dengue (DBD)',

            'tindakan' => [
                ['nama' => 'Pemasangan Infus', 'tanggal' => '2025-02-01', 'pelaksana' => 'Perawat Ani'],
                ['nama' => 'Pemeriksaan Laboratorium', 'tanggal' => '2025-02-02', 'pelaksana' => 'Laboratorium'],
            ],

            'obat' => [
                ['nama' => 'Parasetamol', 'dosis' => '500 mg', 'frekuensi' => '3x1'],
                ['nama' => 'Infus RL', 'dosis' => '500 ml', 'frekuensi' => '1x'],
            ],

            'anjuran' => "Kontrol 3 hari ke depan.\nMinum air banyak.\nIstirahat cukup.",
            'kota' => 'Blitar',
            'tanggal_cetak' => date('d-m-Y'),
        ];

        /* $pdf = Pdf::loadView('resume_medis', compact('data'))
            ->setPaper('A4', 'portrait');

        return $pdf->stream('resume_medis.pdf'); */
        return view('resume_medis', compact('data'));
    }

    public function generate_word()
    {
        // Lokasi template DOCX yang berisi tabel dan placeholder
        $templatePath = storage_path('app/template/resume_medis.docx');
        $template = new TemplateProcessor($templatePath);
        // Dummy data
        $data = [
            'rm'              => '11223344',
            'sep'             => '0201R0123456789',
            'nama_pasien'     => 'Budi Santoso',
            'nik'             => '3572010102030004',
            'total_biaya'     => 'Rp 2.450.000',
            'jenis_perawatan' => 'Rawat Inap',
            'tgl_masuk'       => '2025-02-01',
            'tgl_keluar'      => '2025-02-05',
            'jumlah_hari'     => '4 Hari',
            'tgl_lahir'       => '1988-04-17',
            'usia'            => '36 Tahun',
            'usia_detail'     => '36 Th 2 Bln 3 Hr',
            'berat_lahir'     => '3.100 gram',
            'jenis_kelamin'   => 'Laki-laki',
            'anamnesa'        => 'Pasien datang dengan keluhan demam dan batuk.',
            'cara_pulang'     => 'Pulang Atas Persetujuan Dokter'
        ];

        // Isi placeholder dengan value dummy
        foreach ($data as $key => $value) {
            $template->setValue($key, $value);
        }

        $data = [];
        $data = collect([
            (object)[
                'diagnosa_name' => 'Demam Berdarah Dengue',
                'kode_im'       => 'A90',
                'icd10'         => 'A90',
                'kasus'         => 'Baru',
                'poli_name'     => 'Poli Penyakit Dalam',
                'dokter'        => 'dr. Andi Prasetyo, Sp.PD'
            ],
            (object)[
                'diagnosa_name' => 'Gastritis Akut',
                'kode_im'       => 'K29.0',
                'icd10'         => 'K29.0',
                'kasus'         => 'Lama',
                'poli_name'     => 'Poli Umum',
                'dokter'        => 'dr. Sinta Lestari'
            ],
            (object)[
                'diagnosa_name' => 'Hipertensi Primer',
                'kode_im'       => 'I10',
                'icd10'         => 'I10',
                'kasus'         => 'Lama',
                'poli_name'     => 'Poli Jantung',
                'dokter'        => 'dr. Budi Santoso, Sp.JP'
            ],
        ])
        ->map(function ($d, $i) {
            return [
                "diag.no"         => $i + 1,
                "diag.nama"       => $d->diagnosa_name,
                "diag.kode_im"    => $d->kode_im,
                "diag.kode_icd10" => $d->icd10,
                "diag.kasus"      => $d->kasus,
                "diag.poli"       => $d->poli_name,
                "diag.dokter"     => $d->dokter,
            ];
        })
        ->toArray();
        $template->cloneRowAndSetValues('diag.no', $data);

        $dataTindakan = collect([
            (object)[
                'nama' => 'Pemeriksaan Fisik Umum',
                'kode_im' => 'PFU001',
                'kode_icd9' => 'Z00.00', // Perhatikan: 'kode_icd9' sesuai dengan placeholder di template
                'dokter' => 'Dr. Andi Wijaya, Sp.PD'
            ],
            (object)[
                'nama' => 'EKG (Elektrokardiogram)',
                'kode_im' => 'EKG002',
                'kode_icd9' => '89.52',
                'dokter' => 'Dr. Sari Dewi, Sp.JP'
            ],
            (object)[
                'nama' => 'USG Abdomen Lengkap',
                'kode_im' => 'USG003',
                'kode_icd9' => '88.76',
                'dokter' => 'Dr. Bambang Supriyadi, Sp.Rad'
            ]
        ])
        ->map(function ($d, $i) {
            return [
                "tind.no"               => $i + 1,
                "tind.nama"             => $d->nama,
                "tind.kode_im"          => $d->kode_im,
                "tind.kode_icd9"        => $d->kode_icd9,
                "tind.dokter"           => $d->dokter,
            ];
        })
        ->toArray();
        
        $template->cloneRowAndSetValues('tind.no', $dataTindakan);

        $dataPemeriksaan = collect([
            (object)[
                'pemeriksaan' => 'Pemeriksaan Darah Lengkap (Hematologi) [Hb: 14 g/dL, Leukosit: 7.800/µL, Trombosit: 250.000/µL][laboratorium]'
            ],
            (object)[
                'pemeriksaan' => 'Foto Thorax [Cor dan pulmo dalam batas normal, tidak tampak infiltrat atau massa][radiologi]'
            ],
            (object)[
                'pemeriksaan' => 'Pemeriksaan Urin Lengkap [Warna: kuning jernih, Protein: negatif, Glukosa: negatif, Sedimen: 2-4 leukosit/LPB][laboratorium]'
            ],
            (object)[
                'pemeriksaan' => 'CT-Scan Kepala [Tidak ditemukan perdarahan, infark, atau massa lesi di parenkim otak][radiologi]'
            ],
            (object)[
                'pemeriksaan' => 'Pemeriksaan Fungsi Hati [SGOT: 25 U/L, SGPT: 28 U/L, Bilirubin total: 0.8 mg/dL][laboratorium]'
            ],
            (object)[
                'pemeriksaan' => 'USG Abdomen [Hati, ginjal, pankreas, dan limpa dalam batas normal, tidak tampak lesi][radiologi]'
            ],
            (object)[
                'pemeriksaan' => 'Pemeriksaan Gula Darah [GDP: 95 mg/dL, GD2JPP: 120 mg/dL, HbA1c: 5.8%][laboratorium]'
            ],
            (object)[
                'pemeriksaan' => 'EKG (Elektrokardiogram) [Irama sinus reguler, frekuensi 72x/menit, tidak ada kelainan iskemia][radiologi]'
            ]
        ])
        ->pluck('pemeriksaan')
        ->map(fn($v) => "- " . $v)
        ->implode("\n");
        $template->setValue('pemeriksaan', $dataPemeriksaan);
        $dataTerapi = collect([
            (object)[
                'terapi' => 'AMLODIPIN 10MG/100 [ 30 BOX ]'
            ],
            (object)[
                'terapi' => 'CAPTOPRIL 25MG/100 [ 20 BOX ]'
            ],
            (object)[
                'terapi' => 'METFORMIN 500MG/100 [ 45 BOX ]'
            ],
            (object)[
                'terapi' => 'SIMVASTATIN 20MG/100 [ 30 BOX ]'
            ],
            (object)[
                'terapi' => 'LOSARTAN 50MG/100 [ 25 BOX ]'
            ],
            (object)[
                'terapi' => 'OMEPRAZOLE 20MG/100 [ 15 BOX ]'
            ],
            (object)[
                'terapi' => 'PARACETAMOL 500MG/100 [ 50 BOX ]'
            ],
            (object)[
                'terapi' => 'CETIRIZINE 10MG/100 [ 20 BOX ]'
            ],
            (object)[
                'terapi' => 'FUROSEMIDE 40MG/100 [ 30 BOX ]'
            ],
            (object)[
                'terapi' => 'INSULIN GLARGINE 100IU/ML [ 5 VIAL ]'
            ]
        ])->pluck('terapi')->implode(", ");
        $template->setValue('terapi', $dataTerapi);
        // Lokasi output DOCX
        $outputPath = storage_path('app/public/resume_dummy.docx');
        $template->saveAs($outputPath);

        // return response()->download($outputPath);
    }

    public function tes_word(Request $request)
    {
        $urlWord = app(\App\Http\Controllers\Api\Word_builderController::class);
        return $urlWord->generate_word($request);
    }

    public function tes_jobs(Request $request)
    {
        SendTteJob::dispatch();
        return "OK";
    }
}
