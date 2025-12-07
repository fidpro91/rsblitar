<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;

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

        $pdf = Pdf::loadView('resume_medis', compact('data'))
            ->setPaper('A4', 'portrait');

        return $pdf->stream('resume_medis.pdf');
    }
}
