<?php
session_start();
$host = "localhost";
$user = "root";
$pass = "";
$db = "image_site";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
