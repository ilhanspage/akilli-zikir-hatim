<?php
require_once __DIR__ . '/../system/auth.php';
$_SESSION = [];
session_destroy();
redirect('/admin/login.php');
