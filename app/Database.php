<?php

require_once "Pager.php";
require_once "TableManager.php";
require_once "IndexManager.php";


class Database {
    private Pager $pager;
    private TableManager $tableManager;
    private IndexManager $indexManager;

    public function __construct(string $dbFile) {
        $this->pager = new Pager($dbFile);
        $this->tableManager = new TableManager($this->pager);
        $this->indexManager = new IndexManager();
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
        } elseif(str_starts_with($input, "INSERT INTO")){
            $this->handleInsert($input);
        }elseif(str_starts_with($input, "SELECT")){
            $this->handleSelect($input);
        } else {
            echo "Unknown command: $input\n";
        }
    }

    public function insert(string $table, array $values): void {
        $tables = $this->tableManager->getTables();
        if (!isset($tables[$table])) {
            throw new Exception("Table '$table' not found.");
        }

        $pages = $tables[$table]["pages"];
        $lastPage = end($pages);
        $pageData = $this->pager->readPage((int)$lastPage);
        $key = $values['id'];
        $offset = strlen(trim($pageData));

        if (strlen(trim($pageData)) + strlen(json_encode($values)) >= 4096) {
            $newPage = $this->pager->allocateNewPage();
            $tables[$table]["pages"][] = $newPage;
            $this->updateMetaFile($tables);
            $lastPage = $newPage;
        }
        $this->indexManager->updateIndex("users", $key, $lastPage, $offset);
        $newData = trim($pageData) . json_encode($values) . "|";
        $this->pager->writePage((int)$lastPage, $newData);
    }

    public function select(string $table): array {
        $tables = $this->tableManager->getTables();
        if (!isset($tables[$table])) {
            throw new Exception("Table '$table' not found.");
        }

        $result = [];
        foreach ($tables[$table]["pages"] as $page) {
            $pageData = $this->pager->readPage((int)$page);
            $lines = explode("|", trim($pageData));

            foreach ($lines as $line) {
                if (!empty($line)) {
                    $result[] = json_decode($line, true);
                }
            }
        }
        return $result;
    }

    public function searchWithIndex(string $table, string $key): array | null {
        $indexFile = $table . ".idx";
        if (!file_exists($indexFile)) {
            throw new Exception("Index file not found.");
        }

        // インデックスをロード
        $index = json_decode(file_get_contents($indexFile), true);
        if (!isset($index[$key])) {
            throw new Exception("Key not found.");
        }

        [$page, $offset] = $index[$key];
        $pageData = $this->pager->readPage($page);
        $lines = explode("|", trim($pageData));
        foreach ($lines as $line) {
            $record = json_decode($line, true);
            if ($record && isset($record["id"]) && $record["id"] == $key) {
                return $record;
            }
        }
        return null;
    }

    private function handleInsert(string $input): void {
        // 例: INSERT INTO users (id, name) VALUES (1, 'Alice');
        if (!preg_match('/INSERT INTO (\w+) \((.+)\) VALUES \((.+)\);?/', $input, $matches)) {
            echo "Invalid INSERT syntax.\n";
            return;
        }

        $table = $matches[1];
        $columns = explode(",", $matches[2]);
        $values = explode(",", $matches[3]);

        if (count($columns) !== count($values)) {
            echo "Column count does not match value count.\n";
            return;
        }

        $this->insert($table, array_combine($columns, $values));
        echo "Row inserted.\n";
    }

    private function handleSelect(string $input): void {
        // 例: SELECT * FROM users;
        if (!preg_match('/SELECT \* FROM (\w+);?/', $input, $matches)) {
            echo "Invalid SELECT syntax.\n";
            return;
        }

        $table = $matches[1];
        $data = $this->select($table);
        if (empty($data)) {
            echo "No rows found.\n";
        } else {
            foreach ($data as $row) {
                echo json_encode($row) . "\n";
            }
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

    private function updateMetaFile(array $tables): void {
        $content = '';
        foreach ($tables as $name => $info) {
            $content .= "$name|" . implode(",", $info["columns"]) . "|" . implode(",", $info["pages"]) . "\n";
        }
        file_put_contents("tables.meta", $content);
    }
}