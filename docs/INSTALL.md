# Instalação privada via Composer (CI/CD não interativo)

Este pacote é **privado**. Ele é instalado a partir do seu repositório Git
privado, sem publicação no Packagist público. Abaixo estão as formas de instalar
de modo **não interativo** — ideal para pipelines de CI/CD (ex.: DigitalOcean App
Platform, Droplets, GitHub Actions).

> **Nomes envolvidos**
> - **Pacote Composer** (o que você dá `require`): `rumbleh/laravel-ciot-antt`
>   (definido em `composer.json` → `name`).
> - **Repositório Git** (de onde o Composer baixa): ex.
>   `github.com/rumbleh/laravel-ciot-antt`.
>
> Os dois são independentes: o repositório pode ter qualquer nome; o que importa
> para o `require` é o `name` do `composer.json`. Ajuste as URLs abaixo para o seu
> repositório real.

---

## 1. Declare o repositório no projeto que vai consumir o pacote

No `composer.json` do **seu projeto Laravel** (não no pacote), adicione o
repositório e o require:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/rumbleh/laravel-ciot-antt.git"
        }
    ],
    "require": {
        "rumbleh/laravel-ciot-antt": "^1.0"
    }
}
```

> Prefira **tags** (`^1.0`) a `dev-main`. Crie releases com
> `git tag v1.0.0 && git push --tags` no repositório do pacote.

---

## 2. Autenticação não interativa

Escolha **uma** das opções. A opção A (token via variável de ambiente) é a mais
simples para CI/CD.

### Opção A — Token do GitHub via `COMPOSER_AUTH` (recomendado)

1. Gere um **Personal Access Token** (clássico com escopo `repo`, ou um
   *fine-grained token* com permissão de leitura de conteúdo no repositório do
   pacote).
2. Exporte a variável de ambiente no ambiente de build (ela é lida automaticamente
   pelo Composer, sem nenhum prompt):

```bash
export COMPOSER_AUTH='{"github-oauth":{"github.com":"SEU_TOKEN_AQUI"}}'
composer install --no-interaction --prefer-dist --no-dev --optimize-autoloader
```

No **GitHub Actions**:

```yaml
- name: Install dependencies
  env:
    COMPOSER_AUTH: '{"github-oauth":{"github.com":"${{ secrets.CIOT_REPO_TOKEN }}"}}'
  run: composer install --no-interaction --prefer-dist --no-dev --optimize-autoloader
```

Na **DigitalOcean App Platform**: cadastre `COMPOSER_AUTH` como variável de
ambiente (tipo *secret*, *Build & Run* ou apenas *Build Time*) com o JSON acima.
O comando de build padrão (`composer install`) já a utilizará.

> Alternativa equivalente sem `COMPOSER_AUTH`:
> ```bash
> composer config --global --auth github-oauth.github.com "SEU_TOKEN_AQUI"
> ```

### Opção B — Deploy key SSH (somente leitura)

1. No repositório do pacote, cadastre uma **Deploy key** (chave pública,
   *read-only*).
2. Disponibilize a **chave privada** no agente SSH do runner e use a URL SSH no
   `repositories`:

```json
{ "type": "vcs", "url": "git@github.com:rumbleh/laravel-ciot-antt.git" }
```

```bash
eval "$(ssh-agent -s)"
ssh-add - <<< "$CIOT_DEPLOY_KEY"
# evita prompt de host desconhecido:
mkdir -p ~/.ssh && ssh-keyscan github.com >> ~/.ssh/known_hosts 2>/dev/null
composer install --no-interaction --prefer-dist --no-dev --optimize-autoloader
```

### Opção C — `auth.json` no projeto (não versionar)

```bash
composer config http-basic.github.com x-access-token "SEU_TOKEN_AQUI"
# gera ./auth.json — adicione-o ao .gitignore!
```

---

## 3. Comando de instalação (idempotente, sem interação)

```bash
composer install --no-interaction --prefer-dist --no-dev --optimize-autoloader
```

- `--no-interaction`: nunca pergunta nada (essencial em CI/CD).
- `--prefer-dist` + `.gitattributes export-ignore`: baixa só o necessário (sem
  `tests/`, `docs/`).
- `--no-dev`: não instala dependências de desenvolvimento em produção.
- `--optimize-autoloader`: autoload otimizado para produção.

---

## 4. Atualizando a versão

```bash
# no repositório do pacote:
git tag v1.1.0 && git push origin v1.1.0

# no projeto consumidor:
composer update rumbleh/laravel-ciot-antt --no-interaction
```

---

## 5. Checklist de problemas comuns

| Sintoma | Causa provável | Solução |
|---------|----------------|---------|
| `Could not authenticate against github.com` | Token ausente/sem escopo | Configure `COMPOSER_AUTH` (Opção A) com escopo `repo`/leitura de conteúdo |
| `The "...git" repository ... could not be found` | URL do repo errada ou sem acesso | Confira a URL e a permissão da deploy key/token |
| Composer pede senha no CI | Falta `--no-interaction` ou auth | Adicione `--no-interaction` e configure a auth |
| Instala mas não encontra a classe | Cache de autoload | `composer dump-autoload -o` |
| `Package not found` | Tag não existe | Crie e faça push da tag (`git tag`/`git push --tags`) |
