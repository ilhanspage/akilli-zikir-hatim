<?php
$config = __DIR__ . '/system/config.php';
if (!file_exists($config)) {
    header('Location: install.php');
    exit;
}
readfile(__DIR__ . '/app/index.html');
