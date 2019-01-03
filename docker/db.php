<?php

function exception_error_handler($severity, $message, $file, $line) {
    throw new ErrorException($message, 1, $severity, $file, $line);
}
set_error_handler("exception_error_handler");

error_reporting(E_ALL);
ini_set('display_errors', 1);

$databasePrefix = $argv[1];
$database = $databasePrefix . '000';

try {
    $mysqli = new mysqli("easyminer-mysql", "root", "root");
    $mysqli->query('DROP DATABASE IF EXISTS '.$database);
    $mysqli->query('GRANT ALL PRIVILEGES ON *.* TO "'.$database.'"@"%" IDENTIFIED BY "'.$database.'" WITH GRANT OPTION');
    $mysqli->query('CREATE DATABASE '.$database);
    $mysqli->query("GRANT ALL PRIVILEGES ON ".$database.".* TO '".$database."'@'%' IDENTIFIED BY '".$database."' WITH GRANT OPTION");
    $mysqli->select_db($database);

    $sql = file_get_contents('/var/www/html/easyminercenter/app/InstallModule/data/mysql.sql');
    $mysqli->multi_query($sql);

    $mysqli->close();
} catch(Exception $e) {
    echo 'Message: ' . $e->getMessage() . "\n";
    echo 'File: ' . $e->getFile() . "\n";
    echo 'Line: ' . $e->getLine() . "\n";
    exit($e->getCode());
}
