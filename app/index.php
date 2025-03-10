<?php

class InputBuffer {
    public string $buffer = "";

    public function readInput(): void {
        $input = trim(fgets(STDIN));
        if ($input === false) {
            echo "Error reading input\n";
            exit(1);
        }
        $this->buffer = $input;
    }
}

enum PrepareResult {
    case PREPARE_SUCCESS;
    case PREPARE_UNRECOGNIZED_STATEMENT;
}

enum StatementType {
    case INSERT;
    case SELECT;
}
class Statement {
    private StatementType|null $type = null;

    public function prepare(string $input): PrepareResult {
        if (str_starts_with($input, "insert")) {
            $this->type = StatementType::INSERT;
            return PrepareResult::PREPARE_SUCCESS;
        }
        if (str_starts_with($input, "select")) {
            $this->type = StatementType::SELECT;
            return PrepareResult::PREPARE_SUCCESS;
        }
        return PrepareResult::PREPARE_UNRECOGNIZED_STATEMENT;
    }

    public function execute(): void {
        match ($this->type) {
            StatementType::INSERT => print("This is where we would do an insert.\n"),
            StatementType::SELECT => print("This is where we would do a select.\n"),
            default => print("Unknown statement type.\n"),
        };
    }
}

class Row {
    public $id;
    public string $username;
    public string $email;

    public function __construct($id, $username, $email) {
        $this->validate($id, $username, $email);
        $this->id = $id;
        $this->username = $username;
        $this->email = $email;
    }

    public function __toString(): string {
        return $this->id . ' ' . $this->username . ' ' . $this->email;
    }

    private function validate(int $id, string $username, string $email): void {
        if ($id < 0) {
            throw new Exception('ID must be positive');
        }
        if (strlen($username) > 32) {
            throw new Exception('Username must be 32 characters or less');
        }
        if (strlen($email) > 255) {
            throw new Exception('Email must be 255 characters or less');
        }
    }
}

class Cursor {
    private Table $table;
    private int $rowNumber;
    private bool $endOfTable;

    public function __construct(Table $table, int $rowNumber) {
        $this->table = $table;
        $this->rowNumber = $rowNumber;
        $this->endOfTable = $rowNumber >= $table->numRows;
    }

    public function advance(): void {
        $this->rowNumber++;
        if ($this->rowNumber >= $this->table->numRows) {
            $this->endOfTable = true;
        }
    }

    public function getValue(): ?Row {
        if ($this->endOfTable) {
            return null;
        }
        [$pageIndex, $rowIndex] = $this->table->rowSlot($this->rowNumber);
        $page = $this->table->getPage(0);
        $offset = $rowIndex * Table::ROW_SIZE;
        $data = substr($page, $offset, Table::ROW_SIZE);
        return $this->table->deserializeRow($data);
    }

    public function setValue(Row $row): void {
        [$pageIndex, $rowIndex] = $this->table->rowSlot($this->rowNumber);
        if (!isset($this->table->pager->pages[$pageIndex])) {
            $this->table->pager->pages[$pageIndex] = str_repeat("\0", Table::PAGE_SIZE);
        }
        $serializedRow = $this->table->serializeRow($row);
        $offset = $rowIndex * Table::ROW_SIZE;
        $this->table->pager->pages[$pageIndex] = substr_replace(
            $this->table->pager->pages[$pageIndex],
            $serializedRow,
            $offset,
            Table::ROW_SIZE
        );
    }

    public function isEnd(): bool {
        return $this->endOfTable;
    }
}

class Pager {
    public $fileDescriptor;
    public $fileLength;
    public array $pages;

    private function __construct(
        $fileDescriptor,
        $fileLength,
        array $pages
    ) {
        $this->fileDescriptor = $fileDescriptor;
        $this->fileLength = $fileLength;
        $this->pages = $pages;
    }

    public static function open(string $filename): Pager {
        $fileDescriptor = fopen($filename, 'c+b');
        if ($fileDescriptor === false) {
            throw new Exception("Unable to open file");
        }
        $pages =[];
        for ($i = 0; $i < Table::TABLE_MAX_PAGES; $i++) {
            $pages[$i] = null;
        }
        $pages[0] = fread($fileDescriptor, Table::PAGE_SIZE);
        // ファイルの長さを取得
        fseek($fileDescriptor, 0, SEEK_END);
        $fileLength = ftell($fileDescriptor);
        echo "Opened database successfully.\n";
        echo "fileLength: $fileLength\n";
        return new Pager($fileDescriptor, $fileLength, $pages);
    }

    public function flushPage(int $pageNumber, int $size): void {
        if (!isset($this->pages[$pageNumber])) {
            return;
        }
        // ファイルの適切な位置へシーク
        if (fseek($this->fileDescriptor, $pageNumber * Table::PAGE_SIZE) === -1) {
            throw new Exception("Error seeking in file");
        }
        // データを書き込む
        $bytesWritten = fwrite($this->fileDescriptor, substr($this->pages[$pageNumber], $pageNumber * Table::PAGE_SIZE, $size));

        if ($bytesWritten === false) {
            throw new Exception("Error writing to file");
        }
    }

    public function close() {
        fclose($this->fileHandle);
    }
}

class Table {
    public Pager $pager;
    public int $numRows = 0;
    public const PAGE_SIZE = 4096;
    public const ROW_SIZE = 291;
    public const ROWS_PER_PAGE = self::PAGE_SIZE / self::ROW_SIZE;
    public const TABLE_MAX_PAGES = 100;
    public const TABLE_MAX_ROWS = self::ROWS_PER_PAGE * self::TABLE_MAX_PAGES;

    public function __construct(Pager $pager) {
        $this->pager = $pager;
        var_dump($pager);
        $this->numRows = $pager->fileLength / (int)self::ROW_SIZE;
        echo "numRows: $this->numRows\n";
    }

    public function insert(Row $row): void {
        // テーブルの最大値を超えているのかを確認するため先に行数を確認
        $newRows = $this->numRows + 1;
        if ($newRows  >= self::TABLE_MAX_ROWS) {
            throw new Exception('Table is full');
        }
        $cursor = new Cursor($this, $this->numRows);
        $cursor->setValue($row);
        $this->numRows++;
        echo "Inserted row: $row\n";
    }

    public function select(): string {
        $output = '';
        $cursor = new Cursor($this, 0);
        while (!$cursor->isEnd()) {
            $row = $cursor->getValue();
            if ($row !== null) {
                $output .= $row . "\n";
            }
            $cursor->advance();
        }
        return $output . "実行完了。\n";
    }

    public function rowSlot(): array {
        $pageIndex = intdiv($this->numRows, (int)self::ROWS_PER_PAGE);
        $rowIndex = $this->numRows % (int)self::ROWS_PER_PAGE;
        return [$pageIndex, $rowIndex];
    }

    private function cursorValue(Cursor $cursor): int {
        $pageIndex = intdiv($cursor->rowNum, (int)self::ROWS_PER_PAGE);
        $rowIndex = $cursor->rowNum % (int)self::ROWS_PER_PAGE;
        $page = $this->pager->getPage($pageIndex);
        $offset = $rowIndex * (int)self::ROW_SIZE;
        return $offset;
    }

    public function serializeRow(Row $row): string {
        return serialize($row);
    }

    public function deserializeRow(string $data): Row {
        return unserialize($data);
    }

    public function getPage(int $pageNumber): string {
        if($pageNumber > self::TABLE_MAX_PAGES) {
            throw new Exception("ページ番号がページの最大値を超えてます。");
        }

        if(!isset($this->pager->pages[$pageNumber])) {
            $numPages = $this->pager->fileLength / self::PAGE_SIZE;
            if($this->pager->fileLength % self::PAGE_SIZE) {
                $numPages++;
            }
            if($pageNumber <= $numPages) {
                fseek($this->pager->fileDescriptor, $numPages * self::PAGE_SIZE);
                $this->pager->pages[$numPages] = fread($this->pager->fileDescriptor, self::PAGE_SIZE);
            }
        }
        return $this->pager->pages[$pageNumber];
    }
}

enum MetaCommandResult {
    case META_COMMAND_SUCCESS;
    case META_COMMAND_UNRECOGNIZED_COMMAND;
}
class Database {
    private Table $table;
    private InputBuffer $inputBuffer;

    public function __construct(){
        $this->inputBuffer = new InputBuffer();
        $this->open("db.db");
    }
    
    public function run() {
        while (true) {
            try {
                echo "db > ";
                $this->inputBuffer->readInput();
                $input = $this->inputBuffer->buffer;

                if ($input[0] === ".") {
                    $result = $this->metaCommandHandler($input);
                    if ($result === MetaCommandResult::META_COMMAND_UNRECOGNIZED_COMMAND) {
                        printf("Unrecognized keyword at start of '%s'.\n", $input);
                    }
                    continue;
                }

                $parts = explode(" ", $input);
                $command = strtolower($parts[0]);

                if ($command === "insert") {
                    if (count($parts) !== 4 || !is_numeric($parts[1])) {
                        echo "構文エラー: 正しい形式は 'insert [id] [username] [email]' です。\n";
                        continue;
                    }

                    $id = (int)$parts[1];
                    $username = $parts[2];
                    $email = $parts[3];

                    $row = new Row($id, $username, $email);
                    echo $this->table->insert($row);
                } elseif ($command === "select") {
                    echo $this->table->select();
                } else {
                    echo "不明なコマンド: {$input}\n";
                }
            } catch (Exception $e) {
                echo $e->getMessage() . "\n";
            }
            
        }
    }

    private function open(string $filename){
        $pager = Pager::open($filename);
        $this->table = new Table($pager);
    }

    public function close() {
        echo "データベースをクローズします。\n";
        echo $this->table->numRows;
        $numGullPages = intdiv($this->table->numRows, Table::ROWS_PER_PAGE);
        echo "numGullPages: $numGullPages\n";
        // フルページ書き込む
        for ($i = 0; $i < $numGullPages; $i++) {
            if (!isset($this->table->pager->pages[$i])) {
                continue;
            }
            $this->table->pager->flushPage($i, Table::PAGE_SIZE);
            fwrite($this->table->pager->fileDescriptor, $this->table->pager->pages[$i]);
            unset($this->table->pager->pages[$i]);
        }

        // 部分的なページを書き込む（Bツリー導入後は不要）
        $numAdditionalRows = $this->table->numRows % Table::ROWS_PER_PAGE;
        echo "numAdditionalRows: $numAdditionalRows\n";
        if($numAdditionalRows > 0){
            $pageIndex = $numGullPages;
            if(isset($this->table->pager->pages[$pageIndex])){
                $this->table->pager->flushPage($pageIndex, $numAdditionalRows * Table::ROW_SIZE);
                unset($this->table->pager->pages[$pageIndex]);
            }
        }
    }

    private function metaCommandHandler(string $command): MetaCommandResult {
        return match ($command) {
            '.exit' => self::exitCommand(),
            default => MetaCommandResult::META_COMMAND_UNRECOGNIZED_COMMAND,
        };
    }

    private function exitCommand(): MetaCommandResult {
        $this->close();
        echo "データベースを終了します。\n";
        exit(0);
        return MetaCommandResult::META_COMMAND_SUCCESS;
    }
}

$db = new Database();
$db->run();
