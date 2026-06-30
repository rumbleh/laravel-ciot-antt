<?php

declare(strict_types=1);

namespace Rumbleh\CiotAntt\ValueObjects;

use Rumbleh\CiotAntt\Exceptions\CiotValidationException;
use Stringable;

/**
 * CPF (11 dígitos) ou CNPJ (14 dígitos) com validação de dígito verificador.
 *
 * Manual DCS PEF v1.1, regra B4: "CPF: 11 dígitos, com validação de DV;
 * CNPJ: 14 dígitos, com validação de DV". O valor enviado na mensagem JSON é
 * sempre apenas os números (sem máscara).
 */
final class Documento implements Stringable
{
    public const TIPO_CPF = 'CPF';
    public const TIPO_CNPJ = 'CNPJ';

    private function __construct(
        public readonly string $numero,
        public readonly string $tipo,
    ) {
    }

    /**
     * Cria a partir de um CPF ou CNPJ, detectando o tipo pelo número de
     * dígitos. Lança CiotValidationException se inválido.
     */
    public static function de(string $valor): self
    {
        $digitos = self::somenteDigitos($valor);

        return match (strlen($digitos)) {
            11 => self::cpf($digitos),
            14 => self::cnpj($digitos),
            default => throw CiotValidationException::comErros([
                "B4: CPF/CNPJ inválido: '{$valor}' deve ter 11 (CPF) ou 14 (CNPJ) dígitos.",
            ]),
        };
    }

    public static function cpf(string $valor): self
    {
        $digitos = self::somenteDigitos($valor);

        if (! self::cpfValido($digitos)) {
            throw CiotValidationException::comErros([
                "B4: CPF inválido: '{$valor}'.",
            ]);
        }

        return new self($digitos, self::TIPO_CPF);
    }

    public static function cnpj(string $valor): self
    {
        $digitos = self::somenteDigitos($valor);

        if (! self::cnpjValido($digitos)) {
            throw CiotValidationException::comErros([
                "B4: CNPJ inválido: '{$valor}'.",
            ]);
        }

        return new self($digitos, self::TIPO_CNPJ);
    }

    /** Validação sem lançar exceção (útil para regras condicionais). */
    public static function valido(string $valor): bool
    {
        $digitos = self::somenteDigitos($valor);

        return match (strlen($digitos)) {
            11 => self::cpfValido($digitos),
            14 => self::cnpjValido($digitos),
            default => false,
        };
    }

    public function ehCpf(): bool
    {
        return $this->tipo === self::TIPO_CPF;
    }

    public function ehCnpj(): bool
    {
        return $this->tipo === self::TIPO_CNPJ;
    }

    /** Valor "cru" (somente dígitos) para envio na mensagem JSON. */
    public function valor(): string
    {
        return $this->numero;
    }

    public function __toString(): string
    {
        return $this->numero;
    }

    public static function somenteDigitos(string $valor): string
    {
        return preg_replace('/\D+/', '', $valor) ?? '';
    }

    public static function cpfValido(string $cpf): bool
    {
        if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }

        for ($t = 9; $t < 11; $t++) {
            $soma = 0;
            for ($i = 0; $i < $t; $i++) {
                $soma += (int) $cpf[$i] * (($t + 1) - $i);
            }
            $resto = ($soma * 10) % 11;
            $digito = $resto === 10 ? 0 : $resto;
            if ($digito !== (int) $cpf[$t]) {
                return false;
            }
        }

        return true;
    }

    public static function cnpjValido(string $cnpj): bool
    {
        if (strlen($cnpj) !== 14 || preg_match('/^(\d)\1{13}$/', $cnpj)) {
            return false;
        }

        $pesos1 = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        $pesos2 = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

        $calc = static function (array $pesos) use ($cnpj): int {
            $soma = 0;
            foreach ($pesos as $i => $peso) {
                $soma += (int) $cnpj[$i] * $peso;
            }
            $resto = $soma % 11;

            return $resto < 2 ? 0 : 11 - $resto;
        };

        return $calc($pesos1) === (int) $cnpj[12]
            && $calc($pesos2) === (int) $cnpj[13];
    }
}
