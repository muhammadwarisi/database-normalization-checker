<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\ClosureCalculator;
use App\Services\ExtraneousAttributeRemover;
use App\Services\MinimalCoverCalculator;
use App\Services\CandidateKeyFinder;
use App\Services\DependencyClassifier;
use App\Services\SecondNFDecomposer;
use App\Services\NormalizationAnalyzer;
use App\Services\FunctionalDependency;

class NormalizationTest2NF extends TestCase
{
    private NormalizationAnalyzer $analyzer;

    protected function setUp(): void
    {
        parent::setUp();

        $closure  = new ClosureCalculator();
        $this->analyzer = new NormalizationAnalyzer(
            new ExtraneousAttributeRemover($closure),
            new MinimalCoverCalculator($closure),
            new CandidateKeyFinder($closure),
            new DependencyClassifier($closure),
            new SecondNFDecomposer($closure),
        );
    }

    // ----------------------------------------------------------------
    // Helper
    // ----------------------------------------------------------------

    /** Buat FunctionalDependency dari array ['lhs' => [...], 'rhs' => '...'] */
    private function makeFds(array $rawFds): array
    {
        $fds = [];
        foreach ($rawFds as $raw) {
            $lhs = (array) $raw['lhs'];
            // Support multi-RHS dengan koma
            foreach (array_map('trim', explode(',', $raw['rhs'])) as $rhs) {
                $fds[] = new FunctionalDependency($lhs, $rhs);
            }
        }
        return $fds;
    }

    /** Normalisasi array atribut untuk perbandingan */
    private function sortAttrs(array $attrs): array
    {
        sort($attrs);
        return array_values(array_unique($attrs));
    }

    /** Cek apakah relasi dengan atribut tertentu ada di hasil dekomposisi */
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

    // ================================================================
    // LOLOS 2NF — TC 01–07
    // ================================================================

    /**
     * TC-01: PK tunggal, semua non-PK bergantung penuh.
     * Relasi: Student(student_id, name, email, birth_date)
     * PK: student_id
     */
    public function test_tc01_pk_tunggal_lolos_2nf(): void
    {
        $attrs = ['student_id', 'name', 'email', 'birth_date'];
        $fds   = $this->makeFds([
            ['lhs' => ['student_id'], 'rhs' => 'name'],
            ['lhs' => ['student_id'], 'rhs' => 'email'],
            ['lhs' => ['student_id'], 'rhs' => 'birth_date'],
        ]);

        $result = $this->analyzer->normalize('Student', $attrs, $fds, ['student_id']);

        $this->assertTrue($result->is2NF, 'TC-01: Seharusnya lolos 2NF');
        $this->assertEmpty($result->partialDependencies, 'TC-01: Tidak boleh ada partial dependency');
        $this->assertCount(1, $result->relations2NF, 'TC-01: Hanya satu relasi (tidak didekomposisi)');
    }

    /**
     * TC-02: PK tunggal, domain produk.
     * Relasi: Product(product_id, product_name, price, stock)
     * PK: product_id
     */
    public function test_tc02_product_pk_tunggal_lolos_2nf(): void
    {
        $attrs = ['product_id', 'product_name', 'price', 'stock'];
        $fds   = $this->makeFds([
            ['lhs' => ['product_id'], 'rhs' => 'product_name'],
            ['lhs' => ['product_id'], 'rhs' => 'price'],
            ['lhs' => ['product_id'], 'rhs' => 'stock'],
        ]);

        $result = $this->analyzer->normalize('Product', $attrs, $fds, ['product_id']);

        $this->assertTrue($result->is2NF, 'TC-02: Seharusnya lolos 2NF');
        $this->assertEmpty($result->partialDependencies, 'TC-02: Tidak boleh ada partial dependency');
    }

    /**
     * TC-03: PK tunggal, domain order.
     * Relasi: OrderHeader(order_id, customer_id, order_date, total_amount, status)
     * PK: order_id
     */
    public function test_tc03_order_header_pk_tunggal_lolos_2nf(): void
    {
        $attrs = ['order_id', 'customer_id', 'order_date', 'total_amount', 'status'];
        $fds   = $this->makeFds([
            ['lhs' => ['order_id'], 'rhs' => 'customer_id'],
            ['lhs' => ['order_id'], 'rhs' => 'order_date'],
            ['lhs' => ['order_id'], 'rhs' => 'total_amount'],
            ['lhs' => ['order_id'], 'rhs' => 'status'],
        ]);

        $result = $this->analyzer->normalize('OrderHeader', $attrs, $fds, ['order_id']);

        $this->assertTrue($result->is2NF, 'TC-03: Seharusnya lolos 2NF');
        $this->assertEmpty($result->partialDependencies, 'TC-03: Tidak boleh ada partial dependency');
    }

    /**
     * TC-04: PK tunggal, domain booking.
     * Relasi: RoomBooking(booking_id, room_id, guest_id, check_in, check_out)
     * PK: booking_id
     */
    public function test_tc04_room_booking_pk_tunggal_lolos_2nf(): void
    {
        $attrs = ['booking_id', 'room_id', 'guest_id', 'check_in', 'check_out'];
        $fds   = $this->makeFds([
            ['lhs' => ['booking_id'], 'rhs' => 'room_id'],
            ['lhs' => ['booking_id'], 'rhs' => 'guest_id'],
            ['lhs' => ['booking_id'], 'rhs' => 'check_in'],
            ['lhs' => ['booking_id'], 'rhs' => 'check_out'],
        ]);

        $result = $this->analyzer->normalize('RoomBooking', $attrs, $fds, ['booking_id']);

        $this->assertTrue($result->is2NF, 'TC-04: Seharusnya lolos 2NF');
        $this->assertEmpty($result->partialDependencies, 'TC-04: Tidak boleh ada partial dependency');
    }

    /**
     * TC-05: PK composite, semua non-PK full dependency.
     * Relasi: ExamResult(student_id, subject_id, score, grade)
     * PK: (student_id, subject_id)
     * score dan grade hanya bisa ditentukan jika keduanya diketahui.
     */
    public function test_tc05_exam_result_composite_pk_lolos_2nf(): void
    {
        $attrs = ['student_id', 'subject_id', 'score', 'grade'];
        $fds   = $this->makeFds([
            ['lhs' => ['student_id', 'subject_id'], 'rhs' => 'score'],
            ['lhs' => ['student_id', 'subject_id'], 'rhs' => 'grade'],
        ]);

        $result = $this->analyzer->normalize('ExamResult', $attrs, $fds, ['student_id', 'subject_id']);

        $this->assertTrue($result->is2NF, 'TC-05: Seharusnya lolos 2NF');
        $this->assertEmpty($result->partialDependencies, 'TC-05: Tidak boleh ada partial dependency');
        $this->assertCount(1, $result->relations2NF, 'TC-05: Tidak didekomposisi');
    }

    /**
     * TC-06: PK composite, domain penerbangan.
     * Relasi: FlightSeat(flight_id, seat_no, passenger_id, booked_at)
     * PK: (flight_id, seat_no)
     */
    public function test_tc06_flight_seat_composite_pk_lolos_2nf(): void
    {
        $attrs = ['flight_id', 'seat_no', 'passenger_id', 'booked_at'];
        $fds   = $this->makeFds([
            ['lhs' => ['flight_id', 'seat_no'], 'rhs' => 'passenger_id'],
            ['lhs' => ['flight_id', 'seat_no'], 'rhs' => 'booked_at'],
        ]);

        $result = $this->analyzer->normalize('FlightSeat', $attrs, $fds, ['flight_id', 'seat_no']);

        $this->assertTrue($result->is2NF, 'TC-06: Seharusnya lolos 2NF');
        $this->assertEmpty($result->partialDependencies, 'TC-06: Tidak boleh ada partial dependency');
    }

    /**
     * TC-07: PK composite, domain gudang.
     * Relasi: WarehouseSlot(warehouse_id, slot_id, item_id, quantity)
     * PK: (warehouse_id, slot_id)
     */
    public function test_tc07_warehouse_slot_composite_pk_lolos_2nf(): void
    {
        $attrs = ['warehouse_id', 'slot_id', 'item_id', 'quantity'];
        $fds   = $this->makeFds([
            ['lhs' => ['warehouse_id', 'slot_id'], 'rhs' => 'item_id'],
            ['lhs' => ['warehouse_id', 'slot_id'], 'rhs' => 'quantity'],
        ]);

        $result = $this->analyzer->normalize('WarehouseSlot', $attrs, $fds, ['warehouse_id', 'slot_id']);

        $this->assertTrue($result->is2NF, 'TC-07: Seharusnya lolos 2NF');
        $this->assertEmpty($result->partialDependencies, 'TC-07: Tidak boleh ada partial dependency');
    }

    // ================================================================
    // MELANGGAR 2NF — TC 08–16
    // ================================================================

    /**
     * TC-08: Contoh klasik dari paper Demba.
     * Relasi: CourseEnrollment(student_id, course_id, student_name, course_name, enrollment_date, grade)
     * PK: (student_id, course_id)
     * Fp: student_id → student_name, course_id → course_name
     */
    public function test_tc08_course_enrollment_melanggar_2nf(): void
    {
        $attrs = ['student_id', 'course_id', 'student_name', 'course_name', 'enrollment_date', 'grade'];
        $fds   = $this->makeFds([
            ['lhs' => ['student_id'],              'rhs' => 'student_name'],
            ['lhs' => ['course_id'],               'rhs' => 'course_name'],
            ['lhs' => ['student_id', 'course_id'], 'rhs' => 'enrollment_date'],
            ['lhs' => ['student_id', 'course_id'], 'rhs' => 'grade'],
        ]);

        $result = $this->analyzer->normalize('CourseEnrollment', $attrs, $fds, ['student_id', 'course_id']);

        $this->assertFalse($result->is2NF, 'TC-08: Seharusnya melanggar 2NF');
        $this->assertCount(2, $result->partialDependencies, 'TC-08: Harus ada 2 partial dependency');

        $partialLhs = array_map(fn($fd) => $fd->lhs, $result->partialDependencies);
        $this->assertContains(['student_id'], $partialLhs, 'TC-08: student_id harus jadi partial dep');
        $this->assertContains(['course_id'],  $partialLhs, 'TC-08: course_id harus jadi partial dep');

        // Harus menghasilkan 3 relasi hasil dekomposisi
        $this->assertCount(3, $result->relations2NF, 'TC-08: Harus ada 3 relasi hasil dekomposisi');

        $this->assertRelationExists($result->relations2NF, ['student_id', 'student_name']);
        $this->assertRelationExists($result->relations2NF, ['course_id', 'course_name']);
        $this->assertRelationExists($result->relations2NF, ['student_id', 'course_id', 'enrollment_date', 'grade']);
    }

    /**
     * TC-09: Domain order item.
     * Relasi: OrderItem(order_id, product_id, product_name, order_date, quantity, unit_price)
     * PK: (order_id, product_id)
     * Fp: product_id → product_name, order_id → order_date
     */
    public function test_tc09_order_item_melanggar_2nf(): void
    {
        $attrs = ['order_id', 'product_id', 'product_name', 'order_date', 'quantity', 'unit_price'];
        $fds   = $this->makeFds([
            ['lhs' => ['product_id'],              'rhs' => 'product_name'],
            ['lhs' => ['order_id'],                'rhs' => 'order_date'],
            ['lhs' => ['order_id', 'product_id'],  'rhs' => 'quantity'],
            ['lhs' => ['order_id', 'product_id'],  'rhs' => 'unit_price'],
        ]);

        $result = $this->analyzer->normalize('OrderItem', $attrs, $fds, ['order_id', 'product_id']);

        $this->assertFalse($result->is2NF, 'TC-09: Seharusnya melanggar 2NF');
        $this->assertCount(2, $result->partialDependencies, 'TC-09: Harus ada 2 partial dependency');
        $this->assertCount(3, $result->relations2NF, 'TC-09: Harus ada 3 relasi hasil dekomposisi');

        $this->assertRelationExists($result->relations2NF, ['product_id', 'product_name']);
        $this->assertRelationExists($result->relations2NF, ['order_id', 'order_date']);
        $this->assertRelationExists($result->relations2NF, ['order_id', 'product_id', 'quantity', 'unit_price']);
    }

    /**
     * TC-10: Domain karyawan-skill.
     * Relasi: EmployeeSkill(employee_id, skill_id, employee_dept, skill_category, proficiency)
     * PK: (employee_id, skill_id)
     * Fp: employee_id → employee_dept, skill_id → skill_category
     */
    public function test_tc10_employee_skill_melanggar_2nf(): void
    {
        $attrs = ['employee_id', 'skill_id', 'employee_dept', 'skill_category', 'proficiency'];
        $fds   = $this->makeFds([
            ['lhs' => ['employee_id'],             'rhs' => 'employee_dept'],
            ['lhs' => ['skill_id'],                'rhs' => 'skill_category'],
            ['lhs' => ['employee_id', 'skill_id'], 'rhs' => 'proficiency'],
        ]);

        $result = $this->analyzer->normalize('EmployeeSkill', $attrs, $fds, ['employee_id', 'skill_id']);

        $this->assertFalse($result->is2NF, 'TC-10: Seharusnya melanggar 2NF');
        $this->assertCount(2, $result->partialDependencies, 'TC-10: Harus ada 2 partial dependency');
        $this->assertCount(3, $result->relations2NF, 'TC-10: Harus ada 3 relasi hasil dekomposisi');

        $this->assertRelationExists($result->relations2NF, ['employee_id', 'employee_dept']);
        $this->assertRelationExists($result->relations2NF, ['skill_id', 'skill_category']);
        $this->assertRelationExists($result->relations2NF, ['employee_id', 'skill_id', 'proficiency']);
    }

    /**
     * TC-11: Domain project-task.
     * Relasi: ProjectTask(project_id, task_id, project_budget, task_description, assigned_to, estimated_hours)
     * PK: (project_id, task_id)
     * Fp: project_id → project_budget, task_id → task_description
     */
    public function test_tc11_project_task_melanggar_2nf(): void
    {
        $attrs = ['project_id', 'task_id', 'project_budget', 'task_description', 'assigned_to', 'estimated_hours'];
        $fds   = $this->makeFds([
            ['lhs' => ['project_id'],             'rhs' => 'project_budget'],
            ['lhs' => ['task_id'],                'rhs' => 'task_description'],
            ['lhs' => ['project_id', 'task_id'],  'rhs' => 'assigned_to'],
            ['lhs' => ['project_id', 'task_id'],  'rhs' => 'estimated_hours'],
        ]);

        $result = $this->analyzer->normalize('ProjectTask', $attrs, $fds, ['project_id', 'task_id']);

        $this->assertFalse($result->is2NF, 'TC-11: Seharusnya melanggar 2NF');
        $this->assertCount(2, $result->partialDependencies, 'TC-11: Harus ada 2 partial dependency');
        $this->assertCount(3, $result->relations2NF, 'TC-11: Harus ada 3 relasi hasil dekomposisi');

        $this->assertRelationExists($result->relations2NF, ['project_id', 'project_budget']);
        $this->assertRelationExists($result->relations2NF, ['task_id', 'task_description']);
        $this->assertRelationExists($result->relations2NF, ['project_id', 'task_id', 'assigned_to', 'estimated_hours']);
    }

    /**
     * TC-12: Domain sales territory.
     * Relasi: SalesTerritory(salesperson_id, territory_id, salesperson_region, territory_manager, quota_amount)
     * PK: (salesperson_id, territory_id)
     * Fp: salesperson_id → salesperson_region, territory_id → territory_manager
     */
    public function test_tc12_sales_territory_melanggar_2nf(): void
    {
        $attrs = ['salesperson_id', 'territory_id', 'salesperson_region', 'territory_manager', 'quota_amount'];
        $fds   = $this->makeFds([
            ['lhs' => ['salesperson_id'],                  'rhs' => 'salesperson_region'],
            ['lhs' => ['territory_id'],                    'rhs' => 'territory_manager'],
            ['lhs' => ['salesperson_id', 'territory_id'],  'rhs' => 'quota_amount'],
        ]);

        $result = $this->analyzer->normalize('SalesTerritory', $attrs, $fds, ['salesperson_id', 'territory_id']);

        $this->assertFalse($result->is2NF, 'TC-12: Seharusnya melanggar 2NF');
        $this->assertCount(2, $result->partialDependencies, 'TC-12: Harus ada 2 partial dependency');
        $this->assertCount(3, $result->relations2NF, 'TC-12: Harus ada 3 relasi hasil dekomposisi');
    }

    /**
     * TC-13: 3 partial dependency sekaligus.
     * Relasi: ClassSchedule(class_id, teacher_id, class_name, teacher_name, room_id, schedule_time)
     * PK: (class_id, teacher_id)
     * Fp: class_id → class_name, class_id → room_id, teacher_id → teacher_name
     */
    public function test_tc13_class_schedule_tiga_partial_deps(): void
    {
        $attrs = ['class_id', 'teacher_id', 'class_name', 'teacher_name', 'room_id', 'schedule_time'];
        $fds   = $this->makeFds([
            ['lhs' => ['class_id'],               'rhs' => 'class_name'],
            ['lhs' => ['class_id'],               'rhs' => 'room_id'],
            ['lhs' => ['teacher_id'],             'rhs' => 'teacher_name'],
            ['lhs' => ['class_id', 'teacher_id'], 'rhs' => 'schedule_time'],
        ]);

        $result = $this->analyzer->normalize('ClassSchedule', $attrs, $fds, ['class_id', 'teacher_id']);

        $this->assertFalse($result->is2NF, 'TC-13: Seharusnya melanggar 2NF');
        $this->assertCount(3, $result->partialDependencies, 'TC-13: Harus ada 3 partial dependency');

        // Relasi untuk class_id harus mengandung class_name DAN room_id
        $this->assertRelationExists($result->relations2NF, ['class_id', 'class_name', 'room_id']);
        $this->assertRelationExists($result->relations2NF, ['teacher_id', 'teacher_name']);
        $this->assertRelationExists($result->relations2NF, ['class_id', 'teacher_id', 'schedule_time']);
    }

    /**
     * TC-14: Domain supplier-product.
     * Relasi: SupplierProduct(supplier_id, product_id, supplier_name, product_category, lead_time, unit_cost)
     * PK: (supplier_id, product_id)
     * Fp: supplier_id → supplier_name, product_id → product_category
     */
    public function test_tc14_supplier_product_melanggar_2nf(): void
    {
        $attrs = ['supplier_id', 'product_id', 'supplier_name', 'product_category', 'lead_time', 'unit_cost'];
        $fds   = $this->makeFds([
            ['lhs' => ['supplier_id'],              'rhs' => 'supplier_name'],
            ['lhs' => ['product_id'],               'rhs' => 'product_category'],
            ['lhs' => ['supplier_id', 'product_id'],'rhs' => 'lead_time'],
            ['lhs' => ['supplier_id', 'product_id'],'rhs' => 'unit_cost'],
        ]);

        $result = $this->analyzer->normalize('SupplierProduct', $attrs, $fds, ['supplier_id', 'product_id']);

        $this->assertFalse($result->is2NF, 'TC-14: Seharusnya melanggar 2NF');
        $this->assertCount(2, $result->partialDependencies, 'TC-14: Harus ada 2 partial dependency');
        $this->assertCount(3, $result->relations2NF, 'TC-14: Harus ada 3 relasi hasil dekomposisi');

        $this->assertRelationExists($result->relations2NF, ['supplier_id', 'supplier_name']);
        $this->assertRelationExists($result->relations2NF, ['product_id', 'product_category']);
    }

    /**
     * TC-15: Domain perpustakaan.
     * Relasi: LibraryLoan(member_id, book_id, member_name, book_title, loan_date, due_date)
     * PK: (member_id, book_id)
     * Fp: member_id → member_name, book_id → book_title
     */
    public function test_tc15_library_loan_melanggar_2nf(): void
    {
        $attrs = ['member_id', 'book_id', 'member_name', 'book_title', 'loan_date', 'due_date'];
        $fds   = $this->makeFds([
            ['lhs' => ['member_id'],           'rhs' => 'member_name'],
            ['lhs' => ['book_id'],             'rhs' => 'book_title'],
            ['lhs' => ['member_id', 'book_id'],'rhs' => 'loan_date'],
            ['lhs' => ['member_id', 'book_id'],'rhs' => 'due_date'],
        ]);

        $result = $this->analyzer->normalize('LibraryLoan', $attrs, $fds, ['member_id', 'book_id']);

        $this->assertFalse($result->is2NF, 'TC-15: Seharusnya melanggar 2NF');
        $this->assertCount(2, $result->partialDependencies, 'TC-15: Harus ada 2 partial dependency');
        $this->assertCount(3, $result->relations2NF, 'TC-15: Harus ada 3 relasi hasil dekomposisi');

        $this->assertRelationExists($result->relations2NF, ['member_id', 'member_name']);
        $this->assertRelationExists($result->relations2NF, ['book_id', 'book_title']);
        $this->assertRelationExists($result->relations2NF, ['member_id', 'book_id', 'loan_date', 'due_date']);
    }

    /**
     * TC-16: Domain dokter-pasien.
     * Relasi: DoctorPatient(doctor_id, patient_id, doctor_specialty, patient_dob, visit_date, diagnosis)
     * PK: (doctor_id, patient_id)
     * Fp: doctor_id → doctor_specialty, patient_id → patient_dob
     */
    public function test_tc16_doctor_patient_melanggar_2nf(): void
    {
        $attrs = ['doctor_id', 'patient_id', 'doctor_specialty', 'patient_dob', 'visit_date', 'diagnosis'];
        $fds   = $this->makeFds([
            ['lhs' => ['doctor_id'],               'rhs' => 'doctor_specialty'],
            ['lhs' => ['patient_id'],              'rhs' => 'patient_dob'],
            ['lhs' => ['doctor_id', 'patient_id'], 'rhs' => 'visit_date'],
            ['lhs' => ['doctor_id', 'patient_id'], 'rhs' => 'diagnosis'],
        ]);

        $result = $this->analyzer->normalize('DoctorPatient', $attrs, $fds, ['doctor_id', 'patient_id']);

        $this->assertFalse($result->is2NF, 'TC-16: Seharusnya melanggar 2NF');
        $this->assertCount(2, $result->partialDependencies, 'TC-16: Harus ada 2 partial dependency');
        $this->assertCount(3, $result->relations2NF, 'TC-16: Harus ada 3 relasi hasil dekomposisi');

        $this->assertRelationExists($result->relations2NF, ['doctor_id', 'doctor_specialty']);
        $this->assertRelationExists($result->relations2NF, ['patient_id', 'patient_dob']);
        $this->assertRelationExists($result->relations2NF, ['doctor_id', 'patient_id', 'visit_date', 'diagnosis']);
    }

    // ================================================================
    // EDGE CASE — TC 17–20
    // ================================================================

    /**
     * TC-17: Relasi hanya memiliki satu kolom (PK saja).
     * Tidak ada non-key attribute → tidak ada FD → otomatis 2NF.
     */
    public function test_tc17_relasi_satu_kolom_lolos_2nf(): void
    {
        $attrs = ['id'];
        $fds   = [];

        $result = $this->analyzer->normalize('SingleColumn', $attrs, $fds, ['id']);

        $this->assertTrue($result->is2NF, 'TC-17: Relasi satu kolom seharusnya lolos 2NF');
        $this->assertEmpty($result->partialDependencies, 'TC-17: Tidak ada partial dependency');
    }

    /**
     * TC-18: Semua kolom adalah PK.
     * Tidak ada non-key attribute → tidak ada partial dependency.
     */
    public function test_tc18_semua_kolom_pk_lolos_2nf(): void
    {
        $attrs = ['student_id', 'course_id', 'term_id'];
        $fds   = [];

        $result = $this->analyzer->normalize('AllPrimaryKey', $attrs, $fds, ['student_id', 'course_id', 'term_id']);

        $this->assertTrue($result->is2NF, 'TC-18: Semua kolom PK seharusnya lolos 2NF');
        $this->assertEmpty($result->partialDependencies, 'TC-18: Tidak ada partial dependency');
    }

    /**
     * TC-19: PK triple composite dengan partial dep di berbagai level subset.
     * Relasi: MultiKey(a_id, b_id, c_id, a_name, b_name, c_name, ab_value, abc_value)
     * PK: (a_id, b_id, c_id)
     * Fp: a_id → a_name, b_id → b_name, c_id → c_name, a_id,b_id → ab_value
     */
    public function test_tc19_pk_triple_composite_multiple_partial_deps(): void
    {
        $attrs = ['a_id', 'b_id', 'c_id', 'a_name', 'b_name', 'c_name', 'ab_value', 'abc_value'];
        $fds   = $this->makeFds([
            ['lhs' => ['a_id'],           'rhs' => 'a_name'],
            ['lhs' => ['b_id'],           'rhs' => 'b_name'],
            ['lhs' => ['c_id'],           'rhs' => 'c_name'],
            ['lhs' => ['a_id', 'b_id'],   'rhs' => 'ab_value'],
            ['lhs' => ['a_id', 'b_id', 'c_id'], 'rhs' => 'abc_value'],
        ]);

        $result = $this->analyzer->normalize('MultiKey', $attrs, $fds, ['a_id', 'b_id', 'c_id']);

        $this->assertFalse($result->is2NF, 'TC-19: Seharusnya melanggar 2NF');
        $this->assertNotEmpty($result->partialDependencies, 'TC-19: Harus ada partial dependency');

        // Minimal ada 4 partial dep: a_id→a_name, b_id→b_name, c_id→c_name, a_id,b_id→ab_value
        $this->assertGreaterThanOrEqual(4, count($result->partialDependencies),
            'TC-19: Harus mendeteksi minimal 4 partial dependency');

        // abc_value harus masuk ke relasi utama (full dep pada seluruh PK)
        $mainRelation = null;
        foreach ($result->relations2NF as $rel) {
            if (in_array('abc_value', $rel['attributes'])) {
                $mainRelation = $rel;
                break;
            }
        }
        $this->assertNotNull($mainRelation, 'TC-19: Harus ada relasi yang mengandung abc_value');
    }

    /**
     * TC-20: FD redundan — menguji Algoritma A3 (minimal cover).
     * Relasi: EmpDept(emp_id, dept_id, emp_name, dept_name, salary)
     * PK: (emp_id, dept_id)
     * FD redundan: emp_id → dept_name (bisa diturunkan dari emp_id → dept_id → dept_name)
     * Setelah A3, emp_id → dept_name seharusnya dihapus dari Fm.
     */
    public function test_tc20_fd_redundan_dihapus_minimal_cover(): void
    {
        $attrs = ['emp_id', 'dept_id', 'emp_name', 'dept_name', 'salary'];
        $fds   = $this->makeFds([
            ['lhs' => ['emp_id'],            'rhs' => 'emp_name'],
            ['lhs' => ['emp_id'],            'rhs' => 'dept_id'],
            ['lhs' => ['dept_id'],           'rhs' => 'dept_name'],
            ['lhs' => ['emp_id', 'dept_id'], 'rhs' => 'salary'],
            ['lhs' => ['emp_id'],            'rhs' => 'dept_name'],  // ← redundan
        ]);

        $result = $this->analyzer->normalize('EmpDept', $attrs, $fds, ['emp_id', 'dept_id']);

        // Setelah minimal cover, emp_id → dept_name harus hilang
        $minimalCoverStrings = array_map(fn($fd) => (string) $fd, $result->minimalCover);
        $this->assertNotContains(
            'emp_id → dept_name',
            $minimalCoverStrings,
            'TC-20: FD redundan emp_id → dept_name seharusnya dihapus oleh Algoritma A3'
        );

        // Karena emp_id → dept_id ada di FD, emp_id sendiri sudah superkey
        // sehingga candidate key seharusnya { emp_id }
        $ckFlat = array_map(fn($ck) => implode(',', $ck), $result->candidateKeys);
        $this->assertContains('emp_id', $ckFlat,
            'TC-20: emp_id seharusnya menjadi candidate key karena emp_id → dept_id → dept_name');
    }
}