<?php

declare(strict_types=1);

namespace Rumbleh\CiotAntt\Exceptions;

/**
 * Lançada quando a autenticação no Gerador CIOT v3 falha — isto é, quando o
 * endpoint POST /token responde fora da faixa 2xx ou não devolve o campo
 * "token" esperado.
 *
 * Carrega o status HTTP e o corpo retornados pela API (herdados de
 * {@see CiotApiException}) para facilitar o diagnóstico (ex.: API key inválida).
 */
class CiotAuthenticationException extends CiotApiException
{
    public static function statusInvalido(int $status, string $corpo, string $endpoint): self
    {
        return new self(
            sprintf('Falha ao autenticar no Gerador CIOT (HTTP %d). Verifique a API key.', $status),
            $status,
            $corpo,
            $endpoint,
        );
    }

    public static function tokenAusente(string $corpo, string $endpoint): self
    {
        return new self(
            'A API do Gerador CIOT autenticou (HTTP 2xx) mas não retornou o campo "token".',
            null,
            $corpo,
            $endpoint,
        );
    }
}
