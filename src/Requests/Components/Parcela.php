<?php

declare(strict_types=1);

namespace Rumbleh\CiotAntt\Requests\Components;

use Rumbleh\CiotAntt\Support\Payload;

/**
 * Parcela do pagamento a prazo (itens 16.10.1 a 16.10.3).
 *
 * Obrigatórias quando IndPagamento = 1 (a prazo) — regra B105; não devem ser
 * informadas quando à vista — regra B106.
 *
 * Observação sobre o "wire format": o exemplo JSON do manual mostra os campos
 * NumeroParcela/DataVencimento/ValorParcela DENTRO do objeto InfPagamento (uma
 * única parcela). Quando há múltiplas parcelas, o pacote as serializa como uma
 * lista "Parcelas" no objeto InfPagamento (ver InfPagamento::toArray()).
 */
final class Parcela
{
    public function __construct(
        public readonly int $numeroParcela,
        public readonly string $dataVencimento,
        public readonly int|float|string $valorParcela,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return Payload::semNulos([
            'NumeroParcela' => (string) $this->numeroParcela,
            'DataVencimento' => $this->dataVencimento,
            'ValorParcela' => Payload::decimal($this->valorParcela, 2),
        ]);
    }
}
