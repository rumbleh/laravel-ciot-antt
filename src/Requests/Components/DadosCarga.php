<?php

declare(strict_types=1);

namespace Rumbleh\CiotAntt\Requests\Components;

use Rumbleh\CiotAntt\Enums\TipoCarga;
use Rumbleh\CiotAntt\Support\Payload;
use Rumbleh\CiotAntt\Support\Wire;
use Rumbleh\CiotAntt\ValueObjects\Documento;

/**
 * Dados da carga (item 15 da declaração / item 5 da retificação).
 *
 * Obrigatório para operações de carga lotação (1) e fracionada (2).
 *  - CodigoNaturezaCarga (B9/B69): código da natureza da carga.
 *  - PesoCarga (B18): maior que zero e menor que 9.999.999,99.
 *  - CodigoTipoCarga: domínio TipoCarga (1..12).
 *  - ContratantesCargFrac (B64/B67/B119): obrigatório para carga fracionada;
 *    cada item é um CPF/CNPJ e deve diferir do contratante da operação.
 */
final class DadosCarga
{
    /** @var list<string> */
    public readonly array $contratantesCargaFracionada;

    /**
     * @param  list<string>  $contratantesCargaFracionada  CPF/CNPJ (carga fracionada)
     */
    public function __construct(
        public readonly int|string|null $codigoNaturezaCarga = null,
        public readonly int|float|string|null $pesoCarga = null,
        public readonly TipoCarga|int|null $codigoTipoCarga = null,
        array $contratantesCargaFracionada = [],
    ) {
        $this->contratantesCargaFracionada = array_values(array_map(
            static fn (string $doc): string => Documento::de($doc)->valor(),
            $contratantesCargaFracionada,
        ));
    }

    private function tipoCargaValor(): ?int
    {
        if ($this->codigoTipoCarga instanceof TipoCarga) {
            return $this->codigoTipoCarga->value;
        }

        return $this->codigoTipoCarga;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $dados = Payload::semNulos([
            'CodigoNaturezaCarga' => $this->codigoNaturezaCarga !== null ? (string) $this->codigoNaturezaCarga : null,
            'PesoCarga' => $this->pesoCarga !== null ? Payload::decimal($this->pesoCarga, 2) : null,
            'CodigoTipoCarga' => $this->tipoCargaValor() !== null ? (string) $this->tipoCargaValor() : null,
        ]);

        if ($this->contratantesCargaFracionada !== []) {
            $dados[Wire::CONTRATANTES_CARGA_FRAC] = $this->contratantesCargaFracionada;
        }

        return $dados;
    }
}
