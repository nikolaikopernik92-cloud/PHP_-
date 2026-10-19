<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

// Технические детали не показываем пользователю, пишем в журнал ошибок PHP.
ini_set('display_errors', '0');

startSecureSession();

try {
    route();
} catch (StorageException $e) {
    error_log('[tracker] storage: ' . $e->getMessage());
    render('error', ['title' => 'Ошибка хранилища', 'message' => $e->getMessage()], 500);
} catch (Throwable $e) {
    error_log('[tracker] ' . $e);
    render('error', ['title' => 'Ошибка', 'message' => 'Произошла внутренняя ошибка. Попробуйте позже.'], 500);
}
