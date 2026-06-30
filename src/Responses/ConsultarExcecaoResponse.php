<?php

declare(strict_types=1);

namespace Rumbleh\CiotAntt\Responses;

/**
 * Resposta do serviço 07 — ConsultarExcecao.
 *
 * Indica se o CPF/CNPJ do transportador está na lista de exceções à
 * Resolução 5862 (campo Retorno.Flag).
 *
 * Manual DCS PEF v1.1, pág. 41.
 */
final class ConsultarExcecaoResponse extends PefResponse
{
    /**
     * @return array<string, mixed>
     */
    private function retorno(): array
    {
        $r = $this->valor('Retorno');

        return is_array($r) ? array_change_key_case($r, CASE_LOWER) : [];
    }

    public function cpfCnpjTransportador(): ?string
    {
        $v = $this->retorno()['cpfcnpjtransportador'] ?? null;

        return $v !== null ? (string) $v : null;
    }

    /** O transportador está na lista de exceções? */
    public function naLista(): bool
    {
        return self::paraBool($this->retorno()['flag'] ?? null);
    }
}
