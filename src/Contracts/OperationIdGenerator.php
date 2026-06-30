<?php

declare(strict_types=1);

namespace Rumbleh\CiotAntt\Contracts;

use Rumbleh\CiotAntt\Requests\DeclaracaoOperacaoTransporteRequest;

/**
 * Gera o IdOperacaoTransporte (12 dígitos) exigido na declaração de operação
 * de transporte.
 *
 * Conforme o manual DCS PEF v1.1 (regra B4 e B16): "Código Identificador da
 * Operação de Transporte: deve ser gerado conforme padrão definido pela ANTT,
 * utilizando a DLL/Executável disponibilizada para geração do identificador".
 *
 * O pacote NÃO implementa o algoritmo da ANTT (ele depende da
 * DLL/executável oficial). Implemente este contrato fazendo a ponte com a
 * ferramenta da ANTT e registre a classe em config('ciot.operation_id_generator').
 *
 * Caso você já gere o id por fora, basta informá-lo manualmente na requisição
 * (DeclaracaoOperacaoTransporteRequest::comId()) e nenhum gerador é necessário.
 */
interface OperationIdGenerator
{
    /**
     * Retorna o IdOperacaoTransporte (12 dígitos) para a declaração informada.
     *
     * Recebe a requisição já montada para que a implementação possa usar os
     * dados da operação (contratante, contratado, data, etc.) caso o algoritmo
     * oficial precise deles.
     */
    public function gerar(DeclaracaoOperacaoTransporteRequest $request): string;
}
