<?php

declare(strict_types=1);

namespace Rumbleh\CiotAntt;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\ServiceProvider;
use Psr\Log\LoggerInterface;
use Rumbleh\CiotAntt\Contracts\GeradorCiotTransport;
use Rumbleh\CiotAntt\Contracts\OperationIdGenerator;
use Rumbleh\CiotAntt\Contracts\PefTransport;
use Rumbleh\CiotAntt\Http\GuzzleGeradorCiotTransport;
use Rumbleh\CiotAntt\Http\GuzzlePefTransport;
use Rumbleh\CiotAntt\Support\TokenManager;

final class CiotServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/ciot.php', 'ciot');

        // Transporte HTTP/mTLS (cliente PEF).
        $this->app->singleton(PefTransport::class, function (Container $app): PefTransport {
            /** @var array<string, mixed> $config */
            $config = $app['config']->get('ciot', []);

            return new GuzzlePefTransport($config, null, $this->logger($app, $config));
        });

        // Transporte HTTP do Gerador CIOT v3 (token + API key).
        $this->app->singleton(GeradorCiotTransport::class, function (Container $app): GeradorCiotTransport {
            /** @var array<string, mixed> $config */
            $config = $app['config']->get('ciot', []);

            return new GuzzleGeradorCiotTransport(
                $this->configGerador($config),
                null,
                $this->logger($app, $config),
            );
        });

        // Cliente do Gerador CIOT v3 (também é um OperationIdGenerator).
        $this->app->singleton(GeradorCiot::class, function (Container $app): GeradorCiot {
            /** @var array<string, mixed> $config */
            $config = $app['config']->get('ciot', []);
            $gerador = $this->configGerador($config);

            $manager = new TokenManager(
                $app->make(GeradorCiotTransport::class),
                (string) ($gerador['api_key'] ?? ''),
                (int) ($gerador['token_ttl'] ?? 3540),
            );

            return new GeradorCiot($app->make(GeradorCiotTransport::class), $manager);
        });

        $this->app->alias(GeradorCiot::class, 'gerador-ciot');

        // Gerador do IdOperacaoTransporte. Prioridade:
        //  1) classe explícita em "ciot.operation_id_generator";
        //  2) o GeradorCiot, quando a API key v3 está configurada;
        //  3) null (id informado manualmente em cada declaração).
        $this->app->bind(OperationIdGenerator::class, function (Container $app): ?OperationIdGenerator {
            $classe = $app['config']->get('ciot.operation_id_generator');

            if (is_string($classe) && $classe !== '') {
                /** @var OperationIdGenerator $instancia */
                $instancia = $app->make($classe);

                return $instancia;
            }

            $apiKey = $app['config']->get('ciot.gerador.api_key');

            if (is_string($apiKey) && trim($apiKey) !== '') {
                return $app->make(GeradorCiot::class);
            }

            return null;
        });

        // Cliente principal PEF.
        $this->app->singleton(Ciot::class, function (Container $app): Ciot {
            /** @var array<string, mixed> $config */
            $config = $app['config']->get('ciot', []);

            return new Ciot(
                $app->make(PefTransport::class),
                $config,
                $app->make(OperationIdGenerator::class),
            );
        });

        $this->app->alias(Ciot::class, 'ciot');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/ciot.php' => $this->app->configPath('ciot.php'),
            ], 'ciot-config');
        }
    }

    /**
     * @return list<string>
     */
    public function provides(): array
    {
        return [
            PefTransport::class,
            GeradorCiotTransport::class,
            GeradorCiot::class,
            'gerador-ciot',
            OperationIdGenerator::class,
            Ciot::class,
            'ciot',
        ];
    }

    /**
     * Resolve o bloco "gerador" da config, já com a base_url do ambiente atual
     * e os fallbacks de timeout para os valores globais do pacote.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function configGerador(array $config): array
    {
        $gerador = (array) ($config['gerador'] ?? []);
        $ambiente = (string) ($config['ambiente'] ?? 'homologacao');
        $urls = (array) ($gerador['urls'] ?? []);

        return [
            'ambiente' => $ambiente,
            'base_url' => (string) ($urls[$ambiente] ?? ''),
            'api_key' => (string) ($gerador['api_key'] ?? ''),
            'token_ttl' => (int) ($gerador['token_ttl'] ?? 3540),
            'timeout' => (int) ($gerador['timeout'] ?? $config['timeout'] ?? 30),
            'connect_timeout' => (int) ($gerador['connect_timeout'] ?? $config['connect_timeout'] ?? 10),
            'verificar_ssl' => $gerador['verificar_ssl'] ?? true,
        ];
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function logger(Container $app, array $config): ?LoggerInterface
    {
        $canal = $config['log_channel'] ?? null;

        if ($canal === null || ! $app->bound('log')) {
            return null;
        }

        $logger = $app['log']->channel($canal);

        return $logger instanceof LoggerInterface ? $logger : null;
    }
}
