<?php

namespace App\Core;

/**
 * Small helpers for building SQL fragments safely.
 */
final class Sql
{
    /**
     * "%term%" for a LIKE clause. Escapes \ % _ with MySQL's default escape character (\),
     * so no ESCAPE clause is needed. Always bind the result as a parameter.
     */
    public static function contains(string $term): string
    {
        return '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term) . '%';
    }

    /**
     * " LIMIT n OFFSET m" built from validated integers (LIMIT/OFFSET cannot be bound
     * with native prepared statements, so they are clamped and inlined).
     */
    public static function limitOffset(int $limit, int $offset): string
    {
        $limit = max(1, min($limit, 100));
        $offset = max(0, $offset);
        return ' LIMIT ' . $limit . ' OFFSET ' . $offset;
    }
}
