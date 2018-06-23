<?php

class NavInvoice{
    private $tokenUrl = 'https://api-test.onlineszamla.nav.gov.hu/invoiceService/tokenExchange';

    private $user;
    private $password;
    private $company;
    private $taxNumber;
    private $requestIdPrefix = 'TEST';
    private $xmlKey;
    private $xmlChangeKey;
    /*
        A softwareId az adott számlázó program azonosítására szolgáló 18 elemű karaktersorozat. A softwareId képzésére vonatkozó ajánlás: az azonosító első két karaktere a szoftvert fejlesztő cég országkódja ISO 3166 alpha-2 szabvány szerint. Az azonosító további karakterei a fejlesztő cég adó törzsszáma, 4-9 számjegyen. Az azonosító további karaktereit a Gyártó saját maga képezi meg úgy, hogy az azonosító egyedisége biztosított legyen. A Gyártó dönthet arról, hogy egy adott szoftvertermék különböző verzióihoz, vagy a különböző ügyfeleinél működő példányokhoz külön-külön azonosítót képez-e. Ugyanazon szoftververzió ugyanazon példányának az adatszolgáltatás során ugyanazt a softwareId-t kell közölnie magáról.
    */
    private $softwareId = 'HU99999999AAAAA999';
    private $softwareName = 'TEST';
    private $softwareOperation = 'LOCAL_SOFTWARE';
    private $softwareMainVersion = '1.0';
    private $softwareDevName = 'Test Company';
    private $softwareDevContact = 'test@test.hu';
    private $softwareDevCountryCode = 'HU';
    private $softwareDevTaxNumber = '99999999-2-41';

    private $timeStamp;
    private $timeStampFormatted;
    private $signature;
    private $requestId;
    private $lastResult;
    private $token;

    private $log = true;
    private $logFile;


    public function __construct()
    {
        $date_utc = new \DateTime("now");
        $this->timeStampFormatted = $date_utc->format("Y-m-d\TH:i:s.000\Z");
        $this->timeStamp = $date_utc;
        $this->logFile = fopen("log".date('Y-m-d').".txt", "a+");
    }

    public function generateRequestId($customId = null){
        $this->requestId = (empty($customId)?$this->requestIdPrefix.time():$customId);
    }

    public function setSignature($invoice = null){
        $this->signature = strtoupper(hash('sha512',$this->requestId. $this->timeStamp->format('YmdHis'). $this->xmlKey. (!empty($invoice)?$invoice:null)));
    }

    public function generateXmlHeader(){
        return [
            'header' => [
                'requestId' => $this->requestId,
                'timestamp' => $this->timeStampFormatted,
                'requestVersion' => '1.0',
                'headerVersion' => '1.0'
            ],
            'user' => [
                'login' => $this->user,
                'passwordHash' => $this->password,
                'taxNumber' => $this->taxNumber,
                'requestSignature' => $this->signature
            ],
            'software' => [
                'softwareId' => $this->softwareId,
                'softwareName' => $this->softwareName,
                'softwareOperation' => $this->softwareOperation,
                'softwareMainVersion' => $this->softwareMainVersion,
                'softwareDevName' => $this->softwareDevName,
                'softwareDevContact' => $this->softwareDevContact,
                'softwareDevCountryCode' => $this->softwareDevCountryCode,
                'softwareDevTaxNumber' => $this->softwareDevTaxNumber
            ]
        ];
    }

    public function getNewToken(){
        $xml = new SimpleXMLElement('<TokenExchangeRequest/>');
        $xml->addAttribute('xmlns', 'http://schemas.nav.gov.hu/OSA/1.0/api');
        $this->arrayToXml($this->generateXmlHeader(),$xml);
        $xmlString = $xml->asXML();
        var_dump($xmlString);
        $return = $this->callUrl($this->tokenUrl,$xmlString);
        $returnXml = simplexml_load_string($return);
        if(empty($returnXml->encodedExchangeToken)){
            $this->writeLog('Token request failed');
            throw new Exception('Error! Token Missing;');
        }
        $this->token = $returnXml->encodedExchangeToken;

    }

    public function callUrl($url, $xml){
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_VERBOSE, 1);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_URL, $url );
        curl_setopt($ch, CURLOPT_POST, true );
        curl_setopt($ch, CURLOPT_HTTPHEADER,
            array('Content-Type: application/xml',
                'Accept: application/xml'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true );
        curl_setopt($ch, CURLOPT_POSTFIELDS,$xml);
        $result = $this->lastResult = curl_exec($ch);
        curl_close($ch);
        return $result;

    }


    private function arrayToXml( $data, &$xml_data ) {
        foreach( $data as $key => $value ) {
            if( is_numeric($key) ){
                $key = 'item'.$key; //dealing with <0/>..<n/> issues
            }
            if( is_array($value) ) {
                $subnode = $xml_data->addChild($key);
                $this->arrayToXml($value, $subnode);
            } else {
                $xml_data->addChild("$key",htmlspecialchars("$value"));
            }
        }
    }

    public function writeLog($logText){
        fwrite($this->logFile, date('Y-m-d H:i:s').':'. $logText);
    }

    /**
     * @param mixed $user
     * @return NavInvoice
     */
    public function setUser($user)
    {
        $this->user = $user;
        return $this;
    }

    /**
     * @param mixed $password
     * @return NavInvoice
     */
    public function setPassword($password)
    {
        $this->password = strtoupper(hash('sha512',$password));
        return $this;
    }

    /**
     * @param mixed $company
     * @return NavInvoice
     */
    public function setCompany($company)
    {
        $this->company = $company;
        return $this;
    }

    /**
     * @param mixed $taxNumber
     * @return NavInvoice
     */
    public function setTaxNumber($taxNumber)
    {
        $this->taxNumber = $taxNumber;
        return $this;
    }

    /**
     * @param mixed $xmlKey
     * @return NavInvoice
     */
    public function setXmlKey($xmlKey)
    {
        $this->xmlKey = $xmlKey;
        return $this;
    }

    /**
     * @param mixed $xmlChangeKey
     * @return NavInvoice
     */
    public function setXmlChangeKey($xmlChangeKey)
    {
        $this->xmlChangeKey = $xmlChangeKey;
        return $this;
    }

    /**
     * @param string $softwareId
     * @return NavInvoice
     */
    public function setSoftwareId($softwareId)
    {
        $this->softwareId = $softwareId;
        return $this;
    }

    /**
     * @param string $softwareName
     * @return NavInvoice
     */
    public function setSoftwareName($softwareName)
    {
        $this->softwareName = $softwareName;
        return $this;
    }

    /**
     * @param string $softwareOperation
     * @return NavInvoice
     */
    public function setSoftwareOperation($softwareOperation)
    {
        $this->softwareOperation = $softwareOperation;
        return $this;
    }

    /**
     * @param string $softwareMainVersion
     * @return NavInvoice
     */
    public function setSoftwareMainVersion($softwareMainVersion)
    {
        $this->softwareMainVersion = $softwareMainVersion;
        return $this;
    }

    /**
     * @param string $softwareDevName
     * @return NavInvoice
     */
    public function setSoftwareDevName($softwareDevName)
    {
        $this->softwareDevName = $softwareDevName;
        return $this;
    }

    /**
     * @param string $softwareDevContact
     * @return NavInvoice
     */
    public function setSoftwareDevContact($softwareDevContact)
    {
        $this->softwareDevContact = $softwareDevContact;
        return $this;
    }

    /**
     * @param string $softwareDevCountryCode
     * @return NavInvoice
     */
    public function setSoftwareDevCountryCode($softwareDevCountryCode)
    {
        $this->softwareDevCountryCode = $softwareDevCountryCode;
        return $this;
    }

    /**
     * @param string $softwareDevTaxNumber
     * @return NavInvoice
     */
    public function setSoftwareDevTaxNumber($softwareDevTaxNumber)
    {
        $this->softwareDevTaxNumber = $softwareDevTaxNumber;
        return $this;
    }

    /**
     * @param mixed $requestIdPrefix
     * @return NavInvoice
     */
    public function setRequestIdPrefix($requestIdPrefix)
    {
        $this->requestIdPrefix = $requestIdPrefix;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getLastResult($asRaw = true)
    {
        if($asRaw){
            return $this->lastResult;
        }else{
            return simplexml_load_string($this->lastResult);
        }
    }

}