@extends('layouts.app')

@section('title', 'Upload DBML - ERD Normalization Checker')

@section('content')
    <div class="max-w-4xl mx-auto">
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900 mb-2">Analisis Struktur DBML</h1>
            <p class="text-gray-600">Tempel kode DBML Anda di bawah untuk memeriksa kepatuhan normalisasi</p>
        </div>

        {{-- Validation errors --}}
        @if ($errors->any())
            <div class="mb-6 bg-red-50 border border-red-200 rounded-lg p-4">
                <div class="flex">
                    <svg class="h-5 w-5 text-red-400 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd"
                            d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                            clip-rule="evenodd" />
                    </svg>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-red-800">Kesalahan</h3>
                        <ul class="mt-1 text-sm text-red-700 list-disc list-inside">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        @endif

        <div class="bg-white rounded-lg shadow border border-gray-200 p-6">
            <form method="POST" action="{{ route('parse') }}">
                @csrf

                <div class="mb-6">
                    <label for="project_name" class="block text-sm font-medium text-gray-700 mb-2">
                        Nama Proyek
                    </label>
                    <input type="text" id="project_name" name="project_name" required value="{{ old('project_name') }}"
                        class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                        placeholder="e.g., Admin Dashboard ERD">
                </div>

                <div class="mb-6">
                    <label for="dbml_text" class="block text-sm font-medium text-gray-700 mb-2">
                        Kode DBML
                    </label>
                    <textarea id="dbml_text" name="dbml_text" rows="15" required
                        class="w-full px-4 py-3 border border-gray-300 rounded-md font-mono text-sm focus:ring-blue-500 focus:border-blue-500"
                        placeholder="Table users {&#10;  id bigint [pk]&#10;  email varchar(100) [unique]&#10;  name varchar(100)&#10;}">{{ old('dbml_text') }}</textarea>
                    <p class="mt-2 text-sm text-gray-500">Tempel definisi skema DBML Anda di sini</p>
                </div>

                <div class="flex items-center justify-between">
                    <a href="{{ route('home') }}" class="text-gray-600 hover:text-gray-900 text-sm font-medium">
                        ← Kembali ke Proyek
                    </a>
                    <button type="submit"
                        class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        Selanjutnya: Input Ketergantungan →
                    </button>
                </div>
            </form>
        </div>

        {{-- update --}}
        <div class="main-container">
            <!-- Hero Section -->
            <div class="hero">
                <div class="hero-badge">📌 Dokumentasi Resmi</div>
                <h1>DBML Normalization Checker</h1>
                <p>Alat untuk memeriksa apakah struktur database Anda memenuhi kaidah normalisasi 1NF, 2NF, dan 3NF. Cukup
                    paste kode DBML dari dbdiagram.io, dan dapatkan laporan lengkap beserta rekomendasi perbaikan.</p>
            </div>

            <!-- Tentang Section -->
            <div id="tentang" class="card">
                <div class="card-header">
                    <span class="icon">📖</span>
                    <h2>Tentang Alat Ini</h2>
                </div>
                <div class="card-body">
                    <p><strong>DBML Normalization Checker</strong> adalah alat analisis struktur database yang dirancang
                        untuk membantu developer dan database administrator dalam memastikan desain database memenuhi
                        standar normalisasi.</p>

                    <div class="alert-info">
                        <strong>🎯 Tujuan Utama:</strong><br>
                        • Memeriksa keberadaan Primary Key pada setiap tabel<br>
                        • Mendeteksi pelanggaran <strong>2NF</strong> (Partial Dependency)<br>
                        • Mendeteksi pelanggaran <strong>3NF</strong> (Transitive Dependency)<br>
                        • Memberikan rekomendasi perbaikan untuk setiap pelanggaran
                    </div>

                    <table class="info-table" style="margin-top: 20px;">
                        <thead>
                            <tr>
                                <th>Fitur</th>
                                <th>Deskripsi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>📝 Input DBML</td>
                                <td>Textarea untuk menempelkan kode DBML dari dbdiagram.io</td>
                            </tr>
                            <tr>
                                <td>🔍 Analisis Otomatis</td>
                                <td>Scan seluruh struktur tabel dan identifikasi masalah normalisasi</td>
                            </tr>
                            <tr>
                                <td>📊 Laporan Statistik</td>
                                <td>Ringkasan jumlah tabel, tanpa PK, pelanggaran 2NF/3NF</td>
                            </tr>
                            <tr>
                                <td>💡 Rekomendasi Solusi</td>
                                <td>Saran perbaikan untuk setiap pelanggaran yang ditemukan</td>
                            </tr>
                            <tr>
                                <td>📋 Contoh DBML</td>
                                <td>Tombol untuk memuat contoh kode DBML yang siap dianalisis</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Langkah Penggunaan -->
            <div id="langkah" class="card">
                <div class="card-header">
                    <span class="icon">🔄</span>
                    <h2>Langkah-Langkah Penggunaan</h2>
                </div>
                <div class="card-body">
                    <div class="steps-container">
                        <!-- Step 1 -->
                        <div class="step-item">
                            <div class="step-number-circle">1</div>
                            <div class="step-content">
                                <h3>Export Database dari MySQL</h3>
                                <p>Ekspor database Anda dari MySQL/phpMyAdmin ke format SQL.</p>
                                <div class="code-block">
                                    # Via command line<br>
                                    mysqldump -u username -p nama_database > database.sql<br><br>
                                    # Atau via phpMyAdmin<br>
                                    Pilih database → Tab Export → Pilih format SQL → Klik Go
                                </div>
                            </div>
                        </div>

                        <!-- Step 2 -->
                        <div class="step-item">
                            <div class="step-number-circle">2</div>
                            <div class="step-content">
                                <h3>Import ke dbdiagram.io</h3>
                                <p>Upload file SQL ke dbdiagram.io untuk dikonversi ke format DBML.</p>
                                <div class="code-block">
                                    1. Buka https://dbdiagram.io<br>
                                    2. Login atau buat akun (gratis)<br>
                                    3. Klik "Import" → "Import from SQL"<br>
                                    4. Upload file .sql yang telah diekspor<br>
                                    5. DB Diagram akan menghasilkan kode DBML
                                </div>
                            </div>
                        </div>

                        <!-- Step 3 -->
                        <div class="step-item">
                            <div class="step-number-circle">3</div>
                            <div class="step-content">
                                <h3>Copy Kode DBML ke Normalization Checker</h3>
                                <p>Salin kode DBML dari dbdiagram.io dan paste ke alat pemeriksa.</p>
                                <div class="code-block">
                                    1. Blok semua kode DBML (Ctrl+A)<br>
                                    2. Copy kode (Ctrl+C)<br>
                                    3. Paste ke textarea DBML Normalization Checker<br>
                                    4. Klik tombol "Analyze Normalization"<br>
                                    5. Lihat hasil analisis dan rekomendasi perbaikan
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="alert-success" style="margin-top: 20px;">
                        <strong>✅ Selesai!</strong> Anda akan mendapatkan laporan lengkap tentang status normalisasi
                        database Anda.
                    </div>
                </div>
            </div>

            <!-- Contoh DBML -->
            <div id="contoh" class="card">
                <div class="card-header">
                    <span class="icon">📋</span>
                    <h2>Contoh Kode DBML</h2>
                </div>
                <div class="card-body">
                    <p>Berikut adalah contoh kode DBML yang dapat Anda gunakan untuk menguji alat ini. Contoh ini mengandung
                        tabel yang melanggar 2NF dan tabel yang sudah memenuhi 3NF.</p>

                    <div class="example-dbml">
                        <pre>// Tabel yang MELANGGAR 2NF (Partial Dependency)
Table course_enrollments {
  student_id int [pk]
  course_id int [pk]
  student_name varchar(100) [not null]  // ← hanya tergantung student_id
  course_name varchar(100) [not null]   // ← hanya tergantung course_id
  enrollment_date date
  grade varchar(2)
}

// Tabel yang MEMENUHI 3NF
Table products {
  product_id int [pk]
  product_name varchar(100) [not null]
  unit_price decimal(10,2) [not null]
  category varchar(50)
}

// Tabel yang MELANGGAR 2NF
Table order_items {
  order_id int [pk]
  product_id int [pk]
  product_name varchar(100) [not null]  // ← hanya tergantung product_id
  order_date date [not null]            // ← hanya tergantung order_id
  quantity int
}

// Tabel yang MEMENUHI 3NF
Table employees {
  employee_id int [pk]
  employee_name varchar(100) [not null]
  department_id int
  hire_date date
}</pre>
                    </div>

                    <div class="alert-info" style="margin-top: 20px;">
                        <strong>💡 Tips:</strong> Klik tombol "Load Example" pada alat Normalization Checker untuk memuat
                        contoh ini secara otomatis.
                    </div>
                </div>
            </div>

            <!-- Penjelasan Normalisasi -->
            <div id="normalisasi" class="card">
                <div class="card-header">
                    <span class="icon">📐</span>
                    <h2>Panduan Normalisasi Database</h2>
                </div>
                <div class="card-body">
                    <h3>📌 1NF (First Normal Form)</h3>
                    <p>Setiap kolom hanya berisi satu nilai (atomic value) dan tidak ada kelompok kolom yang berulang.</p>

                    <h3 style="margin-top: 20px;">📌 2NF (Second Normal Form)</h3>
                    <p>Harus memenuhi 1NF, dan setiap atribut non-prime harus bergantung secara fungsional pada
                        <strong>seluruh primary key (bukan hanya sebagian)</strong>.
                    </p>
                    <div class="alert-warning">
                        <strong>⚠️ Pelanggaran 2NF (Partial Dependency):</strong><br>
                        Terjadi pada tabel dengan <strong>primary key komposit</strong> (lebih dari satu kolom sebagai
                        PK),<br>
                        dimana ada atribut yang hanya bergantung pada <strong>salah satu</strong> bagian dari PK tersebut.
                    </div>

                    <h3 style="margin-top: 20px;">📌 3NF (Third Normal Form)</h3>
                    <p>Harus memenuhi 2NF, dan tidak ada atribut non-prime yang bergantung pada atribut non-prime lainnya
                        (transitive dependency).</p>
                    <div class="alert-warning">
                        <strong>⚠️ Pelanggaran 3NF (Transitive Dependency):</strong><br>
                        Terjadi ketika ada atribut non-key yang bergantung pada atribut non-key lainnya,<br>
                        bukan langsung bergantung pada primary key.
                    </div>

                    <table class="info-table" style="margin-top: 20px;">
                        <thead>
                            <tr>
                                <th>Normal Form</th>
                                <th>Persyaratan</th>
                                <th>Contoh Pelanggaran</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><span class="badge badge-primary">1NF</span></td>
                                <td>Nilai atomic, tidak ada repeating groups</td>
                                <td>Kolom "telepon" berisi "0812, 0813" (lebih dari satu nilai)</td>
                            </tr>
                            <tr>
                                <td><span class="badge badge-primary">2NF</span></td>
                                <td>1NF + Tidak ada partial dependency</td>
                                <td>PK (order_id, product_id) dengan kolom "product_name"</td>
                            </tr>
                            <tr>
                                <td><span class="badge badge-primary">3NF</span></td>
                                <td>2NF + Tidak ada transitive dependency</td>
                                <td>Tabel karyawan dengan kolom "department_name"</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Troubleshooting -->
            <div id="troubleshooting" class="card">
                <div class="card-header">
                    <span class="icon">🔧</span>
                    <h2>Troubleshooting & FAQ</h2>
                </div>
                <div class="card-body">
                    <table class="info-table">
                        <thead>
                            <tr>
                                <th>Masalah</th>
                                <th>Solusi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Tidak ada tabel yang terdeteksi</td>
                                <td>Pastikan format DBML valid. Gunakan format: <code>Table nama_tabel { kolom tipe [pk]
                                        }</code></td>
                            </tr>
                            <tr>
                                <td>Semua tabel terdeteksi tanpa PK</td>
                                <td>Pastikan setiap tabel memiliki kolom dengan atribut <code>[pk]</code>. Atau kolom
                                    bernama "id" akan otomatis dikenali</td>
                            </tr>
                            <tr>
                                <td>Hasil analisis tidak muncul</td>
                                <td>Refresh halaman dan pastikan JavaScript di browser Anda aktif</td>
                            </tr>
                            <tr>
                                <td>Kode DBML dari dbdiagram.io error</td>
                                <td>Pastikan Anda meng-export sebagai SQL dulu, lalu import ke dbdiagram.io untuk
                                    mendapatkan DBML yang valid</td>
                            </tr>
                            <tr>
                                <td>Apa itu partial dependency?</td>
                                <td>Kondisi dimana suatu atribut hanya bergantung pada sebagian dari primary key komposit
                                    (bukan seluruh PK)</td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="alert-info" style="margin-top: 20px;">
                        <strong>📞 Butuh Bantuan?</strong><br>
                        Pastikan kode DBML yang Anda paste mengikuti format standar DBML. Format lengkap dapat dilihat di <a
                            href="https://www.dbml.org/docs/" target="_blank">dokumentasi resmi DBML</a>.
                    </div>
                </div>
            </div>
        </div>

        
    </div>
@endsection
@push('css')
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: #f1f5f9;
            color: #1e293b;
            line-height: 1.6;
        }

        /* Navbar */
        .navbar {
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
            color: white;
            padding: 16px 40px;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }

        .navbar .container {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 20px;
            font-weight: bold;
        }

        .nav-links {
            display: flex;
            gap: 25px;
            flex-wrap: wrap;
        }

        .nav-links a {
            color: #cbd5e1;
            text-decoration: none;
            transition: color 0.3s;
        }

        .nav-links a:hover {
            color: white;
        }

        /* Main Container */
        .main-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        /* Hero Section */
        .hero {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 24px;
            padding: 50px 40px;
            color: white;
            margin-bottom: 40px;
            text-align: center;
        }

        .hero h1 {
            font-size: 42px;
            margin-bottom: 16px;
        }

        .hero p {
            font-size: 18px;
            opacity: 0.9;
            max-width: 700px;
            margin: 0 auto;
        }

        .hero-badge {
            display: inline-block;
            background: rgba(255, 255, 255, 0.2);
            padding: 6px 16px;
            border-radius: 30px;
            font-size: 14px;
            margin-bottom: 20px;
        }

        /* Card Style */
        .card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            overflow: hidden;
            margin-bottom: 30px;
        }

        .card-header {
            background: #f8fafc;
            padding: 20px 30px;
            border-bottom: 2px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .card-header h2 {
            font-size: 22px;
            color: #1e293b;
        }

        .card-header .icon {
            font-size: 28px;
        }

        .card-body {
            padding: 30px;
        }

        /* Step Timeline */
        .steps-container {
            display: flex;
            flex-direction: column;
            gap: 30px;
        }

        .step-item {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }

        .step-number-circle {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            font-weight: bold;
            color: white;
            flex-shrink: 0;
        }

        .step-content {
            flex: 1;
            background: #f8fafc;
            padding: 20px;
            border-radius: 16px;
        }

        .step-content h3 {
            color: #1e293b;
            margin-bottom: 12px;
            font-size: 20px;
        }

        .step-content p {
            color: #475569;
            margin-bottom: 12px;
        }

        .code-block {
            background: #1e293b;
            color: #e2e8f0;
            padding: 16px;
            border-radius: 12px;
            font-family: 'Monaco', 'Menlo', monospace;
            font-size: 13px;
            overflow-x: auto;
            margin-top: 12px;
        }

        /* Table Styles */
        .info-table {
            width: 100%;
            border-collapse: collapse;
        }

        .info-table th {
            background: #f1f5f9;
            padding: 12px 16px;
            text-align: left;
            font-weight: 600;
            border-bottom: 2px solid #e2e8f0;
        }

        .info-table td {
            padding: 12px 16px;
            border-bottom: 1px solid #e2e8f0;
            color: #475569;
        }

        .info-table tr:hover td {
            background: #f8fafc;
        }

        /* Alert Boxes */
        .alert-info {
            background: #e0f2fe;
            border-left: 4px solid #0284c7;
            padding: 16px 20px;
            border-radius: 12px;
            margin: 20px 0;
        }

        .alert-warning {
            background: #fef3c7;
            border-left: 4px solid #d97706;
            padding: 16px 20px;
            border-radius: 12px;
            margin: 20px 0;
        }

        .alert-success {
            background: #d1fae5;
            border-left: 4px solid #10b981;
            padding: 16px 20px;
            border-radius: 12px;
            margin: 20px 0;
        }

        /* Badge */
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-primary {
            background: #e0e7ff;
            color: #4338ca;
        }

        .badge-success {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-warning {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-danger {
            background: #fee2e2;
            color: #991b1b;
        }

        /* Example Section */
        .example-dbml {
            background: #1e293b;
            border-radius: 16px;
            padding: 20px;
            margin-top: 20px;
        }

        .example-dbml pre {
            color: #e2e8f0;
            font-family: 'Monaco', monospace;
            font-size: 13px;
            overflow-x: auto;
        }

        /* Footer */
        .footer {
            background: #1e293b;
            color: #94a3b8;
            text-align: center;
            padding: 30px;
            margin-top: 40px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .hero h1 {
                font-size: 28px;
            }

            .step-number-circle {
                width: 45px;
                height: 45px;
                font-size: 20px;
            }

            .card-header {
                padding: 15px 20px;
            }

            .card-body {
                padding: 20px;
            }

            .navbar .container {
                flex-direction: column;
                text-align: center;
            }
        }
    </style>
@endpush
