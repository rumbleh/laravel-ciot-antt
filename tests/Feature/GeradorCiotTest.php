<?php

declare(strict_types=1);

namespace Rumbleh\CiotAntt\Tests\Feature;

use Closure;
use Rumbleh\CiotAntt\Exceptions\CiotApiException;
use Rumbleh\CiotAntt\Exceptions\CiotAuthenticationException;
use Rumbleh\CiotAntt\Exceptions\CiotValidationException;
use Rumbleh\CiotAntt\GeradorCiot;
use Rumbleh\CiotAntt\Requests\DeclaracaoOperacaoTransporteRequest;
use Rumbleh\CiotAntt\Support\TokenManager;
use Rumbleh\CiotAntt\Tests\Support\FakeGeradorCiotTransport;
use Rumbleh\CiotAntt\Tests\TestCase;

final class GeradorCiotTest extends TestCase
{
    private FakeGeradorCiotTransport $transport;

    protected function setUp(): void
    {
        parent::setUp();
        $this->transport = new FakeGeradorCiotTransport();
    }

    private function gerador(int $ttl = 3540, ?Closure $relogio = null): GeradorCiot
    {
        $tokens = new TokenManager($this->transport, 'API-KEY-TESTE', $ttl, $relogio);

        return new GeradorCiot($this->transport, $tokens);
    }

    // ---- Autenticação ------------------------------------------------------

    public function test_authenticate_retorna_token_e_envia_api_key(): void
    {
        $token = $this->gerador()->authenticate();

        $this->assertSame('JWT-1', $token);
        $this->assertSame(['API-KEY-TESTE'], $this->transport->tokenCalls);
    }

    public function test_token_ausente_na_resposta_lanca_excecao(): void
    {
        $this->transport->programarToken(['mensagem' => 'sem token']);

        $this->expectException(CiotAuthenticationException::class);
        $this->gerador()->authenticate();
    }

    public function test_token_e_reutilizado_entre_chamadas(): void
    {
        $gerador = $this->gerador();

        $gerador->gerarCiot(self::CPF_VALIDO);
        $gerador->gerarCiot(self::CNPJ_VALIDO);

        $this->assertSame(1, $this->transport->totalTokens(), 'Deveria autenticar só uma vez.');
        $this->assertSame('JWT-1', $this->transport->ultimaGerar()['token']);
    }

    public function test_refresh_token_forca_nova_autenticacao(): void
    {
        $gerador = $this->gerador();

        $this->assertSame('JWT-1', $gerador->authenticate());
        $this->assertSame('JWT-2', $gerador->refreshToken());
        $this->assertSame(2, $this->transport->totalTokens());
    }

    public function test_token_expira_e_reautentica(): void
    {
        $agora = 1000;
        $relogio = static function () use (&$agora): int {
            return $agora;
        };

        $gerador = $this->gerador(ttl: 3540, relogio: $relogio);

        $gerador->gerarCiot(self::CPF_VALIDO);          // JWT-1 (expira em 4540)
        $this->assertSame(1, $this->transport->totalTokens());

        $agora = 4540;                                   // exatamente no limite → expirou
        $gerador->gerarCiot(self::CPF_VALIDO);          // JWT-2
        $this->assertSame(2, $this->transport->totalTokens());
    }

    // ---- Geração / consulta ------------------------------------------------

    public function test_gerar_ciot_envia_payload_correto(): void
    {
        $this->gerador()->gerarCiot(self::CPF_VALIDO);

        $this->assertSame(['cpfCnpj' => self::CPF_VALIDO], $this->transport->ultimaGerar()['payload']);
    }

    public function test_consultar_ciot_usa_chave_cnpj(): void
    {
        $this->transport->programarGerar(['dados' => ['ciot' => '555']]);

        $ciot = $this->gerador()->consultarCiot(self::CNPJ_VALIDO);

        $this->assertSame('555', $ciot);
        $this->assertSame(['cnpj' => self::CNPJ_VALIDO], $this->transport->ultimaGerar()['payload']);
    }

    public function test_consultar_ciot_rejeita_cpf(): void
    {
        $this->expectException(CiotValidationException::class);
        $this->gerador()->consultarCiot(self::CPF_VALIDO);
    }

    public function test_documento_invalido_lanca_validation(): void
    {
        $this->expectException(CiotValidationException::class);
        $this->gerador()->gerarCiot('123');
    }

    // ---- Os três formatos de resposta --------------------------------------

    public function test_formato1_dados_minusculo(): void
    {
        $this->transport->programarGerar(['dados' => ['ciot' => '111222333']]);

        $this->assertSame('111222333', $this->gerador()->gerarCiot(self::CPF_VALIDO));
    }

    public function test_formato2_dados_maiusculo(): void
    {
        $this->transport->programarGerar(['Dados' => ['CIOT' => '444555666']]);

        $this->assertSame('444555666', $this->gerador()->gerarCiot(self::CPF_VALIDO));
    }

    public function test_formato3_string_crua(): void
    {
        $this->transport->programarGerar('123456789');

        $this->assertSame('123456789', $this->gerador()->gerarCiot(self::CPF_VALIDO));
    }

    public function test_ciot_no_nivel_raiz(): void
    {
        $this->transport->programarGerar(['CIOT' => '999000111']);

        $this->assertSame('999000111', $this->gerador()->gerarCiot(self::CPF_VALIDO));
    }

    public function test_resposta_sem_ciot_lanca_api_exception(): void
    {
        $this->transport->programarGerar(['mensagem' => 'nada aqui']);

        $this->expectException(CiotApiException::class);
        $this->gerador()->gerarCiot(self::CPF_VALIDO);
    }

    public function test_resposta_string_vazia_lanca_api_exception(): void
    {
        $this->transport->programarGerar('   ');

        $this->expectException(CiotApiException::class);
        $this->gerador()->gerarCiot(self::CPF_VALIDO);
    }

    // ---- Retentativa em 401 ------------------------------------------------

    public function test_401_renova_token_e_tenta_novamente(): void
    {
        $this->transport->programarGerar('987654321');
        $this->transport->proximoGerarLancaUnauthorized = true;

        $ciot = $this->gerador()->gerarCiot(self::CPF_VALIDO);

        $this->assertSame('987654321', $ciot);
        $this->assertSame(2, $this->transport->totalTokens(), 'Deveria reautenticar após 401.');
        $this->assertCount(2, $this->transport->gerarCalls);
        $this->assertSame('JWT-2', $this->transport->ultimaGerar()['token']);
    }

    // ---- Integração com OperationIdGenerator -------------------------------

    public function test_implementa_operation_id_generator_usando_contratado(): void
    {
        $this->transport->programarGerar('000111222333');

        $request = DeclaracaoOperacaoTransporteRequest::cargaLotacao(
            self::CNPJ_VALIDO,
            self::RNTRC_VALIDO,
            self::CNPJ_VALIDO_2,
        );

        $id = $this->gerador()->gerar($request);

        $this->assertSame('000111222333', $id);
        $this->assertSame(['cpfCnpj' => self::CNPJ_VALIDO], $this->transport->ultimaGerar()['payload']);
    }
}
