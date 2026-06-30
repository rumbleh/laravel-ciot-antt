<?php

declare(strict_types=1);

namespace Rumbleh\CiotAntt\Requests;

use Rumbleh\CiotAntt\Requests\Components\OrigemDestino;
use Rumbleh\CiotAntt\Support\Payload;
use Rumbleh\CiotAntt\Support\Wire;

/**
 * Serviço 06 — EncerramentoOperacaoTransporte (POST).
 *
 * Declara que a operação foi executada com sucesso e chegou ao fim.
 *  - CodigoIdentificacaoOperacao: CIOT com dígito verificador (16).
 *  - OrigemDestino (array): obrigatório para TAC-Agregado (tipoOperacao = 3),
 *    com DistanciaPercorrida e QtdViagens; NÃO informado para operação padrão.
 *  - PesoTotalCarga: obrigatório para carga lotação (tipoOperacao = 1).
 *
 * Regras correlatas: B43 (dados de viagem obrigatórios p/ TAC-Agregado),
 * B44 (não informar dados de viagem p/ operação padrão), B116 (TAC-Agregado:
 * encerrar até 10 dias após o início da viagem).
 *
 * Manual DCS PEF v1.1, pág. 36-39.
 */
final class EncerramentoOperacaoTransporteRequest implements PefRequest
{
    public readonly string $codigoIdentificacaoOperacao;

    /** @var list<OrigemDestino> */
    public array $origensDestinos = [];

    public int|float|string|null $pesoTotalCarga = null;

    public function __construct(string $codigoIdentificacaoOperacao)
    {
        $this->codigoIdentificacaoOperacao = trim($codigoIdentificacaoOperacao);
    }

    public function comOrigemDestino(OrigemDestino ...$origensDestinos): self
    {
        $this->origensDestinos = array_merge($this->origensDestinos, array_values($origensDestinos));

        return $this;
    }

    public function comPesoTotalCarga(int|float|string $peso): self
    {
        $this->pesoTotalCarga = $peso;

        return $this;
    }

    public function servico(): string
    {
        return 'EncerramentoOperacaoTransporte';
    }

    public function metodo(): string
    {
        return 'POST';
    }

    public function toArray(): array
    {
        $payload = [
            'CodigoIdentificacaoOperacao' => $this->codigoIdentificacaoOperacao,
        ];

        if ($this->origensDestinos !== []) {
            $payload['OrigemDestino'] = array_map(
                static fn (OrigemDestino $od): array => $od->toArray(),
                $this->origensDestinos,
            );
        }

        if ($this->pesoTotalCarga !== null) {
            $payload['DadosCarga'] = [
                Wire::ENCERRAMENTO_PESO_TOTAL => Payload::decimal($this->pesoTotalCarga, 2),
            ];
        }

        return $payload;
    }

    public function validar(): array
    {
        $erros = [];

        if ($this->codigoIdentificacaoOperacao === '') {
            $erros[] = 'B1: O campo CodigoIdentificacaoOperacao é obrigatório.';
        }

        // Para cada par de viagem (TAC-Agregado) distância e qtd de viagens > 0.
        foreach ($this->origensDestinos as $idx => $od) {
            $dist = $od->distanciaPercorrida !== null ? (int) $od->distanciaPercorrida : 0;
            if ($dist <= 0) {
                $erros[] = "B4: DistanciaPercorrida deve ser maior que zero (OrigemDestino #{$idx}).";
            }
            if ($od->qtdViagens !== null && $od->qtdViagens <= 0) {
                $erros[] = "B4: QtdViagens deve ser maior que zero (OrigemDestino #{$idx}).";
            }
        }

        return $erros;
    }
}
