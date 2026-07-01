<?php

declare(strict_types=1);

namespace Rumbleh\CiotAntt\Exceptions;

/**
 * Lançada quando a API do Gerador CIOT v3 responde HTTP 401 (token ausente,
 * inválido ou expirado) ao chamar o endpoint /gerar.
 *
 * É uma especialização de {@see CiotApiException} para permitir o tratamento
 * específico de "não autorizado" (por exemplo, forçar um refreshToken()).
 */
class CiotUnauthorizedException extends CiotApiException
{
    public static function em(string $endpoint, string $corpo): self
    {
        return new self(
            sprintf('Não autorizado (HTTP 401) ao chamar %s. Token ausente, inválido ou expirado.', $endpoint),
            401,
            $corpo,
            $endpoint,
        );
    }
}
