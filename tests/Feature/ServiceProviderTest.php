<?php

declare(strict_types=1);

namespace Rumbleh\CiotAntt\Tests\Feature;

use Orchestra\Testbench\TestCase;
use Rumbleh\CiotAntt\Ciot;
use Rumbleh\CiotAntt\CiotServiceProvider;
use Rumbleh\CiotAntt\Contracts\OperationIdGenerator;
use Rumbleh\CiotAntt\Contracts\PefTransport;
use Rumbleh\CiotAntt\Facades\Ciot as CiotFacade;
use Rumbleh\CiotAntt\Tests\Support\GeradorDeIdFake;

/**
 * Testa a integração com o container do Laravel (binding, config, facade).
 */
final class ServiceProviderTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [CiotServiceProvider::class];
    }

    protected function getPackageAliases($app): array
    {
        return ['Ciot' => CiotFacade::class];
    }

    public function test_config_e_mesclada(): void
    {
        $this->assertSame('homologacao', config('ciot.ambiente'));
        $this->assertSame(
            'https://appservices.antt.gov.br/pefServices',
            config('ciot.urls.producao'),
        );
    }

    public function test_resolve_o_cliente_e_o_transporte(): void
    {
        $this->assertInstanceOf(PefTransport::class, $this->app->make(PefTransport::class));
        $this->assertInstanceOf(Ciot::class, $this->app->make(Ciot::class));
        $this->assertInstanceOf(Ciot::class, $this->app->make('ciot'));
    }

    public function test_facade_resolve_o_cliente(): void
    {
        $this->assertInstanceOf(Ciot::class, CiotFacade::getFacadeRoot());
    }

    public function test_gerador_de_id_e_null_quando_nao_configurado(): void
    {
        $this->assertNull($this->app->make(OperationIdGenerator::class));
    }

    public function test_gerador_de_id_e_resolvido_quando_configurado(): void
    {
        config()->set('ciot.operation_id_generator', GeradorDeIdFake::class);

        $this->assertInstanceOf(OperationIdGenerator::class, $this->app->make(OperationIdGenerator::class));
    }
}
