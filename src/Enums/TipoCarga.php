<?php

declare(strict_types=1);

namespace Rumbleh\CiotAntt\Enums;

/**
 * Tipo da carga (campo DadosCarga.CodigoTipoCarga).
 *
 * Manual DCS PEF v1.1, pág. 21-22 (item 15.3).
 */
enum TipoCarga: int
{
    case GranelSolido = 1;
    case GranelLiquido = 2;
    case FrigorificadaOuAquecida = 3;
    case Conteinerizada = 4;
    case CargaGeral = 5;
    case Neogranel = 6;
    case PerigosaGranelSolido = 7;
    case PerigosaGranelLiquido = 8;
    case PerigosaFrigorificadaOuAquecida = 9;
    case PerigosaConteinerizada = 10;
    case PerigosaCargaGeral = 11;
    case CargaGranelPressurizada = 12;

    public function label(): string
    {
        return match ($this) {
            self::GranelSolido => 'Granel sólido',
            self::GranelLiquido => 'Granel líquido',
            self::FrigorificadaOuAquecida => 'Frigorificada ou Aquecida',
            self::Conteinerizada => 'Conteinerizada',
            self::CargaGeral => 'Carga Geral',
            self::Neogranel => 'Neogranel',
            self::PerigosaGranelSolido => 'Perigosa (granel sólido)',
            self::PerigosaGranelLiquido => 'Perigosa (granel líquido)',
            self::PerigosaFrigorificadaOuAquecida => 'Perigosa (Frigorificada ou Aquecida)',
            self::PerigosaConteinerizada => 'Perigosa (conteinerizada)',
            self::PerigosaCargaGeral => 'Perigosa (carga geral)',
            self::CargaGranelPressurizada => 'Carga Granel Pressurizada',
        };
    }
}
