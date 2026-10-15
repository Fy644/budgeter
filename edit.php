<?php
    require_once 'db.php';
    if (!isset($_SESSION['user_name'])) {
        header("Location: login.php");
        exit();
    }
    if ($_SESSION['user_perms'] !== 0) {
        header("Location: index.php");
        exit();
    }

    $requestedId = $_GET['id'] ?? '';
    $purchaseId = is_string($requestedId) ? filter_var($requestedId, FILTER_VALIDATE_INT) : false;
    if (!$purchaseId || $purchaseId < 1) {
        http_response_code(400);
        exit('A valid purchase ID is required.');
    }

    $stmt = $conn->prepare('SELECT * FROM compras WHERE id = ?');
    $stmt->bind_param('i', $purchaseId);
    $stmt->execute();
    $purchase = $stmt->get_result()->fetch_assoc();
    if (!$purchase) {
        http_response_code(404);
        exit('Purchase not found.');
    }

    $values = [
        'store' => $purchase['tienda'],
        'amount' => $purchase['precio'],
        'currency' => $purchase['currency'],
        'exchange_rate' => $purchase['exchangerate'],
        'product' => $purchase['producto'],
        'category' => $purchase['category'],
        'responsible' => $purchase['responsable'],
        'description' => $purchase['descripcion'],
        'date' => (new DateTimeImmutable($purchase['datetime']))->format('Y-m-d\TH:i:s'),
        'payment_method' => $purchase['method'],
    ];
    $errorMessage = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $fields = ['store', 'amount', 'currency', 'exchange_rate', 'product', 'category', 'responsible', 'description', 'date', 'payment_method'];
        foreach ($fields as $field) {
            $value = $_POST[$field] ?? null;
            if (!is_string($value)) {
                $errorMessage = 'Please complete all purchase fields with valid values.';
                break;
            }
            $values[$field] = trim($value);
        }

        if ($errorMessage === '' && in_array('', $values, true)) {
            $errorMessage = 'Please complete all purchase fields.';
        }
        if ($errorMessage === '' && (!is_numeric($values['amount']) || !is_numeric($values['exchange_rate']))) {
            $errorMessage = 'Amount and exchange rate must be numbers.';
        }

        $purchaseDate = $errorMessage === ''
            ? DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s', $values['date'])
            : false;
        if ($errorMessage === '' && (!$purchaseDate || $purchaseDate->format('Y-m-d\TH:i:s') !== $values['date'])) {
            $purchaseDate = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $values['date']);
            if (!$purchaseDate || $purchaseDate->format('Y-m-d\TH:i') !== $values['date']) {
                $errorMessage = 'Enter a valid purchase date and time.';
            }
        }

        if ($errorMessage === '') {
            $amount = (float) $values['amount'];
            $exchangeRate = (float) $values['exchange_rate'];
            $dateValue = $purchaseDate->format('Y-m-d H:i:s');
            $update = $conn->prepare('UPDATE compras SET tienda = ?, precio = ?, currency = ?, exchangerate = ?, producto = ?, responsable = ?, descripcion = ?, `datetime` = ?, method = ?, category = ? WHERE id = ?');
            $update->bind_param(
                'sdsdssssssi',
                $values['store'],
                $amount,
                $values['currency'],
                $exchangeRate,
                $values['product'],
                $values['responsible'],
                $values['description'],
                $dateValue,
                $values['payment_method'],
                $values['category'],
                $purchaseId
            );

            if ($update->execute()) {
                header('Location: select-edit.php?date=' . rawurlencode($purchaseDate->format('Y-m-d')) . '&saved=1');
                exit();
            }
            $errorMessage = 'The purchase could not be updated.';
        }
    }

    $currencyOptions = ['USD', 'MXN', 'CAD'];
    $categoryOptions = ['Food', 'Clothing', 'Electronics', 'Entertainment', 'Health', 'Transportation', 'Education', 'Gusguera', 'Other'];
    $responsibleOptions = ['Kadir', 'Anne', 'Other'];
    $paymentMethodOptions = ['Cash', 'Card'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit purchase</title>
    <link rel="stylesheet" href="https://unpkg.com/7.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="home-page">
        <ul role="listbox" class="has-hover">
            <li role="option"><a href="index.php">Home</a></li>
            <li role="option"><a href="insert.php">Insert new product</a></li>
            <li role="option" class="active"><a href="select-edit.php">Edit product</a></li>
            <li role="option"><a href="view.php">View purchases</a></li>
        </ul>
    </div>
    <div class="window active edit-purchase-window">
        <div class="title-bar">
            <div class="title-bar-text">Edit purchase</div>
        </div>
        <div class="window-body has-space">
            <?php if ($errorMessage !== ''): ?>
                <p role="alert"><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>
            <form method="POST" action="edit.php?id=<?= $purchaseId ?>" class="insert-form-grid">
                <div class="group">
                    <label for="store">Store</label>
                    <input id="store" type="text" name="store" value="<?= htmlspecialchars((string) $values['store'], ENT_QUOTES, 'UTF-8') ?>" required>
                </div>
                <div class="group">
                    <label for="amount">Amount</label>
                    <input id="amount" type="number" name="amount" step="0.01" value="<?= htmlspecialchars((string) $values['amount'], ENT_QUOTES, 'UTF-8') ?>" required>
                </div>
                <div class="group">
                    <label for="currency">Currency</label>
                    <select id="currency" name="currency" required>
                        <?php if (!in_array($values['currency'], $currencyOptions, true)): ?>
                            <option value="<?= htmlspecialchars((string) $values['currency'], ENT_QUOTES, 'UTF-8') ?>" selected><?= htmlspecialchars((string) $values['currency'], ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endif; ?>
                        <?php foreach ($currencyOptions as $option): ?>
                            <option value="<?= $option ?>" <?= $values['currency'] === $option ? 'selected' : '' ?>><?= $option ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="group">
                    <label for="exchange_rate">Exchange rate to CAD</label>
                    <input id="exchange_rate" type="number" name="exchange_rate" step="any" value="<?= htmlspecialchars((string) $values['exchange_rate'], ENT_QUOTES, 'UTF-8') ?>" required>
                </div>
                <div class="group">
                    <label for="product">Product</label>
                    <input id="product" type="text" name="product" value="<?= htmlspecialchars((string) $values['product'], ENT_QUOTES, 'UTF-8') ?>" required>
                </div>
                <div class="group">
                    <label for="category">Product category</label>
                    <select id="category" name="category" required>
                        <?php if (!in_array($values['category'], $categoryOptions, true)): ?>
                            <option value="<?= htmlspecialchars((string) $values['category'], ENT_QUOTES, 'UTF-8') ?>" selected><?= htmlspecialchars((string) $values['category'], ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endif; ?>
                        <?php foreach ($categoryOptions as $option): ?>
                            <option value="<?= $option ?>" <?= $values['category'] === $option ? 'selected' : '' ?>><?= $option ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="group">
                    <label for="responsible">Responsible</label>
                    <select id="responsible" name="responsible" required>
                        <?php if (!in_array($values['responsible'], $responsibleOptions, true)): ?>
                            <option value="<?= htmlspecialchars((string) $values['responsible'], ENT_QUOTES, 'UTF-8') ?>" selected><?= htmlspecialchars((string) $values['responsible'], ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endif; ?>
                        <?php foreach ($responsibleOptions as $option): ?>
                            <option value="<?= $option ?>" <?= $values['responsible'] === $option ? 'selected' : '' ?>><?= $option ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" rows="2" required><?= htmlspecialchars((string) $values['description'], ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>
                <div class="group">
                    <label for="date">Date &amp; time</label>
                    <input id="date" type="datetime-local" name="date" step="1" value="<?= htmlspecialchars((string) $values['date'], ENT_QUOTES, 'UTF-8') ?>" required>
                </div>
                <div class="group">
                    <label for="payment_method">Payment method</label>
                    <select id="payment_method" name="payment_method" required>
                        <?php if (!in_array($values['payment_method'], $paymentMethodOptions, true)): ?>
                            <option value="<?= htmlspecialchars((string) $values['payment_method'], ENT_QUOTES, 'UTF-8') ?>" selected><?= htmlspecialchars((string) $values['payment_method'], ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endif; ?>
                        <?php foreach ($paymentMethodOptions as $option): ?>
                            <option value="<?= $option ?>" <?= $values['payment_method'] === $option ? 'selected' : '' ?>><?= $option ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <section class="insert-form-submit" style="display: flex; justify-content: flex-end; gap: 6px">
                    <a href="select-edit.php?date=<?= rawurlencode((new DateTimeImmutable($purchase['datetime']))->format('Y-m-d')) ?>">Cancel</a>
                    <button class="default" type="submit">Save changes</button>
                </section>
            </form>
        </div>
    </div>
</body>
</html>
