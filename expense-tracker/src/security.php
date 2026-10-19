<?php
declare(strict_types=1);

/** Запускает сессию с безопасными параметрами cookie. */
function startSecureSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/**
 * CSRF-токен хранится в сессии (на клиенте только копия в скрытом поле формы).
 *
 * @param array<string,mixed> $session обычно $_SESSION
 */
function csrfToken(array &$session): string
{
    if (!isset($session['csrf']) || !is_string($session['csrf'])) {
        $session['csrf'] = bin2hex(random_bytes(32));
    }

    return $session['csrf'];
}

/** Сравнивает присланный токен с сессионным в постоянное время (hash_equals). */
function csrfVerify(array $session, mixed $token): bool
{
    return is_string($token)
        && isset($session['csrf'])
        && is_string($session['csrf'])
        && hash_equals($session['csrf'], $token);
}

/** Flash-сообщение живёт ровно до следующего показа. */
function setFlash(array &$session, string $type, string $text): void
{
    $session['flash'] = ['type' => $type, 'text' => $text];
}

/** @return array{type: string, text: string}|null */
function pullFlash(array &$session): ?array
{
    $flash = $session['flash'] ?? null;
    unset($session['flash']);

    return (is_array($flash) && isset($flash['type'], $flash['text'])) ? $flash : null;
}
