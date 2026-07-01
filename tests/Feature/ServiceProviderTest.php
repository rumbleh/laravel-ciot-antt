<?php

declare(strict_types=1);

namespace Rumbleh\CiotAntt\Tests\Feature;

use Orchestra\Testbench\TestCase;
use Rumbleh\CiotAntt\Ciot;
use Rumbleh\CiotAntt\CiotServiceProvider;
use Rumbleh\CiotAntt\Contracts\GeradorCiotTransport;
use Rumbleh\CiotAntt\Contracts\OperationIdGenerator;
use Rumbleh\CiotAntt\Contracts\PefTransport;
use Rumbleh\CiotAntt\Facades\Ciot as CiotFacade;
use Rumbleh\CiotAntt\Facades\GeradorCiot as GeradorCiotFacade;
use Rumbleh\CiotAntt\GeradorCiot;
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
        return [
            'Ciot' => CiotFacade::class,
            'GeradorCiot' => GeradorCiotFacade::class,
        ];
    }

    public function test_config_e_mesclada(): void
    {
        $this->assertSame('homologacao', config('ciot.ambiente'));
        $this->assertSame(
            'https://appservices.antt.gov.br/pefServices',
            config('ciot.urls.producao'),
        );
        $this->assertSame(
            'https://mtcuybq605.execute-api.sa-east-1.amazonaws.com/api-ciot-prd/GeradorCIOT',
            config('ciot.gerador.urls.producao'),
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

    // ---- Gerador CIOT v3 ---------------------------------------------------

    public function test_resolve_o_gerador_e_o_transporte(): void
    {
        $this->assertInstanceOf(GeradorCiotTransport::class, $this->app->make(GeradorCiotTransport::class));
        $this->assertInstanceOf(GeradorCiot::class, $this->app->make(GeradorCiot::class));
        $this->assertInstanceOf(GeradorCiot::class, $this->app->make('gerador-ciot'));
    }

    public function test_facade_do_gerador_resolve(): void
    {
        $this->assertInstanceOf(GeradorCiot::class, GeradorCiotFacade::getFacadeRoot());
    }

    public function test_api_key_define_o_gerador_como_operation_id_generator(): void
    {
        config()->set('ciot.gerador.api_key', 'MINHA-API-KEY');

        $gerador = $this->app->make(OperationIdGenerator::class);

        $this->assertInstanceOf(GeradorCiot::class, $gerador);
    }

    public function test_classe_explicita_tem_prioridade_sobre_api_key(): void
    {
        config()->set('ciot.gerador.api_key', 'MINHA-API-KEY');
        config()->set('ciot.operation_id_generator', GeradorDeIdFake::class);

        $this->assertInstanceOf(GeradorDeIdFake::class, $this->app->make(OperationIdGenerator::class));
    }

    // ---- Resolução da config do gerador (configGerador) --------------------

    /**
     * Lê a config já resolvida (base_url por ambiente, fallbacks de timeout)
     * que o ServiceProvider injetou no transporte do gerador.
     *
     * @return array<string, mixed>
     */
    private function configResolvidaDoGerador(): array
    {
        $this->app->forgetInstance(GeradorCiotTransport::class);
        $transport = $this->app->make(GeradorCiotTransport::class);

        $prop = new \ReflectionProperty($transport, 'config');
        $prop->setAccessible(true);

        /** @var array<string, mixed> $cfg */
        $cfg = $prop->getValue($transport);

        return $cfg;
    }

    public function test_base_url_resolve_para_homologacao_por_padrao(): void
    {
        $this->assertSame(
            'https://appservices-hml.antt.gov.br/pefServices',
            $this->configResolvidaDoGerador()['base_url'],
        );
    }

    public function test_base_url_muda_para_producao_conforme_ambiente(): void
    {
        config()->set('ciot.ambiente', 'producao');

        $this->assertSame(
            'https://mtcuybq605.execute-api.sa-east-1.amazonaws.com/api-ciot-prd/GeradorCIOT',
            $this->configResolvidaDoGerador()['base_url'],
        );
    }

    public function test_timeout_do_gerador_cai_para_o_global_quando_nulo(): void
    {
        config()->set('ciot.timeout', 45);
        config()->set('ciot.gerador.timeout', null);

        $this->assertSame(45, $this->configResolvidaDoGerador()['timeout']);
    }

    public function test_timeout_especifico_do_gerador_tem_prioridade(): void
    {
        config()->set('ciot.timeout', 45);
        config()->set('ciot.gerador.timeout', 12);

        $this->assertSame(12, $this->configResolvidaDoGerador()['timeout']);
    }
}
