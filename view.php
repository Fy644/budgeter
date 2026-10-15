<?php
    require_once 'db.php';
    if (!isset($_SESSION['user_name'])){
        header("Location: login.php");
        exit();
    }

    $perPage = 10;
    $periodsResult = $conn->query("
        SELECT DISTINCT DATE_FORMAT(
            CASE WHEN DAY(`datetime`) >= 8 THEN `datetime`
                 ELSE DATE_SUB(`datetime`, INTERVAL 1 MONTH)
            END,
            '%Y-%m'
        ) AS period_month
        FROM compras
        ORDER BY period_month DESC
    ");
    $periods = [];
    while ($period = $periodsResult->fetch_assoc()) {
        $periodStart = DateTimeImmutable::createFromFormat('!Y-m-d', $period['period_month'] . '-08');
        $periodEnd = $periodStart->modify('+1 month')->modify('-1 day');
        $periods[] = [
            'value' => $period['period_month'],
            'label' => $periodStart->format('M j, Y') . ' – ' . $periodEnd->format('M j, Y'),
        ];
    }

    $requestedPeriod = $_GET['month'] ?? '';
    $selectedPeriod = is_string($requestedPeriod) && preg_match('/^\d{4}-\d{2}$/', $requestedPeriod)
        ? $requestedPeriod
        : '';
    if (!in_array($selectedPeriod, array_column($periods, 'value'), true)) {
        $selectedPeriod = '';
    }

    $dateFilter = '';
    if ($selectedPeriod !== '') {
        $periodStart = DateTimeImmutable::createFromFormat('!Y-m-d', $selectedPeriod . '-08');
        $periodEnd = $periodStart->modify('+1 month');
        $periodStartValue = $periodStart->format('Y-m-d H:i:s');
        $periodEndValue = $periodEnd->format('Y-m-d H:i:s');
        $dateFilter = ' WHERE `datetime` >= ? AND `datetime` < ?';
    }

    $countStmt = $conn->prepare('SELECT COUNT(*) AS total FROM compras' . $dateFilter);
    if ($selectedPeriod !== '') {
        $countStmt->bind_param('ss', $periodStartValue, $periodEndValue);
    }
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $totalPurchases = (int) $countResult->fetch_assoc()['total'];
    $totalPages = max(1, (int) ceil($totalPurchases / $perPage));
    $requestedPage = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT);
    $page = min(max(is_int($requestedPage) ? $requestedPage : 1, 1), $totalPages);
    $offset = ($page - 1) * $perPage;

    $stmt = $conn->prepare('SELECT * FROM compras' . $dateFilter . ' ORDER BY `datetime` DESC, id DESC LIMIT ? OFFSET ?');
    if ($selectedPeriod !== '') {
        $stmt->bind_param('ssii', $periodStartValue, $periodEndValue, $perPage, $offset);
    } else {
        $stmt->bind_param('ii', $perPage, $offset);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    $purchaseData = $conn->prepare("
        SELECT category, SUM(precio * exchangerate) AS total_amount
        FROM compras" . $dateFilter . "
        GROUP BY category
    ");
    if ($selectedPeriod !== '') {
        $purchaseData->bind_param('ss', $periodStartValue, $periodEndValue);
    }
    $purchaseData->execute();
    $purchaseData = $purchaseData->get_result();

    $totalSpendingResult = $conn->prepare('SELECT COALESCE(SUM(precio * exchangerate), 0) AS total_spending FROM compras' . $dateFilter);
    if ($selectedPeriod !== '') {
        $totalSpendingResult->bind_param('ss', $periodStartValue, $periodEndValue);
    }
    $totalSpendingResult->execute();
    $totalSpendingResult = $totalSpendingResult->get_result();
    $totalSpending = (float) $totalSpendingResult->fetch_assoc()['total_spending'];

    $personSpendingFilter = $dateFilter . ($selectedPeriod !== '' ? ' AND ' : ' WHERE ') . 'LOWER(responsable) IN ("anne", "kadir")';
    $personSpendingStmt = $conn->prepare('SELECT LOWER(responsable) AS responsible_name, COALESCE(SUM(precio * exchangerate), 0) AS total_spending FROM compras' . $personSpendingFilter . ' GROUP BY LOWER(responsable)');
    if ($selectedPeriod !== '') {
        $personSpendingStmt->bind_param('ss', $periodStartValue, $periodEndValue);
    }
    $personSpendingStmt->execute();
    $personSpendingResult = $personSpendingStmt->get_result();
    $personSpending = ['anne' => 0.0, 'kadir' => 0.0];
    while ($person = $personSpendingResult->fetch_assoc()) {
        $personSpending[strtolower((string) $person['responsible_name'])] = (float) $person['total_spending'];
    }

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
                echo '<li role="option"><a href="select-edit.php">Edit product</a></li>';
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
            <form method="GET" class="month-filter">
                <label for="purchase-month">Purchase period:</label>
                <select id="purchase-month" name="month" onchange="this.form.submit()">
                    <option value="" <?= $selectedPeriod === '' ? 'selected' : '' ?>>All months</option>
                    <?php foreach ($periods as $period): ?>
                        <option value="<?= htmlspecialchars($period['value'], ENT_QUOTES, 'UTF-8') ?>" <?= $selectedPeriod === $period['value'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($period['label'], ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
            <div class="spending-summary">
                <div id="category-purchases" class="category-purchases-chart"></div>
                <section class="total-spending" aria-labelledby="total-spending-title">
                    <h2 id="total-spending-title">Total spending</h2>
                    <p>$<?= number_format($totalSpending, 2, '.', ',') ?></p>
                    <span>CAD</span>
                    <div class="person-spending" aria-label="Total spending by person">
                        <p>Anne: $<?= number_format($personSpending['anne'], 2, '.', ',') ?></p>
                        <p>Kadir: $<?= number_format($personSpending['kadir'], 2, '.', ',') ?></p>
                    </div>
                </section>
            </div>
            <?php if ($totalPages > 1): ?>
                <form method="GET" class="purchase-pagination" aria-label="Choose purchase group">
                    <?php if ($selectedPeriod !== ''): ?>
                        <input type="hidden" name="month" value="<?= htmlspecialchars($selectedPeriod, ENT_QUOTES, 'UTF-8') ?>">
                    <?php endif; ?>
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
                                $description = (string) $row['descripcion'];
                                $descriptionPreview = mb_substr($description, 0, 20, 'UTF-8');
                                if (mb_strlen($description, 'UTF-8') > 20) {
                                    $descriptionPreview .= '…';
                                }
                                $tooltipId = 'description-tooltip-' . (int) $row['id'];
                                echo '<td class="description-cell">';
                                echo '<button type="button" class="description-trigger" aria-describedby="' . $tooltipId . '" aria-expanded="false">'
                                    . htmlspecialchars($descriptionPreview, ENT_QUOTES, 'UTF-8')
                                    . '</button>';
                                echo '<div role="tooltip" id="' . $tooltipId . '" class="description-tooltip" hidden>'
                                    . '<div class="description-tooltip-content">'
                                    . htmlspecialchars($description, ENT_QUOTES, 'UTF-8')
                                    . '</div></div>';
                                echo '</td>';
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

        const descriptionTriggers = document.querySelectorAll('.description-trigger');
        let activeDescriptionTrigger = null;

        const positionDescriptionTooltip = (trigger) => {
            const tooltip = document.getElementById(trigger.getAttribute('aria-describedby'));
            const triggerBounds = trigger.getBoundingClientRect();
            const tooltipBounds = tooltip.getBoundingClientRect();
            const margin = 8;
            const left = Math.max(
                margin,
                Math.min(triggerBounds.left, window.innerWidth - tooltipBounds.width - margin)
            );
            const below = triggerBounds.bottom + 6;
            const above = triggerBounds.top - tooltipBounds.height - 6;
            const top = below + tooltipBounds.height <= window.innerHeight - margin
                ? below
                : above >= margin
                    ? above
                    : Math.max(margin, Math.min(below, window.innerHeight - tooltipBounds.height - margin));

            tooltip.classList.toggle('is-top', top < triggerBounds.top);
            tooltip.style.left = `${left}px`;
            tooltip.style.top = `${top}px`;
        };

        const closeDescriptionTooltips = (except = null) => {
            descriptionTriggers.forEach((trigger) => {
                if (trigger !== except) {
                    trigger.setAttribute('aria-expanded', 'false');
                    const tooltip = document.getElementById(trigger.getAttribute('aria-describedby'));
                    tooltip.hidden = true;
                    tooltip.style.left = '';
                    tooltip.style.top = '';
                    tooltip.classList.remove('is-top');
                    if (tooltip.parentElement === document.body) {
                        trigger.insertAdjacentElement('afterend', tooltip);
                    }
                }
            });
            if (activeDescriptionTrigger !== except) {
                activeDescriptionTrigger = null;
            }
        };

        document.addEventListener('click', (event) => {
            const trigger = event.target.closest('.description-trigger');
            if (trigger) {
                const tooltip = document.getElementById(trigger.getAttribute('aria-describedby'));
                const isOpen = trigger.getAttribute('aria-expanded') === 'true';
                closeDescriptionTooltips(trigger);
                trigger.setAttribute('aria-expanded', String(!isOpen));
                tooltip.hidden = isOpen;
                if (isOpen) {
                    activeDescriptionTrigger = null;
                    tooltip.style.left = '';
                    tooltip.style.top = '';
                    tooltip.classList.remove('is-top');
                    trigger.insertAdjacentElement('afterend', tooltip);
                } else {
                    activeDescriptionTrigger = trigger;
                    document.body.append(tooltip);
                    positionDescriptionTooltip(trigger);
                }
            } else if (!event.target.closest('.description-tooltip')) {
                closeDescriptionTooltips();
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closeDescriptionTooltips();
            }
        });

        window.addEventListener('resize', () => {
            if (activeDescriptionTrigger) {
                positionDescriptionTooltip(activeDescriptionTrigger);
            }
        });
        document.addEventListener('scroll', () => {
            if (activeDescriptionTrigger) {
                positionDescriptionTooltip(activeDescriptionTrigger);
            }
        }, true);
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