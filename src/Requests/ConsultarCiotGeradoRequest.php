<?php

declare(strict_types=1);

namespace Rumbleh\CiotAntt\Requests;

/**
 * Serviço 08 — ConsultarCIOTGerado (POST).
 *
 * Retorna o código identificador da operação de transporte com o dígito
 * verificador gerado na ANTT.
 *  - CodigoIdentificacaoOperacao: CIOT (12, sem DV).
 *  - AnoDeclaracao: ano da declaração (4 dígitos).
 *
 * Manual DCS PEF v1.1, pág. 42-43.
 */
final class ConsultarCiotGeradoRequest implements PefRequest
{
    public readonly string $codigoIdentificacaoOperacao;
    public readonly int $anoDeclaracao;

    public function __construct(string $codigoIdentificacaoOperacao, int $anoDeclaracao)
    {
        $this->codigoIdentificacaoOperacao = trim($codigoIdentificacaoOperacao);
        $this->anoDeclaracao = $anoDeclaracao;
    }

    public function servico(): string
    {
        return 'ConsultarCIOTGerado';
    }

    public function metodo(): string
    {
        return 'POST';
    }

    public function toArray(): array
    {
        return [
            'CodigoIdentificacaoOperacao' => $this->codigoIdentificacaoOperacao,
            'AnoDeclaracao' => $this->anoDeclaracao,
        ];
    }

    public function validar(): array
    {
        $erros = [];

        if ($this->codigoIdentificacaoOperacao === '') {
            $erros[] = 'B1: O campo CodigoIdentificacaoOperacao é obrigatório.';
        }
        if ($this->anoDeclaracao < 2000 || $this->anoDeclaracao > 2999) {
            $erros[] = 'B4: AnoDeclaracao inválido (esperado um ano com 4 dígitos).';
        }

        return $erros;
    }
}
