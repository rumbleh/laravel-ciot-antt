<?php

declare(strict_types=1);

namespace Rumbleh\CiotAntt\Requests;

use DateTimeInterface;
use Rumbleh\CiotAntt\Enums\TipoOperacao;
use Rumbleh\CiotAntt\Requests\Components\DadosCarga;
use Rumbleh\CiotAntt\Requests\Components\InfIndicadoresOperacionais;
use Rumbleh\CiotAntt\Requests\Components\InfPagamento;
use Rumbleh\CiotAntt\Requests\Components\OrigemDestino;
use Rumbleh\CiotAntt\Requests\Components\Veiculo;
use Rumbleh\CiotAntt\Support\Datas;
use Rumbleh\CiotAntt\Support\Payload;
use Rumbleh\CiotAntt\Validation\DeclaracaoValidator;
use Rumbleh\CiotAntt\ValueObjects\Documento;
use Rumbleh\CiotAntt\ValueObjects\Rntrc;

/**
 * Serviço 03 — DeclaracaoOperacaoTransporte (POST).
 *
 * É O serviço que GERA O CIOT: recebe os dados da operação de transporte e
 * retorna o Código Identificador da Operação de Transporte (CIOT) + dígito
 * verificador (ver DeclaracaoOperacaoTransporteResponse).
 *
 * Construtor recebe os campos sempre obrigatórios; os demais (condicionais ao
 * tipo de operação) são adicionados pelos métodos fluentes com*().
 *
 * Manual DCS PEF v1.1, pág. 18-28.
 */
final class DeclaracaoOperacaoTransporteRequest implements PefRequest
{
    public ?string $idOperacaoTransporte = null;

    public readonly string $cpfCnpjContratado;
    public readonly string $rntrcContratado;
    public readonly string $cpfCnpjContratante;

    public ?string $rntrcContratante = null;
    public ?string $cpfCnpjDestinatario = null;

    public ?string $dataDeclaracao = null;
    public bool $indContingencia = false;
    public ?string $justificativaContingencia = null;

    public ?string $dataInicioViagem = null;
    public ?string $dataFimViagem = null;

    /** @var list<Veiculo> */
    public array $veiculos = [];

    /** @var list<OrigemDestino> */
    public array $origensDestinos = [];

    public ?DadosCarga $dadosCarga = null;

    /** @var list<InfPagamento> */
    public array $pagamentos = [];

    public ?InfIndicadoresOperacionais $indicadores = null;

    public function __construct(
        public readonly TipoOperacao $tipoOperacao,
        string $cpfCnpjContratado,
        string $rntrcContratado,
        string $cpfCnpjContratante,
        public int|float|string $valorFrete = 0,
        ?string $idOperacaoTransporte = null,
    ) {
        $this->cpfCnpjContratado = Documento::de($cpfCnpjContratado)->valor();
        $this->rntrcContratado = Rntrc::de($rntrcContratado)->valor();
        $this->cpfCnpjContratante = Documento::de($cpfCnpjContratante)->valor();
        $this->idOperacaoTransporte = $idOperacaoTransporte;
    }

    public static function cargaLotacao(string $cpfCnpjContratado, string $rntrcContratado, string $cpfCnpjContratante): self
    {
        return new self(TipoOperacao::CargaLotacao, $cpfCnpjContratado, $rntrcContratado, $cpfCnpjContratante);
    }

    public static function cargaFracionada(string $cpfCnpjContratado, string $rntrcContratado, string $cpfCnpjContratante): self
    {
        return new self(TipoOperacao::CargaFracionada, $cpfCnpjContratado, $rntrcContratado, $cpfCnpjContratante);
    }

    public static function tacAgregado(string $cpfCnpjContratado, string $rntrcContratado, string $cpfCnpjContratante): self
    {
        return new self(TipoOperacao::TacAgregado, $cpfCnpjContratado, $rntrcContratado, $cpfCnpjContratante);
    }

    public function comId(string $idOperacaoTransporte): self
    {
        $this->idOperacaoTransporte = $idOperacaoTransporte;

        return $this;
    }

    public function comValorFrete(int|float|string $valorFrete): self
    {
        $this->valorFrete = $valorFrete;

        return $this;
    }

    public function comRntrcContratante(string $rntrc): self
    {
        $this->rntrcContratante = Rntrc::de($rntrc)->valor();

        return $this;
    }

    public function comDestinatario(string $cpfCnpj): self
    {
        $this->cpfCnpjDestinatario = Documento::de($cpfCnpj)->valor();

        return $this;
    }

    public function comDataDeclaracao(DateTimeInterface|string $data): self
    {
        $this->dataDeclaracao = Datas::dataHora($data);

        return $this;
    }

    public function emContingencia(string $justificativa): self
    {
        $this->indContingencia = true;
        $this->justificativaContingencia = $justificativa;

        return $this;
    }

    /**
     * Define início e fim da viagem. Use para carga lotação/fracionada.
     * Para TAC-Agregado NÃO informe a data de início (regra B56) — use
     * {@see comDataFimViagem()}.
     */
    public function comViagem(DateTimeInterface|string $inicio, DateTimeInterface|string $fim): self
    {
        $this->dataInicioViagem = Datas::data($inicio);
        $this->dataFimViagem = Datas::data($fim);

        return $this;
    }

    /**
     * Define apenas a data de fim da viagem (sem data de início).
     * Indicado para TAC-Agregado, onde a data de início não deve ser informada
     * (o servidor da ANTT a assume igual à data da declaração — regra B56).
     */
    public function comDataFimViagem(DateTimeInterface|string $fim): self
    {
        $this->dataFimViagem = Datas::data($fim);

        return $this;
    }

    public function comVeiculos(Veiculo ...$veiculos): self
    {
        $this->veiculos = array_merge($this->veiculos, array_values($veiculos));

        return $this;
    }

    public function comOrigemDestino(OrigemDestino ...$origensDestinos): self
    {
        $this->origensDestinos = array_merge($this->origensDestinos, array_values($origensDestinos));

        return $this;
    }

    public function comDadosCarga(DadosCarga $dadosCarga): self
    {
        $this->dadosCarga = $dadosCarga;

        return $this;
    }

    public function comPagamentos(InfPagamento ...$pagamentos): self
    {
        $this->pagamentos = array_merge($this->pagamentos, array_values($pagamentos));

        return $this;
    }

    public function comIndicadores(InfIndicadoresOperacionais $indicadores): self
    {
        $this->indicadores = $indicadores;

        return $this;
    }

    public function servico(): string
    {
        return 'DeclaracaoOperacaoTransporte';
    }

    public function metodo(): string
    {
        return 'POST';
    }

    public function toArray(): array
    {
        $payload = Payload::semNulos([
            'IdOperacaoTransporte' => $this->idOperacaoTransporte,
            'TipoOperacao' => $this->tipoOperacao->value,
            'CpfCnpjContratado' => $this->cpfCnpjContratado,
            'RNTRCContratado' => $this->rntrcContratado,
            'CpfCnpjContratante' => $this->cpfCnpjContratante,
            'RNTRCContratante' => $this->rntrcContratante,
            'CpfCnpjDestinatario' => $this->cpfCnpjDestinatario,
            'ValorFrete' => Payload::decimal($this->valorFrete, 2),
            'DataDeclaracao' => $this->dataDeclaracao,
            'IndContingencia' => $this->indContingencia,
            'JustificativaContingencia' => $this->justificativaContingencia,
            'DataInicioViagem' => $this->dataInicioViagem,
            'DataFimViagem' => $this->dataFimViagem,
        ]);

        $payload['Veiculos'] = array_map(static fn (Veiculo $v): array => $v->toArray(), $this->veiculos);

        if ($this->origensDestinos !== []) {
            $payload['OrigemDestino'] = array_map(
                static fn (OrigemDestino $od): array => $od->toArray(),
                $this->origensDestinos,
            );
        }

        if ($this->dadosCarga !== null) {
            $payload['DadosCarga'] = $this->dadosCarga->toArray();
        }

        $payload['InfPagamento'] = array_map(
            static fn (InfPagamento $p): array => $p->toArray(),
            $this->pagamentos,
        );

        if ($this->indicadores !== null) {
            $payload['InfIndicadoresOperacionais'] = $this->indicadores->toArray();
        }

        return $payload;
    }

    public function validar(): array
    {
        return DeclaracaoValidator::validar($this);
    }
}
