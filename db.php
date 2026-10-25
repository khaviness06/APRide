<?php
$servername = "127.0.0.1";
$port = 3307;
$username = "root";
$password = "";
$dbname = "apride";

$conn = new mysqli($servername, $username, $password, $dbname, $port);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Without this, characters like "→" and "CO₂" are corrupted on the way into the database.
$conn->set_charset("utf8mb4");
