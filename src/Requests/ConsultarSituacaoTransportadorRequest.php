<?php

declare(strict_types=1);

namespace Rumbleh\CiotAntt\Requests;

use Rumbleh\CiotAntt\ValueObjects\Documento;
use Rumbleh\CiotAntt\ValueObjects\Rntrc;

/**
 * Serviço 01 — ConsultarSituacaoTransportador (POST).
 *
 * Recebe os dados do transportador e os valida junto ao RNTRC, retornando a
 * categoria (TAC/ETC/CTC) e se ele se equipara ao TAC.
 *
 * Manual DCS PEF v1.1, pág. 11-13.
 */
final class ConsultarSituacaoTransportadorRequest implements PefRequest
{
    public readonly string $cpfCnpjInteressado;
    public readonly string $cpfCnpjTransportador;
    public readonly string $rntrcTransportador;

    public function __construct(
        string $cpfCnpjInteressado,
        string $cpfCnpjTransportador,
        string $rntrcTransportador,
    ) {
        $this->cpfCnpjInteressado = Documento::de($cpfCnpjInteressado)->valor();
        $this->cpfCnpjTransportador = Documento::de($cpfCnpjTransportador)->valor();
        $this->rntrcTransportador = Rntrc::de($rntrcTransportador)->valor();
    }

    public function servico(): string
    {
        return 'ConsultarSituacaoTransportador';
    }

    public function metodo(): string
    {
        return 'POST';
    }

    public function toArray(): array
    {
        return [
            'CpfCnpjInteressado' => $this->cpfCnpjInteressado,
            'CpfCnpjTransportador' => $this->cpfCnpjTransportador,
            'RNTRCTransportador' => $this->rntrcTransportador,
        ];
    }

    public function validar(): array
    {
        // Formatos já validados nos Value Objects (B4/B60). Sem regras
        // condicionais adicionais para esta consulta.
        return [];
    }
}
