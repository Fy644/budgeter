<?php
    require_once 'db.php';
    if (!isset($_SESSION['user_id'])){
        header("Location: login.php");
        exit();
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Insert new item</title>
    <link rel="stylesheet" href="https://unpkg.com/7.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="home-page">
        <ul role="listbox" class="has-hover">
            <li role="option" class="active">
                <a href="index.php">Home</a>
            </li>
            <li role="option">
                <a href="insert.php">Insert New Item</a>
            </li>
        </ul>
    </div>
</body>
</html>