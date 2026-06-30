<?php

declare(strict_types=1);

namespace Rumbleh\CiotAntt\Requests\Components;

use Rumbleh\CiotAntt\Support\Payload;

/**
 * Indicadores operacionais (item 17 da declaração).
 *
 * Objeto obrigatório quando TipoOperacao = 1 (carga lotação).
 *  - IndAltoDesempenho: true = operação de alto desempenho / false = padrão.
 *  - IndRetornoVazio: true = operação com retorno vazio / false = sem previsão.
 *  - ComposicaoVeicular: true = é composição veicular / false = não é.
 */
final class InfIndicadoresOperacionais
{
    public function __construct(
        public readonly bool $indAltoDesempenho = false,
        public readonly bool $indRetornoVazio = false,
        public readonly bool $composicaoVeicular = false,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return Payload::semNulos([
            'IndAltoDesempenho' => $this->indAltoDesempenho,
            'IndRetornoVazio' => $this->indRetornoVazio,
            'ComposicaoVeicular' => $this->composicaoVeicular,
        ]);
    }
}
