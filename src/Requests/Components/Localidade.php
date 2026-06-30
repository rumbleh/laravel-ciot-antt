<?php

declare(strict_types=1);

namespace Rumbleh\CiotAntt\Requests\Components;

use Rumbleh\CiotAntt\Support\Payload;

/**
 * Origem OU destino de um par OrigemDestino.
 *
 * Manual DCS PEF v1.1 (itens 14.1/14.2): a localização pode ser informada por
 * município (IBGE), CEP ou coordenadas geográficas (latitude+longitude). Quando
 * há mais de um tipo no mesmo par OD deve-se usar o mais específico:
 * LatLong -> CEP -> Cidade (regra de precedência do manual).
 *
 * Regras correlatas:
 *  - B109: ao menos uma forma de localização deve ser identificável.
 *  - B110: latitude e longitude devem ser informadas em conjunto.
 *  - B111: origem e destino devem usar o mesmo tipo de localização.
 *
 * $sufixo distingue os nomes dos campos no JSON ("Origem" ou "Destino"),
 * ex.: CodigoMunicipioOrigem x CodigoMunicipioDestino.
 */
final class Localidade
{
    public function __construct(
        public readonly string $sufixo,
        public readonly ?string $codigoMunicipio = null,
        public readonly ?string $cep = null,
        public readonly ?string $latitude = null,
        public readonly ?string $longitude = null,
    ) {
    }

    public static function origem(): self
    {
        return new self('Origem');
    }

    public static function destino(): self
    {
        return new self('Destino');
    }

    public function comMunicipio(int|string $codigoIbge): self
    {
        return new self($this->sufixo, (string) $codigoIbge, $this->cep, $this->latitude, $this->longitude);
    }

    public function comCep(string $cep): self
    {
        $cep = preg_replace('/\D+/', '', $cep) ?? '';

        return new self($this->sufixo, $this->codigoMunicipio, $cep, $this->latitude, $this->longitude);
    }

    public function comCoordenadas(float|string $latitude, float|string $longitude): self
    {
        return new self(
            $this->sufixo,
            $this->codigoMunicipio,
            $this->cep,
            Payload::decimal($latitude, 6),
            Payload::decimal($longitude, 6),
        );
    }

    /** Tipo de localização identificável, segundo a precedência do manual. */
    public function tipoLocalizacao(): ?string
    {
        if ($this->latitude !== null && $this->longitude !== null) {
            return 'coordenadas';
        }
        if ($this->cep !== null && $this->cep !== '') {
            return 'cep';
        }
        if ($this->codigoMunicipio !== null && $this->codigoMunicipio !== '') {
            return 'municipio';
        }

        return null;
    }

    /** Latitude/longitude informadas isoladamente (viola B110). */
    public function coordenadasIncompletas(): bool
    {
        return ($this->latitude !== null) xor ($this->longitude !== null);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return Payload::semNulos([
            "CodigoMunicipio{$this->sufixo}" => $this->codigoMunicipio,
            "Cep{$this->sufixo}" => $this->cep,
            "Latitude{$this->sufixo}" => $this->latitude,
            "Longitude{$this->sufixo}" => $this->longitude,
        ]);
    }
}
