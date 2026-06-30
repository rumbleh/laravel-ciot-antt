<?php

declare(strict_types=1);

namespace Rumbleh\CiotAntt\Enums;

/**
 * Ambiente do web service PEF da ANTT.
 *
 * Endereços (manual DCS PEF v1.1, pág. 66):
 *  - Homologação: https://appservices-hml.antt.gov.br/pefServices
 *  - Produção:    https://appservices.antt.gov.br/pefServices
 */
enum Ambiente: string
{
    case Homologacao = 'homologacao';
    case Producao = 'producao';

    public function label(): string
    {
        return match ($this) {
            self::Homologacao => 'Homologação',
            self::Producao => 'Produção',
        };
    }

    public function ehProducao(): bool
    {
        return $this === self::Producao;
    }
}
