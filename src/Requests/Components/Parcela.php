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
 * Observação sobre o "wire format": os campos NumeroParcela/DataVencimento/
 * ValorParcela vão ACHATADOS DENTRO do objeto InfPagamento. Como InfPagamento já
 * é uma lista no JSON, múltiplas parcelas viram múltiplos objetos InfPagamento —
 * um por parcela, cada um com esses campos (não há sub-array "Parcelas"). Ver
 * InfPagamento::paraLista().
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
