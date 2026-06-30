<?php

declare(strict_types=1);

namespace Rumbleh\CiotAntt\Tests\Unit\Validation;

use DateTimeImmutable;
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
use Rumbleh\CiotAntt\Requests\DeclaracaoOperacaoTransporteRequest as Req;
use Rumbleh\CiotAntt\Tests\TestCase;

final class DeclaracaoValidatorTest extends TestCase
{
    private function hoje(): DateTimeImmutable
    {
        return new DateTimeImmutable('today');
    }

    private function baseCargaLotacao(): Req
    {
        $inicio = $this->hoje()->modify('+1 day');
        $fim = $this->hoje()->modify('+5 days');

        return Req::cargaLotacao(self::CNPJ_VALIDO, self::RNTRC_VALIDO, self::CNPJ_VALIDO_2)
            ->comId('000000000123')
            ->comValorFrete(1500)
            ->comDestinatario(self::CNPJ_VALIDO_3)
            ->comDataDeclaracao(new DateTimeImmutable('now'))
            ->comViagem($inicio, $fim)
            ->comVeiculos(Veiculo::automotor(self::PLACA_BR, 2, self::RNTRC_VALIDO))
            ->comOrigemDestino(
                OrigemDestino::entre(
                    Localidade::origem()->comMunicipio(3550308),
                    Localidade::destino()->comMunicipio(3304557),
                )->comDistancia(430),
            )
            ->comDadosCarga(new DadosCarga(1234, 25000, TipoCarga::CargaGeral))
            ->comPagamentos(new InfPagamento(
                tipoPagamento: TipoPagamento::ContaCorrente,
                cpfCnpjCreditado: self::CNPJ_VALIDO,
                codigoInstituicaoFinanceira: 1,
                numeroAgencia: '1234',
                numeroConta: '56789',
            ))
            ->comIndicadores(new InfIndicadoresOperacionais());
    }

    private function baseTacAgregado(): Req
    {
        // TAC-Agregado: NÃO se informa data de início (regra B56) — apenas o fim.
        $fim = $this->hoje()->modify('+10 days');

        return Req::tacAgregado(self::CPF_VALIDO, self::RNTRC_VALIDO, self::CNPJ_VALIDO)
            ->comId('000000000999')
            ->comValorFrete(3000)
            ->comDataDeclaracao(new DateTimeImmutable('now'))
            ->comDataFimViagem($fim)
            ->comVeiculos(Veiculo::automotor(self::PLACA_BR, 3, self::RNTRC_VALIDO))
            ->comPagamentos(new InfPagamento(
                tipoPagamento: TipoPagamento::Outros,
                cpfCnpjCreditado: self::CPF_VALIDO,
            ));
    }

    /**
     * @param  list<string>  $erros
     */
    private function assertRegra(string $codigo, array $erros): void
    {
        $achou = array_filter($erros, static fn (string $e): bool => str_starts_with($e, $codigo . ':'));
        $this->assertNotEmpty(
            $achou,
            "Esperava erro da regra {$codigo}. Erros: " . json_encode($erros, JSON_UNESCAPED_UNICODE),
        );
    }

    public function test_declaracao_valida_nao_tem_erros(): void
    {
        $this->assertSame([], $this->baseCargaLotacao()->validar());
    }

    public function test_tac_agregado_valido_nao_tem_erros(): void
    {
        $this->assertSame([], $this->baseTacAgregado()->validar());
    }

    public function test_b120_valor_frete_zero(): void
    {
        $r = $this->baseCargaLotacao()->comValorFrete(0);
        $this->assertRegra('B120', $r->validar());
    }

    public function test_b1_id_obrigatorio(): void
    {
        $r = $this->baseCargaLotacao();
        $r->idOperacaoTransporte = null;
        $this->assertRegra('B1', $r->validar());
    }

    public function test_b1_pagamento_obrigatorio(): void
    {
        $r = $this->baseCargaLotacao();
        $r->pagamentos = [];
        $this->assertRegra('B1', $r->validar());
    }

    public function test_b49_maximo_cinco_veiculos(): void
    {
        $r = $this->baseCargaLotacao();
        $r->veiculos = [
            Veiculo::automotor(self::PLACA_BR, 2, self::RNTRC_VALIDO),
            Veiculo::implemento('AAA0001', 2),
            Veiculo::implemento('AAA0002', 2),
            Veiculo::implemento('AAA0003', 2),
            Veiculo::implemento('AAA0004', 2),
            Veiculo::implemento('AAA0005', 2),
        ];
        $this->assertRegra('B49', $r->validar());
    }

    public function test_b5_placas_duplicadas(): void
    {
        $r = $this->baseCargaLotacao();
        $r->veiculos = [
            Veiculo::automotor(self::PLACA_BR, 2, self::RNTRC_VALIDO),
            Veiculo::implemento(self::PLACA_BR, 2),
        ];
        $this->assertRegra('B5', $r->validar());
    }

    public function test_b83_sem_automotor(): void
    {
        $r = $this->baseCargaLotacao();
        $r->veiculos = [Veiculo::implemento('AAA0001', 2)];
        $this->assertRegra('B83', $r->validar());
    }

    public function test_b84_mais_de_um_automotor(): void
    {
        $r = $this->baseCargaLotacao();
        $r->veiculos = [
            Veiculo::automotor(self::PLACA_BR, 2, self::RNTRC_VALIDO),
            Veiculo::automotor('AAA0001', 2, self::RNTRC_VALIDO),
        ];
        $this->assertRegra('B84', $r->validar());
    }

    public function test_b101_eixos_automotor_fora_do_intervalo(): void
    {
        $r = $this->baseCargaLotacao();
        $r->veiculos = [Veiculo::automotor(self::PLACA_BR, 1, self::RNTRC_VALIDO)];
        $this->assertRegra('B101', $r->validar());
    }

    public function test_b117_cavalo_trator_sem_implemento(): void
    {
        $r = $this->baseCargaLotacao();
        $r->veiculos = [Veiculo::automotor(self::PLACA_BR, 3, self::RNTRC_VALIDO, cavaloTrator: true)];
        $this->assertRegra('B117', $r->validar());
    }

    public function test_b103_contingencia_sem_justificativa(): void
    {
        $r = $this->baseCargaLotacao();
        $r->indContingencia = true;
        $this->assertRegra('B103', $r->validar());
    }

    public function test_b104_justificativa_sem_contingencia(): void
    {
        $r = $this->baseCargaLotacao();
        $r->justificativaContingencia = 'algo';
        $this->assertRegra('B104', $r->validar());
    }

    public function test_b13_fim_antes_do_inicio(): void
    {
        $r = $this->baseCargaLotacao()->comViagem(
            $this->hoje()->modify('+5 days'),
            $this->hoje()->modify('+2 days'),
        );
        $this->assertRegra('B13', $r->validar());
    }

    public function test_b6_inicio_no_passado(): void
    {
        $r = $this->baseCargaLotacao()->comViagem(
            $this->hoje()->modify('-2 days'),
            $this->hoje()->modify('+2 days'),
        );
        $this->assertRegra('B6', $r->validar());
    }

    public function test_b12_inicio_anterior_a_declaracao(): void
    {
        // Em contingência (B6/B22 suprimidas), com declaração depois do início.
        $r = $this->baseCargaLotacao()
            ->emContingencia('Sistema da ANTT indisponível')
            ->comDataDeclaracao($this->hoje()->modify('+1 day')->setTime(10, 0))
            ->comViagem($this->hoje(), $this->hoje()->modify('+2 days'));
        $this->assertRegra('B12', $r->validar());
    }

    public function test_b22_inicio_alem_de_trinta_dias(): void
    {
        $r = $this->baseCargaLotacao()->comViagem(
            $this->hoje()->modify('+45 days'),
            $this->hoje()->modify('+46 days'),
        );
        $this->assertRegra('B22', $r->validar());
    }

    public function test_b21_intervalo_maior_que_noventa_dias(): void
    {
        $r = $this->baseCargaLotacao()->comViagem(
            $this->hoje()->modify('+1 day'),
            $this->hoje()->modify('+100 days'),
        );
        $this->assertRegra('B21', $r->validar());
    }

    public function test_b30_tac_agregado_intervalo_maior_que_trinta_dias(): void
    {
        // Início efetivo = data da declaração (hoje); fim a +40 dias => > 30.
        $r = $this->baseTacAgregado()->comDataFimViagem($this->hoje()->modify('+40 days'));
        $this->assertRegra('B30', $r->validar());
    }

    public function test_b56_tac_agregado_nao_pode_informar_data_inicio_viagem(): void
    {
        $r = $this->baseTacAgregado();
        $r->dataInicioViagem = $this->hoje()->modify('+1 day')->format('Y-m-d');
        $this->assertRegra('B56', $r->validar());
    }

    public function test_b99_outros_com_campo_bancario(): void
    {
        $r = $this->baseCargaLotacao();
        $r->pagamentos = [new InfPagamento(
            tipoPagamento: TipoPagamento::Outros,
            cpfCnpjCreditado: self::CNPJ_VALIDO,
            numeroConta: '12345',
        )];
        $this->assertRegra('B99', $r->validar());
    }

    public function test_b66_destinatario_obrigatorio_para_carga(): void
    {
        $r = $this->baseCargaLotacao();
        $r->cpfCnpjDestinatario = null;
        $this->assertRegra('B66', $r->validar());
    }

    public function test_b109_origem_destino_obrigatorio_para_carga(): void
    {
        $r = $this->baseCargaLotacao();
        $r->origensDestinos = [];
        $this->assertRegra('B109', $r->validar());
    }

    public function test_b82_distancia_zero(): void
    {
        $r = $this->baseCargaLotacao();
        $r->origensDestinos = [
            OrigemDestino::entre(
                Localidade::origem()->comMunicipio(3550308),
                Localidade::destino()->comMunicipio(3304557),
            ),
        ];
        $this->assertRegra('B82', $r->validar());
    }

    public function test_b110_coordenadas_incompletas(): void
    {
        $origem = new Localidade('Origem', latitude: '-23.550000');
        $destino = Localidade::destino()->comMunicipio(3304557);
        $r = $this->baseCargaLotacao();
        $r->origensDestinos = [OrigemDestino::entre($origem, $destino)->comDistancia(430)];
        $this->assertRegra('B110', $r->validar());
    }

    public function test_b111_tipos_de_localizacao_diferentes(): void
    {
        $r = $this->baseCargaLotacao();
        $r->origensDestinos = [
            OrigemDestino::entre(
                Localidade::origem()->comMunicipio(3550308),
                Localidade::destino()->comCep('20040002'),
            )->comDistancia(430),
        ];
        $this->assertRegra('B111', $r->validar());
    }

    public function test_b62_dados_carga_obrigatorio_para_carga(): void
    {
        $r = $this->baseCargaLotacao();
        $r->dadosCarga = null;
        $this->assertRegra('B62', $r->validar());
    }

    public function test_b69_natureza_carga_obrigatoria(): void
    {
        $r = $this->baseCargaLotacao();
        $r->dadosCarga = new DadosCarga(pesoCarga: 25000, codigoTipoCarga: TipoCarga::CargaGeral);
        $this->assertRegra('B69', $r->validar());
    }

    public function test_b18_peso_zero(): void
    {
        $r = $this->baseCargaLotacao();
        $r->dadosCarga = new DadosCarga(codigoNaturezaCarga: 1234, pesoCarga: 0, codigoTipoCarga: TipoCarga::CargaGeral);
        $this->assertRegra('B18', $r->validar());
    }

    public function test_indicadores_obrigatorios_para_carga_lotacao(): void
    {
        $r = $this->baseCargaLotacao();
        $r->indicadores = null;
        $this->assertRegra('B1', $r->validar());
    }

    public function test_b67_carga_fracionada_sem_contratantes(): void
    {
        $r = Req::cargaFracionada(self::CNPJ_VALIDO, self::RNTRC_VALIDO, self::CNPJ_VALIDO_2)
            ->comId('000000000123')
            ->comValorFrete(1500)
            ->comDestinatario(self::CNPJ_VALIDO_3)
            ->comDataDeclaracao(new DateTimeImmutable('now'))
            ->comViagem($this->hoje()->modify('+1 day'), $this->hoje()->modify('+5 days'))
            ->comVeiculos(Veiculo::automotor(self::PLACA_BR, 2, self::RNTRC_VALIDO))
            ->comOrigemDestino(
                OrigemDestino::entre(
                    Localidade::origem()->comMunicipio(3550308),
                    Localidade::destino()->comMunicipio(3304557),
                )->comDistancia(430),
            )
            ->comDadosCarga(new DadosCarga(1234, 25000, TipoCarga::CargaGeral))
            ->comPagamentos(new InfPagamento(
                tipoPagamento: TipoPagamento::ContaCorrente,
                cpfCnpjCreditado: self::CNPJ_VALIDO,
                codigoInstituicaoFinanceira: 1,
                numeroAgencia: '1234',
                numeroConta: '56789',
            ));

        $this->assertRegra('B67', $r->validar());
    }

    public function test_b119_contratante_carga_fracionada_igual_ao_da_operacao(): void
    {
        $r = Req::cargaFracionada(self::CNPJ_VALIDO, self::RNTRC_VALIDO, self::CNPJ_VALIDO_2)
            ->comId('000000000123')
            ->comValorFrete(1500)
            ->comDestinatario(self::CNPJ_VALIDO_3)
            ->comDataDeclaracao(new DateTimeImmutable('now'))
            ->comViagem($this->hoje()->modify('+1 day'), $this->hoje()->modify('+5 days'))
            ->comVeiculos(Veiculo::automotor(self::PLACA_BR, 2, self::RNTRC_VALIDO))
            ->comOrigemDestino(
                OrigemDestino::entre(
                    Localidade::origem()->comMunicipio(3550308),
                    Localidade::destino()->comMunicipio(3304557),
                )->comDistancia(430),
            )
            ->comDadosCarga(new DadosCarga(
                codigoNaturezaCarga: 1234,
                pesoCarga: 25000,
                codigoTipoCarga: TipoCarga::CargaGeral,
                contratantesCargaFracionada: [self::CNPJ_VALIDO_2], // == contratante
            ))
            ->comPagamentos(new InfPagamento(
                tipoPagamento: TipoPagamento::ContaCorrente,
                cpfCnpjCreditado: self::CNPJ_VALIDO,
                codigoInstituicaoFinanceira: 1,
                numeroAgencia: '1234',
                numeroConta: '56789',
            ));

        $this->assertRegra('B119', $r->validar());
    }

    public function test_b62_tac_agregado_nao_pode_informar_origem_destino(): void
    {
        $r = $this->baseTacAgregado();
        $r->origensDestinos = [
            OrigemDestino::entre(
                Localidade::origem()->comMunicipio(3550308),
                Localidade::destino()->comMunicipio(3304557),
            )->comDistancia(430),
        ];
        $this->assertRegra('B62', $r->validar());
    }

    public function test_b92_conta_corrente_sem_dados_bancarios(): void
    {
        $r = $this->baseCargaLotacao();
        $r->pagamentos = [new InfPagamento(
            tipoPagamento: TipoPagamento::ContaCorrente,
            cpfCnpjCreditado: self::CNPJ_VALIDO,
        )];
        $this->assertRegra('B92', $r->validar());
    }

    public function test_b92_pix_sem_chave(): void
    {
        $r = $this->baseCargaLotacao();
        $r->pagamentos = [new InfPagamento(
            tipoPagamento: TipoPagamento::Pix,
            cpfCnpjCreditado: self::CNPJ_VALIDO,
        )];
        $this->assertRegra('B92', $r->validar());
    }

    public function test_b99_pix_com_dados_bancarios(): void
    {
        $r = $this->baseCargaLotacao();
        $r->pagamentos = [new InfPagamento(
            tipoPagamento: TipoPagamento::Pix,
            cpfCnpjCreditado: self::CNPJ_VALIDO,
            codigoInstituicaoFinanceira: 1,
            chavePix: self::CNPJ_VALIDO,
            identificadorPix: 'E123',
        )];
        $this->assertRegra('B99', $r->validar());
    }

    public function test_b105_a_prazo_sem_parcelas(): void
    {
        $r = $this->baseCargaLotacao();
        $r->pagamentos = [new InfPagamento(
            tipoPagamento: TipoPagamento::ContaCorrente,
            indPagamento: IndPagamento::APrazo,
            cpfCnpjCreditado: self::CNPJ_VALIDO,
            codigoInstituicaoFinanceira: 1,
            numeroAgencia: '1234',
            numeroConta: '56789',
        )];
        $this->assertRegra('B105', $r->validar());
    }

    public function test_b106_a_vista_com_parcelas(): void
    {
        $r = $this->baseCargaLotacao();
        $r->pagamentos = [(new InfPagamento(
            tipoPagamento: TipoPagamento::ContaCorrente,
            indPagamento: IndPagamento::AVista,
            cpfCnpjCreditado: self::CNPJ_VALIDO,
            codigoInstituicaoFinanceira: 1,
            numeroAgencia: '1234',
            numeroConta: '56789',
            parcelas: [new Parcela(1, '2026-07-10', 1500)],
        ))];
        $this->assertRegra('B106', $r->validar());
    }
}
