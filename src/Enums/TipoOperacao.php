<?php

declare(strict_types=1);

namespace Rumbleh\CiotAntt\Enums;

/**
 * Tipo da operação de transporte (campo TipoOperacao).
 *
 * Manual DCS PEF v1.1, serviço DeclaracaoOperacaoTransporte (pág. 18).
 */
enum TipoOperacao: int
{
    case CargaLotacao = 1;
    case CargaFracionada = 2;
    case TacAgregado = 3;

    public function label(): string
    {
        return match ($this) {
            self::CargaLotacao => 'Operação Carga Lotação',
            self::CargaFracionada => 'Operação Carga Fracionada',
            self::TacAgregado => 'Operação TAC-Agregado',
        };
    }

    /** Operações de carga (lotação ou fracionada) compartilham várias regras. */
    public function ehCarga(): bool
    {
        return $this === self::CargaLotacao || $this === self::CargaFracionada;
    }
}
