<?php

class Pager {
    private string $filename;
    private int $pageSize = 4096;
    private $file;

    public function __construct(string $filename) {
        $this->filename = $filename;
        $this->file = fopen($filename, 'c+b'); // 読み書き用に開く（なければ作成）
    }

    public function readPage(int $pageNum): string {
        fseek($this->file, $pageNum * $this->pageSize);
        return fread($this->file, $this->pageSize) ?: str_repeat("\0", $this->pageSize);
    }

    public function writePage(int $pageNum, string $data): void {
        if (strlen($data) > $this->pageSize) {
            throw new Exception("データがページサイズを超えています");
        }
        fseek($this->file, $pageNum * $this->pageSize);
        fwrite($this->file, str_pad($data, $this->pageSize, "\0"));
    }

    public function close(): void {
        fclose($this->file);
    }
}