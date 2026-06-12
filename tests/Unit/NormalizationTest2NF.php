<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\ClosureCalculator;
use App\Services\ExtraneousAttributeRemover;
use App\Services\MinimalCoverCalculator;
use App\Services\DependencyClassifier;
use App\Services\SecondNFDecomposer;
use App\Services\FunctionalDependency;

class NormalizationTest2NF extends TestCase
{
    // ==================== A1 : Closure Computation ====================

    /** @test Path 1: F kosong → closure = himpunan awal */
    public function a1_path1_empty_fd()
    {
        $calc = new ClosureCalculator();
        $attrs = ['id_karyawan', 'nama_karyawan'];
        $deps = [];
        $result = $calc->compute($attrs, $deps);
        $this->assertEquals(['id_karyawan', 'nama_karyawan'], $result);
    }

    /** @test Path 2: FD ada tapi LHS tidak ada di closure awal */
    public function a1_path2_fd_exists_but_not_applicable()
    {
        $calc = new ClosureCalculator();
        $attrs = ['id_karyawan']; // hanya tahu id karyawan
        // FD: dari id_departemen kita bisa tahu nama_departemen
        $deps = [new FunctionalDependency(['id_departemen'], 'nama_departemen')];
        $result = $calc->compute($attrs, $deps);
        $this->assertEquals(['id_karyawan'], $result);
    }

    /** @test Path 3: FD berantai, closure bertambah */
    public function a1_path3_closure_grows()
    {
        $calc = new ClosureCalculator();
        $attrs = ['id_karyawan'];
        $deps = [
            new FunctionalDependency(['id_karyawan'], 'nama_karyawan'),
            new FunctionalDependency(['nama_karyawan'], 'kota_tinggal')
        ];
        $result = $calc->compute($attrs, $deps);
        $this->assertEqualsCanonicalizing(['id_karyawan', 'nama_karyawan', 'kota_tinggal'], $result);
    }

    // ==================== A2 : Remove Implied Extraneous Attributes ====================

    /** @test Path 1: F kosong → langsung kembali G kosong */
    public function a2_path1_empty_f()
    {
        $remover = new ExtraneousAttributeRemover(new ClosureCalculator());
        $deps = [];
        $result = $remover->remove($deps);
        $this->assertEmpty($result);
    }

    /** @test Path 2: Hanya FD dengan LHS tunggal → tidak ada perubahan */
    public function a2_path2_only_singleton_lhs()
    {
        $remover = new ExtraneousAttributeRemover(new ClosureCalculator());
        // FD: id_karyawan → nama_karyawan, id_departemen → nama_departemen
        $deps = [
            new FunctionalDependency(['id_karyawan'], 'nama_karyawan'),
            new FunctionalDependency(['id_departemen'], 'nama_departemen')
        ];
        $result = $remover->remove($deps);
        $this->assertCount(2, $result);
        $this->assertTrue($result[0]->equals(new FunctionalDependency(['id_karyawan'], 'nama_karyawan')));
        $this->assertTrue($result[1]->equals(new FunctionalDependency(['id_departemen'], 'nama_departemen')));
    }

    /** @test Path 3: Ada composite lhs tapi semua atribut hanya di LHS (lo) → tidak ada perubahan */
    public function a2_path3_composite_lhs_but_no_lr()
    {
        $remover = new ExtraneousAttributeRemover(new ClosureCalculator());
        // FD: (id_karyawan, id_departemen) → tanggal_mulai
        // Atribut id_karyawan dan id_departemen hanya muncul di LHS (lo), tidak pernah di RHS → bukan lr, tidak perlu diperiksa
        $deps = [new FunctionalDependency(['id_karyawan', 'id_departemen'], 'tanggal_mulai')];
        $result = $remover->remove($deps);
        $this->assertCount(1, $result);
        $this->assertTrue($result[0]->equals(new FunctionalDependency(['id_karyawan', 'id_departemen'], 'tanggal_mulai')));
    }

    /** @test Path 4: Ada atribut lr tetapi tidak extraneous (gagal) → tidak ada perubahan */
    public function a2_path4_lr_but_not_extraneous()
    {
        $remover = new ExtraneousAttributeRemover(new ClosureCalculator());
        // FD: (id_karyawan, id_departemen) → nama_departemen
        // dan id_karyawan → nama_karyawan (tidak membantu untuk id_departemen)
        // Atribut id_departemen ∈ lr (karena muncul juga di RHS di FD lain? tidak di sini, tapi agar ada lr, kita perlu contoh)
        // Lebih baik gunakan contoh: (kode_produk, id_karyawan) → harga, dan id_karyawan → nama_karyawan
        // Di sini atribut id_karyawan ∈ lr, tetapi tidak extraneous karena tanpa id_karyawan, kode_produk saja tidak bisa menentukan harga (tidak ada FD)
        $deps = [
            new FunctionalDependency(['kode_produk', 'id_karyawan'], 'harga'),
            new FunctionalDependency(['id_karyawan'], 'nama_karyawan')
        ];
        $result = $remover->remove($deps);
        // Pastikan masih ada FD dengan RHS 'harga' (bisa masih berupa composite)
        $found = false;
        foreach ($result as $fd) {
            if ($fd->rhs === 'harga') {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found);
    }

    /** @test Path 5: Ada atribut lr dan extraneous → berhasil dihapus */
    public function a2_path5_lr_extraneous_removed()
    {
        $remover = new ExtraneousAttributeRemover(new ClosureCalculator());
        // FD: (id_karyawan, id_departemen) → nama_departemen
        // dan id_karyawan → id_departemen
        // Maka id_departemen adalah extraneous karena id_karyawan sudah menentukan id_departemen
        $deps = [
            new FunctionalDependency(['id_karyawan', 'id_departemen'], 'nama_departemen'),
            new FunctionalDependency(['id_karyawan'], 'id_departemen')
        ];
        $result = $remover->remove($deps);
        // Hasilnya: id_karyawan → nama_departemen (id_departemen dihapus dari LHS)
        $this->assertCount(2, $result);
        $foundNew = false;
        foreach ($result as $fd) {
            if ($fd->lhs == ['id_karyawan'] && $fd->rhs == 'nama_departemen') {
                $foundNew = true;
            }
        }
        $this->assertTrue($foundNew);
    }

    /** @test Path 6: Multiple extraneous removal dalam satu FD (loop berulang) */
    public function a2_path6_multiple_extraneous_in_one_fd()
    {
        $remover = new ExtraneousAttributeRemover(new ClosureCalculator());
        // FD: (id_karyawan, id_departemen, kode_proyek) → nama_proyek
        // dan id_karyawan → id_departemen, serta id_karyawan → kode_proyek
        // Maka id_departemen dan kode_proyek extraneous, akhirnya LHS tinggal id_karyawan
        $deps = [
            new FunctionalDependency(['id_karyawan', 'id_departemen', 'kode_proyek'], 'nama_proyek'),
            new FunctionalDependency(['id_karyawan'], 'id_departemen'),
            new FunctionalDependency(['id_karyawan'], 'kode_proyek')
        ];
        $result = $remover->remove($deps);
        // Harusnya menjadi id_karyawan → nama_proyek
        $found = false;
        foreach ($result as $fd) {
            if ($fd->lhs == ['id_karyawan'] && $fd->rhs == 'nama_proyek') {
                $found = true;
            }
        }
        $this->assertTrue($found);
    }

    /** @test Path 7: Seluruh FD diproses, lebih dari satu FD di G */
    public function a2_path7_multiple_fds_processed()
    {
        $remover = new ExtraneousAttributeRemover(new ClosureCalculator());
        // Kasus campuran:
        // FD1: (id_karyawan, id_departemen) → nama_karyawan
        // FD2: id_karyawan → id_departemen
        // FD3: (id_departemen, kode_lokasi) → alamat
        $deps = [
            new FunctionalDependency(['id_karyawan', 'id_departemen'], 'nama_karyawan'),
            new FunctionalDependency(['id_karyawan'], 'id_departemen'),
            new FunctionalDependency(['id_departemen', 'kode_lokasi'], 'alamat')
        ];
        $result = $remover->remove($deps);
        // FD1 menjadi id_karyawan → nama_karyawan (id_departemen dihapus)
        // FD3 tidak berubah karena id_departemen bukan lr? (tidak muncul di RHS FD lain di sini)
        $this->assertCount(3, $result);
        $found = false;
        foreach ($result as $fd) {
            if ($fd->lhs == ['id_karyawan'] && $fd->rhs == 'nama_karyawan') {
                $found = true;
            }
        }
        $this->assertTrue($found);
    }

// ==================== A3 : Remove Redundant Dependencies ====================

    /** @test Path 1: F kosong → Fm kosong */
    public function a3_path1_empty_f()
    {
        $calc = new MinimalCoverCalculator(new ClosureCalculator());
        $deps = [];
        $result = $calc->compute($deps);
        $this->assertEmpty($result);
    }

    /** @test Path 2: Ada FD tapi tidak ada pasangan RHS sama → tidak ada perubahan */
    public function a3_path2_no_matching_rhs()
    {
        $calc = new MinimalCoverCalculator(new ClosureCalculator());
        $deps = [
            new FunctionalDependency(['id_karyawan'], 'nama_karyawan'),
            new FunctionalDependency(['id_departemen'], 'nama_departemen')
        ];
        $result = $calc->compute($deps);
        $this->assertCount(2, $result);
    }

    /** @test Path 3: Ada pasangan RHS sama, tapi X⊈Y⁺ (gagal hapus) */
    public function a3_path3_pair_rhs_same_but_not_redundant()
    {
        $calc = new MinimalCoverCalculator(new ClosureCalculator());
        // FD: id_karyawan → kota, dan id_departemen → kota
        // Tidak ada FD lain, maka id_karyawan tidak ⊆ closure(id_departemen) tanpa FD kedua
        $deps = [
            new FunctionalDependency(['id_karyawan'], 'kota'),
            new FunctionalDependency(['id_departemen'], 'kota')
        ];
        $result = $calc->compute($deps);
        // Tidak ada yang dihapus, tetap 2 FD
        $this->assertCount(2, $result);
    }

    /** @test Path 4: Ada pasangan redundan, Y→A dihapus */
    public function a3_path4_redundant_dependency_removed()
    {
        $calc = new MinimalCoverCalculator(new ClosureCalculator());
        // FD: id_karyawan → kota, id_departemen → kota, dan id_departemen → id_karyawan
        // Maka id_karyawan ⊆ closure(id_departemen) karena id_departemen → id_karyawan, lalu id_karyawan → kota
        // Sehingga id_departemen → kota redundan, dihapus
        $deps = [
            new FunctionalDependency(['id_karyawan'], 'kota'),
            new FunctionalDependency(['id_departemen'], 'kota'),
            new FunctionalDependency(['id_departemen'], 'id_karyawan')
        ];
        $result = $calc->compute($deps);
        // Pastikan id_departemen → kota tidak ada
        $hasRedundant = false;
        foreach ($result as $fd) {
            if ($fd->lhs == ['id_departemen'] && $fd->rhs == 'kota') {
                $hasRedundant = true;
                break;
            }
        }
        $this->assertFalse($hasRedundant);
    }

    // ==================== A4 : Klasifikasi Full / Partial Dependencies ====================

    /** @test Path 1: Fm kosong → Fp kosong, Ff kosong */
    public function a4_path1_empty_fm()
    {
        $classifier = new DependencyClassifier(new ClosureCalculator());
        $fm = [];
        $candidateKeys = [['A']];
        $result = $classifier->classify($fm, $candidateKeys);
        $this->assertEmpty($result['partial']);
        $this->assertEmpty($result['full']);
    }

    /** @test Path 2: FD ada tapi tidak memenuhi syarat partial → semua tetap full */
    public function a4_path2_no_partial()
    {
        $classifier = new DependencyClassifier(new ClosureCalculator());
        // FK: A→B, dengan A candidate key (bukan proper subset) atau B key attribute.
        // Misal candidate keys: {A}, B bukan key attribute → A→B adalah full karena A = CK, bukan proper subset.
        $fm = [new FunctionalDependency(['A'], 'B')];
        $candidateKeys = [['A']];
        $result = $classifier->classify($fm, $candidateKeys);
        $this->assertEmpty($result['partial']);
        $this->assertCount(1, $result['full']);
    }

    /** @test Path 3: Ada partial dependency, tapi tidak ada FD transitif */
    public function a4_path3_partial_without_transitive()
    {
        $classifier = new DependencyClassifier(new ClosureCalculator());
        // Candidate key: AB. FD: A→C (A ⊂ AB, C bukan key attribute). Partial.
        $fm = [
            new FunctionalDependency(['A', 'B'], 'D'), // FD utama? Biar ada partial: A→C
            new FunctionalDependency(['A'], 'C')
        ];
        // Candidate keys: AB
        $candidateKeys = [['A', 'B']];
        $result = $classifier->classify($fm, $candidateKeys);
        // A→C harus partial, tidak ada FD transitif karena C tidak bisa menentukan FD lain.
        $this->assertCount(1, $result['partial']);
        $this->assertTrue($result['partial'][0]->equals(new FunctionalDependency(['A'], 'C')));
        $this->assertCount(1, $result['full']); // AB→D tetap full
    }

    /** @test Path 4: Ada partial dependency + FD transitif (cascade) */
    public function a4_path4_partial_with_transitive()
    {
        $classifier = new DependencyClassifier(new ClosureCalculator());
        // Candidate key: AB. FD: A→C (partial), lalu C→D (transitif). Saat A→C dipindah, C→D juga ikut.
        $fm = [
            new FunctionalDependency(['A', 'B'], 'E'), // full
            new FunctionalDependency(['A'], 'C'),
            new FunctionalDependency(['C'], 'D')
        ];
        $candidateKeys = [['A', 'B']];
        $result = $classifier->classify($fm, $candidateKeys);
        // A→C dan C→D harus pindah ke partial, AB→E tetap full
        $this->assertCount(2, $result['partial']);
        $this->assertCount(1, $result['full']);
    }

    // ==================== A5 : Dekomposisi ke 2NF ====================

    /** @test Path 1: Fp kosong → langsung output relasi tunggal (sudah 2NF) */
    public function a5_path1_fp_empty()
    {
        $decomposer = new SecondNFDecomposer(new ClosureCalculator());
        $attributes = ['A', 'B', 'C'];
        $fullDeps = [new FunctionalDependency(['A'], 'B')];
        $partialDeps = [];
        $candidateKeys = [['A']];
        $primaryKey = ['A'];
        $result = $decomposer->decompose($attributes, $fullDeps, $partialDeps, $candidateKeys, $primaryKey);
        $this->assertTrue($result['is2NF']);
        $this->assertCount(1, $result['relations']);
    }

    /** @test Path 2: Fp tidak kosong, tapi Y bukan proper subset candidate key → tidak ada dekomposisi */
    public function a5_path2_no_proper_subset()
    {
        $decomposer = new SecondNFDecomposer(new ClosureCalculator());
        // Partial dependency: Y = AB, candidate key = AB (bukan proper subset)
        $attributes = ['A', 'B', 'C'];
        $fullDeps = [];
        $partialDeps = [new FunctionalDependency(['A', 'B'], 'C')];
        $candidateKeys = [['A', 'B']];
        $primaryKey = ['A', 'B'];
        $result = $decomposer->decompose($attributes, $fullDeps, $partialDeps, $candidateKeys, $primaryKey);
        // Karena Y = candidate key (bukan proper subset), tidak dibuat relasi baru. Hasil tetap satu relasi? Sesuai path 2, tidak ada dekomposisi.
        // Method decompose akan menganggap semua FD partial? Mari cek: di kode, pengecekan proper subset dilakukan di isProperSubsetOfSomeCandidateKey.
        // Karena Y = CK, bukan proper subset, maka tidak diproses. Akhirnya relasi utama dengan Xf = semua atribut.
        $this->assertFalse($result['is2NF']); // masih ada partial, tapi tidak didekomposisi karena tidak memenuhi syarat.
        $this->assertCount(1, $result['relations']); // hanya relasi utama
    }

    /** @test Path 3: Ada partial dependency → dekomposisi dilakukan */
    public function a5_path3_partial_decomposition()
    {
        $decomposer = new SecondNFDecomposer(new ClosureCalculator());
        // Tabel enrollment(student_id, course_id, student_name, grade), PK = (student_id,course_id)
        // Partial: student_id → student_name
        $attributes = ['student_id', 'course_id', 'student_name', 'grade'];
        $fullDeps = [new FunctionalDependency(['student_id', 'course_id'], 'grade')];
        $partialDeps = [new FunctionalDependency(['student_id'], 'student_name')];
        $candidateKeys = [['student_id', 'course_id']];
        $primaryKey = ['student_id', 'course_id'];
        $result = $decomposer->decompose($attributes, $fullDeps, $partialDeps, $candidateKeys, $primaryKey);
        $this->assertFalse($result['is2NF']);
        // Harus ada minimal 2 relasi: R_student_id dan RK (student_id,course_id, grade)
        $this->assertGreaterThanOrEqual(2, count($result['relations']));
        // Cek apakah ada relasi dengan primary key student_id
        $found = false;
        foreach ($result['relations'] as $rel) {
            if ($rel['primaryKey'] == ['student_id']) {
                $found = true;
            }
        }
        $this->assertTrue($found);
    }
}
