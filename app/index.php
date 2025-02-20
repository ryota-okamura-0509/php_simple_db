<?php

class Row {
    public $id;
    public string $username;
    public string $email;

    public function __construct($id, $username, $email) {
        $this->id = $id;
        $this->username = $username;
        $this->email = $email;
    }

    public function __toString(): string {
        return $this->id . ' ' . $this->username . ' ' . $this->email;
    }
}

class Table {
    private array $pages = [];
    private int $num_rows = 0;
    private const PAGE_SIZE = 4096;
    private const ROW_SIZE = 291;
    private const ROWS_PER_PAGE = self::PAGE_SIZE / self::ROW_SIZE;
    private const TABLE_MAX_PAGES = 100;
    private const TABLE_MAX_ROWS = self::ROWS_PER_PAGE * self::TABLE_MAX_PAGES;

    public function insert(string $username, string $email): void {
        if ($this->num_rows >= self::TABLE_MAX_ROWS) {
            throw new Exception('Table is full');
        }

        $id = $this->num_rows;
        $row = new Row($id, $username, $email);
        $page_num = (int)($id / self::ROWS_PER_PAGE);
        $page = $this->pages[$page_num];
        if ($page === null) {
            $page = str_repeat("\0", self::PAGE_SIZE);
            $this->pages[$page_num] = $page;
        }

        $row_offset = $id % self::ROWS_PER_PAGE;
        $byte_offset = $row_offset * self::ROW_SIZE;
        $page = substr_replace($page, $row, $byte_offset, self::ROW_SIZE);
        $this->pages[$page_num] = $page;
        $this->num_rows++;
    }
}