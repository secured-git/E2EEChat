<?php
$files = glob(__DIR__ . '/*.json');

foreach ($files as $file) {
    if (file_exists($file)) {
        unlink($file);
    }
}
