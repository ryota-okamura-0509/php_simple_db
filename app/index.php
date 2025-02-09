<?php

class InputBuffer {
    public string $buffer;

    public function __construct() {
        $this->buffer = "";
    }
}

class Statement {
    public StatementType | null $type;
}   

enum MetaCommandResult {
    case META_COMMAND_SUCCESS;
    case META_COMMAND_UNRECOGNIZED_COMMAND;
}

enum PrepareResult {
    case PREPARE_SUCCESS;
    case PREPARE_UNRECOGNIZED_STATEMENT;
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
        return PrepareResult::PREPARE_SUCCESS;
    }
    if (str_starts_with($inputBuffer->buffer, "select")) {
        $statement->type = StatementType::SELECT;
        return PrepareResult::PREPARE_SUCCESS;
    }
    return PrepareResult::PREPARE_UNRECOGNIZED_STATEMENT;
}

function execute_statement(Statement $statement) {
    switch ($statement->type) {
        case StatementType::INSERT:
            echo "This is where we would do an insert.\n";
            break;
        case StatementType::SELECT:
            echo "This is where we would do a select.\n";
            break;
        default:
            break;
    }
}

$inputBuffer = new InputBuffer();
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
    if(prepare_statement($inputBuffer, $statement) === PrepareResult::PREPARE_UNRECOGNIZED_STATEMENT) {
        echo "Unrecognized keyword at start of '{$inputBuffer->buffer}'.\n";
        continue;
    }

    execute_statement($statement);
    echo "Executed.\n";
}