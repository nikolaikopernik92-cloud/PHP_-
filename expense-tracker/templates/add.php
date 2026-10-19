<?php
declare(strict_types=1);

/**
 * @var array<string,string> $errors
 * @var array<string,string> $values
 */
?>
<h1>Новая операция</h1>

<?php if ($errors !== []): ?>
    <div class="alert bad">Исправьте ошибки в форме.</div>
<?php endif; ?>

<form method="post" action="<?= h(url('add')) ?>" class="card form" novalidate>
    <input type="hidden" name="_csrf" value="<?= h(csrfToken($_SESSION)) ?>">

    <label>Тип операции
        <select name="type">
            <?php foreach (transactionTypes() as $code => $label): ?>
                <option value="<?= h($code) ?>"<?= $values['type'] === $code ? ' selected' : '' ?>><?= h($label) ?></option>
            <?php endforeach; ?>
        </select>
        <?= fieldError($errors, 'type') ?>
    </label>

    <label>Сумма, ₸
        <input type="text" name="amount" inputmode="decimal" placeholder="например, 1500 или 1500,50" value="<?= h($values['amount']) ?>">
        <?= fieldError($errors, 'amount') ?>
    </label>

    <label>Дата
        <input type="date" name="date" min="<?= h(MIN_DATE) ?>" max="<?= h(date('Y-m-d')) ?>" value="<?= h($values['date']) ?>">
        <?= fieldError($errors, 'date') ?>
    </label>

    <label>Категория
        <select name="category">
            <option value="">— выберите —</option>
            <optgroup label="Расходы">
                <?php foreach (expenseCategories() as $c): ?>
                    <option value="<?= h($c) ?>"<?= $values['category'] === $c ? ' selected' : '' ?>><?= h($c) ?></option>
                <?php endforeach; ?>
            </optgroup>
            <optgroup label="Доходы">
                <?php foreach (incomeCategories() as $c): ?>
                    <?php if (!in_array($c, expenseCategories(), true)): ?>
                        <option value="<?= h($c) ?>"<?= $values['category'] === $c ? ' selected' : '' ?>><?= h($c) ?></option>
                    <?php endif; ?>
                <?php endforeach; ?>
            </optgroup>
        </select>
        <small class="muted">«Другое» подходит и для расходов, и для доходов.</small>
        <?= fieldError($errors, 'category') ?>
    </label>

    <label>Комментарий (необязательно, до <?= MAX_COMMENT_LENGTH ?> символов)
        <textarea name="comment" rows="3"><?= h($values['comment']) ?></textarea>
        <?= fieldError($errors, 'comment') ?>
    </label>

    <div class="actions">
        <button class="btn" type="submit">Сохранить</button>
        <a class="btn ghost" href="<?= h(url('journal')) ?>">Отмена</a>
    </div>
</form>
