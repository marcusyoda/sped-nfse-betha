<?php
error_reporting(E_ALL);
ini_set('display_errors', 'On');
require_once '../bootstrap.php';

use NFePHP\Common\Certificate;
use NFePHP\NFSeBetha\Tools;
use NFePHP\NFSeBetha\Common\Soap\SoapFake;
use NFePHP\NFSeBetha\Common\FakePretty;

try {
    
    $config = [
        'cnpj' => '99999999000191',
        'im' => '1733160024',
        'cmun' => '4204608', //ira determinar as urls e outros dados
        'razao' => 'Empresa Test Ltda',
        'tpamb' => 2
    ];

    $configJson = json_encode($config);

    $content = file_get_contents('expired_certificate.pfx');
    $password = 'associacao';
    $cert = Certificate::readPfx($content, $password);
    
    $soap = new SoapFake();
    $soap->disableCertValidation(true);
    
    $tools = new Tools($configJson, $cert);
    $tools->loadSoapClass($soap);

    $params = new \stdClass();
    $params->numero = '100';
    $params->pagina = 1;
    
    $params->data_emissao_ini = '2019-12-01';
    $params->data_emissao_fim = '2019-12-31';
    
    $params->competencia_ini = '2019-12-01';
    $params->competencia_fim = '2019-12-01';
    
    $params->prestador = new \stdClass();
    $params->prestador->cnpj = "12345678901234";
    $params->prestador->im = "123456";

    $params->intermediario = new \stdClass();
    $params->intermediario->cnpj = "12345678901234";
    $params->intermediario->im = "123456";
    
    $response = $tools->consultarNfseTomado($params);
    
    echo FakePretty::prettyPrint($response, '');
 
} catch (\Exception $e) {
    echo $e->getMessage();
}
