<?php

declare(strict_types=1);

namespace Rumbleh\CiotAntt\Http;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;
use Rumbleh\CiotAntt\Contracts\PefTransport;
use Rumbleh\CiotAntt\Exceptions\CiotConfigurationException;
use Rumbleh\CiotAntt\Exceptions\CiotConnectionException;

/**
 * Implementação do transporte PEF sobre Guzzle, com autenticação mútua (mTLS)
 * por certificado digital A1 (ICP-Brasil) em arquivo .pfx/.p12 ou par PEM.
 *
 * O certificado .pfx é lido com ext-openssl e extraído (uma única vez) para
 * arquivos PEM temporários com permissão 0600, exigidos pelo cURL/Guzzle.
 */
final class GuzzlePefTransport implements PefTransport
{
    private ?Client $client;

    /** @var array<string, mixed>|null */
    private ?array $certOptions = null;

    /** @var list<string> */
    private array $arquivosTemporarios = [];

    private bool $persistirCertificado = false;

    /**
     * @param  array<string, mixed>  $config  Bloco de configuração ciot.php já resolvido.
     * @param  Client|null  $client  Cliente Guzzle pré-construído (usado em testes com MockHandler).
     */
    public function __construct(
        private readonly array $config,
        ?Client $client = null,
        private readonly ?LoggerInterface $logger = null,
    ) {
        $this->client = $client;
    }

    public function post(string $servico, array $payload): array
    {
        return $this->enviar('POST', $servico, ['json' => $payload]);
    }

    public function get(string $servico, array $query): array
    {
        return $this->enviar('GET', $servico, ['query' => $query]);
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private function enviar(string $metodo, string $servico, array $options): array
    {
        $url = $this->urlDoServico($servico);

        try {
            $response = $this->client()->request($metodo, $url, $options);
        } catch (ConnectException $e) {
            throw new CiotConnectionException(
                "Falha de conexão/TLS com a ANTT ao chamar {$servico}: {$e->getMessage()}",
                null,
                null,
                $e
            );
        } catch (GuzzleException $e) {
            throw new CiotConnectionException(
                "Erro ao chamar o serviço {$servico} da ANTT: {$e->getMessage()}",
                null,
                null,
                $e
            );
        }

        $status = $response->getStatusCode();
        $corpo = (string) $response->getBody();

        $this->logger?->info('CIOT/ANTT request', [
            'servico' => $servico,
            'metodo' => $metodo,
            'status' => $status,
        ]);

        return $this->decodificar($corpo, $servico, $status);
    }

    private function client(): Client
    {
        if ($this->client !== null) {
            return $this->client;
        }

        $options = array_merge([
            'timeout' => (int) ($this->config['timeout'] ?? 30),
            'connect_timeout' => (int) ($this->config['connect_timeout'] ?? 10),
            'http_errors' => false,
            'verify' => $this->config['verificar_ssl'] ?? true,
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
        ], $this->certOptions());

        return $this->client = new Client($options);
    }

    /**
     * Monta as opções de certificado cliente (mTLS) para o Guzzle.
     *
     * @return array<string, mixed>
     */
    private function certOptions(): array
    {
        if ($this->certOptions !== null) {
            return $this->certOptions;
        }

        $cert = (array) ($this->config['certificado'] ?? []);
        $tipo = strtolower((string) ($cert['tipo'] ?? 'pfx'));

        if ($tipo === 'pem') {
            return $this->certOptions = $this->opcoesPem($cert);
        }

        return $this->certOptions = $this->opcoesPfx($cert);
    }

    /**
     * @param  array<string, mixed>  $cert
     * @return array<string, mixed>
     */
    private function opcoesPem(array $cert): array
    {
        $certPath = (string) ($cert['cert_path'] ?? '');
        $keyPath = (string) ($cert['key_path'] ?? '');

        if ($certPath === '' || $keyPath === '') {
            throw CiotConfigurationException::certificadoNaoConfigurado();
        }
        if (! is_file($certPath)) {
            throw CiotConfigurationException::arquivoCertificadoNaoEncontrado($certPath);
        }
        if (! is_file($keyPath)) {
            throw CiotConfigurationException::arquivoCertificadoNaoEncontrado($keyPath);
        }

        $senha = (string) ($cert['key_senha'] ?? '');

        return [
            'cert' => $certPath,
            'ssl_key' => $senha !== '' ? [$keyPath, $senha] : $keyPath,
        ];
    }

    /**
     * @param  array<string, mixed>  $cert
     * @return array<string, mixed>
     */
    private function opcoesPfx(array $cert): array
    {
        $pfxPath = (string) ($cert['pfx_path'] ?? '');
        $senha = (string) ($cert['pfx_senha'] ?? '');

        if ($pfxPath === '') {
            throw CiotConfigurationException::certificadoNaoConfigurado();
        }
        if (! is_file($pfxPath)) {
            throw CiotConfigurationException::arquivoCertificadoNaoEncontrado($pfxPath);
        }

        $conteudo = file_get_contents($pfxPath);
        if ($conteudo === false) {
            throw CiotConfigurationException::arquivoCertificadoNaoEncontrado($pfxPath);
        }

        $certs = [];
        if (! openssl_pkcs12_read($conteudo, $certs, $senha)) {
            // Coleta a fila de erros do OpenSSL para diagnóstico.
            $errosOpenssl = [];
            while (($erro = openssl_error_string()) !== false) {
                $errosOpenssl[] = $erro;
            }
            $detalhe = implode('; ', $errosOpenssl);

            // Certificados A1 ICP-Brasil costumam usar algoritmos legados
            // (RC2-40/3DES) que o OpenSSL 3 não habilita por padrão. Tenta o
            // fallback via binário `openssl` com o provider legacy.
            $legado = $this->lerPfxLegado($pfxPath, $senha, (string) ($cert['openssl_bin'] ?? 'openssl'));

            if ($legado !== null) {
                $certs = $legado;
            } elseif ($this->ehErroAlgoritmoLegado($detalhe)) {
                throw CiotConfigurationException::falhaLeituraPfxLegado($detalhe);
            } else {
                throw CiotConfigurationException::falhaLeituraPfx(
                    $detalhe !== '' ? $detalhe : 'senha incorreta ou arquivo PKCS#12 inválido'
                );
            }
        }

        $cacheDir = (string) ($cert['cache_dir'] ?? '');
        $this->persistirCertificado = $cacheDir !== '';
        $dir = $cacheDir !== '' ? $cacheDir : sys_get_temp_dir();

        if (! is_dir($dir) && ! @mkdir($dir, 0700, true) && ! is_dir($dir)) {
            throw new CiotConfigurationException("Não foi possível criar o diretório de cache do certificado: {$dir}");
        }

        $hash = substr(hash('sha256', $conteudo), 0, 16);
        $certFile = $dir . DIRECTORY_SEPARATOR . "ciot_{$hash}.crt.pem";
        $keyFile = $dir . DIRECTORY_SEPARATOR . "ciot_{$hash}.key.pem";

        $certPem = (string) ($certs['cert'] ?? '');
        foreach ((array) ($certs['extracerts'] ?? []) as $extra) {
            $certPem .= (string) $extra;
        }

        $this->gravarSeguro($certFile, $certPem);
        $this->gravarSeguro($keyFile, (string) ($certs['pkey'] ?? ''));

        // Limpa material sensível da memória assim que possível.
        $certs = null;
        unset($conteudo);

        return [
            'cert' => $certFile,
            'ssl_key' => $keyFile,
        ];
    }

    private function ehErroAlgoritmoLegado(string $detalhe): bool
    {
        $d = strtolower($detalhe);

        return str_contains($d, 'unsupported')
            || str_contains($detalhe, '0308010C')
            || str_contains($d, 'digital envelope')
            || str_contains($d, 'rc2')
            || str_contains($d, 'legacy');
    }

    /**
     * Lê um .pfx com algoritmos legados via o binário `openssl` (provider
     * legacy), retornando ['cert' => ..., 'pkey' => ..., 'extracerts' => [...]]
     * ou null em caso de falha/indisponibilidade.
     *
     * @return array{cert: string, pkey: string, extracerts: list<string>}|null
     */
    private function lerPfxLegado(string $pfxPath, string $senha, string $opensslBin): ?array
    {
        if (! function_exists('proc_open')) {
            return null;
        }

        $descritores = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        // A senha vai por stdin (-passin stdin) para não aparecer na lista de
        // processos. proc_open com array de argumentos evita injeção de shell.
        $proc = @proc_open(
            [$opensslBin, 'pkcs12', '-legacy', '-in', $pfxPath, '-nodes', '-passin', 'stdin'],
            $descritores,
            $pipes,
        );

        if (! is_resource($proc)) {
            return null;
        }

        fwrite($pipes[0], $senha);
        fclose($pipes[0]);
        $pem = stream_get_contents($pipes[1]) ?: '';
        fclose($pipes[1]);
        fclose($pipes[2]);
        $codigo = proc_close($proc);

        if ($codigo !== 0 || $pem === '') {
            return null;
        }

        return $this->separarPem($pem);
    }

    /**
     * Separa um bloco PEM (saída de `openssl pkcs12 -nodes`) em certificado,
     * chave privada e cadeia.
     *
     * @return array{cert: string, pkey: string, extracerts: list<string>}|null
     */
    private function separarPem(string $pem): ?array
    {
        preg_match_all('/-----BEGIN CERTIFICATE-----.*?-----END CERTIFICATE-----/s', $pem, $certMatches);
        preg_match(
            '/-----BEGIN (?:ENCRYPTED |RSA |EC |)PRIVATE KEY-----.*?-----END (?:ENCRYPTED |RSA |EC |)PRIVATE KEY-----/s',
            $pem,
            $keyMatch,
        );

        $certificados = $certMatches[0] ?? [];

        if ($certificados === [] || empty($keyMatch[0])) {
            return null;
        }

        $normaliza = static fn (string $bloco): string => rtrim($bloco) . "\n";

        return [
            'cert' => $normaliza($certificados[0]),
            'pkey' => $normaliza($keyMatch[0]),
            'extracerts' => array_map($normaliza, array_slice($certificados, 1)),
        ];
    }

    private function gravarSeguro(string $path, string $conteudo): void
    {
        $tmp = $path . '.' . getmypid() . '.tmp';

        // Remove resíduo e cria o arquivo já restrito (umask 0077 => 0600),
        // evitando a janela em que a chave privada ficaria world-readable.
        @unlink($tmp);
        $umaskAnterior = umask(0077);
        try {
            $gravou = @file_put_contents($tmp, $conteudo);
        } finally {
            umask($umaskAnterior);
        }

        if ($gravou === false) {
            throw new CiotConfigurationException("Não foi possível gravar o certificado em {$path}.");
        }

        @chmod($tmp, 0600);

        if (! @rename($tmp, $path)) {
            @unlink($tmp);
            throw new CiotConfigurationException("Não foi possível finalizar a gravação do certificado em {$path}.");
        }

        @chmod($path, 0600);

        if (! $this->persistirCertificado) {
            $this->arquivosTemporarios[] = $path;
        }
    }

    private function urlDoServico(string $servico): string
    {
        $ambiente = (string) ($this->config['ambiente'] ?? 'homologacao');
        $urls = (array) ($this->config['urls'] ?? []);
        $base = rtrim((string) ($urls[$ambiente] ?? ''), '/');

        if ($base === '') {
            throw CiotConfigurationException::ambienteInvalido($ambiente);
        }

        $prefix = trim((string) ($this->config['path_prefix'] ?? 'api'), '/');

        $segmentos = array_filter([$base, $prefix, $servico], static fn (string $s): bool => $s !== '');

        return implode('/', $segmentos);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodificar(string $corpo, string $servico, int $status): array
    {
        $corpo = trim($corpo);

        if ($corpo === '') {
            if ($status >= 500) {
                throw new CiotConnectionException(
                    "A ANTT retornou HTTP {$status} sem corpo ao chamar {$servico}.",
                    $status
                );
            }

            return [];
        }

        $dados = json_decode($corpo, true);

        if (! is_array($dados)) {
            throw new CiotConnectionException(
                "Resposta não-JSON da ANTT (HTTP {$status}) ao chamar {$servico}.",
                $status,
                mb_substr($corpo, 0, 500)
            );
        }

        /** @var array<string, mixed> $dados */
        return $dados;
    }

    public function __destruct()
    {
        foreach ($this->arquivosTemporarios as $arquivo) {
            if (is_file($arquivo)) {
                @unlink($arquivo);
            }
        }
    }
}
