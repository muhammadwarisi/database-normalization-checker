<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('project_id')->unique();
            $table->string('name');
            $table->longText('dbml_text');
            $table->json('analysis_result')->nullable();
            $table->integer('tables_count')->default(0);
            $table->integer('issues_count')->default(0);
            $table->integer('passed_tables')->default(0);
            $table->enum('status', ['pending', 'analyzed', 'error'])->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
