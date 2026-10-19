<?php
declare(strict_types=1);

/**
 * @var string $month
 * @var list<string> $months
 * @var list<string> $notes
 * @var int $income
 * @var int $expense
 * @var int $balanceTotal
 * @var list<array{category:string, spent:int, limit:?int, status:array{state:string, percent:int}}> $rows
 * @var array<string, array{spent:int, limit:int, over:int}> $overages
 * @var int $count
 */
$labels = ['none' => 'Лимит не задан', 'ok' => 'В пределах лимита', 'warn' => 'Близко к лимиту', 'over' => 'Лимит превышен'];
?>
<h1>Месячная сводка</h1>

<?php foreach ($notes as $note): ?>
    <div class="alert bad"><?= h($note) ?></div>
<?php endforeach; ?>

<?php foreach ($overages as $category => $w): ?>
    <div class="alert warn">
        Превышен лимит «<?= h((string) $category) ?>»: потрачено <?= h(formatMoney($w['spent'])) ?>
        из <?= h(formatMoney($w['limit'])) ?> (на <?= h(formatMoney($w['over'])) ?> больше).
    </div>
<?php endforeach; ?>

<form method="get" action="index.php" class="filters card">
    <input type="hidden" name="page" value="summary">
    <label>Месяц
        <select name="month" onchange="this.form.submit()">
            <?php foreach ($months as $m): ?>
                <option value="<?= h($m) ?>"<?= $month === $m ? ' selected' : '' ?>><?= h(formatMonth($m)) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <noscript><button class="btn" type="submit">Показать</button></noscript>
</form>

<div class="stats">
    <div class="stat"><span>Доходы за <?= h(formatMonth($month)) ?></span><b class="plus"><?= h(formatMoney($income)) ?></b></div>
    <div class="stat"><span>Расходы за месяц</span><b class="minus"><?= h(formatMoney($expense)) ?></b></div>
    <div class="stat"><span>Итог месяца</span><b><?= h(formatMoney($income - $expense)) ?></b></div>
    <div class="stat accent"><span>Остаток средств (всего)</span><b><?= h(formatMoney($balanceTotal)) ?></b></div>
</div>

<?php if ($count === 0): ?>
    <section class="card empty"><p>За выбранный месяц операций нет.</p></section>
<?php endif; ?>

<div class="tablewrap">
    <table>
        <thead>
        <tr><th>Категория расходов</th><th class="num">Потрачено</th><th class="num">Лимит</th><th>Использование</th></tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $row): ?>
            <?php $state = $row['status']['state']; ?>
            <tr>
                <td><?= h($row['category']) ?></td>
                <td class="num"><?= h(formatMoney($row['spent'])) ?></td>
                <td class="num"><?= $row['limit'] === null ? '—' : h(formatMoney($row['limit'])) ?></td>
                <td>
                    <?php if ($state === 'none'): ?>
                        <span class="muted"><?= h($labels[$state]) ?></span>
                    <?php else: ?>
                        <div class="meter <?= h($state) ?>"><span style="width: <?= (int) min(100, $row['status']['percent']) ?>%"></span></div>
                        <small><?= h($labels[$state]) ?> (<?= (int) $row['status']['percent'] ?>%)</small>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<p><a class="btn ghost" href="<?= h(url('limits')) ?>">Настроить лимиты</a></p>
