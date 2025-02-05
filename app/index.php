<?php

function print_prompt() {
    echo "db > ";
}

function read_input() {
    $input = trim(fgets(STDIN));
    if ($input === false) {
        echo "Error reading input\n";
        exit(1);
    }
    return $input;
}

while (true) {
    print_prompt();
    $input = read_input();

    if ($input === ".exit") {
        exit(0);
    } else {
        echo "Unrecognized command '$input'.\n";
    }
}