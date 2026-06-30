# laravel-ciot-antt

Cliente **Laravel** para emissão de **CIOT** (Código Identificador da Operação de
Transporte) junto ao web service **PEF da ANTT** (Pagamento Eletrônico de Frete),
conforme o manual **DCS PEF v1.1** (Resolução ANTT 5.892/2019 e MP 1.343/2026 —
Piso Mínimo de Frete).

> **Open source (MIT).** Distribuído via Packagist: `composer require rumbleh/laravel-ciot-antt`.
> Detalhes de instalação e CI/CD em [`docs/INSTALL.md`](docs/INSTALL.md).

---

## Recursos

- Os **8 serviços** do PEF/ANTT, tipados e prontos para uso:
  | # | Serviço | Método no pacote |
  |---|---------|------------------|
  | 01 | ConsultarSituacaoTransportador | `consultarSituacaoTransportador()` |
  | 02 | ConsultarFrotaTransportador | `consultarFrotaTransportador()` |
  | 03 | DeclaracaoOperacaoTransporte (**gera o CIOT**) | `declararOperacao()` |
  | 04 | CancelamentoOperacaoTransporte | `cancelarOperacao()` |
  | 05 | RetificacaoOperacaoTransporte | `retificarOperacao()` |
  | 06 | EncerramentoOperacaoTransporte | `encerrarOperacao()` |
  | 07 | ConsultarExcecao | `consultarExcecao()` |
  | 08 | ConsultarCIOTGerado | `consultarCiotGerado()` |
- **mTLS** com certificado ICP-Brasil **A1** (`.pfx`/`.p12`) — ideal para CI/CD.
- **Validação local** das regras de negócio do manual (B1..B122) antes de gastar
  uma requisição na ANTT — mensagens claras referenciando a regra.
- **Value Objects** com validação de CPF/CNPJ (dígito verificador), RNTRC
  (normalização B60) e placa (padrões Brasileiro e Mercosul).
- Geração do `IdOperacaoTransporte` **plugável** (contrato `OperationIdGenerator`)
  ou informada manualmente.
- Respostas tipadas com `sucesso()`, `codigo()`, `mensagem()` e helpers por serviço
  (ex.: `ciotComDigito()`, `avisoTransportador()`).

## Requisitos

- PHP **8.2+** (testado em 8.4) com extensões `json` e `openssl`
- Laravel **11** ou **12**
- Certificado digital **A1** (ICP-Brasil) da Instituição de Pagamento / Empresa
  Transportadora, habilitado pela ANTT

## Instalação rápida

```bash
composer require rumbleh/laravel-ciot-antt
php artisan vendor:publish --tag=ciot-config
```

Build **não interativo** em CI/CD (com `--no-dev`) e configuração do certificado:
veja [`docs/INSTALL.md`](docs/INSTALL.md).

## Uso em 30 segundos

```php
use Rumbleh\CiotAntt\Facades\Ciot;
use Rumbleh\CiotAntt\Requests\DeclaracaoOperacaoTransporteRequest;
use Rumbleh\CiotAntt\Requests\Components\{Veiculo, OrigemDestino, Localidade, DadosCarga, InfPagamento, InfIndicadoresOperacionais};
use Rumbleh\CiotAntt\Enums\{TipoCarga, TipoPagamento};

$declaracao = DeclaracaoOperacaoTransporteRequest::cargaLotacao(
        cpfCnpjContratado: '11222333000181',
        rntrcContratado: '123456789',
        cpfCnpjContratante: '11444777000161',
    )
    ->comId('000000000123')                 // ou registre um OperationIdGenerator
    ->comValorFrete(1500.00)
    ->comDestinatario('19131243000197')
    ->comViagem(inicio: '2026-06-24', fim: '2026-06-28')
    ->comVeiculos(Veiculo::automotor(placa: 'ABC1234', numeroEixos: 2, rntrc: '123456789'))
    ->comOrigemDestino(
        OrigemDestino::entre(
            Localidade::origem()->comMunicipio(3550308),
            Localidade::destino()->comMunicipio(3304557),
        )->comDistancia(430),
    )
    ->comDadosCarga(new DadosCarga(codigoNaturezaCarga: 1234, pesoCarga: 25000, codigoTipoCarga: TipoCarga::CargaGeral))
    ->comPagamentos(new InfPagamento(
        tipoPagamento: TipoPagamento::ContaCorrente,
        cpfCnpjCreditado: '11222333000181',
        codigoInstituicaoFinanceira: 1, numeroAgencia: '1234', numeroConta: '56789-0',
    ))
    ->comIndicadores(new InfIndicadoresOperacionais());

$resposta = Ciot::declararOperacao($declaracao);

if ($resposta->sucesso()) {
    $ciot = $resposta->ciotComDigito();      // 16 dígitos (CIOT + DV)
} else {
    report("ANTT [{$resposta->codigo()}]: {$resposta->mensagem()}");
}
```

## Documentação

- 📘 [`docs/USAGE.md`](docs/USAGE.md) — **guia completo com exemplos** de todos os
  serviços, configuração, certificado, tratamento de erros e regras de negócio.
- 🔐 [`docs/INSTALL.md`](docs/INSTALL.md) — instalação, build em CI/CD e certificado.

## Testes

```bash
composer install
composer test
```

## Ambientes ANTT

| Ambiente | URL base |
|----------|----------|
| Homologação | `https://appservices-hml.antt.gov.br/pefServices` |
| Produção | `https://appservices.antt.gov.br/pefServices` |

> A ANTT exige aprovação dos testes em **homologação** antes de liberar a
> produção. O **host/contexto exato** (segmento `api`) deve ser confirmado com a
> ANTT — é configurável via `CIOT_PATH_PREFIX` sem alterar código.

## Licença

[MIT](LICENSE) © Thiago de Queiroz.
