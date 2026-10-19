<?php
declare(strict_types=1);

/** Проверяет структуру одной записи из JSON; при ошибке бросает StorageException. */
function assertTransactionShape(mixed $record): void
{
    $ok = is_array($record)
        && isset($record['id'], $record['type'], $record['date'], $record['category'], $record['amount'])
        && is_string($record['id'])
        && is_string($record['type'])
        && is_string($record['date'])
        && is_string($record['category'])
        && is_int($record['amount'])
        && array_key_exists($record['type'], transactionTypes());

    if (!$ok) {
        throw new StorageException('Запись в файле данных имеет неверную структуру.');
    }
}

/** @return list<array<string,mixed>> */
function loadTransactions(): array
{
    $data = readJsonFile(transactionsPath(), []);
    foreach ($data as $record) {
        assertTransactionShape($record);
    }

    return array_values($data);
}

/** @param array<string,mixed> $record */
function addTransaction(array $record): void
{
    updateJsonFile(
        transactionsPath(),
        static function (array $items) use ($record): array {
            $items[] = $record;

            return $items;
        },
        []
    );
}

/** Удаляет запись по id. Возвращает true, если запись была найдена. */
function deleteTransaction(string $id): bool
{
    $found = false;

    updateJsonFile(
        transactionsPath(),
        static function (array $items) use ($id, &$found): array {
            $kept = [];
            foreach ($items as $item) {
                if (is_array($item) && ($item['id'] ?? null) === $id) {
                    $found = true;
                    continue;
                }
                $kept[] = $item;
            }

            return $kept;
        },
        []
    );

    return $found;
}

/**
 * Новые операции сверху: сначала по дате, затем по времени создания.
 *
 * @param list<array<string,mixed>> $items
 * @return list<array<string,mixed>>
 */
function sortTransactions(array $items): array
{
    usort(
        $items,
        static fn (array $a, array $b): int => [$b['date'], $b['created_at'] ?? ''] <=> [$a['date'], $a['created_at'] ?? '']
    );

    return $items;
}

/**
 * Фильтр по месяцу (ГГГГ-ММ) и категории; пустая строка = «без ограничения».
 *
 * @param list<array<string,mixed>> $items
 * @return list<array<string,mixed>>
 */
function filterTransactions(array $items, string $month, string $category): array
{
    return array_values(array_filter(
        $items,
        static function (array $t) use ($month, $category): bool {
            if ($month !== '' && substr($t['date'], 0, 7) !== $month) {
                return false;
            }
            if ($category !== '' && $t['category'] !== $category) {
                return false;
            }

            return true;
        }
    ));
}

/** Сумма в тиынах по типу операции: array_filter отбирает, array_reduce суммирует. */
function sumByType(array $items, string $type): int
{
    $selected = array_filter($items, static fn (array $t): bool => $t['type'] === $type);

    return array_reduce($selected, static fn (int $carry, array $t): int => $carry + $t['amount'], 0);
}

/** Остаток средств = доходы − расходы. */
function balance(array $items): int
{
    return sumByType($items, 'income') - sumByType($items, 'expense');
}

/**
 * Расходы по категориям (по убыванию суммы).
 *
 * @return array<string,int>
 */
function expensesByCategory(array $items): array
{
    $expenses = array_filter($items, static fn (array $t): bool => $t['type'] === 'expense');

    $totals = array_reduce(
        $expenses,
        static function (array $carry, array $t): array {
            $carry[$t['category']] = ($carry[$t['category']] ?? 0) + $t['amount'];

            return $carry;
        },
        []
    );
    arsort($totals);

    return $totals;
}

/**
 * Месяцы, в которых есть операции, плюс текущий; новые первыми.
 *
 * @return list<string>
 */
function availableMonths(array $items): array
{
    $months = array_map(static fn (array $t): string => substr($t['date'], 0, 7), $items);
    $months[] = date('Y-m');
    $months = array_values(array_unique($months));
    rsort($months);

    return $months;
}

/* ---------- Лимиты категорий (задание повышенной сложности) ---------- */

/** @return array<string,int> категория => лимит в тиынах */
function loadLimits(): array
{
    $data = readJsonFile(limitsPath(), []);
    foreach ($data as $category => $limit) {
        if (!is_string($category) || !in_array($category, expenseCategories(), true) || !is_int($limit) || $limit <= 0) {
            throw new StorageException('Файл лимитов имеет неверную структуру.');
        }
    }

    return $data;
}

/** @param array<string,int> $limits */
function saveLimits(array $limits): void
{
    updateJsonFile(limitsPath(), static fn (array $current): array => $limits, []);
}

/**
 * Состояние лимита: none (не задан), ok, warn (≥ 80 %), over (превышен).
 *
 * @return array{state: string, percent: int}
 */
function limitStatus(int $spent, ?int $limit): array
{
    if ($limit === null) {
        return ['state' => 'none', 'percent' => 0];
    }

    $percent = (int) floor($spent * 100 / $limit);
    if ($spent > $limit) {
        $state = 'over';
    } elseif ($percent >= 80) {
        $state = 'warn';
    } else {
        $state = 'ok';
    }

    return ['state' => $state, 'percent' => $percent];
}

/**
 * Категории, где расходы за месяц превысили лимит.
 *
 * @param array<string,int> $limits
 * @return array<string, array{spent:int, limit:int, over:int}>
 */
function limitOverages(array $items, array $limits, string $month): array
{
    $spentByCategory = expensesByCategory(filterTransactions($items, $month, ''));
    $result = [];

    foreach ($limits as $category => $limit) {
        $spent = $spentByCategory[$category] ?? 0;
        if ($spent > $limit) {
            $result[$category] = ['spent' => $spent, 'limit' => $limit, 'over' => $spent - $limit];
        }
    }

    return $result;
}
