# Changelog

Todas as mudanças relevantes deste pacote serão documentadas aqui.
O formato segue [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/) e o
versionamento segue [SemVer](https://semver.org/lang/pt-BR/).

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
