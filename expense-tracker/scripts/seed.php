<?php
declare(strict_types=1);

/**
 * Заполняет приложение демонстрационными данными (вымышленные операции за 3 месяца).
 * Запуск:  php scripts/seed.php          (не перезаписывает существующие данные)
 *          php scripts/seed.php --force  (перезаписывает)
 */

require __DIR__ . '/../src/bootstrap.php';

if (is_file(transactionsPath()) && !in_array('--force', $argv, true)) {
    fwrite(STDERR, "Файл данных уже существует. Используйте --force для перезаписи.\n");
    exit(1);
}

mt_srand(42); // детерминированные данные
$today = new DateTimeImmutable('today');

// Диапазоны сумм (тенге) и подходящие комментарии по категориям расходов.
$ranges = [
    'Питание' => [1500, 6000], 'Транспорт' => [500, 2500], 'Учёба' => [3000, 10000],
    'Развлечения' => [2000, 9000], 'Здоровье' => [2000, 8000], 'Связь' => [2000, 3500], 'Другое' => [1000, 5000],
];
$comments = [
    'Питание' => ['Обед в столовой', 'Продукты', 'Кофе и перекус'],
    'Транспорт' => ['Проездной', 'Такси', 'Билет на автобус'],
    'Учёба' => ['Канцтовары', 'Распечатка конспектов', 'Учебник'],
    'Развлечения' => ['Кино с друзьями', 'Кафе с друзьями', 'Игры'],
    'Здоровье' => ['Аптека', 'Витамины', 'Приём врача'],
    'Связь' => ['Мобильная связь', 'Интернет'],
    'Другое' => ['Подарок', 'Бытовые мелочи'],
];
$categories = array_keys($ranges);

$records = [];
$make = static function (string $type, int $tenge, string $date, string $category, string $comment) use (&$records): void {
    $records[] = [
        'id' => bin2hex(random_bytes(8)),
        'type' => $type,
        'amount' => $tenge * 100 + (mt_rand(0, 1) === 1 ? 50 : 0),
        'date' => $date,
        'category' => $category,
        'comment' => $comment,
        'created_at' => $date . 'T12:00:00+05:00',
    ];
};

for ($back = 2; $back >= 0; $back--) {
    $start = $today->modify('first day of this month')->modify("-{$back} months");
    $dates = [];
    for ($day = 1; $day <= 28; $day++) {
        $d = $start->setDate((int) $start->format('Y'), (int) $start->format('m'), $day);
        if ($d <= $today) {
            $dates[] = $d;
        }
    }

    foreach ($dates as $d) {
        $dayNumber = (int) $d->format('d');
        if ($dayNumber === 5) {
            $make('income', 60000, $d->format('Y-m-d'), 'Стипендия', 'Стипендия за месяц');
        }
        if ($dayNumber === 15) {
            $make('income', 45000, $d->format('Y-m-d'), 'Подработка', 'Подработка');
        }
        if ($dayNumber === 1) {
            $make('expense', 45000, $d->format('Y-m-d'), 'Жильё', 'Аренда комнаты');
        }
    }

    // 8 случайных расходов в месяц (по доступным датам), комментарий подходит категории.
    for ($i = 0; $i < 8 && $dates !== []; $i++) {
        $d = $dates[mt_rand(0, count($dates) - 1)];
        $category = $categories[mt_rand(0, count($categories) - 1)];
        [$min, $max] = $ranges[$category];
        $options = $comments[$category];
        $make('expense', mt_rand($min, $max), $d->format('Y-m-d'), $category, $options[mt_rand(0, count($options) - 1)]);
    }

    // В текущем месяце гарантированно превышаем лимит «Развлечения» (для демонстрации предупреждения).
    if ($back === 0 && $dates !== []) {
        $first = $dates[0]->format('Y-m-d');
        $make('expense', 6500, $first, 'Развлечения', 'Кино с друзьями');
        $make('expense', 5200, $first, 'Развлечения', 'Кафе с друзьями');
        $make('expense', 4300, $first, 'Развлечения', 'Игры');
    }
}

updateJsonFile(transactionsPath(), static fn (array $current): array => $records, []);
saveLimits(['Питание' => 4000000, 'Развлечения' => 1200000, 'Транспорт' => 1000000]);

echo 'Создано операций: ' . count($records) . ", лимитов: 3\n";
