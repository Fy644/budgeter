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

    $selectedDate = '';
    $errorMessage = '';
    $purchases = null;
    $requestedDate = $_GET['date'] ?? '';

    if (is_string($requestedDate) && $requestedDate !== '') {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $requestedDate);
        if (!$date || $date->format('Y-m-d') !== $requestedDate) {
            $errorMessage = 'Enter a valid date.';
        } else {
            $selectedDate = $requestedDate;
            $start = $date->format('Y-m-d 00:00:00');
            $end = $date->modify('+1 day')->format('Y-m-d 00:00:00');
            $stmt = $conn->prepare('SELECT * FROM compras WHERE `datetime` >= ? AND `datetime` < ? ORDER BY `datetime` DESC, id DESC');
            $stmt->bind_param('ss', $start, $end);
            $stmt->execute();
            $purchases = $stmt->get_result();
        }
    } elseif (isset($_GET['date'])) {
        $errorMessage = 'Enter a valid date.';
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit purchases</title>
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
    <div class="window active edit-purchases-window">
        <div class="title-bar">
            <div class="title-bar-text">Edit purchases</div>
        </div>
        <div class="window-body has-space">
            <?php if (isset($_GET['saved'])): ?>
                <p role="status">Purchase updated.</p>
            <?php endif; ?>
            <form method="GET" action="select-edit.php" class="edit-date-form">
                <label for="date">Show purchases from:</label>
                <input type="date" name="date" id="date" value="<?= htmlspecialchars($selectedDate, ENT_QUOTES, 'UTF-8') ?>" required>
                <button type="submit" id="search-button">Search</button>
            </form>
            <?php if ($errorMessage !== ''): ?>
                <p role="alert"><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></p>
            <?php elseif ($purchases instanceof mysqli_result): ?>
                <?php if ($purchases->num_rows > 0): ?>
                    <p>Select a purchase to edit it.</p>
                    <div class="edit-purchases-table-wrap">
                        <table class="purchases-table edit-purchases-table">
                            <thead>
                                <tr>
                                    <th>Store</th>
                                    <th>Amount (in CAD)</th>
                                    <th>Original Currency</th>
                                    <th>Product</th>
                                    <th>Category</th>
                                    <th>Responsible</th>
                                    <th>Description</th>
                                    <th class="highlighted indicator">Date &amp; Time</th>
                                    <th>Payment Method</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($purchase = $purchases->fetch_assoc()):
                                    $editUrl = 'edit.php?id=' . (int) $purchase['id'];
                                ?>
                                    <tr class="editable-purchase-row" data-edit-url="<?= htmlspecialchars($editUrl, ENT_QUOTES, 'UTF-8') ?>" tabindex="0" aria-label="Edit purchase at <?= htmlspecialchars($purchase['tienda'], ENT_QUOTES, 'UTF-8') ?>: <?= htmlspecialchars($purchase['producto'], ENT_QUOTES, 'UTF-8') ?>">
                                        <td><a href="<?= htmlspecialchars($editUrl, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($purchase['tienda'], ENT_QUOTES, 'UTF-8') ?></a></td>
                                        <td><?= number_format((float) $purchase['precio'] * (float) $purchase['exchangerate'], 2, '.', '') ?></td>
                                        <td><?= htmlspecialchars($purchase['currency'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($purchase['producto'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($purchase['category'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($purchase['responsable'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($purchase['descripcion'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($purchase['datetime'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($purchase['method'], ENT_QUOTES, 'UTF-8') ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p>No purchases found for <?= htmlspecialchars($selectedDate, ENT_QUOTES, 'UTF-8') ?>.</p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    <script>
        document.querySelectorAll('.editable-purchase-row').forEach((row) => {
            row.addEventListener('click', (event) => {
                if (!event.target.closest('a')) {
                    window.location.href = row.dataset.editUrl;
                }
            });
            row.addEventListener('keydown', (event) => {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    window.location.href = row.dataset.editUrl;
                }
            });
        });
    </script>
</body>
</html>
