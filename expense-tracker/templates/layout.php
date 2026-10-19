<?php
declare(strict_types=1);

/** @var string $content */
$flash = pullFlash($_SESSION);
$active = $active ?? '';
$nav = [
    'journal' => 'Журнал',
    'add' => 'Добавить',
    'summary' => 'Сводка',
    'limits' => 'Лимиты',
];
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($title ?? 'Трекер расходов') ?> · Трекер расходов</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header class="top">
    <div class="wrap bar">
        <a class="brand" href="<?= h(url('journal')) ?>">Трекер расходов</a>
        <nav>
            <?php foreach ($nav as $page => $label): ?>
                <a href="<?= h(url($page)) ?>" class="<?= $active === $page ? 'on' : '' ?>"><?= h($label) ?></a>
            <?php endforeach; ?>
        </nav>
    </div>
</header>
<main class="wrap">
    <?php if ($flash !== null): ?>
        <div class="alert <?= $flash['type'] === 'success' ? 'ok' : 'bad' ?>"><?= h($flash['text']) ?></div>
    <?php endif; ?>
    <?= $content ?>
</main>
<footer class="wrap foot">Учебный проект · PHP · данные хранятся в JSON</footer>
</body>
</html>
