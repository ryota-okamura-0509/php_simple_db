<?php

class TableManager {
    private string $metaFile = 'tables.meta';

    public function __construct()
    {
        if(!file_exists($this->metaFile)) {
            // 
            file_put_contents($this->metaFile, '');
        }
    }

    public function createTable(string $name, array $columns): void {
        $existingTables = $this->getTables();

        // すでにテーブルが存在する場合はエラー
        if(isset($existingTables[$name])) {
            throw new Exception("Table $name already exists");
        }

        $columnsStr = implode(',', $columns);
        $tableMeta = "$name|$columnsStr|1"; // 一旦仮で1ページ目とする
        file_put_contents($this->metaFile, $tableMeta, FILE_APPEND);
    }

    public function getTables(): array {
        $tables = [];
        $lines = file($this->metaFile, FILE_IGNORE_NEW_LINES);
        foreach($lines as $line) {
            list($name, $columns, $firstPage) = explode('|', $line);
            $tables[$name] = [
                'columns' => explode(',', $columns),
                'firstPage' => (int) $firstPage,
            ];
        }
        return $tables;
    }
}