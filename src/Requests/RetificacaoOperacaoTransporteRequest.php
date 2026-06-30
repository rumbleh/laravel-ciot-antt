<?php

declare(strict_types=1);

namespace Rumbleh\CiotAntt\Requests;

use DateTimeInterface;
use Rumbleh\CiotAntt\Requests\Components\DadosCarga;
use Rumbleh\CiotAntt\Requests\Components\OrigemDestino;
use Rumbleh\CiotAntt\Support\Datas;
use Rumbleh\CiotAntt\Support\Payload;

/**
 * Serviço 05 — RetificacaoOperacaoTransporte (POST).
 *
 * Retifica uma operação de transporte já declarada (status "Declarado").
 *  - CodigoIdentificacaoOperacao: CIOT com dígito verificador (16).
 *  - ValorFrete: > 0 (B80/B120).
 *  - DataFimViagem, OrigemDestino: obrigatórios apenas para TAC-Agregado (B57/B64).
 *  - DadosCarga: para carga fracionada pode ou não ser retificado.
 *
 * Regras: B121 — não é permitido retificar carga lotação (tipoOperacao = 1).
 *
 * Manual DCS PEF v1.1, pág. 32-35.
 */
final class RetificacaoOperacaoTransporteRequest implements PefRequest
{
    public readonly string $codigoIdentificacaoOperacao;
    public int|float|string|null $valorFrete = null;
    public ?string $dataFimViagem = null;

    /** @var list<OrigemDestino> */
    public array $origensDestinos = [];

    public ?DadosCarga $dadosCarga = null;

    public function __construct(string $codigoIdentificacaoOperacao)
    {
        $this->codigoIdentificacaoOperacao = trim($codigoIdentificacaoOperacao);
    }

    public function comValorFrete(int|float|string $valorFrete): self
    {
        $this->valorFrete = $valorFrete;

        return $this;
    }

    public function comDataFimViagem(DateTimeInterface|string $data): self
    {
        $this->dataFimViagem = Datas::data($data);

        return $this;
    }

    public function comOrigemDestino(OrigemDestino ...$origensDestinos): self
    {
        $this->origensDestinos = array_merge($this->origensDestinos, array_values($origensDestinos));

        return $this;
    }

    public function comDadosCarga(DadosCarga $dadosCarga): self
    {
        $this->dadosCarga = $dadosCarga;

        return $this;
    }

    public function servico(): string
    {
        return 'RetificacaoOperacaoTransporte';
    }

    public function metodo(): string
    {
        return 'POST';
    }

    public function toArray(): array
    {
        $payload = Payload::semNulos([
            'CodigoIdentificacaoOperacao' => $this->codigoIdentificacaoOperacao,
            'ValorFrete' => $this->valorFrete !== null ? Payload::decimal($this->valorFrete, 2) : null,
            'DataFimViagem' => $this->dataFimViagem,
        ]);

        if ($this->origensDestinos !== []) {
            $payload['OrigemDestino'] = array_map(
                static fn (OrigemDestino $od): array => $od->toArray(),
                $this->origensDestinos,
            );
        }

        if ($this->dadosCarga !== null) {
            $payload['DadosCarga'] = $this->dadosCarga->toArray();
        }

        return $payload;
    }

    public function validar(): array
    {
        $erros = [];

        if ($this->codigoIdentificacaoOperacao === '') {
            $erros[] = 'B1: O campo CodigoIdentificacaoOperacao é obrigatório.';
        }

        if ($this->valorFrete !== null) {
            $valor = is_string($this->valorFrete)
                ? (float) str_replace(',', '.', $this->valorFrete)
                : (float) $this->valorFrete;
            if ($valor <= 0.0) {
                $erros[] = 'B120: O valor do frete, quando informado na retificação, deve ser maior que zero.';
            }
        }

        if ($this->dataFimViagem !== null && ! Datas::formatoDataValido($this->dataFimViagem)) {
            $erros[] = 'B4: DataFimViagem inválida. Use o formato yyyy-MM-dd.';
        }

        return $erros;
    }
}
