<?php

declare(strict_types=1);

namespace Rumbleh\CiotAntt\Requests;

/**
 * Serviço 04 — CancelamentoOperacaoTransporte (POST).
 *
 * Cancela uma operação de transporte ainda não realizada.
 *  - CodigoIdentificacaoOperacao: CIOT *com* dígito verificador (16).
 *  - MotivoCancelamento: até 500 caracteres (B34/B37/B39).
 *
 * Manual DCS PEF v1.1, pág. 29-31.
 */
final class CancelamentoOperacaoTransporteRequest implements PefRequest
{
    public readonly string $codigoIdentificacaoOperacao;
    public readonly string $motivoCancelamento;

    public function __construct(string $codigoIdentificacaoOperacao, string $motivoCancelamento)
    {
        $this->codigoIdentificacaoOperacao = trim($codigoIdentificacaoOperacao);
        $this->motivoCancelamento = $motivoCancelamento;
    }

    public function servico(): string
    {
        return 'CancelamentoOperacaoTransporte';
    }

    public function metodo(): string
    {
        return 'POST';
    }

    public function toArray(): array
    {
        return [
            'CodigoIdentificacaoOperacao' => $this->codigoIdentificacaoOperacao,
            'MotivoCancelamento' => $this->motivoCancelamento,
        ];
    }

    public function validar(): array
    {
        $erros = [];

        if ($this->codigoIdentificacaoOperacao === '') {
            $erros[] = 'B1: O campo CodigoIdentificacaoOperacao é obrigatório.';
        }
        if (trim($this->motivoCancelamento) === '') {
            $erros[] = 'B1: O campo MotivoCancelamento é obrigatório.';
        } elseif (mb_strlen($this->motivoCancelamento) > 500) {
            $erros[] = 'B4: MotivoCancelamento excede o tamanho máximo de 500 caracteres.';
        }

        return $erros;
    }
}
