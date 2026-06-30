<?php

declare(strict_types=1);

namespace Rumbleh\CiotAntt\Tests\Unit\Enums;

use Rumbleh\CiotAntt\Enums\Ambiente;
use Rumbleh\CiotAntt\Enums\IndPagamento;
use Rumbleh\CiotAntt\Enums\TipoCarga;
use Rumbleh\CiotAntt\Enums\TipoOperacao;
use Rumbleh\CiotAntt\Enums\TipoPagamento;
use Rumbleh\CiotAntt\Tests\TestCase;

final class EnumsTest extends TestCase
{
    public function test_tipo_operacao_valores(): void
    {
        $this->assertSame(1, TipoOperacao::CargaLotacao->value);
        $this->assertSame(2, TipoOperacao::CargaFracionada->value);
        $this->assertSame(3, TipoOperacao::TacAgregado->value);

        $this->assertTrue(TipoOperacao::CargaLotacao->ehCarga());
        $this->assertTrue(TipoOperacao::CargaFracionada->ehCarga());
        $this->assertFalse(TipoOperacao::TacAgregado->ehCarga());
    }

    public function test_tipo_pagamento_dados_bancarios_e_pix(): void
    {
        $this->assertTrue(TipoPagamento::CartaoPrePago->exigeDadosBancarios());
        $this->assertTrue(TipoPagamento::ContaCorrente->exigeDadosBancarios());
        $this->assertTrue(TipoPagamento::ContaPoupanca->exigeDadosBancarios());
        $this->assertTrue(TipoPagamento::ContaPagamento->exigeDadosBancarios());
        $this->assertFalse(TipoPagamento::Outros->exigeDadosBancarios());
        $this->assertFalse(TipoPagamento::Pix->exigeDadosBancarios());

        $this->assertTrue(TipoPagamento::Pix->exigeDadosPix());
        $this->assertFalse(TipoPagamento::ContaCorrente->exigeDadosPix());
    }

    public function test_ind_pagamento(): void
    {
        $this->assertSame(0, IndPagamento::AVista->value);
        $this->assertSame(1, IndPagamento::APrazo->value);
        $this->assertTrue(IndPagamento::APrazo->exigeParcelas());
        $this->assertFalse(IndPagamento::AVista->exigeParcelas());
    }

    public function test_tipo_carga_cobre_todos_os_doze_codigos(): void
    {
        $this->assertCount(12, TipoCarga::cases());
        $this->assertSame(1, TipoCarga::GranelSolido->value);
        $this->assertSame(12, TipoCarga::CargaGranelPressurizada->value);
    }

    public function test_ambiente(): void
    {
        $this->assertSame('homologacao', Ambiente::Homologacao->value);
        $this->assertSame('producao', Ambiente::Producao->value);
        $this->assertTrue(Ambiente::Producao->ehProducao());
        $this->assertFalse(Ambiente::Homologacao->ehProducao());
    }
}
