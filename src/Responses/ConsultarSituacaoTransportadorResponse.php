<?php

declare(strict_types=1);

namespace Rumbleh\CiotAntt\Responses;

use Rumbleh\CiotAntt\Enums\TipoTransportador;

/**
 * Resposta do serviço 01 — ConsultarSituacaoTransportador.
 *
 * Manual DCS PEF v1.1, pág. 12-13.
 */
final class ConsultarSituacaoTransportadorResponse extends PefResponse
{
    public function cpfCnpjTransportador(): ?string
    {
        return $this->valorString('CpfCnpjTransportador', 'CPFCNPJTransportador');
    }

    public function rntrcTransportador(): ?string
    {
        return $this->valorString('RNTRCTransportador');
    }

    public function nomeRazaoSocial(): ?string
    {
        return $this->valorString('NomeRazaoSocialTransportador');
    }

    public function rntrcAtivo(): bool
    {
        return $this->valorBool('RNTRCAtivo');
    }

    public function tipoTransportador(): ?TipoTransportador
    {
        $tipo = $this->valorString('TipoTransportador');

        return $tipo !== null ? TipoTransportador::tryFrom(strtoupper(trim($tipo))) : null;
    }

    public function equiparadoTac(): bool
    {
        return $this->valorBool('EquiparadoTAC');
    }

    /** Atalho: o transportador está apto a realizar transporte (RNTRC ativo). */
    public function apto(): bool
    {
        return $this->rntrcAtivo();
    }
}
