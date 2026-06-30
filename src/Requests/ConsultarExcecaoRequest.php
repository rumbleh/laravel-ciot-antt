<?php

declare(strict_types=1);

namespace Rumbleh\CiotAntt\Requests;

use Rumbleh\CiotAntt\ValueObjects\Documento;

/**
 * Serviço 07 — ConsultarExcecao (GET).
 *
 * Verifica se o transportador está contido na lista de exceções à
 * Resolução 5862.
 *
 * Entrada via query string: api/ConsultarExcecao?CPFCNPJTransportador="..."
 *
 * Manual DCS PEF v1.1, pág. 40-41.
 */
final class ConsultarExcecaoRequest implements PefRequest
{
    public readonly string $cpfCnpjTransportador;

    public function __construct(string $cpfCnpjTransportador)
    {
        $this->cpfCnpjTransportador = Documento::de($cpfCnpjTransportador)->valor();
    }

    public function servico(): string
    {
        return 'ConsultarExcecao';
    }

    public function metodo(): string
    {
        return 'GET';
    }

    public function toArray(): array
    {
        return [
            'CPFCNPJTransportador' => $this->cpfCnpjTransportador,
        ];
    }

    public function validar(): array
    {
        return [];
    }
}
