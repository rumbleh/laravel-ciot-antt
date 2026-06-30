<?php

declare(strict_types=1);

namespace Rumbleh\CiotAntt\Tests\Support;

use Rumbleh\CiotAntt\Contracts\PefTransport;

/**
 * Transporte falso para testes: registra as chamadas feitas e devolve respostas
 * pré-programadas, sem qualquer acesso de rede ou certificado.
 */
final class FakePefTransport implements PefTransport
{
    /** @var list<array{metodo: string, servico: string, payload: array<string, mixed>}> */
    public array $chamadas = [];

    /** @var array<string, array<string, mixed>> respostas por nome de serviço */
    private array $respostas = [];

    /** @var array<string, mixed> resposta padrão quando o serviço não foi configurado */
    private array $respostaPadrao = ['Codigo' => '111', 'Mensagem' => 'OK'];

    /**
     * @param  array<string, mixed>  $resposta
     */
    public function programar(string $servico, array $resposta): self
    {
        $this->respostas[$servico] = $resposta;

        return $this;
    }

    public function post(string $servico, array $payload): array
    {
        return $this->registrar('POST', $servico, $payload);
    }

    public function get(string $servico, array $query): array
    {
        return $this->registrar('GET', $servico, $query);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function registrar(string $metodo, string $servico, array $payload): array
    {
        $this->chamadas[] = [
            'metodo' => $metodo,
            'servico' => $servico,
            'payload' => $payload,
        ];

        return $this->respostas[$servico] ?? $this->respostaPadrao;
    }

    /**
     * @return array{metodo: string, servico: string, payload: array<string, mixed>}|null
     */
    public function ultimaChamada(): ?array
    {
        return $this->chamadas[array_key_last($this->chamadas)] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    public function ultimoPayload(): array
    {
        return $this->ultimaChamada()['payload'] ?? [];
    }
}
