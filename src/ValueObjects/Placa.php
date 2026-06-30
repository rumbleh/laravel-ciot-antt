<?php

declare(strict_types=1);

namespace Rumbleh\CiotAntt\ValueObjects;

use Rumbleh\CiotAntt\Exceptions\CiotValidationException;
use Stringable;

/**
 * Placa de veículo (7 caracteres).
 *
 * Manual DCS PEF v1.1, regra B4: aceita os padrões
 *  - Brasileiro: 3 letras + 4 números (AAA9999)
 *  - Mercosul:   3 letras, 1 número, 1 letra, 2 números (AAA9A99)
 */
final class Placa implements Stringable
{
    private function __construct(
        public readonly string $valor,
    ) {
    }

    public static function de(string $placa): self
    {
        $normalizada = self::normalizar($placa);

        if (! self::valido($normalizada)) {
            throw CiotValidationException::comErros([
                "B4: Placa inválida: '{$placa}'. Use o padrão brasileiro (AAA9999) "
                . 'ou Mercosul (AAA9A99).',
            ]);
        }

        return new self($normalizada);
    }

    public static function normalizar(string $placa): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $placa) ?? '');
    }

    public static function valido(string $placa): bool
    {
        $placa = self::normalizar($placa);

        // Brasileiro AAA9999 ou Mercosul AAA9A99.
        return (bool) preg_match('/^[A-Z]{3}[0-9]{4}$/', $placa)
            || (bool) preg_match('/^[A-Z]{3}[0-9][A-Z][0-9]{2}$/', $placa);
    }

    public function ehMercosul(): bool
    {
        return (bool) preg_match('/^[A-Z]{3}[0-9][A-Z][0-9]{2}$/', $this->valor);
    }

    public function valor(): string
    {
        return $this->valor;
    }

    public function __toString(): string
    {
        return $this->valor;
    }
}
