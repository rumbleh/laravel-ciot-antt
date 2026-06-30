<?php

declare(strict_types=1);

namespace Rumbleh\CiotAntt\Tests\Unit\Responses;

use Rumbleh\CiotAntt\Enums\TipoTransportador;
use Rumbleh\CiotAntt\Exceptions\CiotRejectionException;
use Rumbleh\CiotAntt\Responses\ConsultarCiotGeradoResponse;
use Rumbleh\CiotAntt\Responses\ConsultarExcecaoResponse;
use Rumbleh\CiotAntt\Responses\ConsultarFrotaTransportadorResponse;
use Rumbleh\CiotAntt\Responses\ConsultarSituacaoTransportadorResponse;
use Rumbleh\CiotAntt\Responses\DeclaracaoOperacaoTransporteResponse;
use Rumbleh\CiotAntt\Tests\TestCase;

final class ResponsesTest extends TestCase
{
    public function test_declaracao_monta_ciot_com_digito(): void
    {
        $resp = new DeclaracaoOperacaoTransporteResponse([
            'CodigoIdentificacaoOperacao' => '000000000123',
            'CodigoVerificador' => '4567',
            'Protocolo' => 'PROT123',
            'Codigo' => '110',
            'Mensagem' => 'Dados inseridos com sucesso!',
            'AvisoTransportador' => 'Atenção: piso mínimo aplicado.',
        ]);

        $this->assertSame('000000000123', $resp->ciot());
        $this->assertSame('4567', $resp->codigoVerificador());
        $this->assertSame('0000000001234567', $resp->ciotComDigito());
        $this->assertSame('PROT123', $resp->protocolo());
        $this->assertTrue($resp->sucesso());
        $this->assertFalse($resp->rejeitado());
        $this->assertTrue($resp->temAviso());
        $this->assertSame('Atenção: piso mínimo aplicado.', $resp->avisoTransportador());
    }

    public function test_declaracao_aceita_apelido_id_operacao_transporte(): void
    {
        $resp = new DeclaracaoOperacaoTransporteResponse([
            'IdOperacaoTransporte' => '000000000123',
            'CodigoVerificador' => '0007',
            'Codigo' => '110',
        ]);

        $this->assertSame('000000000123', $resp->ciot());
        $this->assertSame('0000000001230007', $resp->ciotComDigito());
    }

    public function test_codigo_verificador_recebe_padding(): void
    {
        $resp = new DeclaracaoOperacaoTransporteResponse([
            'CodigoIdentificacaoOperacao' => '000000000123',
            'CodigoVerificador' => 7,
            'Codigo' => '110',
        ]);

        $this->assertSame('0007', $resp->codigoVerificador());
    }

    public function test_rejeicao_lanca_excecao_quando_solicitado(): void
    {
        $resp = new DeclaracaoOperacaoTransporteResponse([
            'Codigo' => '205',
            'Mensagem' => 'O campo X é inválido.',
        ]);

        $this->assertTrue($resp->rejeitado());

        $this->expectException(CiotRejectionException::class);
        $resp->lancarSeRejeitado();
    }

    public function test_situacao_transportador(): void
    {
        $resp = new ConsultarSituacaoTransportadorResponse([
            'CpfCnpjTransportador' => self::CPF_VALIDO,
            'RNTRCTransportador' => self::RNTRC_VALIDO,
            'NomeRazaoSocialTransportador' => 'João Motorista',
            'RNTRCAtivo' => true,
            'TipoTransportador' => 'TAC',
            'EquiparadoTAC' => false,
            'Codigo' => '111',
            'Mensagem' => 'Consulta realizada com sucesso!',
        ]);

        $this->assertSame('João Motorista', $resp->nomeRazaoSocial());
        $this->assertTrue($resp->rntrcAtivo());
        $this->assertTrue($resp->apto());
        $this->assertSame(TipoTransportador::TAC, $resp->tipoTransportador());
        $this->assertFalse($resp->equiparadoTac());
        $this->assertTrue($resp->sucesso());
    }

    public function test_frota_transportador(): void
    {
        $resp = new ConsultarFrotaTransportadorResponse([
            'CpfCnpjTransportador' => self::CPF_VALIDO,
            'RNTRCAtivo' => true,
            'Frota' => [
                ['PlacaVeiculo' => 'ABC1234', 'SituacaoVeiculoFrotaTransportador' => true],
                ['PlacaVeiculo' => 'XYZ9876', 'SituacaoVeiculoFrotaTransportador' => false],
            ],
            'Codigo' => '111',
        ]);

        $this->assertCount(2, $resp->frota());
        $this->assertTrue($resp->veiculoPertence('abc-1234'));
        $this->assertFalse($resp->veiculoPertence('XYZ9876'));
        $this->assertFalse($resp->veiculoPertence('NAO0000'));
    }

    public function test_frota_normaliza_situacao_como_string_ou_inteiro(): void
    {
        // O manual tipa SituacaoVeiculoFrotaTransportador como alfanumérico
        // ("false"/"true", pág. 16) e como inteiro (0, pág. 17). Um (bool) direto
        // trataria a string "false" como true (inversão perigosa).
        $resp = new ConsultarFrotaTransportadorResponse([
            'Frota' => [
                ['PlacaVeiculo' => 'ABC1234', 'SituacaoVeiculoFrotaTransportador' => 'false'],
                ['PlacaVeiculo' => 'DEF5678', 'SituacaoVeiculoFrotaTransportador' => 'true'],
                ['PlacaVeiculo' => 'GHI9012', 'SituacaoVeiculoFrotaTransportador' => 0],
                ['PlacaVeiculo' => 'JKL3456', 'SituacaoVeiculoFrotaTransportador' => 1],
            ],
            'Codigo' => '111',
        ]);

        $this->assertFalse($resp->veiculoPertence('ABC1234'));
        $this->assertTrue($resp->veiculoPertence('DEF5678'));
        $this->assertFalse($resp->veiculoPertence('GHI9012'));
        $this->assertTrue($resp->veiculoPertence('JKL3456'));
    }

    public function test_consultar_excecao(): void
    {
        $resp = new ConsultarExcecaoResponse([
            'Retorno' => [
                'CpfCnpjTransportador' => self::CPF_VALIDO,
                'Flag' => true,
            ],
            'Codigo' => '111',
            'Mensagem' => 'OK',
        ]);

        $this->assertTrue($resp->naLista());
        $this->assertSame(self::CPF_VALIDO, $resp->cpfCnpjTransportador());
    }

    public function test_consultar_ciot_gerado_com_codigo_e_mensagem_em_lista(): void
    {
        $resp = new ConsultarCiotGeradoResponse([
            'CodigoIdentificacaoOperacao' => '0000000001234567',
            'Codigo' => ['111'],
            'Mensagem' => ['Consulta realizada com sucesso!'],
        ]);

        $this->assertSame('0000000001234567', $resp->ciotComDigito());
        $this->assertSame('111', $resp->codigo());
        $this->assertTrue($resp->sucesso());
        $this->assertSame('Consulta realizada com sucesso!', $resp->mensagem());
    }
}
