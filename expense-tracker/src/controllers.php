<?php
declare(strict_types=1);

/** Маршрутизатор: ?page=journal|add|delete|summary|limits. */
function route(): void
{
    $page = $_GET['page'] ?? 'journal';
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if (!is_string($page)) {
        pageNotFound();
        return;
    }

    switch ($page) {
        case 'journal':
            pageJournal();
            break;
        case 'add':
            $method === 'POST' ? handleAddPost() : pageAddForm();
            break;
        case 'delete':
            if ($method !== 'POST') {
                methodNotAllowed();
                return;
            }
            handleDelete();
            break;
        case 'summary':
            pageSummary();
            break;
        case 'limits':
            $method === 'POST' ? handleLimitsPost() : pageLimitsForm();
            break;
        default:
            pageNotFound();
    }
}

function pageNotFound(): void
{
    render('error', ['title' => 'Не найдено', 'message' => 'Такой страницы нет.'], 404);
}

function methodNotAllowed(): void
{
    header('Allow: POST');
    render('error', ['title' => 'Метод не разрешён', 'message' => 'Это действие выполняется только методом POST.'], 405);
}

function csrfRejected(): void
{
    render('error', [
        'title' => 'Запрос отклонён',
        'message' => 'Недействительный токен безопасности. Обновите страницу и повторите действие.',
    ], 403);
}

function pageJournal(): void
{
    $filters = sanitizeFilters($_GET);
    $all = loadTransactions();
    $items = sortTransactions(filterTransactions($all, $filters['month'], $filters['category']));
    $currentMonth = date('Y-m');

    render('journal', [
        'title' => 'Журнал операций',
        'active' => 'journal',
        'filters' => $filters,
        'items' => $items,
        'income' => sumByType($items, 'income'),
        'expense' => sumByType($items, 'expense'),
        'balanceTotal' => balance($all),
        'months' => availableMonths($all),
        'warnings' => limitOverages($all, loadLimits(), $currentMonth),
        'currentMonth' => $currentMonth,
    ]);
}

function pageAddForm(): void
{
    render('add', [
        'title' => 'Новая операция',
        'active' => 'add',
        'errors' => [],
        'values' => [
            'type' => 'expense',
            'amount' => '',
            'date' => date('Y-m-d'),
            'category' => '',
            'comment' => '',
        ],
    ]);
}

function handleAddPost(): void
{
    if (!csrfVerify($_SESSION, $_POST['_csrf'] ?? null)) {
        csrfRejected();
        return;
    }

    $result = validateTransactionInput($_POST, new DateTimeImmutable('today'));
    if ($result['record'] === null) {
        render('add', [
            'title' => 'Новая операция',
            'active' => 'add',
            'errors' => $result['errors'],
            'values' => $result['values'],
        ], 422);
        return;
    }

    addTransaction($result['record']);
    setFlash($_SESSION, 'success', 'Операция добавлена.');
    redirect(url('journal')); // Post/Redirect/Get: F5 не повторит добавление
}

function handleDelete(): void
{
    if (!csrfVerify($_SESSION, $_POST['_csrf'] ?? null)) {
        csrfRejected();
        return;
    }

    $id = $_POST['id'] ?? null;
    if (!is_string($id) || preg_match('/^[a-f0-9]{16}$/', $id) !== 1) {
        setFlash($_SESSION, 'error', 'Некорректный идентификатор операции.');
        redirect(url('journal'));
    }

    $deleted = deleteTransaction($id);
    setFlash($_SESSION, $deleted ? 'success' : 'error', $deleted ? 'Операция удалена.' : 'Операция не найдена.');
    redirect(url('journal'));
}

function pageSummary(): void
{
    $notes = [];
    $month = $_GET['month'] ?? date('Y-m');
    if (!is_string($month) || !isValidMonth($month)) {
        $notes[] = 'Неверный формат месяца — показан текущий месяц.';
        $month = date('Y-m');
    }

    $all = loadTransactions();
    $monthItems = filterTransactions($all, $month, '');
    $limits = loadLimits();
    $byCategory = expensesByCategory($monthItems);

    $rows = [];
    foreach (expenseCategories() as $category) {
        $spent = $byCategory[$category] ?? 0;
        $limit = $limits[$category] ?? null;
        $rows[] = [
            'category' => $category,
            'spent' => $spent,
            'limit' => $limit,
            'status' => limitStatus($spent, $limit),
        ];
    }

    render('summary', [
        'title' => 'Месячная сводка',
        'active' => 'summary',
        'month' => $month,
        'months' => availableMonths($all),
        'notes' => $notes,
        'income' => sumByType($monthItems, 'income'),
        'expense' => sumByType($monthItems, 'expense'),
        'balanceTotal' => balance($all),
        'rows' => $rows,
        'overages' => limitOverages($all, $limits, $month),
        'count' => count($monthItems),
    ]);
}

function pageLimitsForm(): void
{
    $values = [];
    foreach (loadLimits() as $category => $minor) {
        $values[$category] = minorToInput($minor);
    }

    render('limits', ['title' => 'Лимиты категорий', 'active' => 'limits', 'errors' => [], 'values' => $values]);
}

function handleLimitsPost(): void
{
    if (!csrfVerify($_SESSION, $_POST['_csrf'] ?? null)) {
        csrfRejected();
        return;
    }

    $result = validateLimitsInput($_POST['limits'] ?? []);
    if ($result['errors'] !== []) {
        render('limits', [
            'title' => 'Лимиты категорий',
            'active' => 'limits',
            'errors' => $result['errors'],
            'values' => $result['values'],
        ], 422);
        return;
    }

    saveLimits($result['limits']);
    setFlash($_SESSION, 'success', 'Лимиты сохранены.');
    redirect(url('summary'));
}
