<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\ClosureCalculator;
use App\Services\CandidateKeyFinder;
use App\Services\ThirdNFDecomposer;
use App\Services\FunctionalDependency;

class ThirdNFDecomposerTest extends TestCase
{
    protected ThirdNFDecomposer $decomposer;

    protected function setUp(): void
    {
        parent::setUp();
        $closureCalc = new ClosureCalculator();
        $keyFinder = new CandidateKeyFinder($closureCalc);
        $this->decomposer = new ThirdNFDecomposer($closureCalc, $keyFinder);
    }

    /** @test Path 1: Ft kosong (tidak ada FD transitif) -> langsung 3NF, satu relasi */
    public function a6_path1_no_transitive_dependency()
    {
        $relationName = 'R_Student';
        $attributes = ['id', 'name', 'major'];
        $dependencies = [
            new FunctionalDependency(['id'], 'name'),
            new FunctionalDependency(['id'], 'major')
        ];
        $candidateKeys = [['id']];
        $primaryKey = ['id'];

        $result = $this->decomposer->decompose($relationName, $attributes, $dependencies, $candidateKeys, $primaryKey);

        $this->assertTrue($result['is3NF']);
        $this->assertEmpty($result['transitiveDeps']);
        $this->assertCount(1, $result['relations']);
        $this->assertArrayHasKey($relationName, $result['relations']);
        $this->assertEqualsCanonicalizing($attributes, $result['relations'][$relationName]['attributes']);
        $this->assertEqualsCanonicalizing($primaryKey, $result['relations'][$relationName]['primaryKey']);
    }

    /** @test Path 2: Ada FD transitif, RY baru, A ∉ X (A bukan atribut relasi asli) */
    public function a6_path2_new_ry_a_not_in_x()
    {
        $relationName = 'R_Employee';
        $attributes = ['id', 'name', 'dept_id'];
        $dependencies = [
            new FunctionalDependency(['id'], 'name'),
            new FunctionalDependency(['id'], 'dept_id'),
            new FunctionalDependency(['dept_id'], 'dept_name')
        ];
        $candidateKeys = [['id']];
        $primaryKey = ['id'];

        $result = $this->decomposer->decompose($relationName, $attributes, $dependencies, $candidateKeys, $primaryKey);

        $this->assertFalse($result['is3NF']);
        $this->assertCount(1, $result['transitiveDeps']);
        $this->assertArrayHasKey('R_dept_id', $result['relations']);
        $relBaru = $result['relations']['R_dept_id'];
        $this->assertEqualsCanonicalizing(['dept_id', 'dept_name'], $relBaru['attributes']);
        $this->assertEqualsCanonicalizing(['dept_id'], $relBaru['primaryKey']);
        // Relasi utama tetap sama (urutan bebas)
        $this->assertEqualsCanonicalizing(['id', 'name', 'dept_id'], $result['relations'][$relationName]['attributes']);
    }

    /** @test Path 3: Ada FD transitif, RY baru, A ∈ X, dilakukan penghapusan atribut transitif */
    public function a6_path3_new_ry_a_in_x_remove_attributes()
    {
        $relationName = 'R_Employee';
        $attributes = ['id', 'name', 'dept_id', 'dept_name'];
        $dependencies = [
            new FunctionalDependency(['id'], 'name'),
            new FunctionalDependency(['id'], 'dept_id'),
            new FunctionalDependency(['dept_id'], 'dept_name')
        ];
        $candidateKeys = [['id']];
        $primaryKey = ['id'];

        $result = $this->decomposer->decompose($relationName, $attributes, $dependencies, $candidateKeys, $primaryKey);

        $this->assertFalse($result['is3NF']);
        $this->assertCount(1, $result['transitiveDeps']);
        $this->assertArrayHasKey('R_dept_id', $result['relations']);
        $relBaru = $result['relations']['R_dept_id'];
        $this->assertEqualsCanonicalizing(['dept_id', 'dept_name'], $relBaru['attributes']);
        // Relasi utama kehilangan dept_name
        $this->assertEqualsCanonicalizing(['id', 'name', 'dept_id'], $result['relations'][$relationName]['attributes']);
    }

    /** @test Path 4: Ada FD transitif dengan determinant Y sudah pernah dibuat (RY sudah ada), A ∉ X */
    public function a6_path4_existing_ry_a_not_in_x()
    {
        $relationName = 'R_Employee';
        $attributes = ['id', 'name', 'dept_id'];
        $dependencies = [
            new FunctionalDependency(['id'], 'name'),
            new FunctionalDependency(['dept_id'], 'dept_name'),
            new FunctionalDependency(['dept_id'], 'location')
        ];
        $candidateKeys = [['id']];
        $primaryKey = ['id'];

        $result = $this->decomposer->decompose($relationName, $attributes, $dependencies, $candidateKeys, $primaryKey);

        $this->assertFalse($result['is3NF']);
        $this->assertCount(2, $result['transitiveDeps']);
        $this->assertArrayHasKey('R_dept_id', $result['relations']);
        $relBaru = $result['relations']['R_dept_id'];
        $this->assertEqualsCanonicalizing(['dept_id', 'dept_name', 'location'], $relBaru['attributes']);
        // Relasi utama tidak berubah
        $this->assertEqualsCanonicalizing(['id', 'name', 'dept_id'], $result['relations'][$relationName]['attributes']);
    }

    /** @test Path 5: Ada FD transitif dengan RY sudah ada, A ∈ X, dilakukan penghapusan atribut transitif */
    public function a6_path5_existing_ry_a_in_x_remove_attributes()
    {
        $relationName = 'R_Employee';
        $attributes = ['id', 'name', 'dept_id', 'dept_name', 'location'];
        $dependencies = [
            new FunctionalDependency(['id'], 'name'),
            new FunctionalDependency(['dept_id'], 'dept_name'),
            new FunctionalDependency(['dept_id'], 'location')
        ];
        $candidateKeys = [['id']];
        $primaryKey = ['id'];

        $result = $this->decomposer->decompose($relationName, $attributes, $dependencies, $candidateKeys, $primaryKey);

        $this->assertFalse($result['is3NF']);
        $this->assertCount(2, $result['transitiveDeps']);
        $relBaru = $result['relations']['R_dept_id'];
        $this->assertEqualsCanonicalizing(['dept_id', 'dept_name', 'location'], $relBaru['attributes']);
        // Relasi utama: setelah kedua FD, dept_name dan location dihapus (karena keduanya A ∈ X dan ∈ closure masing-masing)
        $expectedMain = ['id', 'name', 'dept_id'];
        $this->assertEqualsCanonicalizing($expectedMain, $result['relations'][$relationName]['attributes']);
    }

    /** @test Path 5 variant: closure A⁺ lebih dari satu atribut (rantai) */
    public function a6_path5_existing_ry_a_in_x_with_closure_chain()
    {
        $relationName = 'R_Order';
        $attributes = ['order_id', 'customer_id', 'customer_name', 'customer_city', 'product_id'];
        $dependencies = [
            new FunctionalDependency(['order_id'], 'customer_id'),
            new FunctionalDependency(['order_id'], 'product_id'),
            new FunctionalDependency(['customer_id'], 'customer_name'),
            new FunctionalDependency(['customer_id'], 'customer_city')
        ];
        $candidateKeys = [['order_id']];
        $primaryKey = ['order_id'];

        $result = $this->decomposer->decompose($relationName, $attributes, $dependencies, $candidateKeys, $primaryKey);

        $this->assertFalse($result['is3NF']);
        $relBaru = $result['relations']['R_customer_id'];
        $this->assertEqualsCanonicalizing(['customer_id', 'customer_name', 'customer_city'], $relBaru['attributes']);
        $this->assertEqualsCanonicalizing(['customer_id'], $relBaru['primaryKey']);
        // Relasi utama tersisa: order_id, customer_id, product_id (customer_name dan customer_city dihapus)
        $expectedMain = ['order_id', 'customer_id', 'product_id'];
        $this->assertEqualsCanonicalizing($expectedMain, $result['relations'][$relationName]['attributes']);
    }
}