<?php

declare(strict_types=1);

namespace Rumbleh\CiotAntt\Contracts;

/**
 * Abstração da camada de transporte HTTP/mTLS com o web service PEF da ANTT.
 *
 * Existe para desacoplar a lógica de negócio do cliente HTTP concreto
 * (Guzzle), permitindo testar todos os serviços com um transporte falso/mock
 * sem realizar chamadas de rede.
 *
 * Implementações devem:
 *  - aplicar o certificado A1 (mTLS) e a verificação TLS configurados;
 *  - serializar o corpo como JSON (POST) ou query string (GET);
 *  - decodificar a resposta JSON em array associativo;
 *  - lançar CiotConnectionException em falhas de rede/HTTP/JSON.
 */
interface PefTransport
{
    /**
     * Executa uma requisição POST com corpo JSON e retorna a resposta
     * decodificada.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function post(string $servico, array $payload): array;

    /**
     * Executa uma requisição GET com query string e retorna a resposta
     * decodificada.
     *
     * @param  array<string, scalar>  $query
     * @return array<string, mixed>
     */
    public function get(string $servico, array $query): array;
}
