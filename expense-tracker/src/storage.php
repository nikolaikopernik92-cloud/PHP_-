<?php
declare(strict_types=1);

/**
 * Контролируемая ошибка хранилища. Текст сообщения безопасен для показа пользователю
 * (без путей и технических деталей).
 */
final class StorageException extends RuntimeException
{
}

/**
 * Читает JSON-файл. Нет файла или он пустой — вернёт $default.
 * Повреждённый JSON или неверная структура — StorageException.
 *
 * @param array<mixed> $default
 * @return array<mixed>
 */
function readJsonFile(string $path, array $default): array
{
    if (!is_file($path)) {
        return $default;
    }

    $raw = @file_get_contents($path);
    if ($raw === false) {
        throw new StorageException('Не удалось прочитать файл данных.');
    }
    if (trim($raw) === '') {
        return $default;
    }

    try {
        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        throw new StorageException('Файл данных повреждён (некорректный JSON).', 0, $e);
    }

    if (!is_array($data)) {
        throw new StorageException('Файл данных имеет неверную структуру.');
    }

    return $data;
}

/**
 * Атомарно изменяет JSON-файл: «прочитать → изменить → записать» под эксклюзивной блокировкой.
 * Запись идёт во временный файл, затем rename() подменяет оригинал целиком,
 * поэтому читатель никогда не увидит наполовину записанный файл.
 *
 * @param callable(array<mixed>): array<mixed> $mutator
 * @param array<mixed> $default
 * @return array<mixed> новое содержимое
 */
function updateJsonFile(string $path, callable $mutator, array $default): array
{
    $dir = dirname($path);
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new StorageException('Каталог данных недоступен.');
    }
    if (!is_writable($dir)) {
        throw new StorageException('Каталог данных недоступен для записи.');
    }

    $lock = @fopen($path . '.lock', 'c');
    if ($lock === false) {
        throw new StorageException('Не удалось создать файл блокировки.');
    }

    try {
        if (!flock($lock, LOCK_EX)) {
            throw new StorageException('Не удалось получить блокировку файла данных.');
        }

        $updated = $mutator(readJsonFile($path, $default));

        try {
            $json = json_encode($updated, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        } catch (JsonException $e) {
            throw new StorageException('Не удалось сформировать данные для записи.', 0, $e);
        }

        $tmp = $path . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (@file_put_contents($tmp, $json . "\n") === false) {
            throw new StorageException('Не удалось записать данные.');
        }
        if (!@rename($tmp, $path)) {
            @unlink($tmp);
            throw new StorageException('Не удалось заменить файл данных.');
        }

        return $updated;
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}
