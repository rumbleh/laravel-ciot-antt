<?php

declare(strict_types=1);

namespace Rumbleh\CiotAntt\Tests\Support;

use Rumbleh\CiotAntt\Contracts\OperationIdGenerator;
use Rumbleh\CiotAntt\Requests\DeclaracaoOperacaoTransporteRequest;

/**
 * Implementação fake do gerador de IdOperacaoTransporte para testes do
 * container/ServiceProvider.
 */
final class GeradorDeIdFake implements OperationIdGenerator
{
    public function gerar(DeclaracaoOperacaoTransporteRequest $request): string
    {
        return '111122223333';
    }
}
