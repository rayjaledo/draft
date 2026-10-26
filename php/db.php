<?php
$host = "localhost";
$user = "root";
$password = "";
$database = "shrcms";

$conn = new mysqli($host, $user, $password, $database);

if ($conn->connect_error) {
    die("Koneksyon sa database ay nabigo: " . $conn->connect_error);
}
?>