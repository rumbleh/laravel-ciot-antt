<?php

declare(strict_types=1);

namespace Rumbleh\CiotAntt\Requests;

/**
 * Contrato comum a todas as requisições dos 8 serviços do PEF/ANTT.
 *
 * - servico(): nome do serviço (último segmento da URL), ex.:
 *   "DeclaracaoOperacaoTransporte".
 * - metodo(): verbo HTTP ("POST" para 7 serviços; "GET" para ConsultarExcecao).
 * - toArray(): payload já no formato JSON esperado pela ANTT (POST) ou os
 *   parâmetros de query (GET).
 * - validar(): regras de negócio do manual verificáveis localmente. Retorna a
 *   lista de mensagens de erro (cada uma referenciando a regra, ex.: "B18:...").
 *   Lista vazia = nada a apontar localmente.
 *
 * Observação: erros de FORMATO (CPF/CNPJ/placa/RNTRC inválidos) são lançados
 * imediatamente na construção dos objetos (via Value Objects), com a regra B4.
 * O validar() concentra as regras CONDICIONAIS e de relacionamento entre campos.
 */
interface PefRequest
{
    public function servico(): string;

    public function metodo(): string;

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array;

    /**
     * @return list<string>
     */
    public function validar(): array;
}
