<?php
declare(strict_types=1);

/**
 * Автотесты без внешних библиотек.
 * Запуск:  php tests/run.php
 * Код возврата: 0 — все проверки пройдены, 1 — есть ошибки.
 * Данные пишутся во временный каталог и удаляются в конце.
 */

$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tracker_test_' . bin2hex(random_bytes(4));
putenv('TRACKER_DATA_DIR=' . $tmp);

require __DIR__ . '/../src/bootstrap.php';

$passed = 0;
$failed = 0;

function check(string $name, bool $condition): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "  [PASS] $name\n";
    } else {
        $failed++;
        echo "  [FAIL] $name\n";
    }
}

function section(string $title): void
{
    echo "\n== $title ==\n";
}

function throwsStorage(callable $fn): bool
{
    try {
        $fn();
    } catch (StorageException) {
        return true;
    }

    return false;
}

$today = new DateTimeImmutable('today');
$good = ['type' => 'expense', 'amount' => '1500,50', 'date' => $today->format('Y-m-d'), 'category' => 'Питание', 'comment' => 'Обед'];

/* ---------- 1. Обычный сценарий ---------- */
section('Обычный сценарий');
$r = validateTransactionInput($good, $today);
check('корректный ввод проходит валидацию', $r['record'] !== null && $r['errors'] === []);
check('сумма "1500,50" хранится как 150050 тиынов', ($r['record']['amount'] ?? null) === 150050);
check('id записи — 16 hex-символов', preg_match('/^[a-f0-9]{16}$/', (string) ($r['record']['id'] ?? '')) === 1);
check('parseAmountMinor("150") = 15000', parseAmountMinor('150') === 15000);
check('parseAmountMinor("150.05") = 15005', parseAmountMinor('150.05') === 15005);
check('parseAmountMinor("150,5") = 15050', parseAmountMinor('150,5') === 15050);
check('parseAmountMinor("12 000,50") = 1200050', parseAmountMinor('12 000,50') === 1200050);
check('formatMoney(150050) содержит "1 500,50"', str_contains(formatMoney(150050), "1\u{00A0}500,50"));

/* ---------- 2. Пустые значения ---------- */
section('Пустые значения');
$r = validateTransactionInput([], $today);
check('пустая форма: ошибки по сумме, дате, категории, типу',
    isset($r['errors']['amount'], $r['errors']['date'], $r['errors']['category'], $r['errors']['type']) && $r['record'] === null);
$r = validateTransactionInput(array_merge($good, ['amount' => '   ']), $today);
check('сумма из пробелов считается пустой', isset($r['errors']['amount']));
$r = validateTransactionInput(array_merge($good, ['comment' => '']), $today);
check('пустой комментарий допустим', $r['record'] !== null);

/* ---------- 3. Граничные значения ---------- */
section('Граничные значения');
$r = validateTransactionInput(array_merge($good, ['amount' => '0']), $today);
check('сумма 0 отклоняется', isset($r['errors']['amount']));
$r = validateTransactionInput(array_merge($good, ['amount' => '0,01']), $today);
check('минимальная сумма 0,01 допустима', ($r['record']['amount'] ?? null) === 1);
check('максимальная сумма 999999999.99 допустима', parseAmountMinor('999999999.99') === 99999999999);
check('10 цифр в целой части отклоняются', parseAmountMinor('1234567890') === null);
check('3 знака после запятой отклоняются', parseAmountMinor('1.234') === null);
$r = validateTransactionInput(array_merge($good, ['date' => $today->modify('+1 day')->format('Y-m-d')]), $today);
check('завтрашняя дата отклоняется', isset($r['errors']['date']));
$r = validateTransactionInput(array_merge($good, ['date' => '2000-01-01']), $today);
check('минимальная дата 2000-01-01 допустима', $r['record'] !== null);
$r = validateTransactionInput(array_merge($good, ['date' => '1999-12-31']), $today);
check('дата раньше 2000-01-01 отклоняется', isset($r['errors']['date']));
$r = validateTransactionInput(array_merge($good, ['comment' => str_repeat('я', MAX_COMMENT_LENGTH)]), $today);
check('комментарий ровно 200 символов допустим', $r['record'] !== null);
$r = validateTransactionInput(array_merge($good, ['comment' => str_repeat('я', MAX_COMMENT_LENGTH + 1)]), $today);
check('комментарий 201 символ отклоняется', isset($r['errors']['comment']));

/* ---------- 4. Некорректный тип и формат ---------- */
section('Некорректный тип и формат');
$r = validateTransactionInput(array_merge($good, ['amount' => ['1']]), $today);
check('массив вместо суммы отклоняется', isset($r['errors']['amount']) && $r['record'] === null);
$r = validateTransactionInput(array_merge($good, ['category' => ['Питание']]), $today);
check('массив вместо категории отклоняется', isset($r['errors']['category']));
check('текст вместо числа: parseAmountMinor("abc") = null', parseAmountMinor('abc') === null);
check('отрицательная сумма: parseAmountMinor("-5") = null', parseAmountMinor('-5') === null);
check('экспонента: parseAmountMinor("1e3") = null', parseAmountMinor('1e3') === null);
check('несуществующая дата 2026-02-31 отклоняется', parseDate('2026-02-31') === null);
check('формат 18.10.2026 отклоняется', parseDate('18.10.2026') === null);
check('корректная дата 2026-10-18 принимается', parseDate('2026-10-18') !== null);
$r = validateTransactionInput(array_merge($good, ['category' => 'Стипендия']), $today);
check('доходная категория для расхода отклоняется', isset($r['errors']['category']));
$r = validateTransactionInput(array_merge($good, ['type' => 'income', 'category' => 'Стипендия']), $today);
check('доходная категория для дохода допустима', $r['record'] !== null);
$r = validateTransactionInput(array_merge($good, ['category' => 'Несуществующая']), $today);
check('категория вне белого списка отклоняется', isset($r['errors']['category']));
$r = validateTransactionInput(array_merge($good, ['type' => 'transfer']), $today);
check('неизвестный тип операции отклоняется', isset($r['errors']['type']));
$r = validateTransactionInput(array_merge($good, ['comment' => "строка1\nстрока2\t  конец"]), $today);
check('нормализация: переносы и табы схлопываются в пробел', ($r['record']['comment'] ?? '') === 'строка1 строка2 конец');
$xss = '<script>alert(1)</script>';
$r = validateTransactionInput(array_merge($good, ['comment' => $xss]), $today);
check('HTML в комментарии сохраняется как текст', ($r['record']['comment'] ?? '') === $xss);
check('h() экранирует теги', !str_contains(h($xss), '<script>') && str_contains(h($xss), '&lt;script&gt;'));
check('h() экранирует кавычки', h('"\'') === '&quot;&#039;');

/* ---------- 5. Фильтры и итоги (array_filter / array_reduce) ---------- */
section('Фильтры и итоги');
$items = [
    ['id' => 'a', 'type' => 'income', 'amount' => 5000000, 'date' => '2026-10-05', 'category' => 'Стипендия', 'created_at' => '2026-10-05T10:00:00+05:00'],
    ['id' => 'b', 'type' => 'expense', 'amount' => 150050, 'date' => '2026-10-06', 'category' => 'Питание', 'created_at' => '2026-10-06T10:00:00+05:00'],
    ['id' => 'c', 'type' => 'expense', 'amount' => 250000, 'date' => '2026-10-07', 'category' => 'Транспорт', 'created_at' => '2026-10-07T10:00:00+05:00'],
    ['id' => 'd', 'type' => 'expense', 'amount' => 100000, 'date' => '2026-09-20', 'category' => 'Питание', 'created_at' => '2026-09-20T10:00:00+05:00'],
];
check('сумма доходов', sumByType($items, 'income') === 5000000);
check('сумма расходов', sumByType($items, 'expense') === 500050);
check('остаток средств', balance($items) === 4499950);
check('сумма по пустому списку = 0', sumByType([], 'expense') === 0);
check('фильтр по месяцу 2026-10', count(filterTransactions($items, '2026-10', '')) === 3);
check('фильтр по категории Питание', count(filterTransactions($items, '', 'Питание')) === 2);
check('фильтр месяц + категория', count(filterTransactions($items, '2026-10', 'Питание')) === 1);
check('без фильтров возвращает всё', count(filterTransactions($items, '', '')) === 4);
$byCat = expensesByCategory($items);
check('расходы по категориям: Питание = 250050', ($byCat['Питание'] ?? 0) === 250050);
check('категории отсортированы по убыванию суммы: Питание первая', array_key_first($byCat) === 'Питание');
check('textLength считает символы, а не байты', textLength('яя') === 2);
check('сортировка: новые операции первыми', sortTransactions($items)[0]['id'] === 'c');
check('availableMonths содержит текущий месяц', in_array(date('Y-m'), availableMonths($items), true));

/* ---------- 6. Лимиты ---------- */
section('Лимиты категорий');
check('лимит не задан → none', limitStatus(1000, null)['state'] === 'none');
check('79% → ok', limitStatus(79, 100)['state'] === 'ok');
check('80% → warn', limitStatus(80, 100)['state'] === 'warn');
check('ровно 100% → warn (не превышен)', limitStatus(100, 100)['state'] === 'warn');
check('101 из 100 → over', limitStatus(101, 100)['state'] === 'over');
$over = limitOverages($items, ['Питание' => 100000, 'Транспорт' => 300000], '2026-10');
check('превышен только лимит Питания за октябрь', array_keys($over) === ['Питание'] && $over['Питание']['over'] === 50050);
$lim = validateLimitsInput(['Питание' => '40 000', 'Транспорт' => '', 'Учёба' => '0', 'Связь' => 'abc', 'Здоровье' => ['1']]);
check('лимит 40 000 принят (4 000 000 тиынов)', ($lim['limits']['Питание'] ?? null) === 4000000);
check('пустой лимит пропускается', !isset($lim['limits']['Транспорт']) && !isset($lim['errors']['Транспорт']));
check('нулевой лимит отклоняется', isset($lim['errors']['Учёба']));
check('текстовый лимит отклоняется', isset($lim['errors']['Связь']));
check('массив вместо лимита отклоняется', isset($lim['errors']['Здоровье']));
check('форма лимитов не массив → ошибка формы', isset(validateLimitsInput('x')['errors']['_form']));

/* ---------- 7. Фильтры из GET ---------- */
section('Безопасное чтение фильтров');
$f = sanitizeFilters(['month' => '2026-13', 'category' => 'Взлом']);
check('неверный месяц и категория сбрасываются', $f['month'] === '' && $f['category'] === '' && count($f['notes']) === 2);
$f = sanitizeFilters(['month' => ['x'], 'category' => ['y']]);
check('массивы в фильтрах сбрасываются', $f['month'] === '' && $f['category'] === '');
$f = sanitizeFilters(['month' => '2026-10', 'category' => 'Питание']);
check('корректные фильтры принимаются', $f['month'] === '2026-10' && $f['category'] === 'Питание' && $f['notes'] === []);

/* ---------- 8. CSRF ---------- */
section('CSRF-токен');
$session = [];
$token = csrfToken($session);
check('токен: 64 hex-символа', preg_match('/^[a-f0-9]{64}$/', $token) === 1);
check('токен стабилен в пределах сессии', csrfToken($session) === $token);
check('верный токен принимается', csrfVerify($session, $token));
check('неверный токен отклоняется', !csrfVerify($session, str_repeat('0', 64)));
check('отсутствующий токен (null) отклоняется', !csrfVerify($session, null));
check('пустой токен отклоняется', !csrfVerify($session, ''));
check('токен-массив отклоняется', !csrfVerify($session, [$token]));
check('пустая сессия: любой токен отклоняется', !csrfVerify([], $token));
$flashSession = [];
setFlash($flashSession, 'success', 'ok');
check('flash читается один раз', pullFlash($flashSession) !== null && pullFlash($flashSession) === null);

/* ---------- 9. Хранилище JSON ---------- */
section('Хранилище JSON');
check('нет файла → пустой список', loadTransactions() === []);
$r1 = validateTransactionInput($good, $today);
addTransaction($r1['record']);
check('запись добавлена и читается', count(loadTransactions()) === 1);
check('файл данных создан', is_file(transactionsPath()));
check('временные .tmp-файлы не остаются', glob($tmp . '/*.tmp') === []);
for ($i = 0; $i < 30; $i++) {
    $rr = validateTransactionInput($good, $today);
    addTransaction($rr['record']);
}
check('после 30 добавлений подряд записей 31 (без потерь)', count(loadTransactions()) === 31);
check('удаление существующей записи возвращает true', deleteTransaction($r1['record']['id']) === true);
check('после удаления записей 30', count(loadTransactions()) === 30);
check('повторное удаление возвращает false', deleteTransaction($r1['record']['id']) === false);
saveLimits(['Питание' => 4000000]);
check('лимиты сохраняются и читаются', loadLimits() === ['Питание' => 4000000]);

file_put_contents(transactionsPath(), '{это не json');
check('повреждённый JSON → StorageException', throwsStorage(fn () => loadTransactions()));
file_put_contents(transactionsPath(), '{"a": 1}');
check('неверная структура записи → StorageException', throwsStorage(fn () => loadTransactions()));
file_put_contents(transactionsPath(), '"строка"');
check('JSON не массив → StorageException', throwsStorage(fn () => loadTransactions()));
file_put_contents(limitsPath(), '{"Питание": -5}');
check('отрицательный лимит в файле → StorageException', throwsStorage(fn () => loadLimits()));
file_put_contents(transactionsPath(), '');
check('пустой файл считается пустым списком', loadTransactions() === []);

$blocker = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tracker_blocker_' . bin2hex(random_bytes(4));
file_put_contents($blocker, 'x');
putenv('TRACKER_DATA_DIR=' . $blocker); // «каталог» данных — на самом деле обычный файл
check('недоступный каталог → StorageException', throwsStorage(fn () => addTransaction($r1['record'])));
putenv('TRACKER_DATA_DIR=' . $tmp);
unlink($blocker);

/* ---------- Очистка и итог ---------- */
foreach (glob($tmp . '/*') ?: [] as $file) {
    @unlink($file);
}
@rmdir($tmp);

echo "\nИтог: пройдено $passed, провалено $failed\n";
exit($failed === 0 ? 0 : 1);
