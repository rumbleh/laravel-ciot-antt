<?php

declare(strict_types=1);

namespace Rumbleh\CiotAntt\Tests\Support;

use Rumbleh\CiotAntt\Contracts\GeradorCiotTransport;
use Rumbleh\CiotAntt\Exceptions\CiotUnauthorizedException;

/**
 * Transporte falso do Gerador CIOT v3 para testes: registra as chamadas e
 * devolve respostas pré-programadas, sem qualquer acesso de rede.
 */
final class FakeGeradorCiotTransport implements GeradorCiotTransport
{
    /** @var list<string> API keys recebidas em cada POST /token */
    public array $tokenCalls = [];

    /** @var list<array{token: string, payload: array<string, mixed>}> */
    public array $gerarCalls = [];

    /** Quando definido, sobrescreve a resposta padrão do /token. */
    /** @var array<string, mixed>|null */
    private ?array $tokenResposta = null;

    /** @var array<string, mixed>|string */
    private array|string $gerarResposta = ['dados' => ['ciot' => '123456789']];

    /** Faz o próximo postGerar lançar 401 (uma única vez). */
    public bool $proximoGerarLancaUnauthorized = false;

    private int $contadorToken = 0;

    /**
     * @param  array<string, mixed>  $resposta
     */
    public function programarToken(array $resposta): self
    {
        $this->tokenResposta = $resposta;

        return $this;
    }

    /**
     * @param  array<string, mixed>|string  $resposta
     */
    public function programarGerar(array|string $resposta): self
    {
        $this->gerarResposta = $resposta;

        return $this;
    }

    public function postToken(string $apiKey): array
    {
        $this->tokenCalls[] = $apiKey;
        $this->contadorToken++;

        return $this->tokenResposta ?? ['token' => 'JWT-' . $this->contadorToken];
    }

    public function postGerar(string $token, array $payload): array|string
    {
        $this->gerarCalls[] = ['token' => $token, 'payload' => $payload];

        if ($this->proximoGerarLancaUnauthorized) {
            $this->proximoGerarLancaUnauthorized = false;

            throw CiotUnauthorizedException::em('/gerar', '{"erro":"401"}');
        }

        return $this->gerarResposta;
    }

    public function totalTokens(): int
    {
        return count($this->tokenCalls);
    }

    /**
     * @return array{token: string, payload: array<string, mixed>}|null
     */
    public function ultimaGerar(): ?array
    {
        return $this->gerarCalls[array_key_last($this->gerarCalls)] ?? null;
    }
}
