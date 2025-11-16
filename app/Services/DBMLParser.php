<?php

namespace App\Services;

class DBMLParser
{
    public function parse(string $dbmlText): array
    {
        $tables = [];
        
        // Remove comments
        $dbmlText = preg_replace('/\/\/.*$/m', '', $dbmlText);
        $dbmlText = preg_replace('/\/\*.*?\*\//s', '', $dbmlText);
        
        // Extract tables
        preg_match_all('/Table\s+(\w+)\s*\{([^}]+)\}/s', $dbmlText, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            $tableName = $match[1];
            $tableBody = $match[2];
            
            $table = [
                'name' => $tableName,
                'columns' => $this->parseColumns($tableBody),
            ];
            
            $tables[] = $table;
        }
        
        if (empty($tables)) {
            throw new \Exception("No tables found in DBML");
        }
        
        return $tables;
    }
    
    private function parseColumns(string $tableBody): array
    {
        $columns = [];
        $lines = explode("\n", $tableBody);
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            // Parse: column_name type [attributes]
            if (preg_match('/^(\w+)\s+(\w+(?:\([^)]+\))?(?:\[\])?)\s*(.*)$/', $line, $colMatch)) {
                $columnName = $colMatch[1];
                $columnType = $colMatch[2];
                $attributes = $colMatch[3] ?? '';
                
                $column = [
                    'name' => $columnName,
                    'type' => $columnType,
                    'pk' => $this->hasAttribute($attributes, 'pk'),
                    'unique' => $this->hasAttribute($attributes, 'unique'),
                    'not_null' => $this->hasAttribute($attributes, 'not null'),
                    'is_array' => str_contains($columnType, '[]'),
                ];
                
                $columns[] = $column;
            }
        }
        
        return $columns;
    }
    
    private function hasAttribute(string $attributes, string $attr): bool
    {
        return stripos($attributes, $attr) !== false;
    }
}