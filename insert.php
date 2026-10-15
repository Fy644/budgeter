<?php
    require_once 'db.php';
    if (!isset($_SESSION['user_name'])){
        header("Location: login.php");
        exit();
    }
    if ($_SESSION['user_perms'] !== 0) {
        header("Location: index.php");
        exit();
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $store = $_POST['store'] ?? '';
        $amount = $_POST['amount'] ?? '';
        $currency = $_POST['text7'] ?? '';
        $exchangeRate = $_POST['text9'] ?? '';
        $product = $_POST['product'] ?? '';
        $category = $_POST['text10'] ?? '';
        $responsable = $_POST['text4'] ?? '';
        $description = $_POST['description'] ?? '';
        $date = str_replace('T', ' ', $_POST['date'] ?? '');
        $paymentMethod = $_POST['text8'] ?? '';

        $stmt = $conn->prepare('INSERT INTO compras (tienda, precio, currency, exchangerate, producto, responsable, descripcion, `datetime`, method, category) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->bind_param('sdsdssssss', $store, $amount, $currency, $exchangeRate, $product, $responsable, $description, $date, $paymentMethod, $category);

        if ($stmt->execute()) {
            header('Location: index.php?inserted=1');
            exit();
        } else {
            echo "Error: " . htmlspecialchars($stmt->error);
        }
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
            <?php if ($_SESSION['user_perms'] === 0) 
                echo '<li role="option"><a href="insert.php">Insert new product</a></li>';
                echo '<li role="option"><a href="select-edit.php">Edit product</a></li>';
            ?>
            <li role="option">
                <a href="view.php">View purchases</a>
            </li>
        </ul>
    </div>
    <div class="window active" style="width: 550px; max-width: calc(100vw - 2rem);">
        <div class="title-bar">
            <div class="title-bar-text">Insert new item</div>
        </div>
        <div class="window-body has-space">
            <form method="POST" class="insert-form-grid">
                <div class="group">
                    <label for="text1">Store</label>
                    <input id="text1" type="text" name="store" required />
                </div>
                <div class="group">
                    <label for="text2">Amount</label>
                    <input id="text2" type="number" name="amount" step="0.01" required />
                </div>
                <div class="group">
                    <label for="text7">Currency</label>
                    <select name="text7">
                        <option value="USD">USD</option>
                        <option value="MXN">MXN</option>
                        <option value="CAD">CAD</option>
                    </select>
                </div>
                <div class="group">
                    <label for="text9">Exchange rate to CAD</label>
                    <input id="text9" type="number" name="text9" step="0.0000000000000000001" required />
                </div>
                <div class="group">
                    <label for="text3">Product</label>
                    <input id="text3" type="text" name="product" required />
                </div>
                <div class="group">
                    <label for="text10">Product category</label>
                    <select name="text10">
                        <option value="Food">Food</option>
                        <option value="Clothing">Clothing</option>
                        <option value="Electronics">Electronics</option>
                        <option value="Entertainment">Entertainment</option>
                        <option value="Health">Health</option>
                        <option value="Transportation">Transportation</option>
                        <option value="Education">Education</option>
                        <option value="Gusguera">Gusguera</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="group">
                    <label for="text4">Responsable</label>
                    <select name="text4">
                        <option value="Kadir">Kadir</option>
                        <option value="Anne">Anne</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="group">
                    <label for="text5">Description</label>
                    <textarea id="text5" name="description" rows="2" required></textarea>
                </div>
                <div class="group">
                    <label for="text6">Date & time</label>
                    <input id="text6" type="datetime-local" name="date" required />
                </div>
                <div class="group">
                    <label for="text8">Payment method</label>
                    <select name="text8">
                        <option value="Cash">Cash</option>
                        <option value="Card">Card</option>
                    </select>
                </div>
                <section class="insert-form-submit" style="display: flex; justify-content: flex-end; gap: 6px">
                    <button class="default">Submit</button>
                </section>
            </form>
        </div>
    </div>
</body>
</html>