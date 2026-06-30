<?php

declare(strict_types=1);

namespace Rumbleh\CiotAntt\Tests\Unit\Requests;

use Rumbleh\CiotAntt\Enums\IndPagamento;
use Rumbleh\CiotAntt\Enums\TipoCarga;
use Rumbleh\CiotAntt\Enums\TipoPagamento;
use Rumbleh\CiotAntt\Requests\Components\DadosCarga;
use Rumbleh\CiotAntt\Requests\Components\InfIndicadoresOperacionais;
use Rumbleh\CiotAntt\Requests\Components\InfPagamento;
use Rumbleh\CiotAntt\Requests\Components\Localidade;
use Rumbleh\CiotAntt\Requests\Components\OrigemDestino;
use Rumbleh\CiotAntt\Requests\Components\Parcela;
use Rumbleh\CiotAntt\Requests\Components\Veiculo;
use Rumbleh\CiotAntt\Requests\DeclaracaoOperacaoTransporteRequest;
use Rumbleh\CiotAntt\Tests\TestCase;

final class DeclaracaoPayloadTest extends TestCase
{
    private function declaracaoCargaLotacaoValida(): DeclaracaoOperacaoTransporteRequest
    {
        return DeclaracaoOperacaoTransporteRequest::cargaLotacao(
            self::CNPJ_VALIDO,
            self::RNTRC_VALIDO,
            self::CNPJ_VALIDO_2,
        )
            ->comId('000000000123')
            ->comValorFrete(1500.5)
            ->comDestinatario(self::CNPJ_VALIDO_3)
            ->comDataDeclaracao('2026-06-23T10:00:00')
            ->comViagem('2026-06-24', '2026-06-28')
            ->comVeiculos(
                Veiculo::automotor(self::PLACA_BR, 3, self::RNTRC_VALIDO, cavaloTrator: true),
                Veiculo::implemento(self::PLACA_IMPLEMENTO, 2),
            )
            ->comOrigemDestino(
                OrigemDestino::entre(
                    Localidade::origem()->comMunicipio(3550308),
                    Localidade::destino()->comMunicipio(3304557),
                )->comDistancia(430),
            )
            ->comDadosCarga(new DadosCarga(
                codigoNaturezaCarga: 1234,
                pesoCarga: 25000.5,
                codigoTipoCarga: TipoCarga::CargaGeral,
            ))
            ->comPagamentos(new InfPagamento(
                tipoPagamento: TipoPagamento::ContaCorrente,
                cpfCnpjCreditado: self::CNPJ_VALIDO,
                codigoInstituicaoFinanceira: 1,
                numeroAgencia: '1234',
                numeroConta: '56789-0',
            ))
            ->comIndicadores(new InfIndicadoresOperacionais(
                indAltoDesempenho: true,
                indRetornoVazio: false,
                composicaoVeicular: true,
            ));
    }

    public function test_payload_carga_lotacao_tem_a_estrutura_do_manual(): void
    {
        $payload = $this->declaracaoCargaLotacaoValida()->toArray();

        $this->assertSame('000000000123', $payload['IdOperacaoTransporte']);
        $this->assertSame(1, $payload['TipoOperacao']);
        $this->assertSame(self::CNPJ_VALIDO, $payload['CpfCnpjContratado']);
        $this->assertSame(self::RNTRC_VALIDO, $payload['RNTRCContratado']);
        $this->assertSame(self::CNPJ_VALIDO_2, $payload['CpfCnpjContratante']);
        $this->assertSame(self::CNPJ_VALIDO_3, $payload['CpfCnpjDestinatario']);
        $this->assertSame('1500.50', $payload['ValorFrete']);
        $this->assertSame('2026-06-23T10:00:00', $payload['DataDeclaracao']);
        $this->assertFalse($payload['IndContingencia']);
        $this->assertSame('2026-06-24', $payload['DataInicioViagem']);
        $this->assertSame('2026-06-28', $payload['DataFimViagem']);
        $this->assertArrayNotHasKey('JustificativaContingencia', $payload);
    }

    public function test_payload_serializa_veiculos(): void
    {
        $payload = $this->declaracaoCargaLotacaoValida()->toArray();

        $this->assertCount(2, $payload['Veiculos']);
        $this->assertSame([
            'Placa' => 'ABC1234',
            'RNTRC' => self::RNTRC_VALIDO,
            'NumeroEixos' => '3',
        ], $payload['Veiculos'][0]);
        $this->assertSame('XYZ9876', $payload['Veiculos'][1]['Placa']);
        $this->assertSame('2', $payload['Veiculos'][1]['NumeroEixos']);
    }

    public function test_payload_serializa_origem_destino_e_distancia(): void
    {
        $payload = $this->declaracaoCargaLotacaoValida()->toArray();

        $od = $payload['OrigemDestino'][0];
        $this->assertSame(['CodigoMunicipioOrigem' => '3550308'], $od['Origem']);
        $this->assertSame(['CodigoMunicipioDestino' => '3304557'], $od['Destino']);
        $this->assertSame(430, $od['DistanciaPercorrida']);
    }

    public function test_payload_serializa_dados_carga(): void
    {
        $payload = $this->declaracaoCargaLotacaoValida()->toArray();

        $this->assertSame([
            'CodigoNaturezaCarga' => '1234',
            'PesoCarga' => '25000.50',
            'CodigoTipoCarga' => '5',
        ], $payload['DadosCarga']);
    }

    public function test_payload_serializa_pagamento_e_indicadores(): void
    {
        $payload = $this->declaracaoCargaLotacaoValida()->toArray();

        $pag = $payload['InfPagamento'][0];
        $this->assertSame(2, $pag['TipoPagamento']);
        $this->assertSame('1', $pag['CodigoInstituicaoFinanceira']);
        $this->assertSame('1234', $pag['NumeroAgencia']);
        $this->assertSame('56789-0', $pag['NumeroConta']);
        $this->assertSame(self::CNPJ_VALIDO, $pag['CpfCnpjCreditado']);
        $this->assertSame(0, $pag['IndPagamento']);

        $this->assertSame([
            'IndAltoDesempenho' => true,
            'IndRetornoVazio' => false,
            'ComposicaoVeicular' => true,
        ], $payload['InfIndicadoresOperacionais']);
    }

    public function test_contingencia_inclui_justificativa(): void
    {
        $payload = $this->declaracaoCargaLotacaoValida()
            ->emContingencia('Indisponibilidade do sistema da ANTT')
            ->toArray();

        $this->assertTrue($payload['IndContingencia']);
        $this->assertSame('Indisponibilidade do sistema da ANTT', $payload['JustificativaContingencia']);
    }

    public function test_pagamento_a_prazo_com_parcela_unica_e_achatado(): void
    {
        $pag = (new InfPagamento(
            tipoPagamento: TipoPagamento::Pix,
            indPagamento: IndPagamento::APrazo,
            cpfCnpjCreditado: self::CPF_VALIDO,
            chavePix: self::CPF_VALIDO,
            identificadorPix: 'E1234',
        ))->comParcelas(new Parcela(1, '2026-07-10', 1500.50));

        $array = $pag->toArray();

        $this->assertSame(6, $array['TipoPagamento']);
        $this->assertSame('1', $array['NumeroParcela']);
        $this->assertSame('2026-07-10', $array['DataVencimento']);
        $this->assertSame('1500.50', $array['ValorParcela']);
        $this->assertArrayNotHasKey('Parcelas', $array);
    }

    public function test_pagamento_a_prazo_com_multiplas_parcelas_usa_lista(): void
    {
        $pag = (new InfPagamento(
            tipoPagamento: TipoPagamento::Pix,
            indPagamento: IndPagamento::APrazo,
            cpfCnpjCreditado: self::CPF_VALIDO,
            chavePix: self::CPF_VALIDO,
            identificadorPix: 'E1234',
        ))->comParcelas(
            new Parcela(1, '2026-07-10', 750.25),
            new Parcela(2, '2026-08-10', 750.25),
        );

        $array = $pag->toArray();

        $this->assertCount(2, $array['Parcelas']);
        $this->assertSame('2', $array['Parcelas'][1]['NumeroParcela']);
        $this->assertArrayNotHasKey('NumeroParcela', $array);
    }

    public function test_tac_agregado_payload_minimo(): void
    {
        $payload = DeclaracaoOperacaoTransporteRequest::tacAgregado(
            self::CPF_VALIDO,
            self::RNTRC_VALIDO,
            self::CNPJ_VALIDO,
        )
            ->comId('000000000999')
            ->comValorFrete(3000)
            ->comDataDeclaracao('2026-06-23T10:00:00')
            ->comDataFimViagem('2026-07-10') // TAC-Agregado: sem data de início (B56)
            ->comVeiculos(Veiculo::automotor(self::PLACA_BR, 3, self::RNTRC_VALIDO))
            ->comPagamentos(new InfPagamento(
                tipoPagamento: TipoPagamento::Outros,
                cpfCnpjCreditado: self::CPF_VALIDO,
            ))
            ->toArray();

        $this->assertSame(3, $payload['TipoOperacao']);
        $this->assertSame('2026-07-10', $payload['DataFimViagem']);
        $this->assertArrayNotHasKey('DataInicioViagem', $payload);
        $this->assertArrayNotHasKey('OrigemDestino', $payload);
        $this->assertArrayNotHasKey('DadosCarga', $payload);
        $this->assertArrayNotHasKey('CpfCnpjDestinatario', $payload);
        $this->assertArrayNotHasKey('InfIndicadoresOperacionais', $payload);
    }
}
