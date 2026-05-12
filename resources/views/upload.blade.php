@extends('layouts.app')

@section('title', 'Upload SQL - ERD Normalization Checker')

@section('content')
    <div class="max-w-4xl mx-auto">
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900 mb-2">Analisis Struktur Database (SQL)</h1>
            <p class="text-gray-600">Upload file SQL dump (CREATE TABLE + INSERT opsional) untuk memeriksa kepatuhan normalisasi</p>
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
            <form method="POST" action="{{ route('parse') }}" enctype="multipart/form-data">
                @csrf

                <div class="mb-6">
                    <label for="project_name" class="block text-sm font-medium text-gray-700 mb-2">
                        Nama Proyek
                    </label>
                    <input type="text" id="project_name" name="project_name" required value="{{ old('project_name') }}"
                        class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                        placeholder="e.g., Aplikasi Penjualan">
                </div>

                <div class="mb-6">
                    <label for="sql_file" class="block text-sm font-medium text-gray-700 mb-2">
                        File SQL (.sql)
                    </label>
                    <input type="file" id="sql_file" name="sql_file" accept=".sql" required
                        class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                    <p class="mt-2 text-sm text-gray-500">
                        Upload file SQL dump yang berisi CREATE TABLE statements (dan INSERT opsional).<br>
                        Gunakan <code>mysqldump -u user -p database > database.sql</code> atau ekspor dari phpMyAdmin.
                    </p>
                </div>

                <div class="flex items-center justify-between">
                    <a href="{{ route('home') }}" class="text-gray-600 hover:text-gray-900 text-sm font-medium">
                        ← Kembali ke Beranda
                    </a>
                    <button type="submit"
                        class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        Selanjutnya: Input Ketergantungan →
                    </button>
                </div>
            </form>
        </div>

        {{-- Informasi tambahan --}}
        <div class="main-container">
            <!-- Hero Section -->
            <div class="hero">
                <div class="hero-badge">📌 Alat Normalisasi Database</div>
                <h1>SQL Normalization Checker</h1>
                <p>Alat untuk memeriksa apakah struktur database Anda memenuhi kaidah normalisasi 1NF, 2NF, dan 3NF. Upload file SQL Anda, dapatkan laporan lengkap beserta rekomendasi perbaikan.</p>
            </div>

            <!-- Tentang Section -->
            <div id="tentang" class="card">
                <div class="card-header">
                    <span class="icon">📖</span>
                    <h2>Tentang Alat Ini</h2>
                </div>
                <div class="card-body">
                    <p><strong>SQL Normalization Checker</strong> adalah alat analisis struktur database yang dirancang untuk membantu developer dan database administrator dalam memastikan desain database memenuhi standar normalisasi (1NF, 2NF, 3NF) berdasarkan algoritma Demba.</p>

                    <div class="alert-info">
                        <strong>🎯 Tujuan Utama:</strong><br>
                        • Membaca file SQL dump (CREATE TABLE + INSERT)<br>
                        • Mendeteksi pelanggaran 1NF (multi-value, repeating groups, composite columns)<br>
                        • Mendeteksi pelanggaran 2NF (Partial Dependency)<br>
                        • Mendeteksi pelanggaran 3NF (Transitive Dependency)<br>
                        • Memberikan rekomendasi perbaikan untuk setiap pelanggaran
                    </div>
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
                        <div class="step-item">
                            <div class="step-number-circle">1</div>
                            <div class="step-content">
                                <h3>Ekspor Database ke SQL</h3>
                                <p>Ekspor database Anda dari MySQL/PostgreSQL/phpMyAdmin ke format SQL.</p>
                                <div class="code-block">
                                    # MySQL / MariaDB (via command line)<br>
                                    mysqldump -u username -p nama_database > database.sql<br><br>
                                    # PostgreSQL<br>
                                    pg_dump -U username database_name > database.sql<br><br>
                                    # phpMyAdmin<br>
                                    Pilih database → Tab Export → Format SQL → Klik Go
                                </div>
                            </div>
                        </div>

                        <div class="step-item">
                            <div class="step-number-circle">2</div>
                            <div class="step-content">
                                <h3>Upload File SQL</h3>
                                <p>Gunakan form di atas untuk mengunggah file .sql yang telah diekspor.</p>
                                <div class="code-block">
                                    1. Klik tombol "Choose File"<br>
                                    2. Pilih file .sql Anda<br>
                                    3. Isi Nama Proyek (opsional)<br>
                                    4. Klik tombol "Selanjutnya: Input Ketergantungan"
                                </div>
                            </div>
                        </div>

                        <div class="step-item">
                            <div class="step-number-circle">3</div>
                            <div class="step-content">
                                <h3>Analisis & Rekomendasi</h3>
                                <p>Sistem akan memproses file SQL, mendeteksi pelanggaran normalisasi, dan menampilkan laporan lengkap beserta saran perbaikan.</p>
                                <div class="code-block">
                                    • Status 1NF, 2NF, 3NF per tabel<br>
                                    • Daftar ketergantungan fungsional (FD)<br>
                                    • Minimal cover<br>
                                    • Candidate keys<br>
                                    • Rekomendasi dekomposisi tabel
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Contoh SQL -->
            <div id="contoh" class="card">
                <div class="card-header">
                    <span class="icon">📋</span>
                    <h2>Contoh File SQL (dump)</h2>
                </div>
                <div class="card-body">
                    <p>Contoh file SQL dump yang berisi tabel dengan pelanggaran 2NF (partial dependency):</p>
                    <div class="example-dbml">
                        <pre>-- Tabel yang MELANGGAR 2NF (Primary Key composite)
CREATE TABLE course_enrollments (
    student_id INT,
    course_id INT,
    student_name VARCHAR(100) NOT NULL,   -- hanya tergantung student_id
    course_name VARCHAR(100) NOT NULL,    -- hanya tergantung course_id
    enrollment_date DATE,
    grade VARCHAR(2),
    PRIMARY KEY (student_id, course_id)
);

-- Data contoh (optional)
INSERT INTO course_enrollments VALUES 
(1, 101, 'Andi', 'Matematika', '2024-01-01', 'A'),
(1, 102, 'Andi', 'Fisika', '2024-01-01', 'B');</pre>
                    </div>
                    <div class="alert-info" style="margin-top: 20px;">
                        <strong>💡 Tips:</strong> File SQL dapat berisi banyak CREATE TABLE dan INSERT. Sistem akan membaca skema dan sample data untuk deteksi yang lebih akurat.
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
                    <p>Setiap kolom hanya berisi satu nilai (atomic) dan tidak ada kelompok kolom yang berulang.</p>
                    
                    <h3>📌 2NF (Second Normal Form)</h3>
                    <p>1NF + tidak ada partial dependency (atribut non-key bergantung pada sebagian dari primary key komposit).</p>
                    
                    <h3>📌 3NF (Third Normal Form)</h3>
                    <p>2NF + tidak ada transitive dependency (atribut non-key bergantung pada atribut non-key lain).</p>
                    
                    <table class="info-table">
                        <thead>
                            <tr><th>Normal Form</th><th>Persyaratan</th><th>Contoh Pelanggaran</th></tr>
                        </thead>
                        <tbody>
                            <tr><td>1NF</td><td>Atomic values</td><td>Kolom "telepon" berisi '0812,0813'</td></tr>
                            <tr><td>2NF</td><td>Tidak ada partial dependency</td><td>PK (order_id, product_id) dengan kolom 'product_name'</td></tr>
                            <tr><td>3NF</td><td>Tidak ada transitive dependency</td><td>Tabel karyawan dengan kolom 'department_name' (FK ke dept_id)</td></tr>
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
                        <thead><tr><th>Masalah</th><th>Solusi</th></tr></thead>
                        <tbody>
                            <tr><td>File SQL tidak terdeteksi</td><td>Pastikan file berekstensi .sql dan berisi pernyataan CREATE TABLE yang valid.</td></tr>
                            <tr><td>Tidak ada sample data</td><td>Sistem tetap bisa mendeteksi 1NF (repeating groups, composite columns) tetapi multi-value attribute tidak bisa dideteksi. Sertakan INSERT statements untuk hasil optimal.</td></tr>
                            <tr><td>Hasil analisis tidak muncul</td><td>Refresh halaman dan pastikan tidak ada error pada file SQL (misalnya sintaks tidak dikenal).</td></tr>
                            <tr><td>Apa itu partial dependency?</td><td>Kondisi dimana atribut non-key hanya bergantung pada sebagian dari primary key komposit (bukan seluruh PK).</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('css')
    <style>
        /* Sama seperti style sebelumnya, atau bisa ditambahkan sedikit penyesuaian */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f1f5f9; color: #1e293b; line-height: 1.6; }
        .navbar { background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); color: white; padding: 16px 40px; position: sticky; top: 0; z-index: 100; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
        .navbar .container { max-width: 1200px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; }
        .logo { display: flex; align-items: center; gap: 10px; font-size: 20px; font-weight: bold; }
        .nav-links { display: flex; gap: 25px; flex-wrap: wrap; }
        .nav-links a { color: #cbd5e1; text-decoration: none; transition: color 0.3s; }
        .nav-links a:hover { color: white; }
        .main-container { max-width: 1200px; margin: 0 auto; padding: 40px 20px; }
        .hero { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 24px; padding: 50px 40px; color: white; margin-bottom: 40px; text-align: center; }
        .hero h1 { font-size: 42px; margin-bottom: 16px; }
        .hero p { font-size: 18px; opacity: 0.9; max-width: 700px; margin: 0 auto; }
        .hero-badge { display: inline-block; background: rgba(255,255,255,0.2); padding: 6px 16px; border-radius: 30px; font-size: 14px; margin-bottom: 20px; }
        .card { background: white; border-radius: 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); overflow: hidden; margin-bottom: 30px; }
        .card-header { background: #f8fafc; padding: 20px 30px; border-bottom: 2px solid #e2e8f0; display: flex; align-items: center; gap: 12px; }
        .card-header h2 { font-size: 22px; color: #1e293b; }
        .card-header .icon { font-size: 28px; }
        .card-body { padding: 30px; }
        .steps-container { display: flex; flex-direction: column; gap: 30px; }
        .step-item { display: flex; gap: 20px; flex-wrap: wrap; }
        .step-number-circle { width: 60px; height: 60px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 28px; font-weight: bold; color: white; flex-shrink: 0; }
        .step-content { flex: 1; background: #f8fafc; padding: 20px; border-radius: 16px; }
        .step-content h3 { color: #1e293b; margin-bottom: 12px; font-size: 20px; }
        .code-block { background: #1e293b; color: #e2e8f0; padding: 16px; border-radius: 12px; font-family: monospace; font-size: 13px; overflow-x: auto; margin-top: 12px; }
        .info-table { width: 100%; border-collapse: collapse; }
        .info-table th { background: #f1f5f9; padding: 12px 16px; text-align: left; border-bottom: 2px solid #e2e8f0; }
        .info-table td { padding: 12px 16px; border-bottom: 1px solid #e2e8f0; color: #475569; }
        .alert-info { background: #e0f2fe; border-left: 4px solid #0284c7; padding: 16px 20px; border-radius: 12px; margin: 20px 0; }
        .example-dbml { background: #1e293b; border-radius: 16px; padding: 20px; margin-top: 20px; }
        .example-dbml pre { color: #e2e8f0; font-family: monospace; font-size: 13px; overflow-x: auto; }
        @media (max-width: 768px) {
            .hero h1 { font-size: 28px; }
            .step-number-circle { width: 45px; height: 45px; font-size: 20px; }
            .card-header { padding: 15px 20px; }
            .card-body { padding: 20px; }
        }
    </style>
@endpush