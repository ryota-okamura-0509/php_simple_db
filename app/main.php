<?php

require_once "Pager.php";
require_once "TableManager.php";

class SimpleDB {
    private Pager $pager;

    public function __construct(string $filename) {
        $this->pager = new Pager($filename);
        $this->tableManager = new TableManager();
    }

    public function execute(string $input): void {
        $input = trim($input);

        if ($input === "exit") {
            echo "Bye!\n";
            exit(0);
        } elseif (str_starts_with($input, "CREATE TABLE")) {
            $this->handleCreateTable($input);
        } elseif ($input === "SHOW TABLES") {
            $this->handleShowTables();
        } else {
            echo "Unknown command: $input\n";
        }
    }

    private function handleCreateTable(string $input): void {
        // 例: CREATE TABLE users (id INT, name TEXT);
        if (!preg_match('/CREATE TABLE (\w+) \((.+)\);?/', $input, $matches)) {
            echo "Invalid CREATE TABLE syntax.\n";
            return;
        }

        $tableName = $matches[1];
        $columns = explode(",", trim($matches[2]));

        try {
            $this->tableManager->createTable($tableName, $columns);
            echo "Table '$tableName' created.\n";
        } catch (Exception $e) {
            echo "Error: " . $e->getMessage() . "\n";
        }
    }

    private function handleShowTables(): void {
        $tables = $this->tableManager->getTables();
        if (empty($tables)) {
            echo "No tables found.\n";
        } else {
            foreach ($tables as $name => $info) {
                echo "$name (" . implode(", ", $info["columns"]) . ")\n";
            }
        }
    }
}

// 実行部分
$db = new SimpleDB("data.db");

echo "SimpleDB REPL (type 'exit' to quit)\n";
while (true) {
    echo "> ";
    $input = trim(fgets(STDIN));
    $db->execute($input);
}