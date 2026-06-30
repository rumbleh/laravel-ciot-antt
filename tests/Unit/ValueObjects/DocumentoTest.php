<?php

declare(strict_types=1);

namespace Rumbleh\CiotAntt\Tests\Unit\ValueObjects;

use PHPUnit\Framework\Attributes\DataProvider;
use Rumbleh\CiotAntt\Exceptions\CiotValidationException;
use Rumbleh\CiotAntt\Tests\TestCase;
use Rumbleh\CiotAntt\ValueObjects\Documento;

final class DocumentoTest extends TestCase
{
    public function test_detecta_cpf_pelo_numero_de_digitos(): void
    {
        $doc = Documento::de(self::CPF_VALIDO);

        $this->assertTrue($doc->ehCpf());
        $this->assertFalse($doc->ehCnpj());
        $this->assertSame(self::CPF_VALIDO, $doc->valor());
    }

    public function test_detecta_cnpj_pelo_numero_de_digitos(): void
    {
        $doc = Documento::de(self::CNPJ_VALIDO);

        $this->assertTrue($doc->ehCnpj());
        $this->assertSame(self::CNPJ_VALIDO, (string) $doc);
    }

    public function test_remove_mascara_e_mantem_apenas_digitos(): void
    {
        $doc = Documento::de('529.982.247-25');

        $this->assertSame('52998224725', $doc->valor());
    }

    public function test_lanca_excecao_para_cpf_com_dv_invalido(): void
    {
        $this->expectException(CiotValidationException::class);
        Documento::cpf('52998224724');
    }

    public function test_lanca_excecao_para_cnpj_com_dv_invalido(): void
    {
        $this->expectException(CiotValidationException::class);
        Documento::cnpj('11222333000182');
    }

    public function test_lanca_excecao_para_quantidade_invalida_de_digitos(): void
    {
        $this->expectException(CiotValidationException::class);
        Documento::de('123');
    }

    public function test_rejeita_sequencias_repetidas(): void
    {
        $this->assertFalse(Documento::valido('11111111111'));
        $this->assertFalse(Documento::valido('00000000000000'));
    }

    #[DataProvider('documentosValidos')]
    public function test_metodo_valido_reconhece_documentos_validos(string $valor): void
    {
        $this->assertTrue(Documento::valido($valor));
    }

    /**
     * @return list<array{string}>
     */
    public static function documentosValidos(): array
    {
        return [
            [self::CPF_VALIDO],
            [self::CPF_VALIDO_2],
            [self::CNPJ_VALIDO],
            [self::CNPJ_VALIDO_2],
            [self::CNPJ_VALIDO_3],
        ];
    }
}
