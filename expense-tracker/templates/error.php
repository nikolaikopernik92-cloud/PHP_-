<?php
declare(strict_types=1);

/**
 * @var string $title
 * @var string $message
 */
?>
<section class="card">
    <h1><?= h($title) ?></h1>
    <p><?= h($message) ?></p>
    <p><a class="btn" href="<?= h(url('journal')) ?>">К журналу операций</a></p>
</section>
