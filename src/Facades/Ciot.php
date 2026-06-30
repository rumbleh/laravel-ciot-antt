<?php

declare(strict_types=1);

namespace Rumbleh\CiotAntt\Facades;

use Illuminate\Support\Facades\Facade;
use Rumbleh\CiotAntt\Responses\CancelamentoOperacaoTransporteResponse;
use Rumbleh\CiotAntt\Responses\ConsultarCiotGeradoResponse;
use Rumbleh\CiotAntt\Responses\ConsultarExcecaoResponse;
use Rumbleh\CiotAntt\Responses\ConsultarFrotaTransportadorResponse;
use Rumbleh\CiotAntt\Responses\ConsultarSituacaoTransportadorResponse;
use Rumbleh\CiotAntt\Responses\DeclaracaoOperacaoTransporteResponse;
use Rumbleh\CiotAntt\Responses\EncerramentoOperacaoTransporteResponse;
use Rumbleh\CiotAntt\Responses\RetificacaoOperacaoTransporteResponse;

/**
 * @method static ConsultarSituacaoTransportadorResponse consultarSituacaoTransportador(\Rumbleh\CiotAntt\Requests\ConsultarSituacaoTransportadorRequest $request)
 * @method static ConsultarFrotaTransportadorResponse consultarFrotaTransportador(\Rumbleh\CiotAntt\Requests\ConsultarFrotaTransportadorRequest $request)
 * @method static DeclaracaoOperacaoTransporteResponse declararOperacao(\Rumbleh\CiotAntt\Requests\DeclaracaoOperacaoTransporteRequest $request)
 * @method static CancelamentoOperacaoTransporteResponse cancelarOperacao(\Rumbleh\CiotAntt\Requests\CancelamentoOperacaoTransporteRequest $request)
 * @method static RetificacaoOperacaoTransporteResponse retificarOperacao(\Rumbleh\CiotAntt\Requests\RetificacaoOperacaoTransporteRequest $request)
 * @method static EncerramentoOperacaoTransporteResponse encerrarOperacao(\Rumbleh\CiotAntt\Requests\EncerramentoOperacaoTransporteRequest $request)
 * @method static ConsultarExcecaoResponse consultarExcecao(\Rumbleh\CiotAntt\Requests\ConsultarExcecaoRequest|string $request)
 * @method static ConsultarCiotGeradoResponse consultarCiotGerado(\Rumbleh\CiotAntt\Requests\ConsultarCiotGeradoRequest $request)
 * @method static array enviar(\Rumbleh\CiotAntt\Requests\PefRequest $request)
 *
 * @see \Rumbleh\CiotAntt\Ciot
 */
final class Ciot extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Rumbleh\CiotAntt\Ciot::class;
    }
}
