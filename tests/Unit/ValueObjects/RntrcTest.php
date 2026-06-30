<?php

declare(strict_types=1);

namespace Rumbleh\CiotAntt\Tests\Unit\ValueObjects;

use Rumbleh\CiotAntt\Exceptions\CiotValidationException;
use Rumbleh\CiotAntt\Tests\TestCase;
use Rumbleh\CiotAntt\ValueObjects\Rntrc;

final class RntrcTest extends TestCase
{
    public function test_mantem_nove_digitos(): void
    {
        $this->assertSame('123456789', Rntrc::de('123456789')->valor());
    }

    public function test_completa_oito_digitos_com_zero_a_esquerda(): void
    {
        // Regra B60: 8 dígitos -> completar com zero à esquerda.
        $this->assertSame('012345678', Rntrc::de('12345678')->valor());
    }

    public function test_sete_digitos_e_invalido(): void
    {
        // Manual: "informando 7 dígitos ele será considerado inválido".
        $this->expectException(CiotValidationException::class);
        Rntrc::de('1234567');
    }

    public function test_dez_digitos_e_invalido(): void
    {
        $this->expectException(CiotValidationException::class);
        Rntrc::de('1234567890');
    }

    public function test_normalizar_retorna_null_para_invalido(): void
    {
        $this->assertNull(Rntrc::normalizar('1234567'));
        $this->assertSame('012345678', Rntrc::normalizar('12345678'));
        $this->assertSame('123456789', Rntrc::normalizar('123456789'));
    }

    public function test_metodo_valido(): void
    {
        $this->assertTrue(Rntrc::valido('12345678'));
        $this->assertTrue(Rntrc::valido('123456789'));
        $this->assertFalse(Rntrc::valido('1234567'));
    }
}
