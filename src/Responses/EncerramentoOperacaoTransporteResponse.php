<?php

declare(strict_types=1);

namespace Rumbleh\CiotAntt\Responses;

/**
 * Resposta do serviço 06 — EncerramentoOperacaoTransporte.
 *
 * Manual DCS PEF v1.1, pág. 39.
 */
final class EncerramentoOperacaoTransporteResponse extends PefResponse
{
    public function codigoIdentificacaoOperacao(): ?string
    {
        return $this->valorString('CodigoIdentificacaoOperacao');
    }

    public function dataEncerramento(): ?string
    {
        return $this->valorString('DataEncerramento');
    }
}
