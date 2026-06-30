<?php

declare(strict_types=1);

use Rumbleh\CiotAntt\Enums\Ambiente;

return [

    /*
    |--------------------------------------------------------------------------
    | Ambiente
    |--------------------------------------------------------------------------
    |
    | Define qual ambiente do web service PEF da ANTT será utilizado.
    | Valores aceitos: "homologacao" ou "producao".
    |
    | A ANTT exige aprovação dos testes em homologação antes de habilitar o
    | uso em produção (ver manual DCS PEF — "Ambiente de Homologação").
    |
    */
    'ambiente' => env('CIOT_AMBIENTE', Ambiente::Homologacao->value),

    /*
    |--------------------------------------------------------------------------
    | URLs base dos web services
    |--------------------------------------------------------------------------
    |
    | Endereços oficiais publicados no manual DCS PEF v1.1 (pág. 66).
    | O segmento "path_prefix" é concatenado à URL base para formar o endpoint
    | de cada serviço, ex.: {base}/{path_prefix}/DeclaracaoOperacaoTransporte.
    |
    | IMPORTANTE: o manual repete "será necessário solicitar à ANTT o host e
    | contexto". Caso a ANTT informe um contexto diferente de "api", ajuste
    | apenas o "path_prefix" abaixo — nenhum código precisa mudar.
    |
    */
    'urls' => [
        Ambiente::Homologacao->value => env(
            'CIOT_URL_HOMOLOGACAO',
            'https://appservices-hml.antt.gov.br/pefServices'
        ),
        Ambiente::Producao->value => env(
            'CIOT_URL_PRODUCAO',
            'https://appservices.antt.gov.br/pefServices'
        ),
    ],

    'path_prefix' => env('CIOT_PATH_PREFIX', 'api'),

    /*
    |--------------------------------------------------------------------------
    | Certificado digital (mTLS — autenticação mútua)
    |--------------------------------------------------------------------------
    |
    | A comunicação usa TLS 1.2+ com autenticação mútua por certificado
    | digital ICP-Brasil A1 ou A3 (ver manual — "Padrão de Segurança").
    |
    | Em servidores de CI/CD o padrão é o A1 em arquivo PKCS#12 (.pfx/.p12).
    | Informe o caminho do arquivo e a senha. O pacote extrai o par
    | certificado+chave automaticamente para uso pelo cURL/Guzzle.
    |
    | Alternativamente, é possível apontar diretamente para arquivos PEM já
    | separados (cert + key), úteis quando o certificado já foi convertido.
    |
    */
    'certificado' => [
        // "pfx" (PKCS#12) ou "pem" (par cert/key separados).
        'tipo' => env('CIOT_CERT_TIPO', 'pfx'),

        // Caminho absoluto do arquivo .pfx/.p12 (quando tipo = "pfx").
        'pfx_path' => env('CIOT_CERT_PFX_PATH'),
        'pfx_senha' => env('CIOT_CERT_PFX_SENHA'),

        // Caminhos dos arquivos PEM (quando tipo = "pem").
        'cert_path' => env('CIOT_CERT_PEM_CERT_PATH'),
        'key_path' => env('CIOT_CERT_PEM_KEY_PATH'),
        'key_senha' => env('CIOT_CERT_PEM_KEY_SENHA'),

        // Diretório onde os PEMs temporários (extraídos do .pfx) são gravados
        // com permissão 0600. Por padrão usa o storage temporário do sistema.
        'cache_dir' => env('CIOT_CERT_CACHE_DIR'),

        // Caminho do binário `openssl`. Usado como fallback automático para ler
        // certificados A1 com algoritmos legados sob OpenSSL 3 (provider legacy).
        'openssl_bin' => env('CIOT_OPENSSL_BIN', 'openssl'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Verificação TLS do servidor
    |--------------------------------------------------------------------------
    |
    | true  -> valida a cadeia do servidor (recomendado em produção).
    | false -> desabilita verificação (NÃO recomendado).
    | string -> caminho de um bundle CA customizado.
    |
    */
    'verificar_ssl' => env('CIOT_VERIFICAR_SSL', true),

    /*
    |--------------------------------------------------------------------------
    | Tempo limite (segundos)
    |--------------------------------------------------------------------------
    */
    'timeout' => (int) env('CIOT_TIMEOUT', 30),
    'connect_timeout' => (int) env('CIOT_CONNECT_TIMEOUT', 10),

    /*
    |--------------------------------------------------------------------------
    | Validação prévia (client-side) das regras de negócio
    |--------------------------------------------------------------------------
    |
    | Quando true, o pacote aplica localmente as regras de negócio do manual
    | que podem ser verificadas antes do envio (formato de CPF/CNPJ, placa,
    | datas, obrigatoriedades por tipo de operação/pagamento etc.), lançando
    | CiotValidationException com mensagens claras antes de gastar uma
    | requisição na ANTT. Desative para enviar "cru" e deixar a ANTT validar.
    |
    */
    'validar_antes_de_enviar' => env('CIOT_VALIDAR_ANTES', true),

    /*
    |--------------------------------------------------------------------------
    | Geração do IdOperacaoTransporte
    |--------------------------------------------------------------------------
    |
    | O IdOperacaoTransporte (12 dígitos) é gerado pela DLL/executável
    | disponibilizada pela ANTT. Registre aqui a classe que implementa
    | Rumbleh\CiotAntt\Contracts\OperationIdGenerator para gerá-lo
    | automaticamente, ou deixe null e informe o id manualmente em cada
    | declaração.
    |
    */
    'operation_id_generator' => env('CIOT_OPERATION_ID_GENERATOR'),

    /*
    |--------------------------------------------------------------------------
    | Identificação da administradora/emissora (opcional)
    |--------------------------------------------------------------------------
    |
    | Metadados úteis para logs e para implementações de OperationIdGenerator.
    |
    */
    'emissora' => [
        'cnpj' => env('CIOT_EMISSORA_CNPJ'),
        'codigo_administradora' => env('CIOT_CODIGO_ADMINISTRADORA'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Canal de log para registrar requisições/respostas (sem dados sensíveis
    | do certificado). Deixe null para não registrar.
    |
    */
    'log_channel' => env('CIOT_LOG_CHANNEL'),
];
