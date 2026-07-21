<?php

namespace App\Support;

/**
 * Normaliza o filtro de empresa usado em dashboards e telas:
 * null = todas · 'none' = sem empresa · int = uma empresa específica.
 */
class CompanyFilter
{
    public const NONE = 'none';

    public static function normalize(mixed $value): int|string|null
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value === self::NONE) {
            return self::NONE;
        }

        return (int) $value;
    }

    /** Aplica o filtro numa query cuja tabela tem a coluna company_id (ex: assets). */
    public static function applyToCompanyColumn(mixed $query, int|string|null $companyId): mixed
    {
        return $query
            ->when($companyId === self::NONE, fn ($q) => $q->whereNull('company_id'))
            ->when(is_int($companyId), fn ($q) => $q->where('company_id', $companyId));
    }
}
