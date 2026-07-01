<?php

declare(strict_types=1);

namespace Rumbleh\CiotAntt\Exceptions;

/**
 * Lançada quando a configuração do pacote está incompleta ou inválida
 * (ex.: certificado ausente, ambiente desconhecido, gerador de id não
 * registrado).
 */
class CiotConfigurationException extends CiotException
{
    public static function certificadoNaoConfigurado(): self
    {
        return new self(
            'Certificado digital não configurado. Defina CIOT_CERT_PFX_PATH e '
            . 'CIOT_CERT_PFX_SENHA (ou os caminhos PEM) em config/ciot.php.'
        );
    }

    public static function arquivoCertificadoNaoEncontrado(string $path): self
    {
        return new self("Arquivo de certificado não encontrado: {$path}");
    }

    public static function falhaLeituraPfx(string $detalhe): self
    {
        return new self(
            "Não foi possível ler o certificado PKCS#12 (.pfx). Verifique o "
            . "arquivo e a senha. Detalhe: {$detalhe}"
        );
    }

    public static function falhaLeituraPfxLegado(string $detalhe): self
    {
        return new self(
            'Não foi possível ler o certificado PKCS#12 (.pfx): o arquivo usa '
            . 'algoritmos legados (comum em certificados A1 ICP-Brasil), não '
            . 'habilitados por padrão no OpenSSL 3. Soluções: (1) reexporte o .pfx '
            . 'com cifra moderna (`openssl pkcs12 -export -in origem.pfx -out novo.pfx`); '
            . 'ou (2) habilite o provider "legacy" no openssl.cnf; ou (3) garanta que '
            . 'o binário `openssl` esteja disponível no servidor para o fallback '
            . "automático do pacote. Detalhe: {$detalhe}"
        );
    }

    public static function ambienteInvalido(string $ambiente): self
    {
        return new self(
            "Ambiente CIOT inválido: '{$ambiente}'. Use 'homologacao' ou 'producao'."
        );
    }

    public static function apiKeyNaoConfigurada(): self
    {
        return new self(
            'A API key do Gerador CIOT v3 não foi configurada. Defina CIOT_API_KEY '
            . '(config "ciot.gerador.api_key") na aplicação que usa o pacote.'
        );
    }

    public static function urlGeradorNaoConfigurada(string $ambiente): self
    {
        return new self(
            "URL do Gerador CIOT v3 não configurada para o ambiente '{$ambiente}'. "
            . 'Defina "ciot.gerador.urls" em config/ciot.php.'
        );
    }

    public static function geradorDeIdNaoRegistrado(): self
    {
        return new self(
            'Nenhum IdOperacaoTransporte foi informado e nenhum '
            . 'OperationIdGenerator foi registrado. Informe o id manualmente '
            . 'em DeclaracaoOperacaoTransporteRequest::comId() ou registre uma '
            . 'implementação de Rumbleh\\CiotAntt\\Contracts\\OperationIdGenerator '
            . 'na configuração "ciot.operation_id_generator".'
        );
    }
}
