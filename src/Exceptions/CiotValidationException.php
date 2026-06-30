<?php

declare(strict_types=1);

namespace Rumbleh\CiotAntt\Exceptions;

/**
 * Lançada quando a validação local (client-side) das regras de negócio do
 * manual falha, antes de enviar a requisição à ANTT.
 *
 * A propriedade $erros contém a lista de mensagens (uma por regra violada),
 * cada uma referenciando a regra do manual (ex.: "B18", "B80").
 */
class CiotValidationException extends CiotException
{
    /**
     * @param  list<string>  $erros
     */
    public function __construct(
        public readonly array $erros,
        string $message = '',
    ) {
        parent::__construct(
            $message !== '' ? $message : $this->resumo()
        );
    }

    /**
     * @param  list<string>  $erros
     */
    public static function comErros(array $erros): self
    {
        return new self($erros);
    }

    /** @return list<string> */
    public function erros(): array
    {
        return $this->erros;
    }

    private function resumo(): string
    {
        $total = count($this->erros);
        $primeiros = implode(' | ', array_slice($this->erros, 0, 3));

        return $total === 1
            ? "Validação CIOT falhou: {$primeiros}"
            : "Validação CIOT falhou ({$total} erros): {$primeiros}"
                . ($total > 3 ? ' | ...' : '');
    }
}
