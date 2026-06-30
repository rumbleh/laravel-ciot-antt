<?php

declare(strict_types=1);

namespace Rumbleh\CiotAntt\Enums;

/**
 * Categoria do transportador no RNTRC (campo TipoTransportador).
 *
 * Manual DCS PEF v1.1, serviço ConsultarSituacaoTransportador (pág. 12).
 *  - TAC: Transportador Autônomo de Cargas
 *  - ETC: Empresa de Transporte Rodoviário de Cargas
 *  - CTC: Cooperativa de Transporte de Cargas
 */
enum TipoTransportador: string
{
    case TAC = 'TAC';
    case ETC = 'ETC';
    case CTC = 'CTC';

    public function label(): string
    {
        return match ($this) {
            self::TAC => 'Transportador Autônomo de Cargas (TAC)',
            self::ETC => 'Empresa de Transporte Rodoviário de Cargas (ETC)',
            self::CTC => 'Cooperativa de Transporte de Cargas (CTC)',
        };
    }
}
