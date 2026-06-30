<?php

declare(strict_types=1);

namespace Rumbleh\CiotAntt;

use DateTimeImmutable;
use Rumbleh\CiotAntt\Contracts\OperationIdGenerator;
use Rumbleh\CiotAntt\Contracts\PefTransport;
use Rumbleh\CiotAntt\Exceptions\CiotConfigurationException;
use Rumbleh\CiotAntt\Exceptions\CiotValidationException;
use Rumbleh\CiotAntt\Requests\CancelamentoOperacaoTransporteRequest;
use Rumbleh\CiotAntt\Requests\ConsultarCiotGeradoRequest;
use Rumbleh\CiotAntt\Requests\ConsultarExcecaoRequest;
use Rumbleh\CiotAntt\Requests\ConsultarFrotaTransportadorRequest;
use Rumbleh\CiotAntt\Requests\ConsultarSituacaoTransportadorRequest;
use Rumbleh\CiotAntt\Requests\DeclaracaoOperacaoTransporteRequest;
use Rumbleh\CiotAntt\Requests\EncerramentoOperacaoTransporteRequest;
use Rumbleh\CiotAntt\Requests\PefRequest;
use Rumbleh\CiotAntt\Requests\RetificacaoOperacaoTransporteRequest;
use Rumbleh\CiotAntt\Responses\CancelamentoOperacaoTransporteResponse;
use Rumbleh\CiotAntt\Responses\ConsultarCiotGeradoResponse;
use Rumbleh\CiotAntt\Responses\ConsultarExcecaoResponse;
use Rumbleh\CiotAntt\Responses\ConsultarFrotaTransportadorResponse;
use Rumbleh\CiotAntt\Responses\ConsultarSituacaoTransportadorResponse;
use Rumbleh\CiotAntt\Responses\DeclaracaoOperacaoTransporteResponse;
use Rumbleh\CiotAntt\Responses\EncerramentoOperacaoTransporteResponse;
use Rumbleh\CiotAntt\Responses\RetificacaoOperacaoTransporteResponse;

/**
 * Cliente de alto nível para os 8 serviços do PEF/ANTT (emissão de CIOT).
 *
 * Cada método recebe um objeto de requisição (montado pelos builders fluentes),
 * aplica a validação local de regras de negócio (se habilitada na config) e
 * retorna um objeto de resposta tipado.
 *
 * Erros de validação local lançam CiotValidationException ANTES da chamada de
 * rede. Falhas de comunicação lançam CiotConnectionException. As respostas de
 * rejeição da ANTT (código != 110/111) NÃO lançam exceção por padrão — use
 * $response->rejeitado() / $response->lancarSeRejeitado() conforme preferir.
 */
final class Ciot
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        private readonly PefTransport $transport,
        private readonly array $config = [],
        private readonly ?OperationIdGenerator $idGenerator = null,
    ) {
    }

    // ---- 01 ----------------------------------------------------------------

    public function consultarSituacaoTransportador(
        ConsultarSituacaoTransportadorRequest $request
    ): ConsultarSituacaoTransportadorResponse {
        return new ConsultarSituacaoTransportadorResponse($this->enviar($request));
    }

    // ---- 02 ----------------------------------------------------------------

    public function consultarFrotaTransportador(
        ConsultarFrotaTransportadorRequest $request
    ): ConsultarFrotaTransportadorResponse {
        return new ConsultarFrotaTransportadorResponse($this->enviar($request));
    }

    // ---- 03 (gera o CIOT) --------------------------------------------------

    public function declararOperacao(
        DeclaracaoOperacaoTransporteRequest $request
    ): DeclaracaoOperacaoTransporteResponse {
        $this->resolverIdOperacao($request);
        $this->preencherDataDeclaracao($request);

        return new DeclaracaoOperacaoTransporteResponse($this->enviar($request));
    }

    // ---- 04 ----------------------------------------------------------------

    public function cancelarOperacao(
        CancelamentoOperacaoTransporteRequest $request
    ): CancelamentoOperacaoTransporteResponse {
        return new CancelamentoOperacaoTransporteResponse($this->enviar($request));
    }

    // ---- 05 ----------------------------------------------------------------

    public function retificarOperacao(
        RetificacaoOperacaoTransporteRequest $request
    ): RetificacaoOperacaoTransporteResponse {
        return new RetificacaoOperacaoTransporteResponse($this->enviar($request));
    }

    // ---- 06 ----------------------------------------------------------------

    public function encerrarOperacao(
        EncerramentoOperacaoTransporteRequest $request
    ): EncerramentoOperacaoTransporteResponse {
        return new EncerramentoOperacaoTransporteResponse($this->enviar($request));
    }

    // ---- 07 ----------------------------------------------------------------

    public function consultarExcecao(
        ConsultarExcecaoRequest|string $request
    ): ConsultarExcecaoResponse {
        if (is_string($request)) {
            $request = new ConsultarExcecaoRequest($request);
        }

        return new ConsultarExcecaoResponse($this->enviar($request));
    }

    // ---- 08 ----------------------------------------------------------------

    public function consultarCiotGerado(
        ConsultarCiotGeradoRequest $request
    ): ConsultarCiotGeradoResponse {
        return new ConsultarCiotGeradoResponse($this->enviar($request));
    }

    // ------------------------------------------------------------------------

    /**
     * Executa uma requisição arbitrária e retorna o array bruto decodificado.
     * Útil para depuração ou para serviços ainda não tipados.
     *
     * @return array<string, mixed>
     */
    public function enviar(PefRequest $request): array
    {
        if ($this->deveValidar()) {
            $erros = $request->validar();
            if ($erros !== []) {
                throw CiotValidationException::comErros($erros);
            }
        }

        $payload = $request->toArray();

        return $request->metodo() === 'GET'
            ? $this->transport->get($request->servico(), $payload)
            : $this->transport->post($request->servico(), $payload);
    }

    private function deveValidar(): bool
    {
        return (bool) ($this->config['validar_antes_de_enviar'] ?? true);
    }

    private function resolverIdOperacao(DeclaracaoOperacaoTransporteRequest $request): void
    {
        if ($request->idOperacaoTransporte !== null && trim($request->idOperacaoTransporte) !== '') {
            return;
        }

        if ($this->idGenerator === null) {
            throw CiotConfigurationException::geradorDeIdNaoRegistrado();
        }

        $request->comId($this->idGenerator->gerar($request));
    }

    private function preencherDataDeclaracao(DeclaracaoOperacaoTransporteRequest $request): void
    {
        if ($request->dataDeclaracao === null) {
            $request->comDataDeclaracao(new DateTimeImmutable('now'));
        }
    }
}
