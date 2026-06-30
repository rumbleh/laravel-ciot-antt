<?php

declare(strict_types=1);

namespace Rumbleh\CiotAntt\Tests\Unit\ValueObjects;

use PHPUnit\Framework\Attributes\DataProvider;
use Rumbleh\CiotAntt\Exceptions\CiotValidationException;
use Rumbleh\CiotAntt\Tests\TestCase;
use Rumbleh\CiotAntt\ValueObjects\Placa;

final class PlacaTest extends TestCase
{
    public function test_aceita_padrao_brasileiro(): void
    {
        $this->assertSame('ABC1234', Placa::de('ABC1234')->valor());
    }

    public function test_aceita_padrao_mercosul(): void
    {
        $placa = Placa::de('ABC1D23');

        $this->assertSame('ABC1D23', $placa->valor());
        $this->assertTrue($placa->ehMercosul());
    }

    public function test_normaliza_removendo_hifen_e_caixa(): void
    {
        $this->assertSame('ABC1234', Placa::de('abc-1234')->valor());
    }

    public function test_padrao_brasileiro_nao_e_mercosul(): void
    {
        $this->assertFalse(Placa::de('ABC1234')->ehMercosul());
    }

    #[DataProvider('placasInvalidas')]
    public function test_lanca_excecao_para_placa_invalida(string $placa): void
    {
        $this->expectException(CiotValidationException::class);
        Placa::de($placa);
    }

    /**
     * @return list<array{string}>
     */
    public static function placasInvalidas(): array
    {
        return [
            ['AB1234'],     // letras de menos
            ['ABCD234'],    // formato inválido
            ['ABC12345'],   // dígitos de mais
            ['1234ABC'],    // ordem invertida
            [''],
        ];
    }
}
