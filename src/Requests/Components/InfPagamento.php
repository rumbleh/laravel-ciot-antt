<?php

declare(strict_types=1);

namespace Rumbleh\CiotAntt\Requests\Components;

use Rumbleh\CiotAntt\Enums\IndPagamento;
use Rumbleh\CiotAntt\Enums\TipoPagamento;
use Rumbleh\CiotAntt\Support\Payload;
use Rumbleh\CiotAntt\ValueObjects\Documento;

/**
 * Informações do pagamento do frete (item 16 da declaração).
 *
 * No JSON, InfPagamento é uma LISTA de objetos deste tipo. Cada objeto descreve
 * uma forma de pagamento, com campos condicionados ao TipoPagamento (regras
 * B92/B99) e ao IndPagamento (regras B105/B106).
 *
 * Campos por TipoPagamento:
 *  - 1..4 (cartão/contas): CodigoInstituicaoFinanceira, NumeroAgencia,
 *    NumeroConta (B92/B99).
 *  - 6 (Pix): ChavePix e IdentificadorPix (B92/B99).
 *  - CpfCnpjCreditado é sempre obrigatório (recebedor do pagamento).
 *
 * Parcelamento (IndPagamento = 1): ver classe Parcela. Os campos da parcela
 * (NumeroParcela/DataVencimento/ValorParcela) são ACHATADOS no objeto. Como
 * InfPagamento já é uma lista no JSON, cada parcela vira um objeto próprio (não
 * há sub-array "Parcelas" — ver paraLista()).
 */
final class InfPagamento
{
    /** @var list<Parcela> */
    public readonly array $parcelas;

    public readonly ?string $cpfCnpjCreditado;

    /**
     * @param  list<Parcela>  $parcelas
     */
    public function __construct(
        public readonly TipoPagamento $tipoPagamento,
        public readonly IndPagamento $indPagamento = IndPagamento::AVista,
        ?string $cpfCnpjCreditado = null,
        public readonly ?int $codigoInstituicaoFinanceira = null,
        public readonly ?string $numeroAgencia = null,
        public readonly ?string $numeroConta = null,
        public readonly ?string $chavePix = null,
        public readonly ?string $identificadorPix = null,
        public readonly ?string $codigoPagamento = null,
        array $parcelas = [],
    ) {
        $this->cpfCnpjCreditado = $cpfCnpjCreditado !== null
            ? Documento::de($cpfCnpjCreditado)->valor()
            : null;
        $this->parcelas = array_values($parcelas);
    }

    public function comParcelas(Parcela ...$parcelas): self
    {
        return new self(
            $this->tipoPagamento,
            IndPagamento::APrazo,
            $this->cpfCnpjCreditado,
            $this->codigoInstituicaoFinanceira,
            $this->numeroAgencia,
            $this->numeroConta,
            $this->chavePix,
            $this->identificadorPix,
            $this->codigoPagamento,
            array_values($parcelas),
        );
    }

    /**
     * Representação em objeto único: cabeçalho + a primeira parcela (se houver).
     * Para o wire format completo — que pode render vários objetos, um por
     * parcela — use paraLista(). Útil para à vista / parcela única.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->paraLista()[0];
    }

    /**
     * "Wire format" da ANTT: uma LISTA de objetos InfPagamento.
     *
     * À vista → 1 objeto (apenas o cabeçalho). A prazo → 1 objeto POR PARCELA,
     * cada um com o cabeçalho + NumeroParcela/DataVencimento/ValorParcela
     * ACHATADOS no mesmo nível. A especificação (item 16 + regra B105 do manual
     * DCS PEF, e o exemplo JSON págs. 24-26) NÃO usa um sub-array "Parcelas":
     * o próprio InfPagamento já é a lista, então cada parcela é um objeto.
     *
     * @return list<array<string, mixed>>
     */
    public function paraLista(): array
    {
        $cabecalho = $this->cabecalhoToArray();

        if ($this->parcelas === []) {
            return [$cabecalho];
        }

        return array_map(
            static fn (Parcela $p): array => array_merge($cabecalho, $p->toArray()),
            $this->parcelas,
        );
    }

    /**
     * Cabeçalho do pagamento (todos os campos exceto os de parcela).
     *
     * @return array<string, mixed>
     */
    private function cabecalhoToArray(): array
    {
        return Payload::semNulos([
            'TipoPagamento' => $this->tipoPagamento->value,
            'CodigoInstituicaoFinanceira' => $this->codigoInstituicaoFinanceira !== null
                ? (string) $this->codigoInstituicaoFinanceira
                : null,
            'NumeroAgencia' => $this->numeroAgencia,
            'NumeroConta' => $this->numeroConta,
            'ChavePix' => $this->chavePix,
            'CpfCnpjCreditado' => $this->cpfCnpjCreditado,
            'CodigoPagamento' => $this->codigoPagamento,
            'IdentificadorPix' => $this->identificadorPix,
            'IndPagamento' => $this->indPagamento->value,
        ]);
    }
}
