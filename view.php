<?php
    require_once 'db.php';
    if (!isset($_SESSION['user_name'])){
        header("Location: login.php");
        exit();
    }

    $perPage = 10;
    $countResult = $conn->query('SELECT COUNT(*) AS total FROM compras');
    $totalPurchases = (int) $countResult->fetch_assoc()['total'];
    $totalPages = max(1, (int) ceil($totalPurchases / $perPage));
    $requestedPage = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT);
    $page = min(max(is_int($requestedPage) ? $requestedPage : 1, 1), $totalPages);
    $offset = ($page - 1) * $perPage;

    $stmt = $conn->prepare('SELECT * FROM compras ORDER BY `datetime` DESC, id DESC LIMIT ? OFFSET ?');
    $stmt->bind_param('ii', $perPage, $offset);
    $stmt->execute();
    $result = $stmt->get_result();

    $purchaseData = $conn->query("
        SELECT compra.category AS category, SUM(compra.precio * compra.exchangerate) AS total_amount
        FROM compras AS compra
        GROUP BY compra.category
    ");
    $totalSpendingResult = $conn->query('SELECT COALESCE(SUM(precio * exchangerate), 0) AS total_spending FROM compras');
    $totalSpending = (float) $totalSpendingResult->fetch_assoc()['total_spending'];
    $purchaseChartData = [];
    while ($compra = $purchaseData->fetch_assoc()) {
        $purchaseChartData[] = [
            'name' => $compra['category'],
            'y' => round((float) $compra['total_amount'], 2),
        ];
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
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
            ?>
            <li role="option">
                <a href="view.php">View purchases</a>
            </li>
        </ul>
    </div>
    <div class="window active" style=" max-width: calc(100vw - 2rem);">
        <div class="title-bar">
            <div class="title-bar-text">Budgeter</div>
        </div>
        <div class="window-body has-space">
            <div class="spending-summary">
                <div id="category-purchases" class="category-purchases-chart"></div>
                <section class="total-spending" aria-labelledby="total-spending-title">
                    <h2 id="total-spending-title">Total spending</h2>
                    <p>$<?= number_format($totalSpending, 2, '.', ',') ?></p>
                    <span>CAD</span>
                </section>
            </div>
            <?php if ($totalPages > 1): ?>
                <form method="GET" class="purchase-pagination" aria-label="Choose purchase group">
                    <?php for ($groupPage = 1; $groupPage <= $totalPages; $groupPage++):
                        $firstPurchase = (($groupPage - 1) * $perPage) + 1;
                        $lastPurchase = min($groupPage * $perPage, $totalPurchases);
                    ?>
                        <button
                            type="submit"
                            name="page"
                            value="<?= $groupPage ?>"
                            <?= $groupPage === $page ? 'aria-current="page"' : '' ?>
                        ><?= $firstPurchase ?>–<?= $lastPurchase ?></button>
                    <?php endfor; ?>
                </form>
            <?php endif; ?>
            <table class="purchases-table">
                <thead>
                    <tr>
                        <th>Store</th>
                        <th>Amount (in CAD)</th>
                        <th>Original Currency</th>
                        <th>Product</th>
                        <th>Category</th>
                        <th>Responsible</th>
                        <th>Description</th>
                        <th class="highlighted indicator">Date & Time</th>
                        <th>Payment Method</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                        if ($result->num_rows > 0) {
                            while($row = $result->fetch_assoc()) {
                                echo "<tr>";
                                echo "<td>" . htmlspecialchars($row['tienda'], ENT_QUOTES, 'UTF-8') . "</td>";
                                echo "<td>" . number_format((float)$row['precio'] * (float)$row['exchangerate'], 2, '.', '') . "</td>";
                                echo "<td>" . htmlspecialchars($row['currency'], ENT_QUOTES, 'UTF-8') . "</td>";
                                echo "<td>" . htmlspecialchars($row['producto'], ENT_QUOTES, 'UTF-8') . "</td>";
                                echo "<td>" . htmlspecialchars($row['category'], ENT_QUOTES, 'UTF-8') . "</td>";
                                echo "<td>" . htmlspecialchars($row['responsable'], ENT_QUOTES, 'UTF-8') . "</td>";
                                echo "<td>" . mb_substr($row['descripcion'], 0, 20, 'UTF-8') . "...</td>";
                                echo "<td>" . htmlspecialchars($row['datetime'], ENT_QUOTES, 'UTF-8') . "</td>";
                                echo "<td>" . htmlspecialchars($row['method'], ENT_QUOTES, 'UTF-8') . "</td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='10'>No purchases found</td></tr>";
                        }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
    <script>
        document.querySelectorAll('.purchases-table tbody tr').forEach((row) => {
            row.addEventListener('mouseenter', () => row.classList.add('highlighted'));
            row.addEventListener('mouseleave', () => row.classList.remove('highlighted'));
        });
    </script>
    <script src="https://code.highcharts.com/highcharts.js"></script>
    <script>
        Highcharts.chart('category-purchases', {
            colors: ['#3b82c4', '#70b7e6', '#5e9f78', '#e5b957', '#9b83c7', '#e28468', '#55b9b2', '#8b9baa'],
            chart: {
                type: 'pie',
                backgroundColor: 'transparent',
                borderColor: '#ffffff',
                borderWidth: 1,
                borderRadius: 7,
                style: {
                    fontFamily: '"Segoe UI", Tahoma, sans-serif'
                }
            },
            title: {
                text: 'Purchases by Category',
                style: {
                    color: '#173b5e',
                    fontSize: '16px',
                    fontWeight: '600',
                    textShadow: '0 1px 0 #fff'
                }
            },
            tooltip: {
                backgroundColor: {
                    linearGradient: [0, 0, 0, 60],
                    stops: [
                        [0, '#ffffff'],
                        [1, '#e4f1fb']
                    ]
                },
                style: {
                    color: '#173b5e',
                },
                borderColor: '#87aac8',
                borderRadius: 5,
                shadow: true,
                pointFormat: '<b>{point.y:.2f} CAD</b> ({point.percentage:.1f}%)'
            },
            plotOptions: {
                pie: {
                    allowPointSelect: true,
                    cursor: 'pointer',
                    borderColor: '#f8fcff',
                    borderWidth: 2,
                    shadow: {
                        color: 'rgba(30, 70, 105, 0.22)',
                        offsetX: 1,
                        offsetY: 2,
                        opacity: 0.2,
                        width: 5
                    },
                    dataLabels: {
                        enabled: true,
                        format: '{point.name}: {point.percentage:.1f}%',
                        distance: 16,
                        connectorColor: '#7397b5',
                        style: {
                            color: '#234765',
                            fontSize: '11px',
                            fontWeight: 'normal',
                            textOutline: '1px #f5faff'
                        }
                    }
                }
            },
            series: [{
                name: 'Total Amount (CAD)',
                colorByPoint: true,
                data: <?= json_encode($purchaseChartData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>

            }]
        });
    </script>
</body>
</html>