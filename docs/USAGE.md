# Guia de uso — laravel-ciot-antt

Guia completo para emitir e gerenciar **CIOT** via web service **PEF da ANTT**
usando este pacote. Os exemplos são copy-paste e cobrem os **8 serviços**,
configuração, certificado, regras de negócio e tratamento de erros.

> Este documento foi escrito para ser entregue ao **Claude Code** (ou a qualquer
> dev) implementar a emissão de CIOT no seu projeto. Cada serviço tem um exemplo
> completo e autocontido.

## Sumário

1. [Conceitos e fluxo](#1-conceitos-e-fluxo)
2. [Instalação e configuração](#2-instalação-e-configuração)
3. [Certificado digital (mTLS)](#3-certificado-digital-mtls)
4. [Geração do IdOperacaoTransporte](#4-geração-do-idoperacaotransporte)
5. [Como chamar (Facade, injeção, standalone)](#5-como-chamar)
6. [Serviço 01 — ConsultarSituacaoTransportador](#6-serviço-01--consultarsituacaotransportador)
7. [Serviço 02 — ConsultarFrotaTransportador](#7-serviço-02--consultarfrotatransportador)
8. [Serviço 03 — DeclaracaoOperacaoTransporte (gera o CIOT)](#8-serviço-03--declaracaooperacaotransporte-gera-o-ciot)
9. [Serviço 04 — CancelamentoOperacaoTransporte](#9-serviço-04--cancelamentooperacaotransporte)
10. [Serviço 05 — RetificacaoOperacaoTransporte](#10-serviço-05--retificacaooperacaotransporte)
11. [Serviço 06 — EncerramentoOperacaoTransporte](#11-serviço-06--encerramentooperacaotransporte)
12. [Serviço 07 — ConsultarExcecao](#12-serviço-07--consultarexcecao)
13. [Serviço 08 — ConsultarCIOTGerado](#13-serviço-08--consultarciotgerado)
14. [Componentes (Veiculo, OrigemDestino, DadosCarga, InfPagamento…)](#14-componentes)
15. [Enums](#15-enums)
16. [Tratamento de erros](#16-tratamento-de-erros)
17. [Regras de negócio validadas localmente](#17-regras-de-negócio-validadas-localmente)
18. [Ambiguidades do manual e como ajustar](#18-ambiguidades-do-manual)

---

## 1. Conceitos e fluxo

- **CIOT**: Código Identificador da Operação de Transporte, gerado pela ANTT na
  **declaração** da operação. A resposta traz o CIOT (12) + dígito verificador (4).
  O identificador **com DV (16)** é o que se usa para cancelar/retificar/encerrar.
- **Tipos de operação** (`TipoOperacao`): `1` Carga Lotação, `2` Carga Fracionada,
  `3` TAC-Agregado. As obrigatoriedades dos campos mudam conforme o tipo.
- **Fluxo típico**:

```
(opcional) ConsultarSituacaoTransportador  -> o contratado está apto? (RNTRC ativo)
(opcional) ConsultarFrotaTransportador     -> as placas pertencem a ele?
DeclaracaoOperacaoTransporte               -> GERA o CIOT  ⭐
   ├─ (se preciso) RetificacaoOperacaoTransporte
   ├─ (antes de iniciar) CancelamentoOperacaoTransporte
   └─ (ao concluir) EncerramentoOperacaoTransporte
ConsultarCIOTGerado / ConsultarExcecao     -> consultas auxiliares
```

---

## 2. Instalação e configuração

```bash
composer require rumbleh/laravel-ciot-antt
php artisan vendor:publish --tag=ciot-config   # publica config/ciot.php
```

(Instalação privada em CI/CD: ver [`INSTALL.md`](INSTALL.md).)

Configure o `.env`:

```dotenv
# Ambiente: homologacao | producao
CIOT_AMBIENTE=homologacao

# Certificado A1 (.pfx/.p12)
CIOT_CERT_TIPO=pfx
CIOT_CERT_PFX_PATH=/var/secrets/certificado.pfx
CIOT_CERT_PFX_SENHA=senha-do-certificado

# (opcional) contexto da URL, caso a ANTT informe algo diferente de "api"
CIOT_PATH_PREFIX=api

# (opcional) classe geradora do IdOperacaoTransporte
# CIOT_OPERATION_ID_GENERATOR="App\\Ciot\\MeuGeradorDeId"

# (opcional) validar regras de negócio localmente antes de enviar (padrão: true)
CIOT_VALIDAR_ANTES=true

# (opcional) canal de log do Laravel para registrar metadados das requisições
# CIOT_LOG_CHANNEL=stack
```

Todas as chaves disponíveis estão documentadas em `config/ciot.php`.

---

## 3. Certificado digital (mTLS)

A comunicação usa **TLS com autenticação mútua**: o servidor da ANTT exige um
certificado **ICP-Brasil A1 ou A3** do CNPJ titular. Em CI/CD, use **A1** em
arquivo `.pfx`/`.p12`.

```dotenv
CIOT_CERT_TIPO=pfx
CIOT_CERT_PFX_PATH=/caminho/seguro/certificado.pfx
CIOT_CERT_PFX_SENHA=...
```

O pacote lê o `.pfx` com `ext-openssl` e extrai o par certificado+chave para
arquivos PEM temporários com permissão `0600` (necessário ao cURL). Você não
precisa converter nada manualmente.

> **Certificados A1 ICP-Brasil e OpenSSL 3.** Muitos `.pfx` A1 usam algoritmos
> legados (RC2-40/3DES) que o OpenSSL 3 (PHP 8.1+) não habilita por padrão —
> `openssl_pkcs12_read()` falharia mesmo com a senha correta. O pacote detecta
> isso e recorre automaticamente ao binário `openssl` (`-legacy`) como fallback.
> Garanta que o `openssl` esteja no PATH (ou configure `CIOT_OPENSSL_BIN`), ou
> reexporte o `.pfx` com cifra moderna.

> **Já tem PEM separado?** Configure:
> ```dotenv
> CIOT_CERT_TIPO=pem
> CIOT_CERT_PEM_CERT_PATH=/caminho/cert.pem
> CIOT_CERT_PEM_KEY_PATH=/caminho/key.pem
> CIOT_CERT_PEM_KEY_SENHA=opcional
> ```

**Boas práticas:** monte o `.pfx` como *secret* (ex.: volume montado, *secret*
da DigitalOcean) e **nunca** versione o arquivo nem a senha (o `.gitignore` já
ignora `*.pfx`/`*.pem`).

---

## 4. Geração do IdOperacaoTransporte

O `IdOperacaoTransporte` (12 dígitos) é gerado pela **DLL/executável oficial da
ANTT** e o algoritmo **não acompanha** este pacote. Há duas formas:

**A) Informar manualmente** (você gera por fora e passa na requisição):

```php
$declaracao->comId('000000000123');
```

**B) Registrar um gerador** que implemente o contrato e faça a ponte com a
ferramenta da ANTT:

```php
namespace App\Ciot;

use Rumbleh\CiotAntt\Contracts\OperationIdGenerator;
use Rumbleh\CiotAntt\Requests\DeclaracaoOperacaoTransporteRequest;

final class MeuGeradorDeId implements OperationIdGenerator
{
    public function gerar(DeclaracaoOperacaoTransporteRequest $request): string
    {
        // Chame aqui a DLL/serviço da ANTT e retorne os 12 dígitos.
        return $this->antt->gerarId(/* ...dados do $request... */);
    }
}
```

```dotenv
CIOT_OPERATION_ID_GENERATOR="App\\Ciot\\MeuGeradorDeId"
```

Com o gerador registrado, você **não** precisa chamar `->comId(...)`: o pacote
gera o id automaticamente na hora de declarar. Sem id e sem gerador, é lançada
`CiotConfigurationException`.

---

## 5. Como chamar

**Facade** (mais simples):

```php
use Rumbleh\CiotAntt\Facades\Ciot;

$resposta = Ciot::consultarExcecao('12345678901');
```

**Injeção de dependência** (recomendado para testes):

```php
use Rumbleh\CiotAntt\Ciot;

class FreteService
{
    public function __construct(private Ciot $ciot) {}

    public function emitir(/* ... */) {
        $resposta = $this->ciot->declararOperacao($declaracao);
    }
}
```

**Standalone** (fora do Laravel, ou em testes):

```php
use Rumbleh\CiotAntt\Ciot;
use Rumbleh\CiotAntt\Http\GuzzlePefTransport;

$config = [
    'ambiente' => 'homologacao',
    'urls' => [
        'homologacao' => 'https://appservices-hml.antt.gov.br/pefServices',
        'producao'    => 'https://appservices.antt.gov.br/pefServices',
    ],
    'path_prefix' => 'api',
    'certificado' => ['tipo' => 'pfx', 'pfx_path' => '/c/cert.pfx', 'pfx_senha' => '...'],
    'verificar_ssl' => true,
    'validar_antes_de_enviar' => true,
];

$ciot = new Ciot(new GuzzlePefTransport($config), $config);
```

---

## 6. Serviço 01 — ConsultarSituacaoTransportador

Valida o transportador no RNTRC e retorna a categoria (TAC/ETC/CTC) e se ele se
equipara ao TAC.

```php
use Rumbleh\CiotAntt\Facades\Ciot;
use Rumbleh\CiotAntt\Requests\ConsultarSituacaoTransportadorRequest;

$resposta = Ciot::consultarSituacaoTransportador(
    new ConsultarSituacaoTransportadorRequest(
        cpfCnpjInteressado: '11444777000161', // quem consulta (sua empresa)
        cpfCnpjTransportador: '12345678901',  // o transportador consultado
        rntrcTransportador: '12345678',       // 8 ou 9 dígitos (normaliza p/ 9 — regra B60)
    ),
);

if ($resposta->sucesso()) {
    $resposta->apto();              // bool: RNTRC ativo
    $resposta->nomeRazaoSocial();   // string
    $resposta->tipoTransportador(); // TipoTransportador::TAC | ETC | CTC | null
    $resposta->equiparadoTac();     // bool
}
```

## 7. Serviço 02 — ConsultarFrotaTransportador

Verifica, por placa, se os veículos pertencem ao transportador.

```php
use Rumbleh\CiotAntt\Facades\Ciot;
use Rumbleh\CiotAntt\Requests\ConsultarFrotaTransportadorRequest;

$resposta = Ciot::consultarFrotaTransportador(
    new ConsultarFrotaTransportadorRequest(
        cpfCnpjInteressado: '11444777000161',
        cpfCnpjTransportador: '12345678901',
        rntrcTransportador: '123456789',
        placas: ['ABC1234', 'XYZ9876'],
    ),
);

foreach ($resposta->frota() as $veiculo) {
    // ['placa' => 'ABC1234', 'pertence' => true]
}

$resposta->veiculoPertence('ABC1234'); // bool
```

## 8. Serviço 03 — DeclaracaoOperacaoTransporte (gera o CIOT)

⭐ Serviço principal. As obrigatoriedades mudam por `TipoOperacao`. Há um
*factory* por tipo: `cargaLotacao()`, `cargaFracionada()`, `tacAgregado()`.

### 8.1 Carga Lotação (TipoOperacao = 1)

```php
use Rumbleh\CiotAntt\Facades\Ciot;
use Rumbleh\CiotAntt\Requests\DeclaracaoOperacaoTransporteRequest;
use Rumbleh\CiotAntt\Requests\Components\{Veiculo, OrigemDestino, Localidade, DadosCarga, InfPagamento, InfIndicadoresOperacionais};
use Rumbleh\CiotAntt\Enums\{TipoCarga, TipoPagamento};

$declaracao = DeclaracaoOperacaoTransporteRequest::cargaLotacao(
        cpfCnpjContratado: '11222333000181',   // transportador (TAC/ETC/CTC)
        rntrcContratado: '123456789',
        cpfCnpjContratante: '11444777000161',   // quem paga o frete
    )
    ->comId('000000000123')                     // dispensável se houver OperationIdGenerator
    ->comValorFrete(1500.00)
    ->comDestinatario('19131243000197')         // obrigatório p/ carga lotação/fracionada
    ->comRntrcContratante('987654321')          // opcional
    ->comDataDeclaracao('2026-06-23T10:00:00')  // opcional: se omitido, usa "agora"
    ->comViagem(inicio: '2026-06-24', fim: '2026-06-28')
    ->comVeiculos(
        // exatamente UM automotor; demais são implementos (regras B83/B84)
        Veiculo::automotor(placa: 'ABC1234', numeroEixos: 3, rntrc: '123456789', cavaloTrator: true),
        Veiculo::implemento(placa: 'XYZ9876', numeroEixos: 2),
    )
    ->comOrigemDestino(
        OrigemDestino::entre(
            Localidade::origem()->comMunicipio(3550308),   // São Paulo (IBGE)
            Localidade::destino()->comMunicipio(3304557),  // Rio de Janeiro (IBGE)
        )->comDistancia(430),                              // km (> 0)
    )
    ->comDadosCarga(new DadosCarga(
        codigoNaturezaCarga: 1234,
        pesoCarga: 25000.0,                 // kg (> 0)
        codigoTipoCarga: TipoCarga::CargaGeral,
    ))
    ->comPagamentos(new InfPagamento(
        tipoPagamento: TipoPagamento::ContaCorrente,
        cpfCnpjCreditado: '11222333000181',
        codigoInstituicaoFinanceira: 1,
        numeroAgencia: '1234',
        numeroConta: '56789-0',
    ))
    ->comIndicadores(new InfIndicadoresOperacionais(  // obrigatório p/ carga lotação
        indAltoDesempenho: false,
        indRetornoVazio: false,
        composicaoVeicular: true,
    ));

$resposta = Ciot::declararOperacao($declaracao);

if ($resposta->sucesso()) {
    $ciot        = $resposta->ciot();          // 12 dígitos
    $dv          = $resposta->codigoVerificador(); // 4 dígitos
    $ciotComDv   = $resposta->ciotComDigito(); // 16 — guarde este p/ cancelar/encerrar
    $protocolo   = $resposta->protocolo();

    if ($resposta->temAviso()) {
        // Apresentação/impressão do aviso é OBRIGATÓRIA (manual, item 6)
        $aviso = $resposta->avisoTransportador();
    }
} else {
    // rejeição da ANTT (ex.: piso mínimo, vínculo de veículo)
    logger()->warning("CIOT rejeitado [{$resposta->codigo()}]: {$resposta->mensagem()}");
}
```

### 8.2 Carga Fracionada (TipoOperacao = 2)

Igual ao lotação, porém **com contratantes da carga fracionada** e **sem**
`InfIndicadoresOperacionais`:

```php
$declaracao = DeclaracaoOperacaoTransporteRequest::cargaFracionada(
        cpfCnpjContratado: '11222333000181',
        rntrcContratado: '123456789',
        cpfCnpjContratante: '11444777000161',
    )
    ->comId('000000000124')
    ->comValorFrete(2200.00)
    ->comDestinatario('19131243000197')
    ->comViagem('2026-06-24', '2026-06-28')
    ->comVeiculos(Veiculo::automotor('ABC1234', 2, '123456789'))
    ->comOrigemDestino(
        OrigemDestino::entre(
            Localidade::origem()->comCep('01001000'),
            Localidade::destino()->comCep('20040002'),
        )->comDistancia(430),
    )
    ->comDadosCarga(new DadosCarga(
        codigoNaturezaCarga: 1234,
        pesoCarga: 18000.0,
        codigoTipoCarga: TipoCarga::CargaGeral,
        contratantesCargaFracionada: ['04252011000110'], // ≠ do contratante (B119)
    ));

$resposta = Ciot::declararOperacao($declaracao);
```

### 8.3 TAC-Agregado (TipoOperacao = 3)

Não informa destinatário, origem/destino, dados de carga nem indicadores na
**declaração** (esses vão no **encerramento**):

```php
$declaracao = DeclaracaoOperacaoTransporteRequest::tacAgregado(
        cpfCnpjContratado: '12345678901',  // deve ser TAC
        rntrcContratado: '123456789',
        cpfCnpjContratante: '11444777000161',
    )
    ->comId('000000000125')
    ->comValorFrete(3000.00)
    // TAC-Agregado NÃO informa data de início (regra B56): o servidor assume
    // dataInicioViagem = dataDeclaracao. Informe apenas o fim (≤ 30 dias — B30).
    ->comDataFimViagem('2026-07-10')
    ->comVeiculos(Veiculo::automotor('ABC1234', 3, '123456789'))
    ->comPagamentos(new InfPagamento(
        tipoPagamento: TipoPagamento::Outros,
        cpfCnpjCreditado: '12345678901',
    ));

$resposta = Ciot::declararOperacao($declaracao);
```

### 8.4 Declaração em contingência

```php
$declaracao->emContingencia('Indisponibilidade do web service da ANTT em 23/06');
```

### 8.5 Pagamento a prazo (parcelado)

```php
use Rumbleh\CiotAntt\Enums\{IndPagamento, TipoPagamento};
use Rumbleh\CiotAntt\Requests\Components\{InfPagamento, Parcela};

$pagamento = (new InfPagamento(
        tipoPagamento: TipoPagamento::Pix,
        indPagamento: IndPagamento::APrazo,
        cpfCnpjCreditado: '11222333000181',
        chavePix: '11222333000181',
        identificadorPix: 'E1234567890',
    ))
    ->comParcelas(
        new Parcela(numeroParcela: 1, dataVencimento: '2026-07-10', valorParcela: 750.00),
        new Parcela(numeroParcela: 2, dataVencimento: '2026-08-10', valorParcela: 750.00),
    );

$declaracao->comPagamentos($pagamento);
```

## 9. Serviço 04 — CancelamentoOperacaoTransporte

Cancela uma operação ainda **não realizada** (prazo: até 24h após o início da
viagem e desde que não consultada pela fiscalização).

```php
use Rumbleh\CiotAntt\Facades\Ciot;
use Rumbleh\CiotAntt\Requests\CancelamentoOperacaoTransporteRequest;

$resposta = Ciot::cancelarOperacao(
    new CancelamentoOperacaoTransporteRequest(
        codigoIdentificacaoOperacao: '0000000001234567', // CIOT + DV (16)
        motivoCancelamento: 'Operação cancelada pelo embarcador.',
    ),
);

$resposta->sucesso();           // bool
$resposta->dataCancelamento();  // string ISO
```

## 10. Serviço 05 — RetificacaoOperacaoTransporte

Retifica uma operação no status "Declarado". **Carga lotação (tipo 1) não pode
ser retificada** (regra B121).

```php
use Rumbleh\CiotAntt\Facades\Ciot;
use Rumbleh\CiotAntt\Requests\RetificacaoOperacaoTransporteRequest;
use Rumbleh\CiotAntt\Requests\Components\DadosCarga;

$resposta = Ciot::retificarOperacao(
    (new RetificacaoOperacaoTransporteRequest('0000000001234567'))
        ->comValorFrete(1800.00)
        ->comDadosCarga(new DadosCarga(codigoNaturezaCarga: 5555, pesoCarga: 12000.0)),
);

$resposta->dataRetificacao(); // string ISO
```

Para **TAC-Agregado** também informe `comDataFimViagem()` e `comOrigemDestino()`.

## 11. Serviço 06 — EncerramentoOperacaoTransporte

Declara a conclusão da operação.

**Carga lotação/fracionada (padrão):** informe o peso total da carga.

```php
use Rumbleh\CiotAntt\Facades\Ciot;
use Rumbleh\CiotAntt\Requests\EncerramentoOperacaoTransporteRequest;

$resposta = Ciot::encerrarOperacao(
    (new EncerramentoOperacaoTransporteRequest('0000000001234567'))
        ->comPesoTotalCarga(25000.0),
);
```

**TAC-Agregado:** informe os dados de viagem (origem/destino, distância e
quantidade de viagens) — encerrar até 10 dias após o início (regra B116):

```php
use Rumbleh\CiotAntt\Requests\Components\{OrigemDestino, Localidade};

$resposta = Ciot::encerrarOperacao(
    (new EncerramentoOperacaoTransporteRequest('0000000009999999'))
        ->comOrigemDestino(
            OrigemDestino::entre(
                Localidade::origem()->comMunicipio(3550308),
                Localidade::destino()->comMunicipio(3304557),
            )->comDistancia(430)->comQtdViagens(4),
        )
        ->comPesoTotalCarga(50000.0),
);

$resposta->dataEncerramento();
```

## 12. Serviço 07 — ConsultarExcecao

Verifica se o transportador está na lista de exceções à Resolução 5862 (GET).

```php
use Rumbleh\CiotAntt\Facades\Ciot;

$resposta = Ciot::consultarExcecao('12345678901'); // aceita string direto

$resposta->naLista();                 // bool
$resposta->cpfCnpjTransportador();    // string
```

## 13. Serviço 08 — ConsultarCIOTGerado

Retorna o CIOT com dígito verificador a partir do id e do ano de declaração.

```php
use Rumbleh\CiotAntt\Facades\Ciot;
use Rumbleh\CiotAntt\Requests\ConsultarCiotGeradoRequest;

$resposta = Ciot::consultarCiotGerado(
    new ConsultarCiotGeradoRequest(
        codigoIdentificacaoOperacao: '000000000123', // 12 dígitos
        anoDeclaracao: 2026,
    ),
);

$resposta->ciotComDigito(); // 16
```

---

## 14. Componentes

| Classe | Para que serve | Construção |
|--------|----------------|-----------|
| `Veiculo` | Veículo da operação | `Veiculo::automotor(placa, numeroEixos, rntrc?, cavaloTrator?)` / `Veiculo::implemento(placa, numeroEixos, rntrc?)` |
| `Localidade` | Origem/destino de um par | `Localidade::origem()` / `::destino()` + `->comMunicipio(ibge)` \| `->comCep('01001000')` \| `->comCoordenadas(lat, long)` |
| `OrigemDestino` | Par origem→destino | `OrigemDestino::entre($origem, $destino)->comDistancia(km)->comQtdViagens(n)` |
| `DadosCarga` | Carga | `new DadosCarga(codigoNaturezaCarga, pesoCarga, codigoTipoCarga, contratantesCargaFracionada?)` |
| `InfPagamento` | Forma de pagamento | `new InfPagamento(tipoPagamento, indPagamento?, cpfCnpjCreditado?, codigoInstituicaoFinanceira?, numeroAgencia?, numeroConta?, chavePix?, identificadorPix?, codigoPagamento?, parcelas?)` |
| `Parcela` | Parcela (a prazo) | `new Parcela(numeroParcela, dataVencimento, valorParcela)` |
| `InfIndicadoresOperacionais` | Indicadores (carga lotação) | `new InfIndicadoresOperacionais(indAltoDesempenho?, indRetornoVazio?, composicaoVeicular?)` |

> **Localização** (regras B109/B110/B111): para cada par OD informe **o mesmo
> tipo** na origem e no destino (município, CEP **ou** coordenadas). Coordenadas
> exigem latitude **e** longitude juntas.

## 15. Enums

```php
use Rumbleh\CiotAntt\Enums\{TipoOperacao, TipoPagamento, IndPagamento, TipoCarga, TipoTransportador, Ambiente};

TipoOperacao::CargaLotacao;     // 1
TipoOperacao::CargaFracionada;  // 2
TipoOperacao::TacAgregado;      // 3

TipoPagamento::CartaoPrePago;   // 1 (IP/IF)
TipoPagamento::ContaCorrente;   // 2
TipoPagamento::ContaPoupanca;   // 3
TipoPagamento::ContaPagamento;  // 4
TipoPagamento::Outros;          // 5
TipoPagamento::Pix;             // 6

IndPagamento::AVista;  // 0
IndPagamento::APrazo;  // 1

TipoCarga::GranelSolido /* 1 */, ..., TipoCarga::CargaGranelPressurizada /* 12 */;

TipoTransportador::TAC | ETC | CTC;
```

## 16. Tratamento de erros

O pacote distingue **três** situações:

| Situação | Como aparece |
|----------|--------------|
| Dados inválidos (validação local) | lança `CiotValidationException` **antes** do envio |
| Falha de rede/TLS/HTTP/JSON | lança `CiotConnectionException` |
| Configuração incompleta (certificado, id, ambiente) | lança `CiotConfigurationException` |
| ANTT recusou por regra de negócio | **não lança**: `$resposta->rejeitado()` / `codigo()` / `mensagem()` |

```php
use Rumbleh\CiotAntt\Exceptions\{CiotValidationException, CiotConnectionException, CiotConfigurationException};

try {
    $resposta = Ciot::declararOperacao($declaracao);

    if ($resposta->rejeitado()) {
        // regra de negócio da ANTT (ex.: 291 piso mínimo, 217 vínculo de veículo)
        return back()->withErrors("ANTT [{$resposta->codigo()}]: {$resposta->mensagem()}");
    }

    $ciot = $resposta->ciotComDigito();
} catch (CiotValidationException $e) {
    // $e->erros() => list<string> com cada regra violada (ex.: "B18: Peso...")
    return back()->withErrors($e->erros());
} catch (CiotConnectionException $e) {
    report($e);
    return back()->withErrors('Falha de comunicação com a ANTT. Tente novamente.');
} catch (CiotConfigurationException $e) {
    report($e); // certificado/id/ambiente mal configurados
    abort(500, 'Configuração do CIOT incompleta.');
}
```

> Prefere tratar rejeição como exceção? Use
> `$resposta->lancarSeRejeitado()` para lançar `CiotRejectionException` quando o
> código não for 110/111.

## 17. Regras de negócio validadas localmente

Quando `CIOT_VALIDAR_ANTES=true` (padrão), o pacote valida localmente — antes de
enviar — as regras verificáveis sem acesso aos dados da ANTT, lançando
`CiotValidationException` com a regra citada. As principais:

- **B1/B4**: obrigatoriedade e formato (CPF/CNPJ com DV, RNTRC B60, placa BR/Mercosul, datas).
- **B5**: placas duplicadas. **B49**: máximo de 5 veículos.
- **B83/B84**: exatamente um veículo automotor. **B101**: eixos (automotor 2–4, implemento 1–4). **B117**: cavalo-trator exige implemento.
- **B103/B104**: justificativa de contingência. **B6/B12/B13/B21/B22/B30**: coerência/janela das datas.
- **B56**: TAC-Agregado não informa data de início. **B66**: destinatário (carga). **B62**: campos proibidos no TAC-Agregado. **B69/B18**: natureza/peso da carga.
- **B82/B109/B110/B111**: distância e localização do par origem/destino.
- **B67/B119**: contratantes da carga fracionada. **B92/B99/B105/B106**: campos por tipo de pagamento e parcelamento.
- **B120**: valor do frete > 0.

**Validadas apenas pela ANTT** (precisam de dados do servidor): piso mínimo de
frete (**B80**), vínculo/exclusividade de veículos (**B15/B20/B73/B107**),
bloqueios/penalidades (**B114**), equiparação TAC (**B100**), certificado de IP
(**B118**), duplicidade de CIOT (**B17/B24/B25/B31/B32/B33**), prazos de
cancelamento/retificação/encerramento (**B35/B36/B39/B116**), etc. Por isso,
**sempre trate `$resposta->rejeitado()`** mesmo passando pela validação local.

## 18. Ambiguidades do manual

O manual DCS PEF v1.1 traz **grafias divergentes** entre as tabelas de leiaute e
os exemplos JSON. O pacote adota a grafia do **exemplo JSON** (o que de fato
trafega) e centraliza os casos ambíguos em `src/Support/Wire.php` — corrigir, se
a homologação exigir, é mudança de **uma linha**:

| Constante | Valor adotado | Alternativa na tabela |
|-----------|---------------|------------------------|
| `Wire::CONTRATANTES_CARGA_FRAC` | `ContratantesCargFrac` | `ContratantesCargaFrac` |
| `Wire::VEICULO_RNTRC` | `RNTRC` | `RNTRCVeiculo` |
| `Wire::ENCERRAMENTO_PESO_TOTAL` | `PesoTotalCarga` | `PesoCarga` |

Da mesma forma, o **contexto da URL** (`api`) e o **host** devem ser confirmados
com a ANTT — ajuste `CIOT_PATH_PREFIX` / `CIOT_URL_*` sem tocar no código.

As respostas são lidas de forma **tolerante** (ignoram caixa e aceitam apelidos
como `CodigoIdentificacaoOperacao`/`IdOperacaoTransporte`), então variações de
grafia na resposta não quebram o parsing.
