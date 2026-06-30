<?php

declare(strict_types=1);

namespace Rumbleh\CiotAntt;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\ServiceProvider;
use Psr\Log\LoggerInterface;
use Rumbleh\CiotAntt\Contracts\OperationIdGenerator;
use Rumbleh\CiotAntt\Contracts\PefTransport;
use Rumbleh\CiotAntt\Http\GuzzlePefTransport;

final class CiotServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/ciot.php', 'ciot');

        // Transporte HTTP/mTLS.
        $this->app->singleton(PefTransport::class, function (Container $app): PefTransport {
            /** @var array<string, mixed> $config */
            $config = $app['config']->get('ciot', []);

            $logger = null;
            $canal = $config['log_channel'] ?? null;
            if ($canal !== null && $app->bound('log')) {
                $logger = $app['log']->channel($canal);
            }

            return new GuzzlePefTransport($config, null, $logger instanceof LoggerInterface ? $logger : null);
        });

        // Gerador opcional do IdOperacaoTransporte.
        $this->app->bind(OperationIdGenerator::class, function (Container $app): ?OperationIdGenerator {
            $classe = $app['config']->get('ciot.operation_id_generator');

            if ($classe === null || $classe === '') {
                return null;
            }

            /** @var OperationIdGenerator $instancia */
            $instancia = $app->make($classe);

            return $instancia;
        });

        // Cliente principal.
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
        return [PefTransport::class, OperationIdGenerator::class, Ciot::class, 'ciot'];
    }
}
