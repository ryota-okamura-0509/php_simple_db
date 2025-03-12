<?php

class Pager {
    private string $dbFile;

    public function __construct(string $dbFile) {
        $this->dbFile = $dbFile;

        // ファイルが存在しない場合は作成
        if (!file_exists($dbFile)) {
            file_put_contents($this->dbFile, str_repeat("\0", 4096)); // 最初のページを確保
        }
    }

    public function readPage(int $pageNumber): string {
        $file = fopen($this->dbFile, 'r');
        fseek($file, $pageNumber * 4096);
        $data = fread($file, 4096);
        fclose($file);
        return $data;
    }

    public function writePage(int $pageNumber, string $data): void {
        $file = fopen($this->dbFile, 'r+');
        fseek($file, $pageNumber * 4096);
        fwrite($file, str_pad($data, 4096, "\0")); // 4KB に揃える
        fclose($file);
    }

    public function allocateNewPage(): int {
        $size = filesize($this->dbFile);
        $newPageNumber = $size / 4096;
        file_put_contents($this->dbFile, str_repeat("\0", 4096), FILE_APPEND); // 新しいページを確保
        return $newPageNumber;
    }
}