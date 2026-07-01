<?php

declare(strict_types=1);

namespace Rumbleh\CiotAntt\Support;

use Closure;
use Rumbleh\CiotAntt\Contracts\GeradorCiotTransport;
use Rumbleh\CiotAntt\Exceptions\CiotAuthenticationException;

/**
 * Gerencia o ciclo de vida do token JWT do Gerador CIOT v3.
 *
 * A DLL oficial reutiliza o token por ~59 minutos; este gerenciador faz o
 * mesmo: autentica apenas quando necessário, reutiliza o token em memória e o
 * renova automaticamente ao expirar (ou sob demanda via {@see refresh()}).
 *
 * O relógio é injetável (Closure que devolve o timestamp Unix) para permitir
 * testar a expiração de forma determinística.
 */
final class TokenManager
{
    private ?string $token = null;

    private int $expiraEm = 0;

    /** @var Closure(): int */
    private readonly Closure $relogio;

    /**
     * @param  int  $ttl  Validade do token em segundos (padrão 3540 = 59 min).
     * @param  (Closure(): int)|null  $relogio  Fonte de tempo; padrão time().
     */
    public function __construct(
        private readonly GeradorCiotTransport $transport,
        private readonly string $apiKey,
        private readonly int $ttl = 3540,
        ?Closure $relogio = null,
    ) {
        $this->relogio = $relogio ?? static fn (): int => time();
    }

    /**
     * Retorna um token válido, autenticando apenas se não houver um em cache
     * ou se o atual já expirou.
     */
    public function token(): string
    {
        if ($this->token !== null && ($this->relogio)() < $this->expiraEm) {
            return $this->token;
        }

        return $this->autenticar();
    }

    /**
     * Força uma nova autenticação (POST /token), descartando o token atual.
     */
    public function autenticar(): string
    {
        $resposta = $this->transport->postToken($this->apiKey);

        $token = $resposta['token'] ?? $resposta['Token'] ?? null;

        if (! is_string($token) || trim($token) === '') {
            throw CiotAuthenticationException::tokenAusente(
                json_encode($resposta) ?: '',
                '/token',
            );
        }

        $this->token = $token;
        $this->expiraEm = ($this->relogio)() + $this->ttl;

        return $token;
    }

    /**
     * Descarta o token em cache e autentica novamente, devolvendo o novo token.
     */
    public function refresh(): string
    {
        $this->esquecer();

        return $this->autenticar();
    }

    /** Esquece o token em cache (a próxima chamada autentica de novo). */
    public function esquecer(): void
    {
        $this->token = null;
        $this->expiraEm = 0;
    }
}
