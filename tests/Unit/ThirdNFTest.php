<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\ClosureCalculator;
use App\Services\ExtraneousAttributeRemover;
use App\Services\MinimalCoverCalculator;
use App\Services\CandidateKeyFinder;
use App\Services\DependencyClassifier;
use App\Services\SecondNFDecomposer;
use App\Services\ThirdNFDecomposer;
use App\Services\NormalizationAnalyzer;
use App\Services\FunctionalDependency;

class ThirdNFTest extends TestCase
{
    private NormalizationAnalyzer $analyzer;

    protected function setUp(): void
    {
        parent::setUp();

        $closure   = new ClosureCalculator();
        $keyFinder = new CandidateKeyFinder($closure); // ← tambah ini

        $this->analyzer = new NormalizationAnalyzer(
            new ExtraneousAttributeRemover($closure),
            new MinimalCoverCalculator($closure),
            $keyFinder,
            new DependencyClassifier($closure),
            new SecondNFDecomposer($closure),
            new ThirdNFDecomposer($closure, $keyFinder),
        );
    }

    // ----------------------------------------------------------------
    // Helper
    // ----------------------------------------------------------------

    private function makeFds(array $rawFds): array
    {
        $fds = [];
        foreach ($rawFds as $raw) {
            foreach (array_map('trim', explode(',', $raw['rhs'])) as $rhs) {
                $fds[] = new FunctionalDependency((array) $raw['lhs'], $rhs);
            }
        }
        return $fds;
    }

    private function sortAttrs(array $attrs): array
    {
        sort($attrs);
        return array_values(array_unique($attrs));
    }

    private function assertRelationExists(array $relations, array $expectedAttrs): void
    {
        $expected = $this->sortAttrs($expectedAttrs);
        foreach ($relations as $rel) {
            if ($this->sortAttrs($rel['attributes']) === $expected) {
                $this->assertTrue(true);
                return;
            }
        }
        $found = array_map(fn($r) => implode(',', $this->sortAttrs($r['attributes'])), $relations);
        $this->fail(
            "Relasi dengan atribut [" . implode(', ', $expected) . "] tidak ditemukan.\n"
                . "Relasi yang ada: " . implode(' | ', $found)
        );
    }

    private function assertTransitiveDepExists(array $transitiveDeps, array $lhs, string $rhs): void
    {
        foreach ($transitiveDeps as $fd) {
            if ($this->sortAttrs($fd->lhs) === $this->sortAttrs($lhs) && $fd->rhs === $rhs) {
                $this->assertTrue(true);
                return;
            }
        }
        $this->fail(
            "Transitive dependency [" . implode(',', $lhs) . " → $rhs] tidak ditemukan.\n"
                . "Transitive deps yang ada: " . implode(', ', array_map(fn($f) => (string)$f, $transitiveDeps))
        );
    }

    // ================================================================
    // LOLOS 3NF — TC 01–07
    // ================================================================

    /**
     * TC-01: PK tunggal, tidak ada transitive dependency.
     * Relasi: Student(id, name, email, birth_date)
     * PK: id
     */
    public function test_tc01_pk_tunggal_lolos_3nf(): void
    {
        $attrs = ['id', 'name', 'email', 'birth_date'];
        $fds   = $this->makeFds([
            ['lhs' => ['id'], 'rhs' => 'name'],
            ['lhs' => ['id'], 'rhs' => 'email'],
            ['lhs' => ['id'], 'rhs' => 'birth_date'],
        ]);

        $result = $this->analyzer->normalize('Student', $attrs, $fds, ['id']);

        $this->assertTrue($result->is2NF, 'TC-01: Seharusnya lolos 2NF');
        $this->assertTrue($result->is3NF, 'TC-01: Seharusnya lolos 3NF');
        $this->assertEmpty($result->transitiveDependencies, 'TC-01: Tidak boleh ada transitive dependency');
    }

    /**
     * TC-02: Semua non-PK langsung bergantung pada PK.
     * Relasi: Product(id, name, price, stock, category)
     * PK: id
     */
    public function test_tc02_semua_non_pk_langsung_lolos_3nf(): void
    {
        $attrs = ['id', 'name', 'price', 'stock', 'category'];
        $fds   = $this->makeFds([
            ['lhs' => ['id'], 'rhs' => 'name'],
            ['lhs' => ['id'], 'rhs' => 'price'],
            ['lhs' => ['id'], 'rhs' => 'stock'],
            ['lhs' => ['id'], 'rhs' => 'category'],
        ]);

        $result = $this->analyzer->normalize('Product', $attrs, $fds, ['id']);

        $this->assertTrue($result->is3NF, 'TC-02: Seharusnya lolos 3NF');
        $this->assertEmpty($result->transitiveDependencies, 'TC-02: Tidak boleh ada transitive dependency');
        $this->assertCount(1, $result->relations3NF, 'TC-02: Tidak perlu dekomposisi');
    }

    /**
     * TC-03: PK composite, semua non-PK full dep, tidak ada transitive.
     * Relasi: ExamResult(student_id, subject_id, score, grade)
     * PK: (student_id, subject_id)
     */
    public function test_tc03_composite_pk_lolos_3nf(): void
    {
        $attrs = ['student_id', 'subject_id', 'score', 'grade'];
        $fds   = $this->makeFds([
            ['lhs' => ['student_id', 'subject_id'], 'rhs' => 'score'],
            ['lhs' => ['student_id', 'subject_id'], 'rhs' => 'grade'],
        ]);

        $result = $this->analyzer->normalize('ExamResult', $attrs, $fds, ['student_id', 'subject_id']);

        $this->assertTrue($result->is2NF, 'TC-03: Seharusnya lolos 2NF');
        $this->assertTrue($result->is3NF, 'TC-03: Seharusnya lolos 3NF');
        $this->assertEmpty($result->transitiveDependencies, 'TC-03: Tidak boleh ada transitive dependency');
    }

    /**
     * TC-04: Non-PK menentukan non-PK lain TAPI yang menentukan adalah candidate key.
     * Relasi: Permission(id, name, guard_name, created_at)
     * PK: id
     * CK alternatif: (name, guard_name) → tidak menghasilkan transitive
     */
    public function test_tc04_candidate_key_alternatif_lolos_3nf(): void
    {
        $attrs = ['id', 'name', 'guard_name', 'created_at'];
        $fds   = $this->makeFds([
            ['lhs' => ['id'],               'rhs' => 'name'],
            ['lhs' => ['id'],               'rhs' => 'guard_name'],
            ['lhs' => ['id'],               'rhs' => 'created_at'],
            ['lhs' => ['name', 'guard_name'], 'rhs' => 'id'],
        ]);

        $result = $this->analyzer->normalize('Permission', $attrs, $fds, ['id']);

        $this->assertTrue($result->is3NF, 'TC-04: Seharusnya lolos 3NF');
        $this->assertEmpty($result->transitiveDependencies, 'TC-04: name,guard_name adalah candidate key sehingga tidak transitive');
    }

    /**
     * TC-05: Relasi hanya PK, tidak ada non-PK.
     * Relasi: Pivot(a_id, b_id)
     * PK: (a_id, b_id)
     */
    public function test_tc05_relasi_hanya_pk_lolos_3nf(): void
    {
        $attrs = ['a_id', 'b_id'];
        $fds   = [];

        $result = $this->analyzer->normalize('Pivot', $attrs, $fds, ['a_id', 'b_id']);

        $this->assertTrue($result->is3NF, 'TC-05: Relasi tanpa non-PK seharusnya lolos 3NF');
        $this->assertEmpty($result->transitiveDependencies, 'TC-05: Tidak ada transitive dependency');
    }

    /**
     * TC-06: FD chain tapi semua LHS adalah candidate key.
     * Relasi: Role(id, name, guard_name)
     * PK: id, CK juga: name
     */
    public function test_tc06_semua_lhs_adalah_candidate_key_lolos_3nf(): void
    {
        $attrs = ['id', 'name', 'guard_name'];
        $fds   = $this->makeFds([
            ['lhs' => ['id'],   'rhs' => 'name'],
            ['lhs' => ['id'],   'rhs' => 'guard_name'],
            ['lhs' => ['name'], 'rhs' => 'id'],
            ['lhs' => ['name'], 'rhs' => 'guard_name'],
        ]);

        $result = $this->analyzer->normalize('Role', $attrs, $fds, ['id']);

        $this->assertTrue($result->is3NF, 'TC-06: Seharusnya lolos 3NF karena name juga candidate key');
        $this->assertEmpty($result->transitiveDependencies, 'TC-06: Tidak ada transitive dependency');
    }

    /**
     * TC-07: Relasi dengan satu kolom saja.
     */
    public function test_tc07_satu_kolom_lolos_3nf(): void
    {
        $attrs = ['id'];
        $fds   = [];

        $result = $this->analyzer->normalize('SingleCol', $attrs, $fds, ['id']);

        $this->assertTrue($result->is3NF, 'TC-07: Relasi satu kolom seharusnya lolos 3NF');
    }

    // ================================================================
    // MELANGGAR 3NF — TC 08–16
    // ================================================================

    /**
     * TC-08: Contoh klasik dari skema employees.
     * Relasi: Employee(id, emp_name, emp_salary, dept_id, dept_name, dept_location)
     * PK: id
     * Transitive: id → dept_id → dept_name, dept_location
     */
    public function test_tc08_employee_transitive_dept_melanggar_3nf(): void
    {
        $attrs = ['id', 'emp_name', 'emp_salary', 'dept_id', 'dept_name', 'dept_location'];
        $fds   = $this->makeFds([
            ['lhs' => ['id'],      'rhs' => 'emp_name'],
            ['lhs' => ['id'],      'rhs' => 'emp_salary'],
            ['lhs' => ['id'],      'rhs' => 'dept_id'],
            ['lhs' => ['dept_id'], 'rhs' => 'dept_name'],
            ['lhs' => ['dept_id'], 'rhs' => 'dept_location'],
        ]);

        $result = $this->analyzer->normalize('Employee', $attrs, $fds, ['id']);

        $this->assertTrue($result->is2NF,  'TC-08: Seharusnya lolos 2NF');
        $this->assertFalse($result->is3NF, 'TC-08: Seharusnya melanggar 3NF');
        $this->assertNotEmpty($result->transitiveDependencies, 'TC-08: Harus ada transitive dependency');

        $this->assertTransitiveDepExists($result->transitiveDependencies, ['dept_id'], 'dept_name');
        $this->assertTransitiveDepExists($result->transitiveDependencies, ['dept_id'], 'dept_location');

        // Harus menghasilkan 2 relasi: Employee tanpa dept_name/dept_location, dan Dept
        $this->assertRelationExists($result->relations3NF, ['id', 'emp_name', 'emp_salary', 'dept_id']);
        $this->assertRelationExists($result->relations3NF, ['dept_id', 'dept_name', 'dept_location']);
    }

    /**
     * TC-09: Domain order — transitive lewat customer_id.
     * Relasi: Order(id, order_date, customer_id, customer_name, customer_city, total_amount)
     * PK: id
     * Transitive: id → customer_id → customer_name, customer_city
     */
    public function test_tc09_order_transitive_customer_melanggar_3nf(): void
    {
        $attrs = ['id', 'order_date', 'customer_id', 'customer_name', 'customer_city', 'total_amount'];
        $fds   = $this->makeFds([
            ['lhs' => ['id'],          'rhs' => 'order_date'],
            ['lhs' => ['id'],          'rhs' => 'customer_id'],
            ['lhs' => ['id'],          'rhs' => 'total_amount'],
            ['lhs' => ['customer_id'], 'rhs' => 'customer_name'],
            ['lhs' => ['customer_id'], 'rhs' => 'customer_city'],
        ]);

        $result = $this->analyzer->normalize('Order', $attrs, $fds, ['id']);

        $this->assertTrue($result->is2NF,  'TC-09: Seharusnya lolos 2NF');
        $this->assertFalse($result->is3NF, 'TC-09: Seharusnya melanggar 3NF');
        $this->assertCount(2, $result->transitiveDependencies, 'TC-09: Harus ada 2 transitive dependency');

        $this->assertTransitiveDepExists($result->transitiveDependencies, ['customer_id'], 'customer_name');
        $this->assertTransitiveDepExists($result->transitiveDependencies, ['customer_id'], 'customer_city');

        $this->assertRelationExists($result->relations3NF, ['id', 'order_date', 'customer_id', 'total_amount']);
        $this->assertRelationExists($result->relations3NF, ['customer_id', 'customer_name', 'customer_city']);
    }

    /**
     * TC-10: Domain mahasiswa — transitive lewat advisor_id.
     * Relasi: Student(id, nim, name, major, advisor_id, advisor_name, advisor_room)
     * PK: id
     * Transitive: id → advisor_id → advisor_name, advisor_room
     */
    public function test_tc10_student_transitive_advisor_melanggar_3nf(): void
    {
        $attrs = ['id', 'nim', 'name', 'major', 'advisor_id', 'advisor_name', 'advisor_room'];
        $fds   = $this->makeFds([
            ['lhs' => ['id'],         'rhs' => 'nim'],
            ['lhs' => ['id'],         'rhs' => 'name'],
            ['lhs' => ['id'],         'rhs' => 'major'],
            ['lhs' => ['id'],         'rhs' => 'advisor_id'],
            ['lhs' => ['advisor_id'], 'rhs' => 'advisor_name'],
            ['lhs' => ['advisor_id'], 'rhs' => 'advisor_room'],
        ]);

        $result = $this->analyzer->normalize('Student', $attrs, $fds, ['id']);

        $this->assertFalse($result->is3NF, 'TC-10: Seharusnya melanggar 3NF');
        $this->assertCount(2, $result->transitiveDependencies, 'TC-10: Harus ada 2 transitive dependency');

        $this->assertRelationExists($result->relations3NF, ['id', 'nim', 'name', 'major', 'advisor_id']);
        $this->assertRelationExists($result->relations3NF, ['advisor_id', 'advisor_name', 'advisor_room']);
    }

    /**
     * TC-11: Domain produk — transitive lewat supplier_id.
     * Relasi: Product(id, name, category, price, stock, supplier_id, supplier_name, supplier_contact)
     * PK: id
     * Transitive: id → supplier_id → supplier_name, supplier_contact
     */
    public function test_tc11_product_transitive_supplier_melanggar_3nf(): void
    {
        $attrs = ['id', 'name', 'category', 'price', 'stock', 'supplier_id', 'supplier_name', 'supplier_contact'];
        $fds   = $this->makeFds([
            ['lhs' => ['id'],          'rhs' => 'name'],
            ['lhs' => ['id'],          'rhs' => 'category'],
            ['lhs' => ['id'],          'rhs' => 'price'],
            ['lhs' => ['id'],          'rhs' => 'stock'],
            ['lhs' => ['id'],          'rhs' => 'supplier_id'],
            ['lhs' => ['supplier_id'], 'rhs' => 'supplier_name'],
            ['lhs' => ['supplier_id'], 'rhs' => 'supplier_contact'],
        ]);

        $result = $this->analyzer->normalize('Product', $attrs, $fds, ['id']);

        $this->assertFalse($result->is3NF, 'TC-11: Seharusnya melanggar 3NF');
        $this->assertCount(2, $result->transitiveDependencies, 'TC-11: Harus ada 2 transitive dependency');

        $this->assertTransitiveDepExists($result->transitiveDependencies, ['supplier_id'], 'supplier_name');
        $this->assertTransitiveDepExists($result->transitiveDependencies, ['supplier_id'], 'supplier_contact');

        $this->assertRelationExists($result->relations3NF, ['id', 'name', 'category', 'price', 'stock', 'supplier_id']);
        $this->assertRelationExists($result->relations3NF, ['supplier_id', 'supplier_name', 'supplier_contact']);
    }

    /**
     * TC-12: Dua rantai transitive sekaligus.
     * Relasi: Invoice(id, date, customer_id, customer_name, sales_id, sales_name, amount)
     * PK: id
     * Transitive: id → customer_id → customer_name
     *             id → sales_id    → sales_name
     */
    public function test_tc12_dua_rantai_transitive_sekaligus(): void
    {
        $attrs = ['id', 'date', 'customer_id', 'customer_name', 'sales_id', 'sales_name', 'amount'];
        $fds   = $this->makeFds([
            ['lhs' => ['id'],          'rhs' => 'date'],
            ['lhs' => ['id'],          'rhs' => 'customer_id'],
            ['lhs' => ['id'],          'rhs' => 'sales_id'],
            ['lhs' => ['id'],          'rhs' => 'amount'],
            ['lhs' => ['customer_id'], 'rhs' => 'customer_name'],
            ['lhs' => ['sales_id'],    'rhs' => 'sales_name'],
        ]);

        $result = $this->analyzer->normalize('Invoice', $attrs, $fds, ['id']);

        $this->assertFalse($result->is3NF, 'TC-12: Seharusnya melanggar 3NF');
        $this->assertCount(2, $result->transitiveDependencies, 'TC-12: Harus ada 2 transitive dependency');

        // 3 relasi: Invoice utama, Customer, Sales
        $this->assertGreaterThanOrEqual(3, count($result->relations3NF), 'TC-12: Harus ada minimal 3 relasi');
        $this->assertRelationExists($result->relations3NF, ['customer_id', 'customer_name']);
        $this->assertRelationExists($result->relations3NF, ['sales_id', 'sales_name']);
    }

    /**
     * TC-13: Transitive 3 tingkat: id → dept_id → manager_id → manager_name.
     * Relasi: Staff(id, name, dept_id, dept_name, manager_id, manager_name)
     * PK: id
     */
    public function test_tc13_transitive_bertingkat(): void
    {
        $attrs = ['id', 'name', 'dept_id', 'dept_name', 'manager_id', 'manager_name'];
        $fds   = $this->makeFds([
            ['lhs' => ['id'],         'rhs' => 'name'],
            ['lhs' => ['id'],         'rhs' => 'dept_id'],
            ['lhs' => ['dept_id'],    'rhs' => 'dept_name'],
            ['lhs' => ['dept_id'],    'rhs' => 'manager_id'],
            ['lhs' => ['manager_id'], 'rhs' => 'manager_name'],
        ]);

        $result = $this->analyzer->normalize('Staff', $attrs, $fds, ['id']);

        $this->assertFalse($result->is3NF, 'TC-13: Seharusnya melanggar 3NF');
        $this->assertNotEmpty($result->transitiveDependencies, 'TC-13: Harus ada transitive dependency');
        $this->assertTransitiveDepExists($result->transitiveDependencies, ['dept_id'], 'dept_name');
    }

    /**
     * TC-14: Proposal dengan data evaluasi dicampur (dari skema denormalisasi).
     * Relasi: Proposal(id, title, division_id, division_name, created_by, created_by_name, budget)
     * PK: id
     * Transitive: id → division_id → division_name
     *             id → created_by  → created_by_name
     */
    public function test_tc14_proposal_denormalisasi_melanggar_3nf(): void
    {
        $attrs = ['id', 'title', 'division_id', 'division_name', 'created_by', 'created_by_name', 'budget'];
        $fds   = $this->makeFds([
            ['lhs' => ['id'],          'rhs' => 'title'],
            ['lhs' => ['id'],          'rhs' => 'division_id'],
            ['lhs' => ['id'],          'rhs' => 'created_by'],
            ['lhs' => ['id'],          'rhs' => 'budget'],
            ['lhs' => ['division_id'], 'rhs' => 'division_name'],
            ['lhs' => ['created_by'],  'rhs' => 'created_by_name'],
        ]);

        $result = $this->analyzer->normalize('Proposal', $attrs, $fds, ['id']);

        $this->assertFalse($result->is3NF, 'TC-14: Seharusnya melanggar 3NF');
        $this->assertCount(2, $result->transitiveDependencies, 'TC-14: Harus ada 2 transitive dependency');

        $this->assertTransitiveDepExists($result->transitiveDependencies, ['division_id'], 'division_name');
        $this->assertTransitiveDepExists($result->transitiveDependencies, ['created_by'],  'created_by_name');
    }

    /**
     * TC-15: Notification dengan sender_name dan recipient_name.
     * Relasi: Notification(id, type, title, recipient_id, recipient_name, sender_id, sender_name)
     * PK: id
     */
    public function test_tc15_notification_transitive_user_melanggar_3nf(): void
    {
        $attrs = ['id', 'type', 'title', 'recipient_id', 'recipient_name', 'sender_id', 'sender_name'];
        $fds   = $this->makeFds([
            ['lhs' => ['id'],           'rhs' => 'type'],
            ['lhs' => ['id'],           'rhs' => 'title'],
            ['lhs' => ['id'],           'rhs' => 'recipient_id'],
            ['lhs' => ['id'],           'rhs' => 'sender_id'],
            ['lhs' => ['recipient_id'], 'rhs' => 'recipient_name'],
            ['lhs' => ['sender_id'],    'rhs' => 'sender_name'],
        ]);

        $result = $this->analyzer->normalize('Notification', $attrs, $fds, ['id']);

        $this->assertFalse($result->is3NF, 'TC-15: Seharusnya melanggar 3NF');
        $this->assertCount(2, $result->transitiveDependencies, 'TC-15: Harus ada 2 transitive dependency');

        $this->assertRelationExists($result->relations3NF, ['recipient_id', 'recipient_name']);
        $this->assertRelationExists($result->relations3NF, ['sender_id', 'sender_name']);
    }

    /**
     * TC-16: FD redundan + transitive sekaligus.
     * Relasi: Booking(id, room_id, room_name, hotel_id, hotel_name, hotel_city, check_in, check_out)
     * PK: id
     * Redundan: id → hotel_name (bisa diturunkan dari id → room_id → hotel_id → hotel_name)
     * Transitive: room_id → hotel_id, hotel_id → hotel_name, hotel_id → hotel_city
     */
    public function test_tc16_fd_redundan_dan_transitive_sekaligus(): void
    {
        $attrs = ['id', 'room_id', 'room_name', 'hotel_id', 'hotel_name', 'hotel_city', 'check_in', 'check_out'];
        $fds   = $this->makeFds([
            ['lhs' => ['id'],       'rhs' => 'room_id'],
            ['lhs' => ['id'],       'rhs' => 'check_in'],
            ['lhs' => ['id'],       'rhs' => 'check_out'],
            ['lhs' => ['room_id'],  'rhs' => 'room_name'],
            ['lhs' => ['room_id'],  'rhs' => 'hotel_id'],
            ['lhs' => ['hotel_id'], 'rhs' => 'hotel_name'],
            ['lhs' => ['hotel_id'], 'rhs' => 'hotel_city'],
            ['lhs' => ['id'],       'rhs' => 'hotel_name'], // ← redundan
        ]);

        $result = $this->analyzer->normalize('Booking', $attrs, $fds, ['id']);

        // A3 harus menghapus FD redundan id → hotel_name
        $minimalCoverStrings = array_map(fn($fd) => (string)$fd, $result->minimalCover);
        $this->assertNotContains(
            'id → hotel_name',
            $minimalCoverStrings,
            'TC-16: FD redundan id → hotel_name harus dihapus oleh A3'
        );

        $this->assertFalse($result->is3NF, 'TC-16: Seharusnya melanggar 3NF');
        $this->assertNotEmpty($result->transitiveDependencies, 'TC-16: Harus ada transitive dependency');
    }

    // ================================================================
    // EDGE CASE — TC 17–20
    // ================================================================

    /**
     * TC-17: Relasi yang melanggar 2NF sekaligus — pastikan 3NF juga dideteksi
     * setelah dekomposisi 2NF.
     * Relasi: CourseEnrollment(student_id, course_id, student_name,
     *                          course_name, dept_id, dept_name, grade)
     * PK: (student_id, course_id)
     * Partial: student_id → student_name, course_id → course_name, course_id → dept_id
     * Transitive (setelah 2NF): course_id → dept_id → dept_name
     */
    public function test_tc17_melanggar_2nf_dan_3nf_sekaligus(): void
    {
        $attrs = ['student_id', 'course_id', 'student_name', 'course_name', 'dept_id', 'dept_name', 'grade'];
        $fds   = $this->makeFds([
            ['lhs' => ['student_id'],              'rhs' => 'student_name'],
            ['lhs' => ['course_id'],               'rhs' => 'course_name'],
            ['lhs' => ['course_id'],               'rhs' => 'dept_id'],
            ['lhs' => ['dept_id'],                 'rhs' => 'dept_name'],
            ['lhs' => ['student_id', 'course_id'], 'rhs' => 'grade'],
        ]);

        $result = $this->analyzer->normalize('CourseEnrollment', $attrs, $fds, ['student_id', 'course_id']);

        $this->assertFalse($result->is2NF, 'TC-17: Seharusnya melanggar 2NF');
        $this->assertFalse($result->is3NF, 'TC-17: Seharusnya melanggar 3NF');
        $this->assertNotEmpty($result->partialDependencies,   'TC-17: Harus ada partial dependency');
        $this->assertNotEmpty($result->transitiveDependencies, 'TC-17: Harus ada transitive dependency');
    }

    /**
     * TC-18: Transitive dependency yang LHS-nya adalah key attribute (bukan transitive).
     * Relasi: UserRole(user_id, role_id, role_name, assigned_at)
     * PK: (user_id, role_id)
     * role_id → role_name: role_id adalah key attribute → BUKAN transitive
     * Seharusnya: partial dependency, bukan transitive
     */
    public function test_tc18_key_attribute_bukan_transitive(): void
    {
        $attrs = ['user_id', 'role_id', 'role_name', 'assigned_at'];
        $fds   = $this->makeFds([
            ['lhs' => ['role_id'],              'rhs' => 'role_name'],
            ['lhs' => ['user_id', 'role_id'],   'rhs' => 'assigned_at'],
        ]);

        $result = $this->analyzer->normalize('UserRole', $attrs, $fds, ['user_id', 'role_id']);

        // role_id → role_name adalah partial dependency (bukan transitive)
        // karena role_id adalah bagian dari candidate key
        $this->assertFalse($result->is2NF, 'TC-18: Seharusnya melanggar 2NF (partial dep)');

        // Setelah 2NF, relasi yang berisi role_id → role_name tidak transitive
        // karena role_id adalah PK dari relasi tersebut
        $transitiveDepsStr = array_map(fn($fd) => (string)$fd, $result->transitiveDependencies);
        $this->assertNotContains(
            'role_id → role_name',
            $transitiveDepsStr,
            'TC-18: role_id → role_name adalah partial dep bukan transitive'
        );
    }

    /**
     * TC-19: Tidak ada FD sama sekali — otomatis lolos 3NF.
     */
    public function test_tc19_tanpa_fd_lolos_3nf(): void
    {
        $attrs = ['id', 'name', 'value'];
        $fds   = [];

        $result = $this->analyzer->normalize('EmptyFD', $attrs, $fds, ['id']);

        $this->assertTrue($result->is3NF, 'TC-19: Tanpa FD seharusnya lolos 3NF');
        $this->assertEmpty($result->transitiveDependencies, 'TC-19: Tidak ada transitive dependency');
    }

    /**
     * TC-20: Minimal cover menghapus FD redundan sebelum cek 3NF.
     * FD redundan: emp_id → dept_name (diturunkan dari emp_id → dept_id → dept_name)
     * Setelah A3, emp_id → dept_name hilang.
     * Transitive yang tersisa: dept_id → dept_name
     */
    public function test_tc20_minimal_cover_sebelum_cek_3nf(): void
    {
        $attrs = ['emp_id', 'emp_name', 'dept_id', 'dept_name', 'salary'];
        $fds   = $this->makeFds([
            ['lhs' => ['emp_id'],  'rhs' => 'emp_name'],
            ['lhs' => ['emp_id'],  'rhs' => 'dept_id'],
            ['lhs' => ['emp_id'],  'rhs' => 'salary'],
            ['lhs' => ['dept_id'], 'rhs' => 'dept_name'],
            ['lhs' => ['emp_id'],  'rhs' => 'dept_name'], // ← redundan
        ]);

        $result = $this->analyzer->normalize('EmpDept', $attrs, $fds, ['emp_id']);

        // A3 harus hapus emp_id → dept_name
        $minimalCoverStrings = array_map(fn($fd) => (string)$fd, $result->minimalCover);
        $this->assertNotContains(
            'emp_id → dept_name',
            $minimalCoverStrings,
            'TC-20: emp_id → dept_name harus dihapus oleh A3 (minimal cover)'
        );

        // Setelah A3, dept_id → dept_name adalah transitive
        $this->assertFalse($result->is3NF, 'TC-20: Seharusnya melanggar 3NF');
        $this->assertTransitiveDepExists($result->transitiveDependencies, ['dept_id'], 'dept_name');

        // Hasil dekomposisi: EmpDept(emp_id, emp_name, dept_id, salary) + Dept(dept_id, dept_name)
        $this->assertRelationExists($result->relations3NF, ['dept_id', 'dept_name']);
    }
}
