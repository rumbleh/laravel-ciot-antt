<?php

declare(strict_types=1);

namespace Rumbleh\CiotAntt\Http;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;
use Rumbleh\CiotAntt\Contracts\GeradorCiotTransport;
use Rumbleh\CiotAntt\Exceptions\CiotApiException;
use Rumbleh\CiotAntt\Exceptions\CiotAuthenticationException;
use Rumbleh\CiotAntt\Exceptions\CiotConfigurationException;
use Rumbleh\CiotAntt\Exceptions\CiotConnectionException;
use Rumbleh\CiotAntt\Exceptions\CiotUnauthorizedException;

/**
 * Implementação do transporte do Gerador CIOT v3 sobre Guzzle.
 *
 * Diferente do transporte PEF (mTLS por certificado), aqui a autenticação é por
 * API key + token JWT:
 *  - POST /token  → header "chave: {API_KEY}", corpo "{}", devolve {"token": "..."}.
 *  - POST /gerar  → header "Authorization: Bearer {TOKEN}", corpo com o documento.
 *
 * Uma única instância do Client é criada e reutilizada. A URL base e a
 * verificação TLS (verify) são totalmente configuráveis — nada fica fixo.
 */
final class GuzzleGeradorCiotTransport implements GeradorCiotTransport
{
    private ?Client $client;

    /**
     * @param  array<string, mixed>  $config  Bloco "gerador" já resolvido: base_url, timeout, verificar_ssl.
     * @param  Client|null  $client  Cliente Guzzle pré-construído (usado em testes com MockHandler).
     */
    public function __construct(
        private readonly array $config,
        ?Client $client = null,
        private readonly ?LoggerInterface $logger = null,
    ) {
        $this->client = $client;
    }

    public function postToken(string $apiKey): array
    {
        if (trim($apiKey) === '') {
            throw CiotConfigurationException::apiKeyNaoConfigurada();
        }

        [$status, $corpo] = $this->enviar('POST', '/token', [
            'headers' => ['chave' => $apiKey],
            'json' => new \stdClass(), // corpo "{}"
        ]);

        if ($status < 200 || $status >= 300) {
            throw CiotAuthenticationException::statusInvalido($status, $corpo, '/token');
        }

        $dados = $this->decodificar($corpo, '/token', $status);

        if (! is_array($dados)) {
            throw CiotAuthenticationException::tokenAusente($corpo, '/token');
        }

        /** @var array<string, mixed> $dados */
        return $dados;
    }

    public function postGerar(string $token, array $payload): array|string
    {
        [$status, $corpo] = $this->enviar('POST', '/gerar', [
            'headers' => ['Authorization' => 'Bearer ' . $token],
            'json' => $payload,
        ]);

        if ($status === 401) {
            throw CiotUnauthorizedException::em('/gerar', $corpo);
        }

        if ($status < 200 || $status >= 300) {
            throw CiotApiException::statusInesperado('/gerar', $status, $corpo);
        }

        $dados = $this->decodificar($corpo, '/gerar', $status);

        // A resposta pode ser um array (formatos com "dados"/"Dados") ou uma
        // string crua ("123456789"). Repassa ambos; a extração do CIOT fica no
        // GeradorCiot. Demais escalares (número) são normalizados para string.
        if (is_array($dados)) {
            return $dados;
        }

        return is_string($dados) ? $dados : (string) $dados;
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array{0: int, 1: string}  [status, corpo]
     */
    private function enviar(string $metodo, string $endpoint, array $options): array
    {
        $url = $this->baseUrl() . $endpoint;

        try {
            $response = $this->client()->request($metodo, $url, $options);
        } catch (ConnectException $e) {
            throw new CiotConnectionException(
                "Falha de conexão/TLS com o Gerador CIOT ao chamar {$endpoint}: {$e->getMessage()}",
                null,
                null,
                $e,
            );
        } catch (GuzzleException $e) {
            throw new CiotConnectionException(
                "Erro ao chamar o Gerador CIOT em {$endpoint}: {$e->getMessage()}",
                null,
                null,
                $e,
            );
        }

        $status = $response->getStatusCode();

        $this->logger?->info('Gerador CIOT request', [
            'endpoint' => $endpoint,
            'metodo' => $metodo,
            'status' => $status,
        ]);

        return [$status, (string) $response->getBody()];
    }

    private function client(): Client
    {
        if ($this->client !== null) {
            return $this->client;
        }

        return $this->client = new Client([
            'timeout' => (int) ($this->config['timeout'] ?? 30),
            'connect_timeout' => (int) ($this->config['connect_timeout'] ?? 10),
            'http_errors' => false,
            'verify' => $this->config['verificar_ssl'] ?? true,
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    private function baseUrl(): string
    {
        $base = rtrim((string) ($this->config['base_url'] ?? ''), '/');

        if ($base === '') {
            throw CiotConfigurationException::urlGeradorNaoConfigurada(
                (string) ($this->config['ambiente'] ?? '')
            );
        }

        return $base;
    }

    /**
     * Decodifica o corpo JSON. Retorna array (objeto), string (JSON string ou
     * texto cru numérico) conforme o formato. Lança CiotApiException se o corpo
     * 2xx for vazio/indecifrável quando se esperava conteúdo.
     *
     * @return array<string, mixed>|string
     */
    private function decodificar(string $corpo, string $endpoint, int $status): array|string
    {
        $corpo = trim($corpo);

        if ($corpo === '') {
            throw CiotApiException::respostaInesperada($endpoint, '');
        }

        $dados = json_decode($corpo, true);

        if (is_array($dados)) {
            /** @var array<string, mixed> $dados */
            return $dados;
        }

        // JSON string ("123456789") decodifica para string/int/float.
        if (is_string($dados) || is_int($dados) || is_float($dados)) {
            return (string) $dados;
        }

        // Não é JSON válido: aceita texto cru não vazio (ex.: "123456789").
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $corpo;
        }

        throw CiotApiException::respostaInesperada($endpoint, mb_substr($corpo, 0, 500));
    }
}
