<?php

class IndexManager {
    public function updateIndex(string $table, string $key, int $page, int $offset): void {
        $indexFile = $table. ".idx";
        if(file_exists(($indexFile))) {
            $index = json_decode(file_get_contents($indexFile), true);
        } else {
            $index = [];
        }
        $index[$key] = [$page, $offset];

        file_put_contents($indexFile, json_encode($index));
    }
}