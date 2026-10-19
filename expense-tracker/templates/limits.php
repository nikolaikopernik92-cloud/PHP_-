<?php
declare(strict_types=1);

/**
 * @var array<string,string> $errors
 * @var array<string,string> $values
 */
?>
<h1>Лимиты категорий</h1>
<p class="muted">Ежемесячный бюджет на категорию расходов. Пустое поле — без лимита.</p>

<?php if (isset($errors['_form'])): ?>
    <div class="alert bad"><?= h($errors['_form']) ?></div>
<?php elseif ($errors !== []): ?>
    <div class="alert bad">Исправьте ошибки в форме.</div>
<?php endif; ?>

<form method="post" action="<?= h(url('limits')) ?>" class="card form" novalidate>
    <input type="hidden" name="_csrf" value="<?= h(csrfToken($_SESSION)) ?>">
    <?php foreach (expenseCategories() as $category): ?>
        <label><?= h($category) ?>, ₸
            <input type="text" name="limits[<?= h($category) ?>]" inputmode="decimal" value="<?= h($values[$category] ?? '') ?>">
            <?= fieldError($errors, $category) ?>
        </label>
    <?php endforeach; ?>
    <div class="actions">
        <button class="btn" type="submit">Сохранить лимиты</button>
        <a class="btn ghost" href="<?= h(url('summary')) ?>">Отмена</a>
    </div>
</form>
