<?php

declare(strict_types=1);

namespace Rumbleh\CiotAntt\Exceptions;

use Throwable;

/**
 * Lançada quando há falha de comunicação (rede, TLS/mTLS, timeout, HTTP 5xx,
 * resposta não-JSON) com o web service da ANTT.
 *
 * Diferente de CiotRejectionException, que representa uma resposta válida da
 * ANTT rejeitando a operação por regra de negócio.
 */
class CiotConnectionException extends CiotException
{
    public function __construct(
        string $message,
        public readonly ?int $statusHttp = null,
        public readonly ?string $corpoResposta = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
