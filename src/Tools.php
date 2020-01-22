<?php

namespace NFePHP\NFSeBetha;

/**
 * Class for comunications with NFSe webserver in Nacional Standard
 *
 * @category  NFePHP
 * @package   NFePHP\NFSeBetha
 * @copyright NFePHP Copyright (c) 2020
 * @license   http://www.gnu.org/licenses/lgpl.txt LGPLv3+
 * @license   https://opensource.org/licenses/MIT MIT
 * @license   http://www.gnu.org/licenses/gpl.txt GPLv3+
 * @author    Roberto L. Machado <linux.rlm at gmail dot com>
 * @link      http://github.com/nfephp-org/sped-nfse-betha for the canonical source repository
 */

use NFePHP\NFSeBetha\Common\Tools as BaseTools;
use NFePHP\NFSeBetha\RpsInterface;
use NFePHP\Common\Certificate;
use NFePHP\Common\Validator;

class Tools extends BaseTools
{
    const CANCEL_ERRO_EMISSAO = 1;
    const CANCEL_SERVICO_NAO_CONCLUIDO = 2;
    const CANCEL_DUPLICIDADE = 4;
    
    protected $xsdpath;
    
    /**
     * Constructor
     * @param string $config
     * @param Certificate $cert
     */
    public function __construct($config, Certificate $cert)
    {
        parent::__construct($config, $cert);
        $path = realpath(
            __DIR__ . '/../storage/schemes'
        );
        $this->xsdpath = $path . '/nfse_v202.xsd';
    }
    
    /**
     * Solicita o cancelamento de NFSe (SINCRONO)
     * @param string $id
     * @param integer $numero
     * @param integer $codigo
     * @return string
     */
    public function cancelarNfse($id, $numero, $codigo = self::CANCEL_ERRO_EMISSAO)
    {
        $operation = 'CancelarNfse';
        $pedido = "<Pedido>"
            . "<InfPedidoCancelamento Id=\"$id\">"
            . "<IdentificacaoNfse>"
            . "<Numero>$numero</Numero>"
            . "<CpfCnpj>";
        if (!empty($this->config->cnpj)) {
            $pedido .= "<Cnpj>{$this->config->cnpj}</Cnpj>";
        } else {
            $pedido .= "<Cpf>{$this->config->cpf}</Cpf>";
        }    
        $pedido .= "</CpfCnpj>"
            . "<InscricaoMunicipal>{$this->config->im}</InscricaoMunicipal>"
            . "<CodigoMunicipio>{$this->config->cmun}</CodigoMunicipio>"
            . "</IdentificacaoNfse>"
            . "<CodigoCancelamento>$codigo</CodigoCancelamento>"
            . "</InfPedidoCancelamento>"
            . "</Pedido>";
        
        $signed = $this->sign($pedido, 'InfPedidoCancelamento', 'Id');
        $content = "<CancelarNfseEnvio xmlns=\"{$this->wsobj->msgns}\">"
            . $signed
            . "</CancelarNfseEnvio>";
        Validator::isValid($content, $this->xsdpath);
        return $this->send($content, $operation);
    }
    
    /**
     * Cancelamento com substituição por novo RPS
     * @param integer $numero_nfse_a_cancelar
     * @param integer $codigo 1-erro emissão 2-serviço não prestado 4-emissão em duplicidade
     * @param RpsInterface $novorps
     * @return string
     */
    public function substituirNfse($numero_nfse_a_cancelar, $codigo = self::CANCEL_ERRO_EMISSAO, RpsInterface $novorps)
    {
        $operation = "SubstituirNfse";
        $novorps->config($this->config);
        $rpssigned = $this->sign($novorps->render(), 'InfDeclaracaoPrestacaoServico', 'Id');
        $pedido = "<Pedido>"
	    . "<InfPedidoCancelamento Id=\"cancel\">"
	    . "<IdentificacaoNfse>"
            . "<Numero>{$numero_nfse_a_cancelar}</Numero>"
	    . "<CpfCnpj>"
	    . "<Cnpj>{$this->config->cnpj}</Cnpj>"
	    . "</CpfCnpj>"
	    . "<InscricaoMunicipal>{$this->config->im}</InscricaoMunicipal>"
	    . "<CodigoMunicipio>{$this->config->cmun}</CodigoMunicipio>"
	    . "</IdentificacaoNfse>"
	    . "<CodigoCancelamento>2</CodigoCancelamento>"
	    . "</InfPedidoCancelamento>"
	    . "</Pedido>";
        $pedidosigned = $this->sign($pedido, 'InfPedidoCancelamento', 'Id');      
        
        $message = "<SubstituirNfseEnvio xmlns=\"{$this->wsobj->msgns}\">"
            . "<SubstituicaoNfse Id=\"subst\">"
            . $pedidosigned
            . $rpssigned
	    . "</SubstituicaoNfse>"
            ."</SubstituirNfseEnvio>";
        
        $content = $this->sign($message, 'SubstituicaoNfse', 'Id');
        Validator::isValid($content, $this->xsdpath);
        return $this->send($content, $operation);
        
    }
    
    /**
     * Consulta Lote RPS (SINCRONO) após envio com recepcionarLoteRps() (ASSINCRONO)
     * complemento do processo de envio assincono.
     * Que deve ser usado quando temos mais de um RPS sendo enviado
     * por vez.
     * @param string $protocolo
     * @return string
     */
    public function consultarLoteRps($protocolo)
    {
        $operation = 'ConsultarLoteRps';
        $content = "<ConsultarLoteRpsEnvio xmlns=\"{$this->wsobj->msgns}\">"
            . $this->prestador
            . "<Protocolo>{$protocolo}</Protocolo>"
            . "</ConsultarLoteRpsEnvio>";
        Validator::isValid($content, $this->xsdpath);
        return $this->send($content, $operation);
    }
    
    /**
     * Consulta NFSe emitidas por serviços prestados
     * @param \stdClass $params
     * @return string
     */
    public function consultarNfsePrestado($params)
    {
        $operation = 'ConsultarNfseServicoPrestado';
        if (!empty($params->pagina)) {
            $params->pagina = 1;
        }
        $content = "<ConsultarNfseServicoPrestadoEnvio xmlns=\"{$this->wsobj->msgns}\">"
            . $this->prestador;
        if (!empty($params->numero)) {
            $content .= "<NumeroNfse>{$params->numero}</NumeroNfse>";
        }
        if (!empty($params->data_emissao_ini) && !empty($params->data_emissao_fim)) {
            $content .= "<PeriodoEmissao>"
		. "<DataInicial>{$params->data_emissao_ini}</DataInicial>"
		. "<DataFinal>{$params->data_emissao_fim}</DataFinal>"
                . "</PeriodoEmissao>";
        } else {
            if (!empty($params->competencia_ini) && !empty($params->competencia_fim)) {
                $content .= "<PeriodoCompetencia>"
                    . "<DataInicial>{$params->competencia_ini}</DataInicial>"
                    . "<DataFinal>{$params->competencia_fim}</DataFinal>"
                    . "</PeriodoCompetencia>";
            }
        }    
        if (!empty($params->tomador)) {
            $content .= "<Tomador>"
		. "<CpfCnpj>";
            if (!empty($params->tomador->cnpj)) {
		$content .= "<Cnpj>{$params->tomador->cnpj}</Cnpj>";
            } else {
                $content .= "<Cpf>{$params->tomador->cpf}</Cpf>";
            }    
            $content .= "</CpfCnpj>"
		. "<InscricaoMunicipal>{$params->tomador->im}</InscricaoMunicipal>"
                . "</Tomador>";
        }
        if (!empty($params->intermediario)) {
            $content .= "<Intermediario>"
		. "<CpfCnpj>";
            if (!empty($params->intermediario->cnpj)) {
		$content .= "<Cnpj>{$params->intermediario->cnpj}</Cnpj>";
            } else {
                $content .= "<Cpf>{$params->intermediario->cpf}</Cpf>";
            }    
            $content .= "</CpfCnpj>"
		. "<InscricaoMunicipal>{$params->intermediario->im}</InscricaoMunicipal>"
                . "</Intermediario>";
        }    
	$content .= "<Pagina>{$params->pagina}</Pagina>"
            . "</ConsultarNfseServicoPrestadoEnvio>";
        Validator::isValid($content, $this->xsdpath);
        return $this->send($content, $operation);
    }
    
    /**
     * Consulta dos serviços tomados
     * @param \stdClass $params
     * @return string
     */
    public function consultarNfseTomado($params)
    {
        $operation = 'ConsultarNfseServicoTomado';
        if (!empty($params->pagina)) {
            $params->pagina = 1;
        }
        
        $content  = "<ConsultarNfseServicoTomadoEnvio xmlns=\"{$this->wsobj->msgns}\">"
            . "<Consulente>"
            . "<CpfCnpj>";
        if (!empty($this->config->cnpj)) {
            $content .= "<Cnpj>{$this->config->cnpj}</Cnpj>";
        } else {
            $content .= "<Cpf>{$this->config->cpf}</Cpf>";
        }    
        $content .= "</CpfCnpj>"
            . "<InscricaoMunicipal>{$this->config->im}</InscricaoMunicipal>"
            . "</Consulente>";
        if (!empty($params->numero)) {
            $content .= "<NumeroNfse>{$params->numero}</NumeroNfse>";
        }    
        if (!empty($params->data_emissao_ini) && !empty($params->data_emissao_fim)) {
            $content .= "<PeriodoEmissao>"
		. "<DataInicial>{$params->data_emissao_ini}</DataInicial>"
		. "<DataFinal>{$params->data_emissao_fim}</DataFinal>"
                . "</PeriodoEmissao>";
        } else {
            if (!empty($params->competencia_ini) && !empty($params->competencia_fim)) {
                $content .= "<PeriodoCompetencia>"
                    . "<DataInicial>{$params->competencia_ini}</DataInicial>"
                    . "<DataFinal>{$params->competencia_fim}</DataFinal>"
                    . "</PeriodoCompetencia>";
            }
        }     
	if (!empty($params->prestador)) {
            $content .= "<Prestador>"
		. "<CpfCnpj>";
            if (!empty($params->prestador->cnpj)) {
		$content .= "<Cnpj>{$params->prestador->cnpj}</Cnpj>";
            } else {
                $content .= "<Cpf>{$params->prestador->cpf}</Cpf>";
            }    
            $content .= "</CpfCnpj>"
		. "<InscricaoMunicipal>{$params->prestador->im}</InscricaoMunicipal>"
                . "</Prestador>";
        }
        if (!empty($params->intermediario)) {
            $content .= "<Intermediario>"
		. "<CpfCnpj>";
            if (!empty($params->intermediario->cnpj)) {
		$content .= "<Cnpj>{$params->intermediario->cnpj}</Cnpj>";
            } else {
                $content .= "<Cpf>{$params->intermediario->cpf}</Cpf>";
            }    
            $content .= "</CpfCnpj>"
		. "<InscricaoMunicipal>{$params->intermediario->im}</InscricaoMunicipal>"
                . "</Intermediario>";
        }    
	$content .= "<Pagina>{$params->pagina}</Pagina>"
            . "</ConsultarNfseServicoTomadoEnvio>";
	Validator::isValid($content, $this->xsdpath);
        return $this->send($content, $operation);
    }
    
    /**
     * Consulta NFSe emitidas por faixa de numeros (SINCRONO)
     * @param integer $numero_ini
     * @param integer $numero_fim
     * @param integer $pagina
     * @return string
     */
    public function consultarNfseFaixa($numero_ini, $numero_fim, $pagina = 1)
    {
        $operation = 'ConsultarNfseFaixa';
        $content = "<ConsultarNfseFaixaEnvio xmlns=\"{$this->wsobj->msgns}\">"
            . $this->prestador
	    . "<Faixa>"
	    . "<NumeroNfseInicial>{$numero_ini}</NumeroNfseInicial>"
            . "<NumeroNfseFinal>{$numero_fim}</NumeroNfseFinal>"
            . "</Faixa>"
            . "<Pagina>{$pagina}</Pagina>"
            . "</ConsultarNfseFaixaEnvio>";
        Validator::isValid($content, $this->xsdpath);
        return $this->send($content, $operation);
    }
    
    /**
     * Consulta NFSe por RPS (SINCRONO)
     * @param integer $numero
     * @param string $serie
     * @param integer $tipo
     * @return string
     */
    public function consultarNfseRps($numero, $serie, $tipo)
    {
        $operation = "ConsultarNfseRps";
        $content = "<ConsultarNfseRpsEnvio xmlns=\"{$this->wsobj->msgns}\">"
	. "<IdentificacaoRps>"
	. "<Numero>{$numero}</Numero>"
	. "<Serie>{$serie}</Serie>"
	. "<Tipo>{$tipo}</Tipo>"
	. "</IdentificacaoRps>"
	. $this->prestador
        . "</ConsultarNfseRpsEnvio>";
        Validator::isValid($content, $this->xsdpath);
        return $this->send($content, $operation);
    }
    
    /**
     * Envia LOTE de RPS para emissão de NFSe (SINCRONO)
     * @param array $arps Array contendo de 1 a 2 RPS::class
     * @param string $lote Número do lote de envio
     * @return string
     * @throws \Exception
     */
    public function recepcionarLoteRps($arps, $lote)
    {
        $operation = 'RecepcionarLoteRpsSincrono';
        $no_of_rps_in_lot = count($arps);
        if ($no_of_rps_in_lot > 2) {
            throw new \Exception('O limite é de 2 RPS por lote enviado em modo sincrono.');
        }
        $content = '';
        foreach ($arps as $rps) {
            $rps->config($this->config);
            $xmlsigned = $this->sign($rps->render(), 'InfDeclaracaoPrestacaoServico', 'Id');
            $content .= $xmlsigned;
        }
        $contentmsg = "<EnviarLoteRpsSincronoEnvio xmlns=\"{$this->wsobj->msgns}\">"
            . "<LoteRps Id=\"lote{$lote}\" versao=\"{$this->wsobj->version}\">"
            . "<NumeroLote>$lote</NumeroLote>"
            . "<CpfCnpj>"
            . "<Cnpj>" . $this->config->cnpj . "</Cnpj>"
            . "</CpfCnpj>"
            . "<InscricaoMunicipal>" . $this->config->im . "</InscricaoMunicipal>"
            . "<QuantidadeRps>$no_of_rps_in_lot</QuantidadeRps>"
            . "<ListaRps>"
            . $content
            . "</ListaRps>"
            . "</LoteRps>"
            . "</EnviarLoteRpsSincronoEnvio>";
        $content = $this->sign($contentmsg, 'LoteRps', 'Id');
        Validator::isValid($content, $this->xsdpath);
        return $this->send($content, $operation);
    }
    
    /**
     * Solicita a emissão de uma NFSe de forma SINCRONA
     * @param RpsInterface $rps
     * @param string $lote Identificação do lote
     * @return string
     */
    public function gerarNfse(RpsInterface $rps)
    {
        $operation = "GerarNfse";
        $rps->config($this->config);
        $xmlsigned = $this->sign($rps->render(), 'InfDeclaracaoPrestacaoServico', 'Id');
        $content = "<GerarNfseEnvio xmlns=\"{$this->wsobj->msgns}\">"
            . $xmlsigned
            . "</GerarNfseEnvio>";
        Validator::isValid($content, $this->xsdpath);
        return $this->send($content, $operation);
    }
}
