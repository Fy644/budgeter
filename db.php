<?php
session_start();
$servername = "localhost";
$username = "root";
$password = "";
$database = "kirkiversary_compras";
$conn = new mysqli($servername, $username, $password, $database);
if ($conn->connect_error) {
    die("Error: " . $conn->connect_error);
}
?>