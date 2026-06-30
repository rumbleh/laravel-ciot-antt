<?php

declare(strict_types=1);

namespace Rumbleh\CiotAntt\Responses;

/**
 * Resposta do serviço 02 — ConsultarFrotaTransportador.
 *
 * Manual DCS PEF v1.1, pág. 15-17.
 */
final class ConsultarFrotaTransportadorResponse extends PefResponse
{
    public function cpfCnpjTransportador(): ?string
    {
        return $this->valorString('CpfCnpjTransportador', 'CPFCNPJTransportador');
    }

    public function rntrcTransportador(): ?string
    {
        return $this->valorString('RNTRCTransportador');
    }

    public function nomeRazaoSocial(): ?string
    {
        return $this->valorString('NomeRazaoSocialTransportador');
    }

    public function rntrcAtivo(): bool
    {
        return $this->valorBool('RNTRCAtivo');
    }

    /**
     * Situação de cada veículo consultado.
     *
     * @return list<array{placa: string, pertence: bool}>
     */
    public function frota(): array
    {
        $frota = $this->valor('Frota');

        if (! is_array($frota)) {
            return [];
        }

        $resultado = [];
        foreach ($frota as $item) {
            if (! is_array($item)) {
                continue;
            }
            $indice = array_change_key_case($item, CASE_LOWER);
            $resultado[] = [
                'placa' => (string) ($indice['placaveiculo'] ?? ''),
                // Normaliza "false"/"true"/0/1/bool (ver PefResponse::paraBool):
                // um (bool) direto trataria a string "false" como true (inversão!).
                'pertence' => self::paraBool($indice['situacaoveiculofrotatransportador'] ?? null),
            ];
        }

        return $resultado;
    }

    /** O veículo (placa) pertence ao transportador? */
    public function veiculoPertence(string $placa): bool
    {
        $placa = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $placa) ?? '');

        foreach ($this->frota() as $item) {
            if (strtoupper($item['placa']) === $placa) {
                return $item['pertence'];
            }
        }

        return false;
    }
}
