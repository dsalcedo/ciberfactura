<?php
namespace Ciberfactura;

use CfdiComplemento;
use CfdiConcepto;
use CfdiEmisor;
use CfdiFactura;
use CfdiImpuesto;
use CfdiReceptor;
use CfdiRegimen;
use CfdiSucursal;

use Illuminate\Support\Facades\Config;

class Cfdi{
    public $xml;
    public $cadenaOriginal;
    public $sello;
    public $key;
    public $certificado;
    public $noCertificado;
    private $tmp_file;
    private $cfdi;

    protected $version = "3.2";

    public function __construct(){
        if(Config::get('packages/raalveco/ciberfactura/config.production')){
            $url_cer = app_path()."/config/packages/raalveco/ciberfactura/".Config::get('packages/raalveco/ciberfactura/config.cer');
            $url_key = app_path()."/config/packages/raalveco/ciberfactura/".Config::get('packages/raalveco/ciberfactura/config.key');
            $clave_privada = Config::get('packages/raalveco/ciberfactura/config.clave_privada');
        }
        else{
            $url_cer = app_path()."/config/packages/raalveco/ciberfactura/goya780416gm0_1210221537s.cer";
            $url_key = app_path()."/config/packages/raalveco/ciberfactura/goya780416gm0_1210221537s.key";
            $clave_privada = "12345678a";
        }

        $this->noCertificado = CfdiBase::getSerialFromCertificate( $url_cer );
        $this->certificado = CfdiBase::getCertificate( $url_cer, false );
        $this->key = CfdiBase::getPrivateKey($url_key, $clave_privada);
    }

    public function cargarFactura($cfdi){
        $this->cfdi = $cfdi;

        $this->xml = new CfdiGenerator($this->cfdi);

        if(!file_exists(public_path()."/temp")){
            mkdir(public_path()."/temp");
        }

        $this->tmp_file = public_path()."/temp/".sha1(date("Y-m-d H:i:s".rand(0,100000))).".xml";
        $this->xml->saveFile($this->tmp_file);

        $this->cadenaOriginal = CfdiBase::getOriginalString($this->tmp_file, app_path().'/config/packages/raalveco/ciberfactura/cadenaoriginal_3_2.xslt');

        $this->cfdi->noCertificado = $this->noCertificado;
        $this->cfdi->certificado = $this->certificado;
        $this->cfdi->save();
    }

    public function sellar(){
        $this->sello = CfdiBase::signData($this->key, $this->cadenaOriginal);

        $this->cfdi->sello = $this->sello;
        $this->cfdi->save();

        return $this->sello;
    }

    public function timbrar($url = false){
        if(!$url){
            $response = CfdiTimbrador::timbrado($this->tmp_file);
        }
        else{
            $response = CfdiTimbrador::timbrado($url);
        }

        if($response["obtenerTimbradoResult"]["timbre"]["!esValido"] == true){
            $timbre = $response["obtenerTimbradoResult"]["timbre"]["TimbreFiscalDigital"];

            $this->xml->timbrar($timbre);
        }

        return $response;
    }

    public function addendar(){
        $this->xml->addendar();
    }

    public function guardar($url = false){
        if(!$url){
            $this->xml->saveFile($this->tmp_file);
        }
        else{
            $this->xml->saveFile($url);
        }
    }

    public function cadenaOriginal(){
        $cadena = $this->cadenaOriginal;

        $cadena = "|".substr($cadena,3);
        $cadena = substr($cadena,0,strlen($cadena)-4)."||";

        return $cadena;
    }
}