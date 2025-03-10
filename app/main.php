<?php

require_once "Pager.php";

class SimpleDB {
    private Pager $pager;

    public function __construct(string $filename) {
        $this->pager = new Pager($filename);
    }

    public function execute(string $input): void {
        $input = trim($input);

        if ($input === "exit") {
            echo "Bye!\n";
            exit(0);
        } elseif (str_starts_with($input, "save ")) {
            $parts = explode(" ", $input, 3);
            if (count($parts) < 3) {
                echo "Usage: save <page_num> <data>\n";
                return;
            }
            $pageNum = (int) $parts[1];
            $data = $parts[2];
            $this->pager->writePage($pageNum, $data);
            echo "Saved to page $pageNum\n";
        } elseif (str_starts_with($input, "load ")) {
            $parts = explode(" ", $input, 2);
            if (count($parts) < 2) {
                echo "Usage: load <page_num>\n";
                return;
            }
            $pageNum = (int) $parts[1];
            $data = $this->pager->readPage($pageNum);
            echo "Page $pageNum: " . trim($data) . "\n";
        } else {
            echo "Unknown command: $input\n";
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