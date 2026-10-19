<?php
declare(strict_types=1);

/**
 * @var array{month: string, category: string, notes: list<string>} $filters
 * @var list<array<string,mixed>> $items
 * @var int $income
 * @var int $expense
 * @var int $balanceTotal
 * @var list<string> $months
 * @var array<string, array{spent:int, limit:int, over:int}> $warnings
 * @var string $currentMonth
 */
$types = transactionTypes();
?>
<h1>Журнал операций</h1>

<?php foreach ($warnings as $category => $w): ?>
    <div class="alert warn">
        Лимит по категории «<?= h((string) $category) ?>» превышен (<?= h(formatMonth($currentMonth)) ?>):
        потрачено <?= h(formatMoney($w['spent'])) ?> из <?= h(formatMoney($w['limit'])) ?>,
        превышение <?= h(formatMoney($w['over'])) ?>.
    </div>
<?php endforeach; ?>

<?php foreach ($filters['notes'] as $note): ?>
    <div class="alert bad"><?= h($note) ?></div>
<?php endforeach; ?>

<form method="get" action="index.php" class="filters card">
    <input type="hidden" name="page" value="journal">
    <label>Месяц
        <select name="month">
            <option value="">Все месяцы</option>
            <?php foreach ($months as $m): ?>
                <option value="<?= h($m) ?>"<?= $filters['month'] === $m ? ' selected' : '' ?>><?= h(formatMonth($m)) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>Категория
        <select name="category">
            <option value="">Все категории</option>
            <?php foreach (allCategories() as $c): ?>
                <option value="<?= h($c) ?>"<?= $filters['category'] === $c ? ' selected' : '' ?>><?= h($c) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <button class="btn" type="submit">Применить</button>
    <a class="btn ghost" href="<?= h(url('journal')) ?>">Сбросить</a>
</form>

<div class="stats">
    <div class="stat"><span>Доходы (по фильтру)</span><b class="plus"><?= h(formatMoney($income)) ?></b></div>
    <div class="stat"><span>Расходы (по фильтру)</span><b class="minus"><?= h(formatMoney($expense)) ?></b></div>
    <div class="stat"><span>Баланс (по фильтру)</span><b><?= h(formatMoney($income - $expense)) ?></b></div>
    <div class="stat accent"><span>Остаток средств (всего)</span><b><?= h(formatMoney($balanceTotal)) ?></b></div>
</div>

<?php if ($items === []): ?>
    <section class="card empty">
        <p>Операций не найдено. Измените фильтр или <a href="<?= h(url('add')) ?>">добавьте первую операцию</a>.</p>
    </section>
<?php else: ?>
    <div class="tablewrap">
        <table>
            <thead>
            <tr><th>Дата</th><th>Тип</th><th>Категория</th><th>Комментарий</th><th class="num">Сумма</th><th></th></tr>
            </thead>
            <tbody>
            <?php foreach ($items as $t): ?>
                <?php $isExpense = $t['type'] === 'expense'; ?>
                <tr>
                    <td><?= h(formatDate($t['date'])) ?></td>
                    <td><?= h($types[$t['type']]) ?></td>
                    <td><?= h($t['category']) ?></td>
                    <td><?= h((string) ($t['comment'] ?? '')) ?></td>
                    <td class="num <?= $isExpense ? 'minus' : 'plus' ?>"><?= $isExpense ? '−' : '+' ?><?= h(formatMoney($t['amount'])) ?></td>
                    <td>
                        <form method="post" action="<?= h(url('delete')) ?>" onsubmit="return confirm('Удалить операцию?');">
                            <input type="hidden" name="_csrf" value="<?= h(csrfToken($_SESSION)) ?>">
                            <input type="hidden" name="id" value="<?= h($t['id']) ?>">
                            <button class="link danger" type="submit">Удалить</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <p class="muted">Показано операций: <?= count($items) ?></p>
<?php endif; ?>
