<?php
use PHPUnit\Framework\TestCase;

class IndexTest extends TestCase {
    private function runScript(array $commands) {
        $descriptorspec = [
            0 => ["pipe", "r"],
            1 => ["pipe", "w"],
        ];
        
        $process = proc_open("php index.php", $descriptorspec, $pipes);
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
    
    public function test_データベースにデータが保存することができる() {
        $result = $this->runScript([
            "insert 1 user1 person1@example.com",
            ".exit",
        ]);
        
        $this->assertEquals([
            "db > Inserted row: 1 user1 person1@example.com",
            "db > データベースを終了します。",
        ], $result);
    }

    public function test_登録されたテーブルのデータを取得することができる() {
        $result = $this->runScript([
            "insert 1 user1 person1@example.com",
            "select",
            ".exit",
        ]);
        
        $this->assertEquals([
            "db > Inserted row: 1 user1 person1@example.com",
            "db > (1, user1, person1@example.com)",
            "実行完了。",
            "db > データベースを終了します。",
        ], $result);
    }
    
    public function test_テーブルの最大データ量を超える場合、登録できない() {
        $script = [];
        for ($i = 1; $i <= 1408; $i++) {
            $script[] = "insert $i user$i person$i@example.com";
        }
        $script[] = ".exit";
        
        $result = $this->runScript($script);
        $this->assertEquals("db > Table is full", $result[count($result) - 2]);
    }
    
    public function test_追加するデータがテーブルの規定違反の場合、登録することができない() {
        $longUsername = str_repeat("a", 32);
        
        $result = $this->runScript([
            "insert 1 $longUsername @example.com",
            "select",
            ".exit",
        ]);
        
        $this->assertEquals([
            "db > Inserted row: 1 $longUsername @example.com",
            "db > (1, $longUsername, @example.com)",
            "実行完了。",
            "db > データベースを終了します。",
        ], $result);
    }
}