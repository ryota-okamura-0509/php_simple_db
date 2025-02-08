<?php

class InputBuffer {
    public $buffer;

    public function __construct() {
        $this->buffer = "";
    }
}

enum MetaCommandResult {
    case META_COMMAND_SUCCESS;
    case META_COMMAND_UNRECOGNIZED_COMMAND;
}

enum PrepareResult {
    case PREPARE_SUCCESS;
    case PREPARE_UNRECOGNIZED_STATEMENT;
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
        exit(MetaCommandResult::META_COMMAND_SUCCESS);
    }
    return MetaCommandResult::META_COMMAND_UNRECOGNIZED_COMMAND;
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
}