# Instalação e uso em CI/CD

Pacote **público** no Packagist — instale normalmente:

```bash
composer require rumbleh/laravel-ciot-antt
php artisan vendor:publish --tag=ciot-config
```

O provider é auto-descoberto (Laravel package discovery); a config fica disponível
em `config('ciot')` mesmo sem publicar.

---

## Build de produção (não interativo)

```bash
composer install --no-interaction --prefer-dist --no-dev --optimize-autoloader
```

- `--no-interaction`: nunca pergunta nada (essencial em CI/CD).
- `--prefer-dist` + `.gitattributes export-ignore`: baixa só o necessário (sem `tests/`, `docs/`).
- `--no-dev`: não instala dependências de desenvolvimento em produção.
- `--optimize-autoloader`: autoload otimizado para produção.

---

## Certificado A1 (mTLS)

O certificado ICP-Brasil **A1** (`.pfx`/`.p12`) **não** deve ser versionado.
Disponibilize-o no ambiente de build/run via *secret* e aponte as variáveis de
ambiente (lidas por `config/ciot.php`):

```bash
CIOT_AMBIENTE=homologacao
CIOT_CERT_TIPO=pfx
CIOT_CERT_PFX_PATH=/caminho/seguro/certificado.pfx
CIOT_CERT_PFX_SENHA=...
```

Veja [`USAGE.md`](USAGE.md) para a configuração completa (certificado, geração do
`IdOperacaoTransporte`, tratamento de erros e regras de negócio).

---

## Atualizando a versão

```bash
composer update rumbleh/laravel-ciot-antt --no-interaction
```

> Releases seguem **SemVer** via tags Git (`git tag v1.1.0 && git push --tags`);
> o Packagist indexa automaticamente quando o webhook está configurado.

---

## Checklist de problemas comuns

| Sintoma | Causa provável | Solução |
|---------|----------------|---------|
| `Package not found` | Tag/versão inexistente no Packagist | Crie e dê push da tag; confirme o webhook do Packagist |
| Instala mas não encontra a classe | Cache de autoload | `composer dump-autoload -o` |
| Erro de mTLS/SSL na ANTT | Caminho/senha do `.pfx` errados | Confira `CIOT_CERT_PFX_PATH` / `CIOT_CERT_PFX_SENHA` |
