<?php

declare(strict_types=1);

namespace Rumbleh\CiotAntt\Tests\Unit\Requests;

use Rumbleh\CiotAntt\Requests\CancelamentoOperacaoTransporteRequest;
use Rumbleh\CiotAntt\Requests\ConsultarCiotGeradoRequest;
use Rumbleh\CiotAntt\Requests\ConsultarExcecaoRequest;
use Rumbleh\CiotAntt\Requests\ConsultarFrotaTransportadorRequest;
use Rumbleh\CiotAntt\Requests\ConsultarSituacaoTransportadorRequest;
use Rumbleh\CiotAntt\Requests\Components\DadosCarga;
use Rumbleh\CiotAntt\Requests\Components\Localidade;
use Rumbleh\CiotAntt\Requests\Components\OrigemDestino;
use Rumbleh\CiotAntt\Requests\EncerramentoOperacaoTransporteRequest;
use Rumbleh\CiotAntt\Requests\RetificacaoOperacaoTransporteRequest;
use Rumbleh\CiotAntt\Tests\TestCase;

final class OutrosPayloadsTest extends TestCase
{
    public function test_consultar_situacao_payload_e_metodo(): void
    {
        $req = new ConsultarSituacaoTransportadorRequest(
            self::CNPJ_VALIDO,
            self::CPF_VALIDO,
            self::RNTRC_8_DIGITOS,
        );

        $this->assertSame('ConsultarSituacaoTransportador', $req->servico());
        $this->assertSame('POST', $req->metodo());
        $this->assertSame([
            'CpfCnpjInteressado' => self::CNPJ_VALIDO,
            'CpfCnpjTransportador' => self::CPF_VALIDO,
            'RNTRCTransportador' => '012345678', // normalizado por B60
        ], $req->toArray());
    }

    public function test_consultar_frota_payload_inclui_placas_normalizadas(): void
    {
        $req = new ConsultarFrotaTransportadorRequest(
            self::CNPJ_VALIDO,
            self::CPF_VALIDO,
            self::RNTRC_VALIDO,
            ['abc-1234', 'XYZ9876'],
        );

        $payload = $req->toArray();
        $this->assertSame(['ABC1234', 'XYZ9876'], $payload['Placas']);
        $this->assertSame('ConsultarFrotaTransportador', $req->servico());
    }

    public function test_consultar_frota_detecta_placas_duplicadas(): void
    {
        $req = new ConsultarFrotaTransportadorRequest(
            self::CNPJ_VALIDO,
            self::CPF_VALIDO,
            self::RNTRC_VALIDO,
            ['ABC1234', 'abc1234'],
        );

        $this->assertContains(
            'B5: Existe duplicidade de placa na lista informada.',
            $req->validar(),
        );
    }

    public function test_cancelamento_payload(): void
    {
        $req = new CancelamentoOperacaoTransporteRequest('0000000001239999', 'Carga cancelada pelo cliente');

        $this->assertSame('CancelamentoOperacaoTransporte', $req->servico());
        $this->assertSame([
            'CodigoIdentificacaoOperacao' => '0000000001239999',
            'MotivoCancelamento' => 'Carga cancelada pelo cliente',
        ], $req->toArray());
        $this->assertSame([], $req->validar());
    }

    public function test_cancelamento_exige_motivo(): void
    {
        $req = new CancelamentoOperacaoTransporteRequest('0000000001239999', '   ');

        $this->assertContains('B1: O campo MotivoCancelamento é obrigatório.', $req->validar());
    }

    public function test_retificacao_payload(): void
    {
        $req = (new RetificacaoOperacaoTransporteRequest('0000000001239999'))
            ->comValorFrete(2000)
            ->comDataFimViagem('2026-07-15')
            ->comOrigemDestino(
                OrigemDestino::entre(
                    Localidade::origem()->comCep('01001000'),
                    Localidade::destino()->comCep('20040002'),
                ),
            )
            ->comDadosCarga(new DadosCarga(codigoNaturezaCarga: 5555, pesoCarga: 12000));

        $payload = $req->toArray();
        $this->assertSame('2000.00', $payload['ValorFrete']);
        $this->assertSame('2026-07-15', $payload['DataFimViagem']);
        $this->assertSame('01001000', $payload['OrigemDestino'][0]['Origem']['CepOrigem']);
        $this->assertSame('12000.00', $payload['DadosCarga']['PesoCarga']);
        $this->assertSame('RetificacaoOperacaoTransporte', $req->servico());
    }

    public function test_encerramento_payload_tac_agregado(): void
    {
        $req = (new EncerramentoOperacaoTransporteRequest('0000000009999999'))
            ->comOrigemDestino(
                OrigemDestino::entre(
                    Localidade::origem()->comMunicipio(3550308),
                    Localidade::destino()->comMunicipio(3304557),
                )->comDistancia(430)->comQtdViagens(4),
            )
            ->comPesoTotalCarga(50000);

        $payload = $req->toArray();
        $this->assertSame('0000000009999999', $payload['CodigoIdentificacaoOperacao']);
        $this->assertSame(430, $payload['OrigemDestino'][0]['DistanciaPercorrida']);
        $this->assertSame(4, $payload['OrigemDestino'][0]['QtdViagens']);
        // Campo "PesoTotalCarga" conforme o exemplo JSON do manual (encerramento).
        $this->assertSame(['PesoTotalCarga' => '50000.00'], $payload['DadosCarga']);
    }

    public function test_consultar_excecao_e_get_com_query(): void
    {
        $req = new ConsultarExcecaoRequest(self::CPF_VALIDO);

        $this->assertSame('GET', $req->metodo());
        $this->assertSame('ConsultarExcecao', $req->servico());
        $this->assertSame(['CPFCNPJTransportador' => self::CPF_VALIDO], $req->toArray());
    }

    public function test_consultar_ciot_gerado_payload(): void
    {
        $req = new ConsultarCiotGeradoRequest('000000000123', 2026);

        $this->assertSame('POST', $req->metodo());
        $this->assertSame([
            'CodigoIdentificacaoOperacao' => '000000000123',
            'AnoDeclaracao' => 2026,
        ], $req->toArray());
        $this->assertSame([], $req->validar());
    }

    public function test_consultar_ciot_gerado_valida_ano(): void
    {
        $req = new ConsultarCiotGeradoRequest('000000000123', 99);

        $this->assertNotEmpty($req->validar());
    }
}
