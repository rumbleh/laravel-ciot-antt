<?php

declare(strict_types=1);

namespace Rumbleh\CiotAntt\ValueObjects;

use Rumbleh\CiotAntt\Exceptions\CiotValidationException;
use Stringable;

/**
 * Número do RNTRC (Registro Nacional de Transportadores Rodoviários de Cargas).
 *
 * Manual DCS PEF v1.1, regra B60 (Normalização do RNTRC):
 *  - 8 dígitos  -> completar com zero à esquerda (vira 9 dígitos)
 *  - 9 dígitos  -> manter o valor informado
 *  - qualquer outra quantidade de dígitos -> inválido (regra B3/B60).
 *
 * O manual também afirma: "Caso seja informado 7 dígitos ele será considerado
 * inválido."
 */
final class Rntrc implements Stringable
{
    private function __construct(
        public readonly string $valor,
    ) {
    }

    public static function de(string $rntrc): self
    {
        $digitos = preg_replace('/\D+/', '', $rntrc) ?? '';
        $normalizado = self::normalizar($digitos);

        if ($normalizado === null) {
            throw CiotValidationException::comErros([
                "B60: RNTRC inválido: '{$rntrc}'. Deve conter 8 (completado com "
                . 'zero à esquerda) ou 9 dígitos.',
            ]);
        }

        return new self($normalizado);
    }

    /**
     * Normaliza o RNTRC conforme a regra B60. Retorna o valor de 9 dígitos ou
     * null quando inválido.
     */
    public static function normalizar(string $digitos): ?string
    {
        $digitos = preg_replace('/\D+/', '', $digitos) ?? '';

        return match (strlen($digitos)) {
            8 => '0' . $digitos,
            9 => $digitos,
            default => null,
        };
    }

    public static function valido(string $rntrc): bool
    {
        return self::normalizar($rntrc) !== null;
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
