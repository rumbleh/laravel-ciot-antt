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
    | Gerador CIOT v3 (API key + token JWT)
    |--------------------------------------------------------------------------
    |
    | Caminho alternativo, mais simples, para gerar o identificador da operação
    | (CIOT) sem o fluxo mTLS completo: autentica em POST /token com a API key
    | (header "chave") e gera/consulta em POST /gerar com o token Bearer.
    |
    | Esta API ocupa o lugar da "DLL oficial" referida pelo contrato
    | OperationIdGenerator. Quando "api_key" está definida e nenhuma classe é
    | informada em "operation_id_generator", o pacote usa automaticamente o
    | GeradorCiot como gerador do IdOperacaoTransporte em declararOperacao().
    |
    | A URL é resolvida pelo mesmo "ambiente" global acima (homologacao/producao)
    | e nunca fica fixa no código.
    |
    */
    'gerador' => [
        // API key da aplicação que consome o pacote (header "chave" no /token).
        'api_key' => env('CIOT_API_KEY'),

        // URLs base por ambiente (sem o sufixo /token ou /gerar).
        'urls' => [
            Ambiente::Homologacao->value => env(
                'CIOT_GERADOR_URL_HOMOLOGACAO',
                'https://appservices-hml.antt.gov.br/pefServices'
            ),
            Ambiente::Producao->value => env(
                'CIOT_GERADOR_URL_PRODUCAO',
                'https://mtcuybq605.execute-api.sa-east-1.amazonaws.com/api-ciot-prd/GeradorCIOT'
            ),
        ],

        // Validade do token em segundos. A DLL reutiliza por ~59 min (3540s).
        'token_ttl' => (int) env('CIOT_GERADOR_TOKEN_TTL', 3540),

        // Tempo limite específico do gerador (cai para o timeout global se nulo).
        'timeout' => env('CIOT_GERADOR_TIMEOUT'),
        'connect_timeout' => env('CIOT_GERADOR_CONNECT_TIMEOUT'),

        // Verificação TLS do gerador. A DLL ignora o certificado; aqui o padrão
        // é seguro (true) e totalmente configurável — nunca fixo. Use false só
        // se necessário (ex.: ambiente de homologação com cadeia incompleta).
        'verificar_ssl' => env('CIOT_GERADOR_VERIFICAR_SSL', true),
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
