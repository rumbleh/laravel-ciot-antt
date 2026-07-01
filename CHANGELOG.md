# Changelog

Todas as mudanças relevantes deste pacote serão documentadas aqui.
O formato segue [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/) e o
versionamento segue [SemVer](https://semver.org/lang/pt-BR/).

## [1.1.0] - 2026-06-30

### Adicionado
- **Gerador CIOT v3** (token + API key) como caminho alternativo, mais simples,
  para gerar/consultar o CIOT — sem o fluxo mTLS completo:
  - Cliente `Rumbleh\CiotAntt\GeradorCiot` e facade `GeradorCiot` com
    `authenticate()`, `gerarCiot()`, `consultarCiot()` e `refreshToken()`.
  - Autenticação em `POST /token` (header `chave`) com reuso automático do
    token por ~59 min (`TokenManager`) e renovação transparente ao expirar
    (inclusive retentativa única em HTTP 401).
  - Interpretação dos três formatos de resposta do `/gerar`
    (`{"dados":{"ciot"}}`, `{"Dados":{"CIOT"}}` e string crua).
  - URL configurável por ambiente (homologação/produção) — nunca fixa; API key
    fornecida pela aplicação consumidora via `CIOT_API_KEY` (`ciot.gerador.api_key`).
  - Verificação TLS configurável (`ciot.gerador.verificar_ssl`).
  - Exceções próprias: `CiotAuthenticationException`, `CiotUnauthorizedException`
    e `CiotApiException` (com status HTTP, corpo e endpoint).
- **Integração automática**: quando a `CIOT_API_KEY` está definida e nenhuma
  classe é informada em `ciot.operation_id_generator`, o `GeradorCiot` passa a
  ser usado como `OperationIdGenerator` — o `IdOperacaoTransporte` exigido por
  `declararOperacao()` é gerado por esta API, ocupando o lugar da DLL oficial.

## [1.0.0] - 2026-06-23

### Adicionado
- Cliente Laravel para os 8 serviços do web service PEF da ANTT (emissão de CIOT),
  baseado no manual DCS PEF v1.1 (MP 1.343/2026 — Piso Mínimo de Frete):
  - `ConsultarSituacaoTransportador`
  - `ConsultarFrotaTransportador`
  - `DeclaracaoOperacaoTransporte` (gera o CIOT)
  - `CancelamentoOperacaoTransporte`
  - `RetificacaoOperacaoTransporte`
  - `EncerramentoOperacaoTransporte`
  - `ConsultarExcecao`
  - `ConsultarCIOTGerado`
- Autenticação mútua (mTLS) com certificado ICP-Brasil A1 (`.pfx`/`.p12`) ou PEM.
- Validação local das regras de negócio do manual (B1..B122) verificáveis antes do envio.
- Value Objects com validação de CPF/CNPJ (DV), RNTRC (regra B60) e placa (BR/Mercosul).
- Contrato plugável `OperationIdGenerator` para geração do `IdOperacaoTransporte`.
- Suíte de testes (PHPUnit) cobrindo os 8 serviços, regras de negócio e parsing de respostas.

### Robustez (revisão de conformidade com o manual)
- Regra **B56**: para TAC-Agregado a `DataInicioViagem` não é exigida nem enviada
  (o servidor assume = `DataDeclaracao`); adicionado `comDataFimViagem()`.
- Regra **B99** também aplicada a `TipoPagamento::Outros` (5): rejeita campos
  bancários/Pix indevidos antes do envio.
- `Payload::decimal()` agora trata separador de milhar (BR/internacional) sem
  corromper valores monetários (ex.: `"1.500,00"` → `"1500.00"`).
- Leitura de situação do veículo (`ConsultarFrotaTransportador`) normaliza
  `"false"`/`"true"`/`0`/`1` corretamente (evita inversão por cast `(bool)`).
- Certificados A1 com algoritmos legados (ICP-Brasil) sob OpenSSL 3: fallback
  automático via binário `openssl -legacy` com mensagem de erro orientativa.
- Chave privada extraída do `.pfx` gravada atomicamente com permissão `0600`
  (sem janela world-readable).
