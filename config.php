<?php

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$host     = "localhost";
$user     = "root";
$password = "";
$database = "sun_son_solar_db";

try {
    $conn = new mysqli($host, $user, $password, $database);
} catch (mysqli_sql_exception $e) {
    die("Connection failed: " . $e->getMessage());
}

$conn->set_charset("utf8mb4");