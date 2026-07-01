<?php

declare(strict_types=1);

namespace Rumbleh\CiotAntt\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static string authenticate()
 * @method static string refreshToken()
 * @method static string gerarCiot(string $cpfCnpj)
 * @method static string consultarCiot(string $cnpj)
 * @method static string gerar(\Rumbleh\CiotAntt\Requests\DeclaracaoOperacaoTransporteRequest $request)
 *
 * @see \Rumbleh\CiotAntt\GeradorCiot
 */
final class GeradorCiot extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Rumbleh\CiotAntt\GeradorCiot::class;
    }
}
