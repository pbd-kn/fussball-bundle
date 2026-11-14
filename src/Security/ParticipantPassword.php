<?php

declare(strict_types=1);

namespace PBDKN\FussballBundle\Security;

final class ParticipantPassword
{
    public static function isHash(string $value): bool
    {
        return !empty(password_get_info($value)['algo']);
    }

    public static function hash(string $value): string
    {
        // Avoid silent truncation by bcrypt and reject NUL before calling PHP.
        if (strlen($value) > 72 || str_contains($value, "\0")) {
            throw new \InvalidArgumentException('Das Passwort darf höchstens 72 Bytes enthalten und keine Nullzeichen.');
        }
        return password_hash($value, PASSWORD_DEFAULT);
    }

    public static function newHash(string $value): string
    {
        if (!mb_check_encoding($value, 'UTF-8') || mb_strlen($value, 'UTF-8') < 12) {
            throw new \InvalidArgumentException('Ein neues Passwort muss mindestens 12 Zeichen enthalten.');
        }
        return self::hash($value);
    }

    public function save(string $value): string
    {
        return self::isHash($value) ? $value : self::newHash($value);
    }
}
