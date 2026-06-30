<?php

declare(strict_types=1);

namespace Rumbleh\CiotAntt\Responses;

/**
 * Resposta do serviço 03 — DeclaracaoOperacaoTransporte.
 *
 * Contém o CIOT gerado pela ANTT. Para os serviços subsequentes
 * (cancelamento/retificação/encerramento) usa-se o CIOT *com* dígito
 * verificador (16 caracteres) — ver ciotComDigito().
 *
 * Manual DCS PEF v1.1, pág. 27-28.
 */
final class DeclaracaoOperacaoTransporteResponse extends PefResponse
{
    /** CIOT (12 dígitos), sem dígito verificador. */
    public function ciot(): ?string
    {
        return $this->valorString('CodigoIdentificacaoOperacao', 'IdOperacaoTransporte');
    }

    /** Dígito verificador (4 dígitos) gerado pela ANTT. */
    public function codigoVerificador(): ?string
    {
        $v = $this->valorString('CodigoVerificador');

        return $v !== null ? str_pad($v, 4, '0', STR_PAD_LEFT) : null;
    }

    /**
     * CIOT com dígito verificador (16 caracteres) — identificador usado nos
     * serviços de cancelamento, retificação, encerramento e consulta.
     */
    public function ciotComDigito(): ?string
    {
        $ciot = $this->ciot();
        $dv = $this->codigoVerificador();

        if ($ciot === null) {
            return null;
        }

        return $ciot . ($dv ?? '');
    }

    /**
     * Aviso cadastrado pela ANTT para o contratado. Quando presente, sua
     * apresentação e impressão no documento são OBRIGATÓRIAS (manual, item 6).
     */
    public function avisoTransportador(): ?string
    {
        $v = $this->valorString('AvisoTransportador');

        return $v !== null && trim($v) !== '' ? $v : null;
    }

    public function temAviso(): bool
    {
        return $this->avisoTransportador() !== null;
    }
}
