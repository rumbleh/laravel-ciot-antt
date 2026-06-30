<?php

declare(strict_types=1);

namespace Rumbleh\CiotAntt\Enums;

/**
 * Tipo de pagamento do frete (campo InfPagamento.TipoPagamento).
 *
 * Manual DCS PEF v1.1, serviço DeclaracaoOperacaoTransporte (pág. 22, item 16.1).
 */
enum TipoPagamento: int
{
    /** Cartão pré-pago emitido por IP ou IF. */
    case CartaoPrePago = 1;
    case ContaCorrente = 2;
    case ContaPoupanca = 3;
    case ContaPagamento = 4;
    case Outros = 5;
    case Pix = 6;

    public function label(): string
    {
        return match ($this) {
            self::CartaoPrePago => 'Cartão pré-pago (IP/IF)',
            self::ContaCorrente => 'Conta Corrente',
            self::ContaPoupanca => 'Conta Poupança',
            self::ContaPagamento => 'Conta Pagamento',
            self::Outros => 'Outros',
            self::Pix => 'Pix',
        };
    }

    /**
     * Tipos que exigem dados bancários (instituição, agência, conta).
     * Manual: regra B92/B99 — obrigatórios quando tipoPagamento = 1, 2, 3 ou 4.
     */
    public function exigeDadosBancarios(): bool
    {
        return in_array($this, [
            self::CartaoPrePago,
            self::ContaCorrente,
            self::ContaPoupanca,
            self::ContaPagamento,
        ], true);
    }

    /** Tipos que exigem dados de Pix (ChavePix / IdentificadorPix). */
    public function exigeDadosPix(): bool
    {
        return $this === self::Pix;
    }
}
