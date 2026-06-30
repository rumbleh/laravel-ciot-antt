<?php

declare(strict_types=1);

namespace Rumbleh\CiotAntt\Responses;

/**
 * Resposta do serviço 04 — CancelamentoOperacaoTransporte.
 *
 * Manual DCS PEF v1.1, pág. 30-31.
 */
final class CancelamentoOperacaoTransporteResponse extends PefResponse
{
    public function codigoIdentificacaoOperacao(): ?string
    {
        return $this->valorString('CodigoIdentificacaoOperacao');
    }

    /** Data/hora do cancelamento (string ISO retornada pela ANTT). */
    public function dataCancelamento(): ?string
    {
        return $this->valorString('DataCancelamento');
    }
}
