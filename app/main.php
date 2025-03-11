<?php

require_once "Pager.php";
require_once "TableManager.php";
require_once "Database.php";


echo "SimpleDB REPL (type 'exit' to quit)\n";
$db = new Database('data.db');
while (true) {
    echo "> ";
    $input = trim(fgets(STDIN));
    $db->execute($input);
}