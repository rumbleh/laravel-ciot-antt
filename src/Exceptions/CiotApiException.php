<?php

declare(strict_types=1);

namespace Rumbleh\CiotAntt\Exceptions;

use Throwable;

/**
 * Lançada quando a API do Gerador CIOT v3 responde com um status HTTP fora da
 * faixa 2xx (ou um corpo inesperado) ao chamar um endpoint.
 *
 * Diferente das exceções do cliente PEF/mTLS (que falam de "serviço"), esta
 * exceção é específica do Gerador CIOT v3 (token + API key) e carrega os dados
 * necessários para diagnóstico: o status HTTP, o corpo retornado e o endpoint
 * chamado.
 */
class CiotApiException extends CiotException
{
    public function __construct(
        string $message,
        public readonly ?int $statusHttp = null,
        public readonly ?string $corpoResposta = null,
        public readonly ?string $endpoint = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function statusInesperado(string $endpoint, int $status, string $corpo): self
    {
        return new self(
            sprintf('A API do Gerador CIOT retornou HTTP %d ao chamar %s.', $status, $endpoint),
            $status,
            $corpo,
            $endpoint,
        );
    }

    public static function respostaInesperada(string $endpoint, string $corpo): self
    {
        return new self(
            sprintf('Não foi possível interpretar a resposta do Gerador CIOT em %s.', $endpoint),
            null,
            $corpo,
            $endpoint,
        );
    }
}
