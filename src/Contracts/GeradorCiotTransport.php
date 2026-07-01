<?php

declare(strict_types=1);

namespace Rumbleh\CiotAntt\Contracts;

/**
 * Abstração da camada de transporte HTTP do Gerador CIOT v3 (token + API key).
 *
 * Existe para desacoplar a lógica de autenticação/geração do cliente HTTP
 * concreto (Guzzle), permitindo testar o {@see \Rumbleh\CiotAntt\GeradorCiot}
 * com um transporte falso, sem chamadas de rede.
 *
 * Implementações devem:
 *  - enviar o header "chave: {API_KEY}" no POST /token;
 *  - enviar "Authorization: Bearer {TOKEN}" no POST /gerar;
 *  - aplicar timeout e verificação TLS configurados;
 *  - lançar exceções próprias do pacote em respostas != 2xx
 *    (CiotAuthenticationException no /token; CiotUnauthorizedException/
 *    CiotApiException no /gerar) e CiotConnectionException em falhas de rede.
 */
interface GeradorCiotTransport
{
    /**
     * Autentica na API (POST /token) usando a API key e retorna o corpo JSON
     * decodificado (espera-se a chave "token").
     *
     * @return array<string, mixed>
     */
    public function postToken(string $apiKey): array;

    /**
     * Executa o POST /gerar com o token Bearer e o payload informado,
     * retornando o corpo decodificado. Pode ser um array (formatos
     * {"dados":{"ciot":...}} / {"Dados":{"CIOT":...}}) ou uma string ("123...").
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|string
     */
    public function postGerar(string $token, array $payload): array|string;
}
