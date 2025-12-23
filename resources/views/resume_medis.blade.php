<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8"/>
    <title>Resume Medis Pasien</title>

    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            line-height: 1.4;
            margin: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 2px solid #000;
        }
        .header h2 {
            margin: 0;
            font-size: 18px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }

        .table-info td {
            padding: 4px 6px;
        }

        .section-title {
            background: #eee;
            padding: 6px;
            font-weight: bold;
            margin-top: 15px;
            border: 1px solid #ccc;
        }

        .table-bordered {
            border: 1px solid #000;
        }
        .table-bordered th, .table-bordered td {
            border: 1px solid #000;
            padding: 5px;
        }

        .signature {
            margin-top: 40px;
            width: 100%;
            text-align: right;
        }
    </style>
</head>

<body>

    <div class="header">
        <h2>RESUME MEDIS PASIEN</h2>
        <small>Rumah Sakit Umum Daerah</small>
    </div>

    <!-- DATA PASIEN -->
    <div class="section-title">Data Pasien</div>
    <table class="table-info">
        <tr>
            <td width="25%">Nama</td>
            <td>: {{ $data['nama'] }}</td>
        </tr>
        <tr>
            <td>No. Rekam Medis</td>
            <td>: {{ $data['no_rm'] }}</td>
        </tr>
        <tr>
            <td>NIK</td>
            <td>: {{ $data['nik'] }}</td>
        </tr>
        <tr>
            <td>Tanggal Lahir</td>
            <td>: {{ $data['tgl_lahir'] }}</td>
        </tr>
        <tr>
            <td>Jenis Kelamin</td>
            <td>: {{ $data['jk'] }}</td>
        </tr>
        <tr>
            <td>Alamat</td>
            <td>: {{ $data['alamat'] }}</td>
        </tr>
    </table>

    <!-- DATA PERAWATAN -->
    <div class="section-title">Detail Perawatan</div>
    <table class="table-info">
        <tr>
            <td width="25%">Tanggal Masuk</td>
            <td>: {{ $data['tgl_masuk'] }}</td>
        </tr>
        <tr>
            <td>Tanggal Keluar</td>
            <td>: {{ $data['tgl_keluar'] }}</td>
        </tr>
        <tr>
            <td>Unit Perawatan</td>
            <td>: {{ $data['unit'] }}</td>
        </tr>
        <tr>
            <td>Dokter Penanggung Jawab</td>
            <td>: {{ $data['dokter'] }}</td>
        </tr>
        <tr>
            <td>Diagnosa Masuk</td>
            <td>: {{ $data['diagnosa_masuk'] }}</td>
        </tr>
        <tr>
            <td>Diagnosa Akhir</td>
            <td>: {{ $data['diagnosa_akhir'] }}</td>
        </tr>
    </table>

    <!-- TINDAKAN -->
    <div class="section-title">Tindakan / Prosedur</div>
    <table class="table-bordered">
        <thead>
            <tr>
                <th width="10%">No</th>
                <th>Tindakan</th>
                <th>Tanggal</th>
                <th>Pelaksana</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data['tindakan'] as $i => $t)
            <tr>
                <td>{{ $i+1 }}</td>
                <td>{{ $t['nama'] }}</td>
                <td>{{ $t['tanggal'] }}</td>
                <td>{{ $t['pelaksana'] }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <!-- OBAT -->
    <div class="section-title">Terapi / Obat yang Diberikan</div>
    <table class="table-bordered">
        <thead>
            <tr>
                <th width="10%">No</th>
                <th>Nama Obat</th>
                <th>Dosis</th>
                <th>Frekuensi</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data['obat'] as $i => $o)
            <tr>
                <td>{{ $i+1 }}</td>
                <td>{{ $o['nama'] }}</td>
                <td>{{ $o['dosis'] }}</td>
                <td>{{ $o['frekuensi'] }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>


    <!-- ANJURAN -->
    <div class="section-title">Anjuran Pulang</div>
    <p>{!! nl2br(e($data['anjuran'])) !!}</p>

    <!-- SIGNATURE -->
    <div class="signature">
        <p>{{ $data['kota'] }}, {{ $data['tanggal_cetak'] }}</p>
        <p>Dokter Penanggung Jawab</p>
        {QR}
        <br><br><br>
        <strong>{{ $data['dokter'] }}</strong>
    </div>

</body>
</html>
