<?php
declare(strict_types=1);

/**
 * Нормализация строки: убирает управляющие символы, схлопывает пробелы, обрезает края.
 * Некорректная UTF-8 последовательность превращается в пустую строку.
 */
function normalizeText(string $value): string
{
    if (preg_match('//u', $value) !== 1) { // не валидная UTF-8 строка
        return '';
    }
    $value = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value) ?? '';
    $value = preg_replace('/\s+/u', ' ', $value) ?? '';

    return trim($value);
}

/** Длина строки в символах (UTF-8) без расширения mbstring. */
function textLength(string $value): int
{
    return (int) preg_match_all('/./su', $value);
}

/**
 * Разбирает сумму в тиынах (целое число), чтобы не накапливать ошибку float.
 * Допустимо: "150", "150,5", "150.05", "12 000,50". До 9 цифр целой части и до 2 знаков дробной.
 * Возвращает null, если формат неверный. Положительность проверяется отдельно.
 */
function parseAmountMinor(string $value): ?int
{
    $value = str_replace(' ', '', normalizeText($value));
    $value = str_replace(',', '.', $value);

    if (preg_match('/^(\d{1,9})(?:\.(\d{1,2}))?$/', $value, $m) !== 1) {
        return null;
    }

    $fraction = isset($m[2]) ? str_pad($m[2], 2, '0') : '00';

    return ((int) $m[1]) * 100 + (int) $fraction;
}

/** Разбирает дату строго в формате ГГГГ-ММ-ДД; несуществующие даты (2026-02-31) отклоняются. */
function parseDate(string $value): ?DateTimeImmutable
{
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
        return null;
    }

    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    $errors = DateTimeImmutable::getLastErrors();

    if ($date === false) {
        return null;
    }
    if ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
        return null;
    }

    return $date->format('Y-m-d') === $value ? $date : null;
}

function isValidMonth(string $value): bool
{
    return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $value) === 1;
}

/**
 * Проверяет данные формы добавления операции.
 *
 * @param array<mixed> $input обычно $_POST
 * @return array{errors: array<string,string>, values: array<string,string>, record: ?array<string,mixed>}
 */
function validateTransactionInput(array $input, DateTimeImmutable $today): array
{
    $errors = [];
    $values = ['type' => '', 'amount' => '', 'date' => '', 'category' => '', 'comment' => ''];

    // 1. Тип данных и нормализация: из формы должны приходить только строки.
    foreach (array_keys($values) as $field) {
        $raw = $input[$field] ?? '';
        if (!is_string($raw)) {
            $errors[$field] = 'Некорректный тип данных.';
            continue;
        }
        $values[$field] = normalizeText($raw);
    }

    // 2. Тип операции.
    if (!isset($errors['type']) && !array_key_exists($values['type'], transactionTypes())) {
        $errors['type'] = 'Выберите тип операции.';
    }

    // 3. Сумма.
    $amount = null;
    if (!isset($errors['amount'])) {
        if ($values['amount'] === '') {
            $errors['amount'] = 'Укажите сумму.';
        } else {
            $amount = parseAmountMinor($values['amount']);
            if ($amount === null) {
                $errors['amount'] = 'Введите число: до 9 цифр и до 2 знаков после запятой.';
            } elseif ($amount <= 0) {
                $errors['amount'] = 'Сумма должна быть больше нуля.';
            }
        }
    }

    // 4. Дата.
    if (!isset($errors['date'])) {
        if ($values['date'] === '') {
            $errors['date'] = 'Укажите дату.';
        } else {
            $date = parseDate($values['date']);
            if ($date === null) {
                $errors['date'] = 'Дата должна быть в формате ГГГГ-ММ-ДД и существовать в календаре.';
            } elseif ($date > $today) {
                $errors['date'] = 'Дата не может быть в будущем.';
            } elseif ($date < new DateTimeImmutable(MIN_DATE)) {
                $errors['date'] = 'Дата не может быть раньше ' . MIN_DATE . '.';
            }
        }
    }

    // 5. Категория (белый список, зависит от типа).
    if (!isset($errors['category'])) {
        if ($values['category'] === '') {
            $errors['category'] = 'Выберите категорию.';
        } elseif (!isset($errors['type']) && !in_array($values['category'], categoriesFor($values['type']), true)) {
            $errors['category'] = 'Категория не подходит для выбранного типа операции.';
        }
    }

    // 6. Комментарий (необязательный).
    if (!isset($errors['comment']) && textLength($values['comment']) > MAX_COMMENT_LENGTH) {
        $errors['comment'] = 'Комментарий не длиннее ' . MAX_COMMENT_LENGTH . ' символов.';
    }

    $record = null;
    if ($errors === []) {
        $record = [
            'id' => bin2hex(random_bytes(8)),
            'type' => $values['type'],
            'amount' => (int) $amount,
            'date' => $values['date'],
            'category' => $values['category'],
            'comment' => $values['comment'],
            'created_at' => date('c'),
        ];
    }

    return ['errors' => $errors, 'values' => $values, 'record' => $record];
}

/**
 * Проверяет форму лимитов: пустое поле = «без лимита», иначе сумма больше нуля.
 *
 * @return array{errors: array<string,string>, values: array<string,string>, limits: array<string,int>}
 */
function validateLimitsInput(mixed $raw): array
{
    if (!is_array($raw)) {
        return ['errors' => ['_form' => 'Некорректные данные формы.'], 'values' => [], 'limits' => []];
    }

    $errors = [];
    $values = [];
    $limits = [];

    foreach (expenseCategories() as $category) {
        $value = $raw[$category] ?? '';
        if (!is_string($value)) {
            $errors[$category] = 'Некорректный тип данных.';
            $values[$category] = '';
            continue;
        }

        $value = normalizeText($value);
        $values[$category] = $value;
        if ($value === '') {
            continue;
        }

        $minor = parseAmountMinor($value);
        if ($minor === null || $minor <= 0) {
            $errors[$category] = 'Введите сумму больше нуля (например, 25000 или 25000,50) либо оставьте поле пустым.';
            continue;
        }
        $limits[$category] = $minor;
    }

    return ['errors' => $errors, 'values' => $values, 'limits' => $limits];
}

/**
 * Безопасно читает фильтры журнала из $_GET. Неверные значения сбрасываются с пояснением.
 *
 * @param array<mixed> $query
 * @return array{month: string, category: string, notes: list<string>}
 */
function sanitizeFilters(array $query): array
{
    $month = $query['month'] ?? '';
    $category = $query['category'] ?? '';
    $notes = [];

    if (!is_string($month) || ($month !== '' && !isValidMonth($month))) {
        $notes[] = 'Неверный формат месяца — фильтр по месяцу сброшен.';
        $month = '';
    }
    if (!is_string($category) || ($category !== '' && !in_array($category, allCategories(), true))) {
        $notes[] = 'Неизвестная категория — фильтр по категории сброшен.';
        $category = '';
    }

    return ['month' => $month, 'category' => $category, 'notes' => $notes];
}
