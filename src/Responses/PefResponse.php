<?php

declare(strict_types=1);

namespace Rumbleh\CiotAntt\Responses;

use Rumbleh\CiotAntt\Exceptions\CiotRejectionException;

/**
 * Base das respostas dos serviços do PEF/ANTT.
 *
 * A leitura dos campos é TOLERANTE: o manual apresenta a mesma informação com
 * grafias/caixa diferentes entre tabelas e exemplos JSON (ex.:
 * "CodigoIdentificacaoOperacao" vs "IdOperacaoTransporte"). A busca é feita
 * por chave ignorando maiúsculas/minúsculas e aceitando múltiplos apelidos.
 *
 * Protocolos de retorno (manual, pág. 64):
 *  - 110: "Dados inseridos com sucesso!"
 *  - 111: "Consulta realizada com sucesso!"
 * Qualquer outro código é tratado como rejeição (regra de negócio B*).
 */
abstract class PefResponse
{
    public const SUCESSO_INSERCAO = '110';
    public const SUCESSO_CONSULTA = '111';

    /** @var array<string, mixed> */
    protected array $raw;

    /** @var array<string, mixed> mapa de chave-minúscula => valor */
    private array $indice;

    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(array $raw, public readonly int $statusHttp = 200)
    {
        $this->raw = $raw;
        $this->indice = [];
        foreach ($raw as $chave => $valor) {
            $this->indice[strtolower((string) $chave)] = $valor;
        }
    }

    /**
     * Retorna o primeiro valor encontrado entre os apelidos informados.
     */
    protected function valor(string ...$apelidos): mixed
    {
        foreach ($apelidos as $apelido) {
            $k = strtolower($apelido);
            if (array_key_exists($k, $this->indice)) {
                return $this->indice[$k];
            }
        }

        return null;
    }

    protected function valorString(string ...$apelidos): ?string
    {
        $v = $this->valor(...$apelidos);

        if ($v === null || is_array($v)) {
            return null;
        }

        return (string) $v;
    }

    protected function valorBool(string ...$apelidos): bool
    {
        return self::paraBool($this->valor(...$apelidos));
    }

    /**
     * Normaliza um valor da ANTT em booleano de forma robusta.
     *
     * Necessário porque o manual representa campos de "situação" de formas
     * diferentes: booleano nativo (true/false), inteiro (0/1) ou string
     * ("true"/"false"). Um cast direto `(bool)` é PERIGOSO: `(bool) "false"`
     * resulta em `true`. Ex.: SituacaoVeiculoFrotaTransportador é tipado como
     * alfanumérico no leiaute (pág. 16) e como inteiro no exemplo JSON (pág. 17).
     */
    protected static function paraBool(mixed $valor): bool
    {
        if (is_bool($valor)) {
            return $valor;
        }
        if (is_int($valor) || is_float($valor)) {
            return $valor != 0;
        }
        if (is_string($valor)) {
            return in_array(strtolower(trim($valor)), ['true', '1'], true);
        }

        return false;
    }

    /**
     * Código de retorno (campo "Codigo"). Em alguns serviços a ANTT devolve o
     * código como lista; nesse caso retorna-se o primeiro item.
     */
    public function codigo(): ?string
    {
        $v = $this->valor('Codigo');

        if (is_array($v)) {
            $v = $v[0] ?? null;
        }

        return $v !== null ? (string) $v : null;
    }

    public function mensagem(): ?string
    {
        $v = $this->valor('Mensagem');

        if (is_array($v)) {
            return implode(' | ', array_map(static fn ($m): string => (string) $m, $v));
        }

        return $v !== null ? (string) $v : null;
    }

    public function protocolo(): ?string
    {
        return $this->valorString('Protocolo');
    }

    public function sucesso(): bool
    {
        $codigo = $this->codigo();

        return in_array($codigo, [self::SUCESSO_INSERCAO, self::SUCESSO_CONSULTA], true);
    }

    public function rejeitado(): bool
    {
        return ! $this->sucesso();
    }

    /**
     * Lança CiotRejectionException quando a resposta não indicar sucesso.
     */
    public function lancarSeRejeitado(): static
    {
        if ($this->rejeitado()) {
            throw new CiotRejectionException(
                (string) ($this->codigo() ?? 'desconhecido'),
                (string) ($this->mensagem() ?? 'Operação rejeitada pela ANTT.'),
                $this->raw,
            );
        }

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function raw(): array
    {
        return $this->raw;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->raw;
    }
}
