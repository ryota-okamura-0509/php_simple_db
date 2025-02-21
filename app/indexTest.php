<?php
use PHPUnit\Framework\TestCase;

class DatabaseTest extends TestCase {
    private function runScript(array $commands) {
        $descriptorspec = [
            0 => ["pipe", "r"],
            1 => ["pipe", "w"],
        ];
        
        $process = proc_open("./db", $descriptorspec, $pipes);
        if (!is_resource($process)) {
            throw new Exception("Failed to open process");
        }
        
        foreach ($commands as $command) {
            fwrite($pipes[0], "$command\n");
        }
        fclose($pipes[0]);
        
        $output = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        proc_close($process);
        
        return explode("\n", trim($output));
    }
    
    public function testInsertAndRetrieveRow() {
        $result = $this->runScript([
            "insert 1 user1 person1@example.com",
            "select",
            ".exit",
        ]);
        
        $this->assertEquals([
            "db > Executed.",
            "db > (1, user1, person1@example.com)",
            "Executed.",
            "db > ",
        ], $result);
    }
    
    public function testTableFullError() {
        $script = [];
        for ($i = 1; $i <= 1401; $i++) {
            $script[] = "insert $i user$i person$i@example.com";
        }
        $script[] = ".exit";
        
        $result = $this->runScript($script);
        $this->assertEquals("db > Error: Table full.", $result[count($result) - 2]);
    }
    
    public function testMaxLengthStrings() {
        $longUsername = str_repeat("a", 32);
        $longEmail = str_repeat("a", 255);
        
        $result = $this->runScript([
            "insert 1 $longUsername $longEmail",
            "select",
            ".exit",
        ]);
        
        $this->assertEquals([
            "db > Executed.",
            "db > (1, $longUsername, $longEmail)",
            "Executed.",
            "db > ",
        ], $result);
    }
    
    public function testStringTooLongError() {
        $longUsername = str_repeat("a", 33);
        $longEmail = str_repeat("a", 256);
        
        $result = $this->runScript([
            "insert 1 $longUsername $longEmail",
            "select",
            ".exit",
        ]);
        
        $this->assertEquals([
            "db > String is too long.",
            "db > Executed.",
            "db > ",
        ], $result);
    }
    
    public function testNegativeIdError() {
        $result = $this->runScript([
            "insert -1 cstack foo@bar.com",
            "select",
            ".exit",
        ]);
        
        $this->assertEquals([
            "db > ID must be positive.",
            "db > Executed.",
            "db > ",
        ], $result);
    }
}
