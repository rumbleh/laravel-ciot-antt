<?php

declare(strict_types=1);

namespace Rumbleh\CiotAntt\Requests\Components;

use Rumbleh\CiotAntt\Support\Payload;
use Rumbleh\CiotAntt\Support\Wire;
use Rumbleh\CiotAntt\ValueObjects\Placa;
use Rumbleh\CiotAntt\ValueObjects\Rntrc;

/**
 * Veículo informado na declaração de operação de transporte (item 13).
 *
 * Regras correlatas:
 *  - B101: NumeroEixos — automotor 2 a 4 eixos; implemento 1 a 4 eixos.
 *  - B83/B84: deve haver exatamente um veículo automotor na lista.
 *  - B5: não pode haver placas duplicadas.
 *  - B117: se o automotor for cavalo-trator, ao menos um implemento é exigido.
 *
 * O flag $automotor permite ao pacote validar B83/B84/B117 localmente. Para
 * a ANTT, o veículo é identificado pela placa/RNTRC; o flag não é enviado.
 */
final class Veiculo
{
    public readonly string $placa;
    public readonly ?string $rntrc;

    public function __construct(
        string $placa,
        public readonly int $numeroEixos,
        ?string $rntrc = null,
        public readonly bool $automotor = true,
        public readonly bool $cavaloTrator = false,
    ) {
        $this->placa = Placa::de($placa)->valor();
        $this->rntrc = $rntrc !== null && $rntrc !== '' ? Rntrc::de($rntrc)->valor() : null;
    }

    /** Veículo automotor (trator/caminhão). Exige RNTRC vinculado (B20). */
    public static function automotor(string $placa, int $numeroEixos, ?string $rntrc = null, bool $cavaloTrator = false): self
    {
        return new self($placa, $numeroEixos, $rntrc, true, $cavaloTrator);
    }

    /** Implemento (reboque/semirreboque). */
    public static function implemento(string $placa, int $numeroEixos, ?string $rntrc = null): self
    {
        return new self($placa, $numeroEixos, $rntrc, false, false);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return Payload::semNulos([
            'Placa' => $this->placa,
            Wire::VEICULO_RNTRC => $this->rntrc,
            'NumeroEixos' => (string) $this->numeroEixos,
        ]);
    }
}
