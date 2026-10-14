<?php
    require_once 'db.php';
    if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['username']) && isset($_POST['password'])) {
        $username = $_POST['username'];
        $password = $_POST['password'];

        $sql = "SELECT * FROM users WHERE username = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if($result->num_rows > 0){
            $user = $result->fetch_assoc();
            if(password_verify($password, $user['password'])){
                $_SESSION['user_name'] = $user['username'];
                $_SESSION['user_perms'] = $user['level'];
                header("Location: index.php");
                exit();
            } else {
                echo "Invalid password.";
            }
        } else {
            echo "User not found.";
        }
    }
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Budgeter - Login</title>
        <link rel="stylesheet" href="https://unpkg.com/7.css">
        <link rel="stylesheet" href="style.css">
    </head>
    <body>
        <form method="POST">
            <div class="window active" style="width: 250px; max-width: calc(100vw - 2rem);">
                <div class="title-bar">
                    <div class="title-bar-text">Login</div>
                </div>
                <div class="window-body has-space">
                    <?php if (isset($_GET['registered'])): ?>
                        <p role="status">Account created. You can now log in.</p>
                    <?php endif; ?>
                    <div class="group" style="width: 200px">
                        <label for="text1">Username</label>
                        <input id="text1" type="text" name="username" />
                    </div>
                    <div class="group" style="width: 200px">
                        <label for="text2">Password</label>
                        <input id="text2" type="password" name="password" />
                    </div>
                    <br>
                    <section style="display: flex; justify-content: flex-end; gap: 6px">
                        <button class="default">OK</button>
                    </section>
                </div>
            </div>
        </form>
    </body>
</html>