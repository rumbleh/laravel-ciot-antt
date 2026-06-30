<?php

declare(strict_types=1);

namespace Rumbleh\CiotAntt\Support;

/**
 * Utilitários para montar o payload JSON enviado à ANTT.
 */
final class Payload
{
    /**
     * Remove apenas os valores estritamente null de um array (preserva 0,
     * false, "" e arrays vazios, que podem ser significativos).
     *
     * @param  array<string, mixed>  $dados
     * @return array<string, mixed>
     */
    public static function semNulos(array $dados): array
    {
        return array_filter($dados, static fn (mixed $v): bool => $v !== null);
    }

    /**
     * Formata um valor monetário/decimal como string com casas fixas,
     * usando ponto como separador decimal (formato esperado pela ANTT).
     *
     * Aceita números e strings em formato brasileiro ("1.500,50"),
     * internacional ("1,500.50"), decimal simples ("1500.50" / "1500,50") ou
     * inteiro — sem corromper o separador de milhar. Ex.: "1.500,00" -> "1500.00".
     */
    public static function decimal(int|float|string $valor, int $casas = 2): string
    {
        if (is_string($valor)) {
            $valor = self::stringParaFloat($valor);
        }

        return number_format((float) $valor, $casas, '.', '');
    }

    /**
     * Converte uma string numérica (com possível separador de milhar/decimal,
     * BR ou internacional) em float, de forma robusta.
     */
    private static function stringParaFloat(string $valor): float
    {
        $v = trim($valor);

        if ($v === '') {
            return 0.0;
        }

        $ultimaVirgula = strrpos($v, ',');
        $ultimoPonto = strrpos($v, '.');

        if ($ultimaVirgula !== false && $ultimoPonto !== false) {
            if ($ultimaVirgula > $ultimoPonto) {
                // Formato brasileiro: ponto = milhar, vírgula = decimal.
                $v = str_replace(['.', ','], ['', '.'], $v);
            } else {
                // Formato internacional: vírgula = milhar, ponto = decimal.
                $v = str_replace(',', '', $v);
            }
        } elseif ($ultimaVirgula !== false) {
            // Apenas vírgula -> separador decimal.
            $v = str_replace(',', '.', $v);
        }
        // Apenas ponto (ou nenhum separador) já está no formato correto.

        return (float) $v;
    }
}
