<?php

declare(strict_types=1);

namespace Rumbleh\CiotAntt\Tests\Feature;

use ReflectionMethod;
use Rumbleh\CiotAntt\Exceptions\CiotConfigurationException;
use Rumbleh\CiotAntt\Http\GuzzlePefTransport;
use Rumbleh\CiotAntt\Tests\TestCase;

/**
 * Exercita a extração do certificado A1 (.pfx) — incluindo o fallback para
 * certificados com algoritmos legados sob OpenSSL 3 (típico de ICP-Brasil) e a
 * garantia de permissão 0600 na chave privada.
 *
 * Os certificados de teste são gerados em runtime via o binário `openssl`;
 * o teste é pulado se ele não estiver disponível.
 */
final class CertificadoPfxTest extends TestCase
{
    private static string $dir = '';
    private const SENHA = 'senha123';

    public static function setUpBeforeClass(): void
    {
        if (! self::temOpenssl()) {
            return;
        }

        self::$dir = sys_get_temp_dir() . '/ciot-pfx-test-' . getmypid();
        @mkdir(self::$dir, 0700, true);

        $k = self::$dir . '/k.pem';
        $c = self::$dir . '/c.pem';

        self::sh(['openssl', 'req', '-x509', '-newkey', 'rsa:2048', '-keyout', $k, '-out', $c,
            '-days', '2', '-nodes', '-subj', '/CN=teste-ciot']);

        // PFX moderno (AES) e legado (algoritmos antigos, como A1 ICP-Brasil).
        self::sh(['openssl', 'pkcs12', '-export', '-inkey', $k, '-in', $c,
            '-out', self::$dir . '/modern.pfx', '-passout', 'pass:' . self::SENHA]);
        self::sh(['openssl', 'pkcs12', '-export', '-legacy', '-inkey', $k, '-in', $c,
            '-out', self::$dir . '/legacy.pfx', '-passout', 'pass:' . self::SENHA]);
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$dir !== '' && is_dir(self::$dir)) {
            foreach (glob(self::$dir . '/*') ?: [] as $f) {
                @unlink($f);
            }
            @rmdir(self::$dir);
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        if (! self::temOpenssl()) {
            $this->markTestSkipped('Binário openssl indisponível.');
        }
    }

    /**
     * @return array{cert: string, ssl_key: string}
     */
    private function extrair(string $pfx, string $senha = self::SENHA): array
    {
        $config = [
            'ambiente' => 'homologacao',
            'urls' => ['homologacao' => 'https://x'],
            'certificado' => [
                'tipo' => 'pfx',
                'pfx_path' => self::$dir . '/' . $pfx,
                'pfx_senha' => $senha,
                'cache_dir' => self::$dir . '/cache',
            ],
        ];

        $transport = new GuzzlePefTransport($config);
        $metodo = new ReflectionMethod($transport, 'certOptions');
        $metodo->setAccessible(true);

        /** @var array{cert: string, ssl_key: string} $opts */
        $opts = $metodo->invoke($transport);

        return $opts;
    }

    private function assertCertificadoValido(array $opts): void
    {
        $this->assertFileExists($opts['cert']);
        $this->assertFileExists($opts['ssl_key']);
        $this->assertStringContainsString('BEGIN CERTIFICATE', (string) file_get_contents($opts['cert']));
        $this->assertStringContainsString('PRIVATE KEY', (string) file_get_contents($opts['ssl_key']));

        // A chave privada nunca pode ficar legível por outros usuários (0600).
        $perm = substr(sprintf('%o', fileperms($opts['ssl_key'])), -4);
        $this->assertSame('0600', $perm);
    }

    public function test_extrai_pfx_moderno(): void
    {
        $this->assertCertificadoValido($this->extrair('modern.pfx'));
    }

    public function test_extrai_pfx_legado_via_fallback_openssl3(): void
    {
        // Sob OpenSSL 3 o openssl_pkcs12_read() nativo falha neste arquivo;
        // o pacote deve recorrer ao fallback via binário `openssl -legacy`.
        $this->assertCertificadoValido($this->extrair('legacy.pfx'));
    }

    public function test_senha_incorreta_lanca_excecao(): void
    {
        $this->expectException(CiotConfigurationException::class);
        $this->extrair('modern.pfx', 'senha-errada');
    }

    public function test_arquivo_inexistente_lanca_excecao(): void
    {
        $this->expectException(CiotConfigurationException::class);
        $this->extrair('nao-existe.pfx');
    }

    private static function temOpenssl(): bool
    {
        if (! function_exists('proc_open')) {
            return false;
        }
        $proc = @proc_open(['openssl', 'version'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (! is_resource($proc)) {
            return false;
        }
        fclose($pipes[1]);
        fclose($pipes[2]);

        return proc_close($proc) === 0;
    }

    /**
     * @param  list<string>  $args
     */
    private static function sh(array $args): void
    {
        $proc = proc_open($args, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (is_resource($proc)) {
            stream_get_contents($pipes[1]);
            stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($proc);
        }
    }
}
