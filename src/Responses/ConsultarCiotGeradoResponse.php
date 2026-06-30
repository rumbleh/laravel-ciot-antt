<?php

declare(strict_types=1);

namespace Rumbleh\CiotAntt\Responses;

/**
 * Resposta do serviço 08 — ConsultarCIOTGerado.
 *
 * Retorna o CIOT com dígito verificador (16). Nesta resposta os campos
 * "Codigo" e "Mensagem" podem vir como listas (arrays) — a classe base já
 * normaliza isso em codigo()/mensagem().
 *
 * Manual DCS PEF v1.1, pág. 43.
 */
final class ConsultarCiotGeradoResponse extends PefResponse
{
    /** CIOT com dígito verificador (16 caracteres). */
    public function ciotComDigito(): ?string
    {
        return $this->valorString('CodigoIdentificacaoOperacao');
    }
}
