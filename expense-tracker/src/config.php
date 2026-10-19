<?php
declare(strict_types=1);

/** Каталог шаблонов представления. */
const TEMPLATES_DIR = __DIR__ . '/../templates';

/** Самая ранняя допустимая дата операции. */
const MIN_DATE = '2000-01-01';

/** Максимальная длина комментария (в символах). */
const MAX_COMMENT_LENGTH = 200;

/**
 * Каталог данных. Лежит вне public/, поэтому JSON нельзя открыть по URL.
 * Переменная окружения TRACKER_DATA_DIR нужна для тестов (временный каталог).
 */
function dataDir(): string
{
    $env = getenv('TRACKER_DATA_DIR');

    return (is_string($env) && $env !== '') ? $env : __DIR__ . '/../var/data';
}

function transactionsPath(): string
{
    return dataDir() . '/transactions.json';
}

function limitsPath(): string
{
    return dataDir() . '/limits.json';
}

/** @return list<string> */
function expenseCategories(): array
{
    return ['Питание', 'Транспорт', 'Жильё', 'Учёба', 'Развлечения', 'Здоровье', 'Связь', 'Другое'];
}

/** @return list<string> */
function incomeCategories(): array
{
    return ['Стипендия', 'Подработка', 'Помощь семьи', 'Другое'];
}

/** @return list<string> */
function allCategories(): array
{
    return array_values(array_unique(array_merge(expenseCategories(), incomeCategories())));
}

/** @return list<string> */
function categoriesFor(string $type): array
{
    return $type === 'income' ? incomeCategories() : expenseCategories();
}

/** @return array<string, string> код типа => подпись */
function transactionTypes(): array
{
    return ['expense' => 'Расход', 'income' => 'Доход'];
}
