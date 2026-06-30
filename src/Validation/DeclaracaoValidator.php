<?php

declare(strict_types=1);

namespace Rumbleh\CiotAntt\Validation;

use Rumbleh\CiotAntt\Enums\TipoOperacao;
use Rumbleh\CiotAntt\Enums\TipoPagamento;
use Rumbleh\CiotAntt\Requests\Components\InfPagamento;
use Rumbleh\CiotAntt\Requests\Components\Veiculo;
use Rumbleh\CiotAntt\Requests\DeclaracaoOperacaoTransporteRequest as Req;
use Rumbleh\CiotAntt\Support\Datas;

/**
 * Aplica, localmente, as regras de negócio do manual DCS PEF v1.1 verificáveis
 * antes do envio da DeclaracaoOperacaoTransporte.
 *
 * Cada mensagem referencia a regra correspondente (B1..B122). Regras que
 * dependem de dados que só a ANTT possui (piso mínimo de frete B80, vínculo de
 * veículos B15/B20/B73, bloqueios B107/B114, equiparação TAC B100, certificado
 * de IP B118 etc.) NÃO são verificadas aqui — apenas no servidor da ANTT.
 */
final class DeclaracaoValidator
{
    /**
     * @return list<string>
     */
    public static function validar(Req $r): array
    {
        $erros = [];

        self::obrigatorios($r, $erros);
        self::veiculos($r, $erros);
        self::contingencia($r, $erros);
        self::datas($r, $erros);
        self::porTipoOperacao($r, $erros);
        self::pagamentos($r, $erros);

        return array_values($erros);
    }

    /**
     * @param  list<string>  $erros
     */
    private static function obrigatorios(Req $r, array &$erros): void
    {
        if ($r->idOperacaoTransporte === null || trim($r->idOperacaoTransporte) === '') {
            $erros[] = 'B1: O campo IdOperacaoTransporte é obrigatório.';
        } elseif (! preg_match('/^\w{1,12}$/', $r->idOperacaoTransporte)) {
            $erros[] = 'B4: IdOperacaoTransporte inválido (esperado até 12 caracteres).';
        }

        if (self::valorFrete($r) <= 0.0) {
            $erros[] = 'B120: O valor do frete deve ser informado e maior que zero.';
        }

        if ($r->dataDeclaracao === null || trim($r->dataDeclaracao) === '') {
            $erros[] = 'B1: O campo DataDeclaracao é obrigatório.';
        } elseif (! Datas::formatoDataHoraValido($r->dataDeclaracao)) {
            $erros[] = 'B4: DataDeclaracao inválida. Use o formato yyyy-MM-ddTHH:mm:ss.';
        }

        if ($r->tipoOperacao === TipoOperacao::TacAgregado) {
            // B56: para TAC-Agregado a data de início NÃO deve ser informada — o
            // servidor da ANTT atribui dataInicioViagem = dataDeclaracao.
            if ($r->dataInicioViagem !== null) {
                $erros[] = 'B56: A data de início da viagem não deve ser informada na declaração de operação de transporte de TAC-Agregado.';
            }
        } elseif ($r->dataInicioViagem === null) {
            $erros[] = 'B1: O campo DataInicioViagem é obrigatório.';
        } elseif (! Datas::formatoDataValido($r->dataInicioViagem)) {
            $erros[] = 'B4: DataInicioViagem inválida. Use o formato yyyy-MM-dd.';
        }

        if ($r->dataFimViagem === null) {
            $erros[] = 'B1: O campo DataFimViagem é obrigatório.';
        } elseif (! Datas::formatoDataValido($r->dataFimViagem)) {
            $erros[] = 'B4: DataFimViagem inválida. Use o formato yyyy-MM-dd.';
        }

        if ($r->veiculos === []) {
            $erros[] = 'B1: É obrigatório informar ao menos um veículo (Veiculos).';
        }

        if ($r->pagamentos === []) {
            $erros[] = 'B1: É obrigatório informar as informações de pagamento (InfPagamento).';
        }
    }

    /**
     * @param  list<string>  $erros
     */
    private static function veiculos(Req $r, array &$erros): void
    {
        if ($r->veiculos === []) {
            return;
        }

        // B49: até 5 veículos.
        if (count($r->veiculos) > 5) {
            $erros[] = 'B49: Somente é permitido informar até 5 veículos para uma declaração de operação de transporte.';
        }

        // B5: placas não podem ser duplicadas.
        $placas = array_map(static fn (Veiculo $v): string => $v->placa, $r->veiculos);
        if (count($placas) !== count(array_unique($placas))) {
            $erros[] = 'B5: Existe duplicidade de placa na lista informada.';
        }

        // B83/B84: exatamente um veículo automotor.
        $automotores = array_filter($r->veiculos, static fn (Veiculo $v): bool => $v->automotor);
        $qtdAutomotores = count($automotores);
        if ($qtdAutomotores === 0) {
            $erros[] = 'B83: É necessário informar ao menos um veículo do tipo automotor.';
        } elseif ($qtdAutomotores > 1) {
            $erros[] = 'B84: Somente um veículo deve ser do tipo automotor.';
        }

        // B101: número de eixos por tipo de veículo.
        foreach ($r->veiculos as $v) {
            $min = $v->automotor ? 2 : 1;
            if ($v->numeroEixos < $min || $v->numeroEixos > 4) {
                $tipo = $v->automotor ? 'automotor (2 a 4 eixos)' : 'implemento (1 a 4 eixos)';
                $erros[] = "B101: Quantidade de eixos inválida para a placa {$v->placa} — {$tipo}.";
            }
        }

        // B117: cavalo-trator exige ao menos um implemento (carga lotação/fracionada).
        if ($r->tipoOperacao->ehCarga()) {
            $temCavaloTrator = (bool) array_filter(
                $r->veiculos,
                static fn (Veiculo $v): bool => $v->automotor && $v->cavaloTrator,
            );
            $temImplemento = (bool) array_filter(
                $r->veiculos,
                static fn (Veiculo $v): bool => ! $v->automotor,
            );
            if ($temCavaloTrator && ! $temImplemento) {
                $erros[] = 'B117: É obrigatório informar ao menos um implemento quando o veículo automotor for do tipo cavalo-trator.';
            }
        }
    }

    /**
     * @param  list<string>  $erros
     */
    private static function contingencia(Req $r, array &$erros): void
    {
        // B103/B104: justificativa obrigatória sse em contingência; proibida caso contrário.
        $temJustificativa = $r->justificativaContingencia !== null && trim($r->justificativaContingencia) !== '';

        if ($r->indContingencia && ! $temJustificativa) {
            $erros[] = 'B103: A justificativa de contingência é obrigatória quando IndContingencia = true.';
        }
        if (! $r->indContingencia && $temJustificativa) {
            $erros[] = 'B104: Não é permitido informar JustificativaContingencia quando IndContingencia = false.';
        }
        if ($temJustificativa && mb_strlen((string) $r->justificativaContingencia) > 255) {
            $erros[] = 'B4: JustificativaContingencia excede o tamanho máximo de 255 caracteres.';
        }
    }

    /**
     * @param  list<string>  $erros
     */
    private static function datas(Req $r, array &$erros): void
    {
        $tac = $r->tipoOperacao === TipoOperacao::TacAgregado;
        $fimOk = $r->dataFimViagem !== null && Datas::formatoDataValido($r->dataFimViagem);
        $declOk = $r->dataDeclaracao !== null && Datas::formatoDataHoraValido($r->dataDeclaracao);

        if (! $fimOk) {
            return;
        }

        $fim = Datas::parseData((string) $r->dataFimViagem);

        // Data de início EFETIVA: para TAC-Agregado o servidor usa a data da
        // declaração (a dataInicioViagem não é informada — regra B56); para carga,
        // usa a dataInicioViagem informada.
        if ($tac) {
            if (! $declOk) {
                return;
            }
            $inicio = Datas::parseDataHora((string) $r->dataDeclaracao)->setTime(0, 0);
        } else {
            if ($r->dataInicioViagem === null || ! Datas::formatoDataValido($r->dataInicioViagem)) {
                return;
            }
            $inicio = Datas::parseData((string) $r->dataInicioViagem);
        }

        $hoje = new \DateTimeImmutable('today');

        // B13: dataFimViagem >= início efetivo.
        if ($fim < $inicio) {
            $erros[] = 'B13: A data prevista para término da viagem deve ser maior ou igual à data de início da viagem.';
        }

        // B12: dataInicioViagem >= dataDeclaracao (apenas carga; no TAC o início É a declaração).
        if (! $tac && $declOk) {
            $decl = Datas::parseDataHora((string) $r->dataDeclaracao);
            if ($inicio < $decl->setTime(0, 0)) {
                $erros[] = 'B12: A data de início da viagem não pode ser inferior à data de declaração.';
            }
        }

        // Janela do intervalo por tipo de operação (B21 = 90d carga; B30 = 30d TAC-Agregado).
        $dias = (int) $inicio->diff($fim)->format('%a');
        if ($tac) {
            if ($dias > 30) {
                $erros[] = 'B30: O intervalo entre início e fim da viagem, para TAC-Agregado, não pode ser superior a 30 dias.';
            }
        } elseif ($dias > 90) {
            $erros[] = 'B21: O intervalo entre a data de início e a data de fim da viagem não pode ser superior a 90 dias.';
        }

        // Regras relativas a "hoje" (B6/B22) usam a dataInicioViagem informada —
        // só se aplicam a carga fora de contingência.
        if (! $tac && ! $r->indContingencia) {
            if ($inicio < $hoje) {
                $erros[] = 'B6: A data de início da viagem não pode ser inferior à data atual.';
            }
            if ($inicio > $hoje->modify('+30 days')) {
                $erros[] = 'B22: A data de início da viagem excede o limite máximo de 30 dias em relação à data atual.';
            }
        }
    }

    /**
     * @param  list<string>  $erros
     */
    private static function porTipoOperacao(Req $r, array &$erros): void
    {
        $carga = $r->tipoOperacao->ehCarga();
        $fracionada = $r->tipoOperacao === TipoOperacao::CargaFracionada;
        $tac = $r->tipoOperacao === TipoOperacao::TacAgregado;

        if ($carga) {
            // B66: destinatário obrigatório para carga lotação/fracionada.
            if ($r->cpfCnpjDestinatario === null) {
                $erros[] = 'B66: CPF/CNPJ do destinatário é obrigatório para o tipo de operação informado.';
            }

            // OrigemDestino e DistanciaPercorrida obrigatórios (B82/B109).
            if ($r->origensDestinos === []) {
                $erros[] = 'B109: É obrigatório informar OrigemDestino (município, CEP ou coordenadas) para carga lotação/fracionada.';
            } else {
                self::origemDestino($r, $erros);
            }

            // DadosCarga obrigatório (B62/B69/B18).
            if ($r->dadosCarga === null) {
                $erros[] = 'B62: DadosCarga é obrigatório para carga lotação/fracionada.';
            } else {
                if ($r->dadosCarga->codigoNaturezaCarga === null || (string) $r->dadosCarga->codigoNaturezaCarga === '') {
                    $erros[] = 'B69: Natureza da carga (CodigoNaturezaCarga) é obrigatória para o tipo de operação informado.';
                }
                $peso = $r->dadosCarga->pesoCarga !== null ? (float) $r->dadosCarga->pesoCarga : 0.0;
                if ($peso <= 0.0) {
                    $erros[] = 'B18: Peso da carga deve ser maior que 0 e menor que 9999999.99.';
                } elseif ($peso >= 9999999.99) {
                    $erros[] = 'B18: Peso da carga deve ser maior que 0 e menor que 9999999.99.';
                }
            }

            // InfIndicadoresOperacionais obrigatório para carga lotação (tipo 1).
            if ($r->tipoOperacao === TipoOperacao::CargaLotacao && $r->indicadores === null) {
                $erros[] = 'B1: InfIndicadoresOperacionais é obrigatório para operações de carga lotação (tipoOperacao = 1).';
            }
        }

        if ($fracionada) {
            // B67: contratantes da carga fracionada obrigatórios.
            $contratantes = $r->dadosCarga?->contratantesCargaFracionada ?? [];
            if ($contratantes === []) {
                $erros[] = 'B67: ContratantesCargaFracionada é obrigatório no serviço de declaração para carga fracionada.';
            }
            // B119: cada contratante da carga fracionada deve diferir do contratante da operação.
            if (in_array($r->cpfCnpjContratante, $contratantes, true)) {
                $erros[] = 'B119: O(s) contratante(s) da carga fracionada não pode(m) ser igual(is) ao contratante da operação de transporte.';
            }
        }

        if ($tac) {
            // B62: para TAC-Agregado, estes campos NÃO devem ser informados na declaração.
            if ($r->cpfCnpjDestinatario !== null) {
                $erros[] = 'B62: Não é permitido informar CpfCnpjDestinatario na declaração de TAC-Agregado.';
            }
            if ($r->origensDestinos !== []) {
                $erros[] = 'B62: Não é permitido informar OrigemDestino na declaração de TAC-Agregado.';
            }
            if ($r->dadosCarga !== null) {
                $erros[] = 'B62: Não é permitido informar DadosCarga na declaração de TAC-Agregado.';
            }
            if ($r->indicadores !== null) {
                $erros[] = 'B62: Não é permitido informar InfIndicadoresOperacionais na declaração de TAC-Agregado.';
            }
        }
    }

    /**
     * @param  list<string>  $erros
     */
    private static function origemDestino(Req $r, array &$erros): void
    {
        foreach ($r->origensDestinos as $i => $od) {
            $tipoOrigem = $od->origem->tipoLocalizacao();
            $tipoDestino = $od->destino->tipoLocalizacao();

            if ($od->origem->coordenadasIncompletas() || $od->destino->coordenadasIncompletas()) {
                $erros[] = "B110: Latitude e longitude devem ser informadas em conjunto (par OrigemDestino #{$i}).";
            }
            if ($tipoOrigem === null || $tipoDestino === null) {
                $erros[] = "B109: Deve ser informada ao menos uma forma de localização para origem e destino (par #{$i}).";
            } elseif ($tipoOrigem !== $tipoDestino) {
                $erros[] = "B111: O tipo de localização informado para origem deve ser o mesmo informado para destino (par #{$i}).";
            }

            // B82: distância obrigatória e > 0 para carga lotação/fracionada.
            $dist = $od->distanciaPercorrida !== null ? (int) $od->distanciaPercorrida : 0;
            if ($dist <= 0) {
                $erros[] = "B82: DistanciaPercorrida deve ser maior que zero (par OrigemDestino #{$i}).";
            }
        }
    }

    /**
     * @param  list<string>  $erros
     */
    private static function pagamentos(Req $r, array &$erros): void
    {
        foreach ($r->pagamentos as $i => $pag) {
            self::camposPorTipoPagamento($pag, $i, $erros);
            self::parcelas($pag, $i, $erros);
        }
    }

    /**
     * @param  list<string>  $erros
     */
    private static function camposPorTipoPagamento(InfPagamento $pag, int $i, array &$erros): void
    {
        // CpfCnpjCreditado sempre obrigatório (recebedor do pagamento).
        if ($pag->cpfCnpjCreditado === null) {
            $erros[] = "B1: CpfCnpjCreditado é obrigatório (InfPagamento #{$i}).";
        }

        if ($pag->tipoPagamento->exigeDadosBancarios()) {
            // B92: instituição/agência/conta obrigatórios para tipos 1..4.
            if ($pag->codigoInstituicaoFinanceira === null) {
                $erros[] = "B92: CodigoInstituicaoFinanceira é obrigatório para o tipo de pagamento informado (InfPagamento #{$i}).";
            }
            if ($pag->tipoPagamento !== TipoPagamento::CartaoPrePago && ($pag->numeroAgencia === null || $pag->numeroAgencia === '')) {
                $erros[] = "B92: NumeroAgencia é obrigatório para o tipo de pagamento informado (InfPagamento #{$i}).";
            }
            if ($pag->numeroConta === null || $pag->numeroConta === '') {
                $erros[] = "B92: NumeroConta é obrigatório para o tipo de pagamento informado (InfPagamento #{$i}).";
            }
            // B99: campos de Pix não devem ser informados.
            if ($pag->chavePix !== null || $pag->identificadorPix !== null) {
                $erros[] = "B99: Campos de Pix não devem ser informados para o tipo de pagamento selecionado (InfPagamento #{$i}).";
            }
        } elseif ($pag->tipoPagamento->exigeDadosPix()) {
            // B92: ChavePix/IdentificadorPix obrigatórios para Pix.
            if ($pag->chavePix === null || $pag->chavePix === '') {
                $erros[] = "B92: ChavePix é obrigatória para o tipo de pagamento Pix (InfPagamento #{$i}).";
            }
            if ($pag->identificadorPix === null || $pag->identificadorPix === '') {
                $erros[] = "B92: IdentificadorPix é obrigatório para o tipo de pagamento Pix (InfPagamento #{$i}).";
            }
            // B99: campos bancários não devem ser informados.
            if ($pag->codigoInstituicaoFinanceira !== null || $pag->numeroAgencia !== null || $pag->numeroConta !== null) {
                $erros[] = "B99: Campos bancários não devem ser informados para pagamento via Pix (InfPagamento #{$i}).";
            }
        } else {
            // Outros (5): não é bancário nem Pix — nenhum campo bancário/Pix deve
            // ser informado (B99, manual itens 16.2-16.8).
            $temBancario = $pag->codigoInstituicaoFinanceira !== null
                || ($pag->numeroAgencia ?? '') !== ''
                || ($pag->numeroConta ?? '') !== '';
            $temPix = ($pag->chavePix ?? '') !== '' || ($pag->identificadorPix ?? '') !== '';
            if ($temBancario || $temPix) {
                $erros[] = "B99: Para o tipo de pagamento Outros não devem ser informados campos bancários ou de Pix (InfPagamento #{$i}).";
            }
        }
    }

    /**
     * @param  list<string>  $erros
     */
    private static function parcelas(InfPagamento $pag, int $i, array &$erros): void
    {
        $temParcelas = $pag->parcelas !== [];

        // B105/B106: parcelas obrigatórias quando a prazo; proibidas à vista.
        if ($pag->indPagamento->exigeParcelas() && ! $temParcelas) {
            $erros[] = "B105: Os campos de parcelamento devem ser informados quando o pagamento for a prazo (InfPagamento #{$i}).";
        }
        if (! $pag->indPagamento->exigeParcelas() && $temParcelas) {
            $erros[] = "B106: Os campos de parcelamento não devem ser informados quando o pagamento for à vista (InfPagamento #{$i}).";
        }
    }

    private static function valorFrete(Req $r): float
    {
        return is_string($r->valorFrete)
            ? (float) str_replace(',', '.', $r->valorFrete)
            : (float) $r->valorFrete;
    }
}
