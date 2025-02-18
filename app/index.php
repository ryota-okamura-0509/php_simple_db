<?php

define("PAGE_SIZE", 4096);
define("USERNAME_SIZE", 32);
define("EMAIL_SIZE", 255);
define("ID_SIZE", 4);
define("ROW_SIZE", USERNAME_SIZE + EMAIL_SIZE + ID_SIZE);
define("ROWS_PER_PAGE", PAGE_SIZE / ROW_SIZE);
define("TABLE_MAX_PAGES", 100);
define("TABLE_MAX_ROWS", ROWS_PER_PAGE * TABLE_MAX_PAGES);

class InputBuffer {
    public string $buffer;

    public function __construct() {
        $this->buffer = "";
    }
}

class Row {
    public int $id;
    public string $username;
    public string $email;

    public function __construct(int $id, string $username, string $email) {
        $this->id = $id;
        $this->username = $username;
        $this->email = $email;
    }
}

class Table {
    public int $num_rows = 0;
    public array $pages = [];
}

class Statement {
    public StatementType | null $type;
    public Row | null $row;
}   

enum MetaCommandResult {
    case META_COMMAND_SUCCESS;
    case META_COMMAND_UNRECOGNIZED_COMMAND;
}

enum PrepareResult {
    case PREPARE_SUCCESS;
    case PREPARE_UNRECOGNIZED_STATEMENT;
    case PREPARE_SYSTEM_ERROR;
    case PREPARE_EXECUTE_TABLE_FULL;
    case PREPARE_EXECUTE_SUCCESS;
}

enum StatementType {
    case INSERT;
    case SELECT;
}

function print_prompt() {
    echo "db > ";
}

function read_input(InputBuffer $inputBuffer) {
    $input = trim(fgets(STDIN));
    if ($input === false) {
        echo "Error reading input\n";
        exit(1);
    }
    
    // 改行を削除して格納
    $inputBuffer->buffer = trim($input);
}

function do_meta_command($inputBuffer): MetaCommandResult {
    if ($inputBuffer->buffer === ".exit") {
        echo "Goodbye!\n";
        exit(1);
    }
    return MetaCommandResult::META_COMMAND_UNRECOGNIZED_COMMAND;
}

function prepare_statement(InputBuffer $inputBuffer, Statement $statement): PrepareResult {
    if (str_starts_with($inputBuffer->buffer, "insert")) {
        $statement->type = StatementType::INSERT;
        $pattern = '/^insert (\d+) (\S+) (\S+)$/';
        if (preg_match($pattern, $inputBuffer->buffer, $matches)) {
            $statement->row = new Row((int)$matches[1], $matches[2], $matches[3]);
            echo "Parsed successfully: ";
            print_r($statement);
        } else {
            return PrepareResult::PREPARE_SYSTEM_ERROR;
        }
        return PrepareResult::PREPARE_SUCCESS;
    }
    if (str_starts_with($inputBuffer->buffer, "select")) {
        $statement->type = StatementType::SELECT;
        return PrepareResult::PREPARE_SUCCESS;
    }
    return PrepareResult::PREPARE_UNRECOGNIZED_STATEMENT;
}

function execute_statement(Statement $statement, Table $table) {
    switch ($statement->type) {
        case StatementType::INSERT:
            return execute_insert($statement, $table);
            break;
        case StatementType::SELECT:
            return execute_select($table);
            break;
        default:
            break;
    }
}

function serialize_row(Row $source) {
    // pack を使い、バイナリデータに変換
    return pack(
        "LA" . USERNAME_SIZE . "A" . EMAIL_SIZE,
        $source->id,
        str_pad($source->username, USERNAME_SIZE, "\0"),
        str_pad($source->email, EMAIL_SIZE, "\0")
    );
}

function deserialize_row(string $source): Row {
    $data = unpack("Lid/A" . USERNAME_SIZE . "username/A" . EMAIL_SIZE . "email", $source);
    return new Row($data['id'], rtrim($data['username'], "\0"), rtrim($data['email'], "\0"));
}

function row_slot(Table $table, int $row_num): int {
    $page_num = $row_num / ROWS_PER_PAGE;
    $page = $table->pages[$page_num];
    if ($page === null) {
        // ページが存在しない場合は新規作成
        $page = $table->pages[$page_num] = str_repeat("\0", PAGE_SIZE);
    }
    $row_offset = $row_num % ROWS_PER_PAGE;
    $byte_offset = $row_offset * ROW_SIZE;
    return count($page) + $byte_offset;
}

function execute_insert(Statement $statement, Table $table): PrepareResult {
    if ($table->num_rows >= TABLE_MAX_ROWS) {
        echo "Table full.\n";
        return PrepareResult::PREPARE_EXECUTE_TABLE_FULL;
    }

    $serialized_row = serialize_row($statement->row);
    $table->pages[intdiv($table->num_rows, ROWS_PER_PAGE)][$table->num_rows % ROWS_PER_PAGE] = $serialized_row;
    $table->num_rows += 1;
    return PrepareResult::PREPARE_EXECUTE_SUCCESS;
}

function execute_select(Table $table) {
    for ($i = 0; $i < $table->num_rows; $i++) {
        $row = deserialize_row(row_slot($table, $i));
        echo "{$row->id} {$row->username} {$row->email}\n";
    }
    PrepareResult::PREPARE_EXECUTE_SUCCESS;
}

$inputBuffer = new InputBuffer();
$table = new Table();
while (true) {
    print_prompt();
    $input = read_input($inputBuffer);
    // メタコマンドの処理
    if($inputBuffer->buffer[0] === ".") {
        switch (do_meta_command($inputBuffer)) {
            case MetaCommandResult::META_COMMAND_SUCCESS:
                break;
            case MetaCommandResult::META_COMMAND_UNRECOGNIZED_COMMAND:
                echo printf("Unrecognized keyword at start of '%s'.\n", $inputBuffer->buffer);
                break;
            default:
                break;
        }
    }

    $statement = new Statement();
    switch(prepare_statement($inputBuffer, $statement)) {
        case PrepareResult::PREPARE_SUCCESS:
            break;
        case PrepareResult::PREPARE_UNRECOGNIZED_STATEMENT:
            echo "Unrecognized keyword at start of '{$inputBuffer->buffer}'.\n";
            break;
        case PrepareResult::PREPARE_SYSTEM_ERROR:
            echo "System error.\n";
            break;
        default:
            break;
    }


    execute_statement($statement,$table);
    echo "Executed.\n";
}