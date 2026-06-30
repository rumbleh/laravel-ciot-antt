<?php

declare(strict_types=1);

namespace Rumbleh\CiotAntt\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * Base dos testes unitários/feature que NÃO precisam de uma aplicação Laravel
 * inicializada (a maioria). Para testes do ServiceProvider/container usa-se
 * Orchestra\Testbench separadamente.
 *
 * CPF/CNPJ abaixo são valores de teste com dígito verificador VÁLIDO.
 */
abstract class TestCase extends BaseTestCase
{
    public const CPF_VALIDO = '52998224725';
    public const CPF_VALIDO_2 = '11144477735';
    public const CNPJ_VALIDO = '11222333000181';
    public const CNPJ_VALIDO_2 = '11444777000161';
    public const CNPJ_VALIDO_3 = '19131243000197';

    public const RNTRC_VALIDO = '123456789';     // 9 dígitos
    public const RNTRC_8_DIGITOS = '12345678';    // vira 012345678

    public const PLACA_BR = 'ABC1234';
    public const PLACA_MERCOSUL = 'ABC1D23';
    public const PLACA_IMPLEMENTO = 'XYZ9876';
}
