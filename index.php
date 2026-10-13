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
                <li role="option">
                    <a href="insert.php">Insert New Item</a>
                </li>
            </ul>
        </div>
        <form method="POST">
            <div class="window active" style="width: 250px; max-width: calc(100vw - 2rem);">
                <div class="title-bar">
                    <div class="title-bar-text">Budgeter</div>
                </div>
                <div class="window-body has-space">
                    <p>Welcome, <?= htmlspecialchars($_SESSION['user_id'], ENT_QUOTES, 'UTF-8') ?>!</p>
                    <section style="display: flex; justify-content: flex-end; gap: 6px">
                        <button>
                            <a href="logout.php" class="default" >Logout</a>
                        </button>
                    </section>
                </div>
            </div>
    </body>
</html>