<?php

declare(strict_types=1);

namespace Rumbleh\CiotAntt\Enums;

/**
 * Indicador de pagamento (campo InfPagamento.IndPagamento).
 *
 * Manual DCS PEF v1.1, pág. 23 (item 16.9): 0 = à vista / 1 = a prazo.
 */
enum IndPagamento: int
{
    case AVista = 0;
    case APrazo = 1;

    public function label(): string
    {
        return match ($this) {
            self::AVista => 'À vista',
            self::APrazo => 'A prazo',
        };
    }

    /** Quando a prazo, os campos de parcelamento são obrigatórios (regra B105). */
    public function exigeParcelas(): bool
    {
        return $this === self::APrazo;
    }
}
