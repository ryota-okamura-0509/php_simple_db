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

class Table {
    private array $pages = [];
    private int $numRows = 0;
    private const PAGE_SIZE = 4096;
    private const ROW_SIZE = 291;
    private const ROWS_PER_PAGE = self::PAGE_SIZE / self::ROW_SIZE;
    private const TABLE_MAX_PAGES = 100;
    private const TABLE_MAX_ROWS = self::ROWS_PER_PAGE * self::TABLE_MAX_PAGES;

    public function insert(Row $row): void {
        $newRows = $this->numRows + 1;
        if ($newRows  >= self::TABLE_MAX_ROWS) {
            throw new Exception('Table is full');
        }
        [$pageIndex, $rowIndex] = $this->rowSlot();
        if (!isset($this->pages[$pageIndex])) {
            $this->pages[$pageIndex] = str_repeat("\0", self::PAGE_SIZE); // 4KBの空データを確保;
        }

        $serializedRow = $this->serializeRow($row);
        $offset = $rowIndex  * self::ROW_SIZE;
        $this->pages[$pageIndex] = substr_replace($this->pages[$pageIndex], $serializedRow, $offset, self::ROW_SIZE);
        $this->numRows++;
        echo "Inserted row: $row\n";
    }

    public function select(): string {
        $output = '';
        foreach ($this->pages as $page) {
            for ($i = 0; $i < self::ROWS_PER_PAGE; $i++) {
                $offset = $i * self::ROW_SIZE;
                $data = substr($page, $offset, self::ROW_SIZE);
                if (trim($data) !== "") {
                    $output .= $this->deserializeRow($data) . "\n";
                }
            }
        }
        return $output . "実行完了。\n";
    }

    private function rowSlot(): array {
        $pageIndex = intdiv($this->numRows, (int)self::ROWS_PER_PAGE);
        $rowIndex = $this->numRows % (int)self::ROWS_PER_PAGE;
        return [$pageIndex, $rowIndex];
    }

    private function serializeRow(Row $row): string {
        return pack("LA32A255", $row->id, $row->username, $row->email);
    }

    private function deserializeRow(string $data): string {
        $unpacked = unpack("Lid/A32username/A255email", $data);
        return "({$unpacked['id']}, " . trim($unpacked['username']) . ", " . trim($unpacked['email']) . ")";
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
        $this->table = new Table();
        $this->inputBuffer = new InputBuffer();
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

    private function metaCommandHandler(string $command): MetaCommandResult {
        return match ($command) {
            '.exit' => self::exitCommand(),
            default => MetaCommandResult::META_COMMAND_UNRECOGNIZED_COMMAND,
        };
    }

    private function exitCommand(): void{
        echo "データベースを終了します。\n";
        exit(0);
    }
}

$db = new Database();
$db->run();
