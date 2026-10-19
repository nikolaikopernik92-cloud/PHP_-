<?php
declare(strict_types=1);

/** Экранирование динамического текста перед выводом в HTML. */
function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Ссылка на страницу приложения (результат нужно дополнительно экранировать через h()). */
function url(string $page, array $params = []): string
{
    return 'index.php?' . http_build_query(['page' => $page] + $params);
}

/** 150050 тиынов => «1 500,50 ₸». */
function formatMoney(int $minor): string
{
    $abs = abs($minor);
    $text = number_format($abs / 100, 2, ',', "\u{00A0}") . "\u{00A0}₸";

    return $minor < 0 ? '−' . $text : $text;
}

/** 150050 => «1500.50» (для значения в поле формы). */
function minorToInput(int $minor): string
{
    return sprintf('%d.%02d', intdiv($minor, 100), $minor % 100);
}

function formatDate(string $iso): string
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $iso);

    return $date === false ? $iso : $date->format('d.m.Y');
}

/** «2026-10» => «Октябрь 2026». */
function formatMonth(string $yearMonth): string
{
    $names = [1 => 'Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь', 'Июль', 'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь'];
    if (!isValidMonth($yearMonth)) {
        return $yearMonth;
    }
    [$year, $month] = explode('-', $yearMonth);

    return $names[(int) $month] . ' ' . $year;
}

/** HTML-подсказка об ошибке поля (текст экранируется внутри). */
function fieldError(array $errors, string $field): string
{
    return isset($errors[$field]) ? '<p class="err">' . h($errors[$field]) . '</p>' : '';
}

/** Подключает шаблон и оборачивает его в layout. Имя шаблона задаёт только код приложения. */
function render(string $template, array $vars = [], int $status = 200): void
{
    http_response_code($status);
    extract($vars, EXTR_SKIP);

    ob_start();
    try {
        require TEMPLATES_DIR . '/' . $template . '.php';
    } catch (Throwable $e) {
        ob_end_clean();
        throw $e;
    }
    $content = ob_get_clean();

    require TEMPLATES_DIR . '/layout.php';
}

/** Перенаправление (Post/Redirect/Get). Принимает только адреса, построенные через url(). */
function redirect(string $location): never
{
    header('Location: ' . $location, true, 303);
    exit;
}
