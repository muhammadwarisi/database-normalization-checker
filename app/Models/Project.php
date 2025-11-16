<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'name',
        'dbml_text',
        'analysis_result',
        'tables_count',
        'issues_count',
        'passed_tables',
        'status',
    ];

    protected $casts = [
        'analysis_result' => 'array',
        'created_at' => 'datetime',
    ];

    public function getShortSummaryAttribute()
    {
        if ($this->issues_count === 0) {
            return "{$this->tables_count} tables, all normalized";
        }
        return "{$this->tables_count} tables, {$this->issues_count} issues found";
    }
}