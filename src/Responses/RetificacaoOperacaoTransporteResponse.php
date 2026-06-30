<?php

declare(strict_types=1);

namespace Rumbleh\CiotAntt\Responses;

/**
 * Resposta do serviço 05 — RetificacaoOperacaoTransporte.
 *
 * Manual DCS PEF v1.1, pág. 35.
 */
final class RetificacaoOperacaoTransporteResponse extends PefResponse
{
    public function codigoIdentificacaoOperacao(): ?string
    {
        return $this->valorString('CodigoIdentificacaoOperacao');
    }

    public function dataRetificacao(): ?string
    {
        return $this->valorString('DataRetificacao');
    }
}
