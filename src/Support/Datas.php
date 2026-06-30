<?php

declare(strict_types=1);

namespace Rumbleh\CiotAntt\Support;

use DateTimeImmutable;
use DateTimeInterface;
use Rumbleh\CiotAntt\Exceptions\CiotValidationException;

/**
 * Formatação e parsing das datas no padrão exigido pelo manual (regra B4):
 *  - Data:       yyyy-MM-dd          (ex.: 2026-06-23)
 *  - Data/hora:  yyyy-MM-ddTHH:mm:ss (ex.: 2026-06-23T14:30:00)
 */
final class Datas
{
    public const FORMATO_DATA = 'Y-m-d';
    public const FORMATO_DATA_HORA = 'Y-m-d\TH:i:s';

    public static function data(DateTimeInterface|string|null $valor): ?string
    {
        return self::formatar($valor, self::FORMATO_DATA);
    }

    public static function dataHora(DateTimeInterface|string|null $valor): ?string
    {
        return self::formatar($valor, self::FORMATO_DATA_HORA);
    }

    private static function formatar(DateTimeInterface|string|null $valor, string $formato): ?string
    {
        if ($valor === null) {
            return null;
        }

        if ($valor instanceof DateTimeInterface) {
            return $valor->format($formato);
        }

        // Já é string: assume-se que está no formato correto. Apenas retorna.
        return $valor;
    }

    public static function parseData(string $valor): DateTimeImmutable
    {
        $dt = DateTimeImmutable::createFromFormat('!' . self::FORMATO_DATA, substr($valor, 0, 10));

        if ($dt === false) {
            throw CiotValidationException::comErros([
                "B4: Data inválida: '{$valor}'. Use o formato yyyy-MM-dd.",
            ]);
        }

        return $dt;
    }

    public static function parseDataHora(string $valor): DateTimeImmutable
    {
        foreach ([self::FORMATO_DATA_HORA, 'Y-m-d\TH:i:s.v\Z', 'Y-m-d\TH:i:s\Z', self::FORMATO_DATA] as $formato) {
            $dt = DateTimeImmutable::createFromFormat($formato, $valor);
            if ($dt !== false) {
                return $dt;
            }
        }

        throw CiotValidationException::comErros([
            "B4: Data/hora inválida: '{$valor}'. Use o formato yyyy-MM-ddTHH:mm:ss.",
        ]);
    }

    public static function formatoDataValido(string $valor): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor)
            && DateTimeImmutable::createFromFormat('!' . self::FORMATO_DATA, $valor) !== false;
    }

    public static function formatoDataHoraValido(string $valor): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$/', $valor)
            && DateTimeImmutable::createFromFormat(self::FORMATO_DATA_HORA, $valor) !== false;
    }
}
