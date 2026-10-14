<! idgaf this is done completely by chatgpt >
<?php
    require_once 'db.php';

    $error = '';
    $username = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if ($username === '' || $password === '' || $confirmPassword === '') {
            $error = 'Please fill in every field.';
        } elseif (mb_strlen($username) > 20) {
            $error = 'Username must be 20 characters or fewer.';
        } elseif ($password !== $confirmPassword) {
            $error = 'The passwords do not match.';
        } else {
            $stmt = $conn->prepare('SELECT id FROM users WHERE username = ?');
            $stmt->bind_param('s', $username);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $error = 'That username is already taken.';
            } else {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $level = 0;
                $stmt = $conn->prepare('INSERT INTO users (username, password, level) VALUES (?, ?, ?)');
                $stmt->bind_param('ssi', $username, $passwordHash, $level);

                if ($stmt->execute()) {
                    header('Location: login.php?registered=1');
                    exit();
                }

                $error = 'Unable to create your account. Please try again.';
            }
        }
    }
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Budgeter - Register</title>
        <link rel="stylesheet" href="https://unpkg.com/7.css">
        <link rel="stylesheet" href="style.css">
    </head>
    <body>
        <form method="POST">
            <div class="window active" style="width: 250px; max-width: calc(100vw - 2rem);">
                <div class="title-bar">
                    <div class="title-bar-text">Register</div>
                </div>
                <div class="window-body has-space">
                    <?php if ($error !== ''): ?>
                        <p role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endif; ?>
                    <div class="group" style="width: 200px">
                        <label for="username">Username</label>
                        <input id="username" type="text" name="username" value="<?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?>" required>
                    </div>
                    <div class="group" style="width: 200px">
                        <label for="password">Password</label>
                        <input id="password" type="password" name="password" required>
                    </div>
                    <div class="group" style="width: 200px">
                        <label for="confirm-password">Confirm password</label>
                        <input id="confirm-password" type="password" name="confirm_password" required>
                    </div>
                    <br>
                    <section style="display: flex; justify-content: flex-end; gap: 6px">
                        <button class="default" type="submit">Create account</button>
                    </section>
                    <p><a href="login.php">Back to login</a></p>
                </div>
            </div>
        </form>
    </body>
</html>