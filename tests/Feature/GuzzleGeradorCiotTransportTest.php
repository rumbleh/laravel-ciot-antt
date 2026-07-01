<?php

declare(strict_types=1);

namespace Rumbleh\CiotAntt\Tests\Feature;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Rumbleh\CiotAntt\Exceptions\CiotApiException;
use Rumbleh\CiotAntt\Exceptions\CiotAuthenticationException;
use Rumbleh\CiotAntt\Exceptions\CiotConfigurationException;
use Rumbleh\CiotAntt\Exceptions\CiotConnectionException;
use Rumbleh\CiotAntt\Exceptions\CiotUnauthorizedException;
use Rumbleh\CiotAntt\Http\GuzzleGeradorCiotTransport;
use Rumbleh\CiotAntt\Tests\TestCase;

final class GuzzleGeradorCiotTransportTest extends TestCase
{
    private const BASE = 'https://mtcuybq605.execute-api.sa-east-1.amazonaws.com/api-ciot-prd/GeradorCIOT';

    /** @var list<array{request: Request}> */
    private array $historico = [];

    /**
     * @param  list<Response|ConnectException>  $respostas
     */
    private function transport(array $respostas): GuzzleGeradorCiotTransport
    {
        $mock = new MockHandler($respostas);
        $stack = HandlerStack::create($mock);
        $this->historico = [];
        $stack->push(Middleware::history($this->historico));

        $client = new Client(['handler' => $stack, 'http_errors' => false]);

        return new GuzzleGeradorCiotTransport(['base_url' => self::BASE], $client);
    }

    // ---- /token ------------------------------------------------------------

    public function test_post_token_envia_header_chave_e_url(): void
    {
        $transport = $this->transport([
            new Response(200, ['Content-Type' => 'application/json'], json_encode(['token' => 'JWT-xyz'])),
        ]);

        $resposta = $transport->postToken('MINHA-API-KEY');

        $this->assertSame(['token' => 'JWT-xyz'], $resposta);

        /** @var Request $request */
        $request = $this->historico[0]['request'];
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame(self::BASE . '/token', (string) $request->getUri());
        $this->assertSame('MINHA-API-KEY', $request->getHeaderLine('chave'));
        $this->assertSame('{}', (string) $request->getBody());
        $this->assertStringContainsString('application/json', $request->getHeaderLine('Content-Type'));
    }

    public function test_post_token_api_key_vazia_lanca_configuracao(): void
    {
        $transport = $this->transport([]);

        $this->expectException(CiotConfigurationException::class);
        $transport->postToken('   ');
    }

    public function test_post_token_status_nao_2xx_lanca_authentication(): void
    {
        $transport = $this->transport([
            new Response(403, ['Content-Type' => 'application/json'], json_encode(['erro' => 'chave invalida'])),
        ]);

        try {
            $transport->postToken('KEY');
            $this->fail('Esperava CiotAuthenticationException.');
        } catch (CiotAuthenticationException $e) {
            $this->assertSame(403, $e->statusHttp);
            $this->assertSame('/token', $e->endpoint);
            $this->assertStringContainsString('chave invalida', (string) $e->corpoResposta);
        }
    }

    // ---- /gerar ------------------------------------------------------------

    public function test_post_gerar_envia_bearer_e_url(): void
    {
        $transport = $this->transport([
            new Response(200, ['Content-Type' => 'application/json'], json_encode(['dados' => ['ciot' => '123']])),
        ]);

        $resposta = $transport->postGerar('JWT-abc', ['cpfCnpj' => self::CPF_VALIDO]);

        $this->assertSame(['dados' => ['ciot' => '123']], $resposta);

        /** @var Request $request */
        $request = $this->historico[0]['request'];
        $this->assertSame(self::BASE . '/gerar', (string) $request->getUri());
        $this->assertSame('Bearer JWT-abc', $request->getHeaderLine('Authorization'));
        $this->assertSame(json_encode(['cpfCnpj' => self::CPF_VALIDO]), (string) $request->getBody());
    }

    public function test_post_gerar_string_crua_retorna_string(): void
    {
        // Corpo "123456789" (texto cru, não-JSON) deve voltar como string.
        $transport = $this->transport([
            new Response(200, ['Content-Type' => 'text/plain'], '123456789'),
        ]);

        $this->assertSame('123456789', $transport->postGerar('JWT', ['cpfCnpj' => self::CPF_VALIDO]));
    }

    public function test_post_gerar_json_string_retorna_string(): void
    {
        // Corpo "\"123456789\"" (JSON string) também volta como string.
        $transport = $this->transport([
            new Response(200, ['Content-Type' => 'application/json'], '"123456789"'),
        ]);

        $this->assertSame('123456789', $transport->postGerar('JWT', ['cpfCnpj' => self::CPF_VALIDO]));
    }

    public function test_post_gerar_texto_nao_json_volta_como_string(): void
    {
        // Corpo não-JSON (ex.: identificador alfanumérico) volta cru como string.
        $transport = $this->transport([
            new Response(200, ['Content-Type' => 'text/plain'], 'CIOT-ABC-999'),
        ]);

        $this->assertSame('CIOT-ABC-999', $transport->postGerar('JWT', ['cpfCnpj' => self::CPF_VALIDO]));
    }

    public function test_post_token_sem_objeto_lanca_authentication(): void
    {
        // /token respondeu 2xx porém com uma string (não um objeto com "token").
        $transport = $this->transport([
            new Response(200, ['Content-Type' => 'application/json'], '"apenas-uma-string"'),
        ]);

        $this->expectException(CiotAuthenticationException::class);
        $transport->postToken('KEY');
    }

    public function test_post_gerar_401_lanca_unauthorized(): void
    {
        $transport = $this->transport([
            new Response(401, ['Content-Type' => 'application/json'], json_encode(['erro' => 'token expirado'])),
        ]);

        try {
            $transport->postGerar('JWT', ['cpfCnpj' => self::CPF_VALIDO]);
            $this->fail('Esperava CiotUnauthorizedException.');
        } catch (CiotUnauthorizedException $e) {
            $this->assertSame(401, $e->statusHttp);
            $this->assertSame('/gerar', $e->endpoint);
        }
    }

    public function test_post_gerar_5xx_lanca_api_exception(): void
    {
        $transport = $this->transport([
            new Response(500, ['Content-Type' => 'application/json'], json_encode(['erro' => 'interno'])),
        ]);

        try {
            $transport->postGerar('JWT', ['cpfCnpj' => self::CPF_VALIDO]);
            $this->fail('Esperava CiotApiException.');
        } catch (CiotApiException $e) {
            $this->assertSame(500, $e->statusHttp);
            $this->assertSame('/gerar', $e->endpoint);
        }
    }

    public function test_post_gerar_corpo_vazio_lanca_api_exception(): void
    {
        $transport = $this->transport([new Response(200, [], '')]);

        $this->expectException(CiotApiException::class);
        $transport->postGerar('JWT', ['cpfCnpj' => self::CPF_VALIDO]);
    }

    public function test_falha_de_conexao_lanca_connection(): void
    {
        $transport = $this->transport([
            new ConnectException('timeout', new Request('POST', self::BASE . '/gerar')),
        ]);

        $this->expectException(CiotConnectionException::class);
        $transport->postGerar('JWT', ['cpfCnpj' => self::CPF_VALIDO]);
    }

    public function test_base_url_ausente_lanca_configuracao(): void
    {
        $mock = new MockHandler([new Response(200, [], json_encode(['token' => 'X']))]);
        $client = new Client(['handler' => HandlerStack::create($mock), 'http_errors' => false]);
        $transport = new GuzzleGeradorCiotTransport(['base_url' => ''], $client);

        $this->expectException(CiotConfigurationException::class);
        $transport->postToken('KEY');
    }

    // ---- Construção do Client (builder interno, sem injeção) ----------------

    /**
     * Constrói o Client via o builder real (sem injetar um Client) e devolve as
     * opções efetivas do Guzzle, para provar que a config do pacote é aplicada.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function opcoesEfetivas(array $config): array
    {
        $transport = new GuzzleGeradorCiotTransport($config);

        $metodo = new \ReflectionMethod($transport, 'client');
        $metodo->setAccessible(true);
        $client = $metodo->invoke($transport);

        $prop = new \ReflectionProperty($client, 'config');
        $prop->setAccessible(true);

        /** @var array<string, mixed> $cfg */
        $cfg = $prop->getValue($client);

        return $cfg;
    }

    public function test_builder_aplica_verify_false_timeout_e_headers(): void
    {
        $cfg = $this->opcoesEfetivas([
            'base_url' => self::BASE,
            'timeout' => 45,
            'connect_timeout' => 7,
            'verificar_ssl' => false,
        ]);

        $this->assertFalse($cfg['verify'], 'verificar_ssl=false deve virar verify=false');
        $this->assertSame(45, $cfg['timeout']);
        $this->assertSame(7, $cfg['connect_timeout']);
        $this->assertFalse($cfg['http_errors']);
        $this->assertSame('application/json', $cfg['headers']['Accept']);
        $this->assertSame('application/json', $cfg['headers']['Content-Type']);
    }

    public function test_builder_verify_true_e_timeout_padrao(): void
    {
        $cfg = $this->opcoesEfetivas(['base_url' => self::BASE]);

        $this->assertTrue($cfg['verify'], 'sem config, verify deve ser true (seguro)');
        $this->assertSame(30, $cfg['timeout']);
        $this->assertSame(10, $cfg['connect_timeout']);
    }

    public function test_client_e_instancia_unica(): void
    {
        $transport = new GuzzleGeradorCiotTransport(['base_url' => self::BASE]);

        $metodo = new \ReflectionMethod($transport, 'client');
        $metodo->setAccessible(true);

        $this->assertSame(
            $metodo->invoke($transport),
            $metodo->invoke($transport),
            'O Client deve ser criado uma única vez e reutilizado.',
        );
    }
}
