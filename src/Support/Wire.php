<?php

declare(strict_types=1);

namespace Rumbleh\CiotAntt\Support;

/**
 * Nomes de campos do JSON ("wire format") que aparecem de forma INCONSISTENTE
 * entre as tabelas de leiaute e os exemplos JSON do manual DCS PEF v1.1.
 *
 * Centralizamos aqui os casos ambíguos para que, caso a homologação da ANTT
 * exija a outra grafia, a correção seja de UMA linha — sem caçar literais pelo
 * código. Por padrão usamos a grafia do EXEMPLO JSON do manual (o "leiaute da
 * mensagem"), que é o que de fato trafega.
 *
 * Referências (página do manual):
 *  - ContratantesCargFrac: tabela (pág. 22) diz "ContratantesCargaFrac";
 *    exemplo JSON (pág. 26) diz "ContratantesCargFrac".
 *  - RNTRC (do veículo): tabela (pág. 19) diz "RNTRCVeiculo"; exemplo JSON
 *    (pág. 25) diz "RNTRC".
 *  - PesoTotalCarga (encerramento): tabela (pág. 37) diz "PesoCarga"; exemplo
 *    JSON (pág. 38) diz "PesoTotalCarga".
 */
final class Wire
{
    /** Lista de CPF/CNPJ contratantes na carga fracionada (DadosCarga). */
    public const CONTRATANTES_CARGA_FRAC = 'ContratantesCargFrac';

    /** RNTRC do veículo dentro do array Veiculos da declaração. */
    public const VEICULO_RNTRC = 'RNTRC';

    /** Peso total da carga no serviço de encerramento (TAC-Agregado). */
    public const ENCERRAMENTO_PESO_TOTAL = 'PesoTotalCarga';
}
