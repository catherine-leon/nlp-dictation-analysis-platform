<?php

$host = getenv("DB_HOST") ?: "localhost";
$user = getenv("DB_USER") ?: "dictation_app";
$password = getenv("DB_PASSWORD") ?: "";
$dbname = getenv("DB_NAME") ?: "dictation_app";

define("GOOGLE_VISION_KEY", getenv("GOOGLE_VISION_KEY") ?: "");

$conn = new mysqli($host, $user, $password, $dbname);

if ($conn->connect_error) {
    die("Erreur connexion : " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");
