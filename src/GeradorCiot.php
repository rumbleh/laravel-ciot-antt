<?php

declare(strict_types=1);

namespace Rumbleh\CiotAntt;

use Rumbleh\CiotAntt\Contracts\GeradorCiotTransport;
use Rumbleh\CiotAntt\Contracts\OperationIdGenerator;
use Rumbleh\CiotAntt\Exceptions\CiotApiException;
use Rumbleh\CiotAntt\Exceptions\CiotUnauthorizedException;
use Rumbleh\CiotAntt\Requests\DeclaracaoOperacaoTransporteRequest;
use Rumbleh\CiotAntt\Support\TokenManager;
use Rumbleh\CiotAntt\ValueObjects\Documento;

/**
 * Cliente de alto nível do Gerador CIOT v3 (token + API key).
 *
 * Abstrai completamente a API: o consumidor não precisa conhecer autenticação,
 * renovação de token, headers ou endpoints — apenas chama gerarCiot() /
 * consultarCiot().
 *
 *   $numero  = $gerador->gerarCiot('12345678901');
 *   $consulta = $gerador->consultarCiot('12345678000199');
 *
 * Também implementa {@see OperationIdGenerator}: registrando-o como
 * "ciot.operation_id_generator" (ou apenas definindo a CIOT_API_KEY), o
 * IdOperacaoTransporte exigido por {@see Ciot::declararOperacao()} passa a ser
 * gerado automaticamente por esta API — ocupando o lugar da DLL oficial.
 */
final class GeradorCiot implements OperationIdGenerator
{
    public function __construct(
        private readonly GeradorCiotTransport $transport,
        private readonly TokenManager $tokens,
    ) {
    }

    /**
     * Garante um token válido (autenticando se necessário) e o retorna.
     */
    public function authenticate(): string
    {
        return $this->tokens->token();
    }

    /**
     * Força a renovação do token (novo POST /token) e retorna o novo JWT.
     */
    public function refreshToken(): string
    {
        return $this->tokens->refresh();
    }

    /**
     * Gera o CIOT a partir de um CPF ou CNPJ.
     */
    public function gerarCiot(string $cpfCnpj): string
    {
        $documento = Documento::de($cpfCnpj)->valor();

        return $this->extrairCiot($this->enviarGerar(['cpfCnpj' => $documento]));
    }

    /**
     * Consulta o CIOT a partir de um CNPJ. Usa o mesmo endpoint /gerar,
     * mudando apenas o payload (chave "cnpj").
     */
    public function consultarCiot(string $cnpj): string
    {
        $documento = Documento::cnpj($cnpj)->valor();

        return $this->extrairCiot($this->enviarGerar(['cnpj' => $documento]));
    }

    /**
     * Implementação de OperationIdGenerator: gera o identificador da operação
     * (CIOT) a partir do CPF/CNPJ do contratado (transportador) da declaração.
     */
    public function gerar(DeclaracaoOperacaoTransporteRequest $request): string
    {
        return $this->gerarCiot($request->cpfCnpjContratado);
    }

    /**
     * Envia o POST /gerar com o token atual. Se o servidor responder 401
     * (token expirado antes do TTL local), renova o token e tenta uma vez mais.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|string
     */
    private function enviarGerar(array $payload): array|string
    {
        try {
            return $this->transport->postGerar($this->tokens->token(), $payload);
        } catch (CiotUnauthorizedException) {
            return $this->transport->postGerar($this->tokens->refresh(), $payload);
        }
    }

    /**
     * Interpreta os três formatos de resposta possíveis do /gerar:
     *   1) {"dados": {"ciot": "123456789"}}
     *   2) {"Dados": {"CIOT": "123456789"}}
     *   3) "123456789"
     *
     * @param  array<string, mixed>|string  $resposta
     */
    private function extrairCiot(array|string $resposta): string
    {
        if (is_string($resposta)) {
            $ciot = trim($resposta);

            if ($ciot !== '') {
                return $ciot;
            }

            throw CiotApiException::respostaInesperada('/gerar', $resposta);
        }

        // Formatos 1 e 2: container "dados"/"Dados" com "ciot"/"CIOT".
        $container = $resposta['dados'] ?? $resposta['Dados'] ?? null;
        if (is_array($container)) {
            $ciot = $this->valorCiot($container);
            if ($ciot !== null) {
                return $ciot;
            }
        }

        // Tolerância: CIOT no nível raiz.
        $ciot = $this->valorCiot($resposta);
        if ($ciot !== null) {
            return $ciot;
        }

        throw CiotApiException::respostaInesperada(
            '/gerar',
            json_encode($resposta) ?: ''
        );
    }

    /**
     * Extrai "ciot"/"CIOT" (case-insensitive) de um array, como string não vazia.
     *
     * @param  array<string, mixed>  $dados
     */
    private function valorCiot(array $dados): ?string
    {
        foreach ($dados as $chave => $valor) {
            if (strcasecmp((string) $chave, 'ciot') === 0 && is_scalar($valor) && (string) $valor !== '') {
                return (string) $valor;
            }
        }

        return null;
    }
}
