<?php

declare(strict_types=1);

namespace Rumbleh\CiotAntt\Requests;

use Rumbleh\CiotAntt\ValueObjects\Documento;
use Rumbleh\CiotAntt\ValueObjects\Placa;
use Rumbleh\CiotAntt\ValueObjects\Rntrc;

/**
 * Serviço 02 — ConsultarFrotaTransportador (POST).
 *
 * Verifica a situação de cada veículo (por placa) sob posse/propriedade do
 * transportador.
 *
 * Manual DCS PEF v1.1, pág. 14-17.
 */
final class ConsultarFrotaTransportadorRequest implements PefRequest
{
    public readonly string $cpfCnpjInteressado;
    public readonly string $cpfCnpjTransportador;
    public readonly string $rntrcTransportador;

    /** @var list<string> */
    public readonly array $placas;

    /**
     * @param  list<string>  $placas
     */
    public function __construct(
        string $cpfCnpjInteressado,
        string $cpfCnpjTransportador,
        string $rntrcTransportador,
        array $placas,
    ) {
        $this->cpfCnpjInteressado = Documento::de($cpfCnpjInteressado)->valor();
        $this->cpfCnpjTransportador = Documento::de($cpfCnpjTransportador)->valor();
        $this->rntrcTransportador = Rntrc::de($rntrcTransportador)->valor();
        $this->placas = array_values(array_map(
            static fn (string $p): string => Placa::de($p)->valor(),
            $placas,
        ));
    }

    public function servico(): string
    {
        return 'ConsultarFrotaTransportador';
    }

    public function metodo(): string
    {
        return 'POST';
    }

    public function toArray(): array
    {
        return [
            'CpfCnpjInteressado' => $this->cpfCnpjInteressado,
            'CpfCnpjTransportador' => $this->cpfCnpjTransportador,
            'RNTRCTransportador' => $this->rntrcTransportador,
            'Placas' => $this->placas,
        ];
    }

    public function validar(): array
    {
        $erros = [];

        if ($this->placas === []) {
            $erros[] = 'B1: O campo Placas é obrigatório (informe ao menos uma placa).';
        }

        // B5: não pode haver placas duplicadas.
        if (count($this->placas) !== count(array_unique($this->placas))) {
            $erros[] = 'B5: Existe duplicidade de placa na lista informada.';
        }

        return $erros;
    }
}
