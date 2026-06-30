<?php

declare(strict_types=1);

namespace Rumbleh\CiotAntt\Exceptions;

/**
 * Lançada quando a ANTT responde com sucesso de transporte (HTTP 2xx) porém
 * rejeita a operação por uma regra de negócio (código diferente de 110/111).
 *
 * O código e a mensagem são os retornados pela ANTT (campos "Codigo" e
 * "Mensagem"). Veja a tabela de regras B1..B122 no manual e os protocolos de
 * retorno (110 = inserido com sucesso, 111 = consulta com sucesso).
 *
 * Por padrão o pacote NÃO lança esta exceção automaticamente: os serviços
 * retornam um objeto de resposta com ->sucesso(), ->codigo() e ->mensagem(),
 * permitindo que você trate a rejeição como preferir. Use
 * Response::lancarSeRejeitado() para optar pelo comportamento de exceção.
 */
class CiotRejectionException extends CiotException
{
    public function __construct(
        public readonly string $codigo,
        public readonly string $mensagemAntt,
        public readonly array $resposta = [],
    ) {
        parent::__construct(
            sprintf('ANTT rejeitou a operação [%s]: %s', $codigo, $mensagemAntt)
        );
    }
}
