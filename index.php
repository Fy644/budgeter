<?php
    require_once 'db.php';
    if (!isset($_SESSION['user_name'])){
        header("Location: login.php");
        exit();
    }
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Budgeter</title>
        <link rel="stylesheet" href="https://unpkg.com/7.css">
        <link rel="stylesheet" href="style.css">
    </head>
    <body>
        <div class="home-page">
            <ul role="listbox" class="has-hover">
                <li role="option" class="active">
                    <a href="index.php">Home</a>
                </li>
                <?php if ($_SESSION['user_perms'] === 0) 
                    echo '<li role="option"><a href="insert.php">Insert new product</a></li>';
                    echo '<li role="option"><a href="select-edit.php">Edit product</a></li>';
                ?>
                <li role="option">
                    <a href="view.php">View purchases</a>
                </li>
            </ul>
        </div>
        <div class="window active" style="width: 250px; max-width: calc(100vw - 2rem);">
            <div class="title-bar">
                <div class="title-bar-text">Budgeter</div>
                <div class="title-bar-controls">
                    <button type="button" aria-label="Close" onclick="window.location.href='logout.php'"></button>
                </div>
            </div>
            <div class="window-body has-space">
                <p>Welcome, <?= htmlspecialchars($_SESSION['user_name'], ENT_QUOTES, 'UTF-8') ?>!</p>
            </div>
        </div>
    </body>
</html>