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
 * Parcelamento (IndPagamento = 1): ver classe Parcela. Quando há exatamente uma
 * parcela, os campos são "achatados" no objeto (como no exemplo JSON do
 * manual); com várias, são enviados como lista "Parcelas".
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
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $dados = Payload::semNulos([
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

        return array_merge($dados, $this->parcelasToArray());
    }

    /**
     * @return array<string, mixed>
     */
    private function parcelasToArray(): array
    {
        if ($this->parcelas === []) {
            return [];
        }

        if (count($this->parcelas) === 1) {
            // Achatamento conforme o exemplo JSON do manual (parcela única).
            return $this->parcelas[0]->toArray();
        }

        return [
            'Parcelas' => array_map(
                static fn (Parcela $p): array => $p->toArray(),
                $this->parcelas,
            ),
        ];
    }
}
