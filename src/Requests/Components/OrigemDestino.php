<?php

declare(strict_types=1);

namespace Rumbleh\CiotAntt\Requests\Components;

use Rumbleh\CiotAntt\Support\Payload;

/**
 * Par Origem/Destino de uma operação de transporte.
 *
 * Usado na declaração (item 14), retificação (item 4) e encerramento (item 2).
 * No encerramento também carrega DistanciaPercorrida e QtdViagens
 * (obrigatórios para TAC-Agregado).
 */
final class OrigemDestino
{
    public function __construct(
        public readonly Localidade $origem,
        public readonly Localidade $destino,
        public readonly int|float|string|null $distanciaPercorrida = null,
        public readonly ?int $qtdViagens = null,
    ) {
    }

    public static function entre(Localidade $origem, Localidade $destino): self
    {
        return new self($origem, $destino);
    }

    public function comDistancia(int|float|string $km): self
    {
        return new self($this->origem, $this->destino, $km, $this->qtdViagens);
    }

    public function comQtdViagens(int $qtd): self
    {
        return new self($this->origem, $this->destino, $this->distanciaPercorrida, $qtd);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return Payload::semNulos([
            'Origem' => $this->origem->toArray(),
            'Destino' => $this->destino->toArray(),
            'DistanciaPercorrida' => $this->distanciaPercorrida !== null
                ? (int) $this->distanciaPercorrida
                : null,
            'QtdViagens' => $this->qtdViagens,
        ]);
    }
}
