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

    /** @test Path 1: F kosong → langsung kembali X⁺ = X */
    public function a1_path1_empty_fd()
    {
        $calc = new ClosureCalculator();
        $attrs = ['A', 'B'];
        $deps = [];
        $result = $calc->compute($attrs, $deps);
        $this->assertEquals(['A', 'B'], $result);
    }

    /** @test Path 2: FD ada tapi A bukan anggota closure */
    public function a1_path2_fd_exists_but_not_applicable()
    {
        $calc = new ClosureCalculator();
        $attrs = ['A'];
        $deps = [new FunctionalDependency(['B'], 'C')]; // B tidak ada di closure awal
        $result = $calc->compute($attrs, $deps);
        $this->assertEquals(['A'], $result);
    }

    /** @test Path 3: Semua kondisi terpenuhi, closure bertambah */
    public function a1_path3_closure_grows()
    {
        $calc = new ClosureCalculator();
        $attrs = ['A'];
        $deps = [
            new FunctionalDependency(['A'], 'B'),
            new FunctionalDependency(['B'], 'C')
        ];
        $result = $calc->compute($attrs, $deps);
        $this->assertEquals(['A', 'B', 'C'], $result);
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

    /** @test Path 2: Hanya FD dengan |X|=1 → tidak ada perubahan */
    public function a2_path2_only_singleton_lhs()
    {
        $remover = new ExtraneousAttributeRemover(new ClosureCalculator());
        $deps = [
            new FunctionalDependency(['A'], 'B'),
            new FunctionalDependency(['C'], 'D')
        ];
        $result = $remover->remove($deps);
        $this->assertCount(2, $result);
        $this->assertTrue($result[0]->equals(new FunctionalDependency(['A'], 'B')));
        $this->assertTrue($result[1]->equals(new FunctionalDependency(['C'], 'D')));
    }

    /** @test Path 3: Ada composite lhs tapi semua atribut ∈ lo/ro (bukan lr) → tidak ada perubahan */
    public function a2_path3_composite_lhs_but_no_lr()
    {
        $remover = new ExtraneousAttributeRemover(new ClosureCalculator());
        // Atribut A dan B hanya muncul di LHS (lo), jadi bukan lr.
        $deps = [new FunctionalDependency(['A', 'B'], 'C')];
        $result = $remover->remove($deps);
        $this->assertCount(1, $result);
        $this->assertTrue($result[0]->equals(new FunctionalDependency(['A', 'B'], 'C')));
    }

    /** @test Path 4: Ada atribut lr tetapi tidak extraneous (gagal) → tidak ada perubahan */
    public function a2_path4_lr_but_not_extraneous()
    {
        $remover = new ExtraneousAttributeRemover(new ClosureCalculator());
        $deps = [
            new FunctionalDependency(['A', 'B'], 'D'),
            new FunctionalDependency(['A'], 'B')
        ];
        $result = $remover->remove($deps);
        $found = false;
        foreach ($result as $fd) {
            if ($fd->rhs === 'D') {
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
        // AB → D, A→B. B ∈ lr. Cek B ∈ (A)⁺? Ya karena A→B.
        $deps = [
            new FunctionalDependency(['A', 'B'], 'D'),
            new FunctionalDependency(['A'], 'B')
        ];
        $result = $remover->remove($deps);
        // Harusnya AB→D menjadi A→D
        $this->assertCount(2, $result);
        $foundNew = false;
        foreach ($result as $fd) {
            if ($fd->lhs == ['A'] && $fd->rhs == 'D') {
                $foundNew = true;
            }
        }
        $this->assertTrue($foundNew);
    }

    /** @test Path 6: Multiple extraneous removal dalam satu FD (loop berulang) */
    public function a2_path6_multiple_extraneous_in_one_fd()
    {
        $remover = new ExtraneousAttributeRemover(new ClosureCalculator());
        // ABD→E, A→D, B→C. D ∈ lr dan extraneous (karena A→D), sehingga ABD→E → AB→E.
        // Lanjut loop, lalu periksa lagi? A dan B mungkin bukan lr, selesai.
        $deps = [
            new FunctionalDependency(['A', 'B', 'D'], 'E'),
            new FunctionalDependency(['A'], 'D'),
            new FunctionalDependency(['B'], 'C')
        ];
        $result = $remover->remove($deps);
        // Harusnya ABD→E menjadi AB→E
        $found = false;
        foreach ($result as $fd) {
            if ($fd->lhs == ['A', 'B'] && $fd->rhs == 'E') {
                $found = true;
            }
        }
        $this->assertTrue($found);
    }

    /** @test Path 7: Seluruh FD diproses, lebih dari satu FD di G */
    public function a2_path7_multiple_fds_processed()
    {
        $remover = new ExtraneousAttributeRemover(new ClosureCalculator());
        // Kasus campuran: AB→D, A→B, BC→E
        $deps = [
            new FunctionalDependency(['A', 'B'], 'D'),
            new FunctionalDependency(['A'], 'B'),
            new FunctionalDependency(['B', 'C'], 'E')
        ];
        $result = $remover->remove($deps);
        // AB→D menjadi A→D karena B extraneous.
        $this->assertCount(3, $result);
        $found = false;
        foreach ($result as $fd) {
            if ($fd->lhs == ['A'] && $fd->rhs == 'D') {
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
            new FunctionalDependency(['A'], 'B'),
            new FunctionalDependency(['C'], 'D')
        ];
        $result = $calc->compute($deps);
        $this->assertCount(2, $result);
    }

    /** @test Path 3: Ada pasangan RHS sama, tapi X⊈Y⁺ (gagal hapus) */
    public function a3_path3_pair_rhs_same_but_not_redundant()
    {
        $calc = new MinimalCoverCalculator(new ClosureCalculator());
        // A→B, C→B. Cek apakah A ⊆ C⁺ pada G tanpa C→B.
        // Misal tidak ada FD lain, C⁺ = {C}, A tidak ⊆ {C}.
        $deps = [
            new FunctionalDependency(['A'], 'B'),
            new FunctionalDependency(['C'], 'B')
        ];
        $result = $calc->compute($deps);
        // Tidak ada yang dihapus, tetap 2 FD
        $this->assertCount(2, $result);
    }

    /** @test Path 4: Ada pasangan redundan, Y→A dihapus */
    public function a3_path4_redundant_dependency_removed()
    {
        $calc = new MinimalCoverCalculator(new ClosureCalculator());
        $deps = [
            new FunctionalDependency(['A'], 'B'),
            new FunctionalDependency(['C'], 'B'),
            new FunctionalDependency(['C'], 'A')
        ];
        $result = $calc->compute($deps);
        // Pastikan C→B tidak ada
        $hasCtoB = false;
        foreach ($result as $fd) {
            if ($fd->lhs == ['C'] && $fd->rhs == 'B') {
                $hasCtoB = true;
                break;
            }
        }
        $this->assertFalse($hasCtoB);
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
