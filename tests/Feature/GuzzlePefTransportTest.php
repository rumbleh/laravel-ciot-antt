<?php

declare(strict_types=1);

namespace Rumbleh\CiotAntt\Tests\Feature;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Rumbleh\CiotAntt\Exceptions\CiotConnectionException;
use Rumbleh\CiotAntt\Http\GuzzlePefTransport;
use Rumbleh\CiotAntt\Tests\TestCase;

final class GuzzlePefTransportTest extends TestCase
{
    /** @var list<array{request: Request}> */
    private array $historico = [];

    /**
     * @param  list<Response>  $respostas
     */
    private function transport(array $respostas): GuzzlePefTransport
    {
        $mock = new MockHandler($respostas);
        $stack = HandlerStack::create($mock);
        $this->historico = [];
        $stack->push(Middleware::history($this->historico));

        $client = new Client(['handler' => $stack, 'http_errors' => false]);

        $config = [
            'ambiente' => 'homologacao',
            'urls' => ['homologacao' => 'https://appservices-hml.antt.gov.br/pefServices'],
            'path_prefix' => 'api',
        ];

        return new GuzzlePefTransport($config, $client);
    }

    public function test_post_monta_url_e_envia_json(): void
    {
        $transport = $this->transport([
            new Response(200, ['Content-Type' => 'application/json'], json_encode(['Codigo' => '110'])),
        ]);

        $resultado = $transport->post('DeclaracaoOperacaoTransporte', ['ValorFrete' => '1500.00']);

        $this->assertSame(['Codigo' => '110'], $resultado);

        /** @var Request $request */
        $request = $this->historico[0]['request'];
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame(
            'https://appservices-hml.antt.gov.br/pefServices/api/DeclaracaoOperacaoTransporte',
            (string) $request->getUri(),
        );
        $this->assertSame('{"ValorFrete":"1500.00"}', (string) $request->getBody());
        $this->assertStringContainsString('application/json', $request->getHeaderLine('Content-Type'));
    }

    public function test_get_monta_query_string(): void
    {
        $transport = $this->transport([
            new Response(200, [], json_encode(['Codigo' => '111'])),
        ]);

        $transport->get('ConsultarExcecao', ['CPFCNPJTransportador' => self::CPF_VALIDO]);

        /** @var Request $request */
        $request = $this->historico[0]['request'];
        $this->assertSame('GET', $request->getMethod());
        $this->assertStringContainsString('ConsultarExcecao?CPFCNPJTransportador=' . self::CPF_VALIDO, (string) $request->getUri());
    }

    public function test_resposta_nao_json_lanca_excecao_de_conexao(): void
    {
        $transport = $this->transport([
            new Response(200, ['Content-Type' => 'text/html'], '<html>erro</html>'),
        ]);

        $this->expectException(CiotConnectionException::class);
        $transport->post('DeclaracaoOperacaoTransporte', []);
    }

    public function test_corpo_vazio_2xx_retorna_array_vazio(): void
    {
        $transport = $this->transport([new Response(204, [], '')]);

        $this->assertSame([], $transport->post('CancelamentoOperacaoTransporte', []));
    }

    public function test_erro_5xx_sem_corpo_lanca_excecao(): void
    {
        $transport = $this->transport([new Response(503, [], '')]);

        $this->expectException(CiotConnectionException::class);
        $transport->post('DeclaracaoOperacaoTransporte', []);
    }

    public function test_rejeicao_4xx_com_json_e_repassada(): void
    {
        // Mesmo em 4xx, se vier JSON com Codigo, repassa para a camada de serviço interpretar.
        $transport = $this->transport([
            new Response(400, ['Content-Type' => 'application/json'], json_encode(['Codigo' => '205', 'Mensagem' => 'inválido'])),
        ]);

        $resultado = $transport->post('DeclaracaoOperacaoTransporte', []);
        $this->assertSame('205', $resultado['Codigo']);
    }
}
