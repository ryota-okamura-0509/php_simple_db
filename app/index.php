<?php

class InputBuffer {
    public $buffer;

    public function __construct() {
        $this->buffer = "";
    }
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

$input_buffer = new InputBuffer();
while (true) {
    print_prompt();
    $input = read_input($input_buffer);

    
}