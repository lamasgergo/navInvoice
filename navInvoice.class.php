<?php

class NavInvoice
{

    private $tokenUrl = 'https://api.onlineszamla.nav.gov.hu/invoiceService/v2/tokenExchange';
    private $invoiceUrl = 'https://api.onlineszamla.nav.gov.hu/invoiceService/v2/manageInvoice';
    private $invoiceQueryUrl = 'https://api.onlineszamla.nav.gov.hu/invoiceService/v2/queryTransactionStatus';
    
    private $tokenUrlTest = 'https://api-test.onlineszamla.nav.gov.hu/invoiceService/v2/tokenExchange';
    private $invoiceUrlTest = 'https://api-test.onlineszamla.nav.gov.hu/invoiceService/v2/manageInvoice';
    private $invoiceQueryUrlTest = 'https://api-test.onlineszamla.nav.gov.hu/invoiceService/v2/queryTransactionStatus';

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
    private $logDir = '';
    private $logFile;

    private $invoiceSupplier = [];
    private $invoiceCustomer = [];
    private $invoiceData = [];
    private $invoiceLines = [];
    private $invoiceSummary = [];
    private $invoiceNumber = '';
    private $invoiceIssueDate = '';

    private $requestCounter = 0;

    private $errors = [];


    public function __construct()
    {
        $date_utc = new \DateTime("now");
        $this->timeStampFormatted = $date_utc->format("Y-m-d\TH:i:s.000\Z");
        $this->timeStamp = $date_utc;
        if ($this->log) $this->logFile = fopen($this->logDir."log" . date('Y-m-d') . ".txt", "a+");
        $this->writeLog('NAV script started');
        $this->invoiceSummary = [
            'invoiceNetAmount' => 0,
            'invoiceNetAmountHUF' => 0,
            'invoiceVatAmount' => 0,
            'invoiceVatAmountHUF' => 0,
            'invoiceGrossAmount' => 0,
            'invoiceGrossAmountHUF' => 0,
        ];
    }

    public function generateRequestId($customId = null)
    {
        $this->requestId = (empty($customId) ? $this->requestIdPrefix . time(). $this->requestCounter : $customId);
        $this->requestCounter++;
    }
    
    public function setTestMode()
    {
        $this->tokenUrl = $this->tokenUrlTest;
        $this->invoiceUrl = $this->invoiceUrlTest;
        $this->invoiceQueryUrl = $this->invoiceQueryUrlTest;
        return $this;
    }    

    public function setSignature($invoice = null)
    {
        $this->signature = strtoupper($this->sha3($this->requestId . $this->timeStamp->format('YmdHis') . $this->xmlKey . (!empty($invoice) ? $invoice : null)));
    }
    
    /*
    * Standalone SHA3 generator for older PHP
    * You can use hash('sha3-512', $string) on  >PHP7.1
    */
    public function sha3($string){
        $sponge = SHA3::init (SHA3::SHA3_512);
        $sponge->absorb ($string);
        $hash = $sponge->squeeze ();  
        return bin2hex($hash);   
    }

    public function generateXmlHeader()
    {
        return [
            'header' => [
                'requestId' => $this->requestId,
                'timestamp' => $this->timeStampFormatted,
                'requestVersion' => '2.0',
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

    public function getNewToken()
    {
        $xml = new SimpleXMLElement('<TokenExchangeRequest/>');
        $xml->addAttribute('xmlns', 'http://schemas.nav.gov.hu/OSA/2.0/api');
        $this->arrayToXml($this->generateXmlHeader(), $xml);
        $xmlString = $xml->asXML();

        $return = $this->callUrl($this->tokenUrl, $xmlString);
        $returnXml = simplexml_load_string($return);
        if (empty($returnXml->encodedExchangeToken)) {
            $this->writeLog('Token request failed'.json_encode($return));
            throw new Exception('Error! Token Missing;'.$return);
        }

        $aes = new AES((string)$returnXml->encodedExchangeToken, $this->xmlChangeKey, 128);
        $this->token = str_replace(chr('0x10'),'',$aes->decrypt());

    }

    /**
     * @param string $taxNumber
     * @param string $companyName
     * @param string $account
     * @param string $postalCode
     * @param string $city
     * @param string $streetName
     * @param string $publicPlaceCategory
     * @param string $number
     * @param string $floor
     * @param string $door
     * @param string $countyCode
     */
    public function addSupplierData(
        $taxNumber = '',
        $companyName = '',
        $account = false,
        $postalCode = '',
        $city = '',
        $streetName = '',
        $publicPlaceCategory = '',
        $number = false,
        $floor = false,
        $door = false,
        $countyCode = 'HU'
    )
    {
        $this->invoiceSupplier = [
            'supplierTaxNumber' => [
                'taxpayerId' => substr($taxNumber, 0, 8),
                'vatCode' => substr($taxNumber, 9, 1),
                'countyCode' => substr($taxNumber, 11, 2),
            ],
            'supplierName' => $companyName,
            'supplierAddress' => [
                'detailedAddress' => [
                    'countryCode' => $countyCode,
                    'postalCode' => $postalCode,
                    'city' => $city,
                    'streetName' => $streetName,
                    'publicPlaceCategory' => $publicPlaceCategory,
                    'number' => $number,
                    'floor' => $floor,
                    'door' => $door,
                ]
            ],
            'supplierBankAccountNumber' => $account
        ];

    }

    /**
     * @param string $taxNumber
     * @param string $companyName
     * @param string $account
     * @param string $postalCode
     * @param string $city
     * @param string $streetName
     * @param string $publicPlaceCategory
     * @param string $number
     * @param string $floor
     * @param string $door
     * @param string $countyCode
     */
    public function addCustomerData(
        $taxNumber = '',
        $companyName = '',
        $account = false,
        $postalCode = '',
        $city = '',
        $streetName = '',
        $publicPlaceCategory = '',
        $number = false,
        $floor = false,
        $door = false,
        $countyCode = 'HU'
    )
    {
        $this->invoiceCustomer = [];
        if($taxNumber !=''){
          $this->invoiceCustomer = array_merge($this->invoiceCustomer, [
            'customerTaxNumber' => [
                'taxpayerId' => substr($taxNumber, 0, 8),
                'vatCode' => substr($taxNumber, 9, 1),
                'countyCode' => substr($taxNumber, 11, 2),
            ]
           ]);   
        }
        $this->invoiceCustomer = array_merge($this->invoiceCustomer, [
            'customerName' => $companyName,
            'customerAddress' => [
                'detailedAddress' => [
                    'countryCode' => $countyCode,
                    'postalCode' => $postalCode,
                    'city' => $city,
                    'streetName' => $streetName,
                    'publicPlaceCategory' => $publicPlaceCategory,
                    'number' => $number,
                    'floor' => $floor,
                    'door' => $door,
                ]
            ],
            'customerBankAccountNumber'  => $account
        ]);

       
    }

    /**
     * @param string $invoiceNumber
     * @param string $invoiceIssueDate Számla kelte
     * @param string $invoiceDeliveryDate Teljesítés Dátuma
     * @param string $paymentDate Fizetés dátuma
     * @param string $currency
     * @param string $paymentMethod
     * @param string $invoiceCategory
     * @param string $invoiceAppearance
     */
    public function addInvoiceData(
        $invoiceNumber = '',
        $invoiceIssueDate = '',
        $invoiceDeliveryDate = '',
        $paymentDate = '',
        $currency = 'HUF',
        $paymentMethod = 'TRANSFER',
        $invoiceCategory = 'NORMAL',
        $invoiceAppearance = 'PAPER',
        $exchangeRate = '1.00'

    )
    {
        $this->invoiceNumber =  $invoiceNumber;
        $this->invoiceIssueDate =  $invoiceIssueDate;
        $this->invoiceData = [
            'invoiceCategory' => $invoiceCategory,
            'invoiceDeliveryDate' => $invoiceDeliveryDate,
            'currencyCode' => $currency,
            'exchangeRate' => $exchangeRate,
            'paymentMethod' => $paymentMethod,
            'paymentDate' => $paymentDate,   
            'invoiceAppearance' => $invoiceAppearance,
        ];
    }


    /**
     * @param int $lineNumber
     * @param string $lineDescription
     * @param int $quantity
     * @param string $unitOfMeasure
     * @param int $unitPrice
     * @param int $lineNetAmount
     * @param int $lineGrossAmountNormal
     * @param float $vatPercentage
     * @param int $lineVatAmount
     * @param string $productCodeCategory
     * @param string $productCodeValue
     */
    public function addInvoiceLine(
        $lineNumber = 1,
        $lineDescription = '',
        $quantity = 1,
        $unitOfMeasure = 'PIECE',
        $unitPrice = 0,
        $lineNetAmount = 0,  
        $lineNetAmountHUF = 0,
        $lineGrossAmountNormal = 0,
        $lineGrossAmountNormalHUF = 0,
        $lineVatAmount = 0,
        $vatPercentage = '0.27',
        $productCodeCategory = 'SZJ',
        $productCodeValue = '72601',
        $lineExpressionIndicator = 'true'
    )
    {
        if($unitOfMeasure=='db')$unitOfMeasure = 'PIECE';
        if($unitOfMeasure=='óra')$unitOfMeasure = 'HOUR';
        if($unitOfMeasure=='nap')$unitOfMeasure = 'DAY';
        if($unitOfMeasure=='hó')$unitOfMeasure = 'MONTH';

        
        $this->invoiceLines[] = [
            'line' => [
                'lineNumber' => $lineNumber,
                'productCodes' => [
                    'productCode' => [
                        'productCodeCategory' => $productCodeCategory,
                        'productCodeValue' => $productCodeValue
                    ]
                ],
                'lineExpressionIndicator' => $lineExpressionIndicator,                
                'lineDescription' => $lineDescription,
                'quantity' => $quantity,
                'unitOfMeasure' => $unitOfMeasure,
                'unitPrice' => $unitPrice,
                'lineAmountsNormal' => [
                    'lineNetAmountData' => [
                        'lineNetAmount' => $lineNetAmount,
                        'lineNetAmountHUF' => $lineNetAmountHUF
                    ],
                    'lineVatRate' => [
                        'vatPercentage' => (float)$vatPercentage
                    ]
                ],
            ]
        ];

        if(empty($this->invoiceSummary[(string)$vatPercentage])){
            $this->invoiceSummary[(string)$vatPercentage] = [
                'vatRateNetAmount' => $lineNetAmount,
                'vatRateNetAmountHUF' => $lineNetAmountHUF,
                'vatRateVatAmount' => $lineVatAmount,
                'vatRateVatAmountHUF' => $lineVatAmount,
                'vatRateGrossAmount' => $lineGrossAmountNormal,
                'vatRateGrossAmountHUF' => $lineGrossAmountNormalHUF,
            ];
        }else{
            $this->invoiceSummary[(string)$vatPercentage]['vatRateNetAmount'] +=  $lineNetAmount;
            $this->invoiceSummary[(string)$vatPercentage]['vatRateNetAmountHUF'] +=  $lineNetAmountHUF;
            $this->invoiceSummary[(string)$vatPercentage]['vatRateVatAmount'] +=  $lineVatAmount;
            $this->invoiceSummary[(string)$vatPercentage]['vatRateVatAmountHUF'] +=  $lineVatAmount;
            $this->invoiceSummary[(string)$vatPercentage]['vatRateGrossAmount'] +=  $lineGrossAmountNormal;
            $this->invoiceSummary[(string)$vatPercentage]['vatRateGrossAmountHUF'] +=  $lineGrossAmountNormalHUF;
        }
        $this->invoiceSummary['invoiceNetAmount'] +=  $lineNetAmount;
        $this->invoiceSummary['invoiceNetAmountHUF'] +=  $lineNetAmountHUF;
        $this->invoiceSummary['invoiceVatAmount'] +=  $lineVatAmount;
        $this->invoiceSummary['invoiceVatAmountHUF'] +=  $lineVatAmount;
        $this->invoiceSummary['invoiceGrossAmount'] +=  $lineGrossAmountNormal;
        $this->invoiceSummary['invoiceGrossAmountHUF'] +=  $lineGrossAmountNormalHUF;

    }

    private function getInvoiceSummary(){
        $summaryByVatRate = '';
        foreach($this->invoiceSummary as $vat => $vatRow ) {
            //TODO: Nem lehet több vat alapján megadni végösszeget? nincs osmétlődő elem...
            if(is_numeric($vat)) {
                $summaryByVatRate['summaryByVatRate'] = [

                    'vatRate' => [
                        'vatPercentage' => (float)$vat
                    ],
                    'vatRateNetData' =>[
                        'vatRateNetAmount' => $this->invoiceSummary[$vat]['vatRateNetAmount'] ,
                        'vatRateNetAmountHUF' => $this->invoiceSummary[$vat]['vatRateNetAmountHUF'] ,
  
                    ]  ,
                    'vatRateVatData' =>[
                      'vatRateVatAmount' => $this->invoiceSummary[$vat]['vatRateVatAmount'],
                      'vatRateVatAmountHUF' => $this->invoiceSummary[$vat]['vatRateVatAmountHUF'],                    
                    ]
                ];
            }
        }

        return [
            'summaryNormal' => [
                $summaryByVatRate,
                'invoiceNetAmount' => $this->invoiceSummary['invoiceNetAmount'],
                'invoiceNetAmountHUF' => $this->invoiceSummary['invoiceNetAmountHUF'],
                'invoiceVatAmount' => $this->invoiceSummary['invoiceVatAmount'],
                'invoiceVatAmountHUF' => $this->invoiceSummary['invoiceVatAmountHUF'],
            ],
            'summaryGrossData' => [
                'invoiceGrossAmount' => $this->invoiceSummary['invoiceGrossAmount'],
                'invoiceGrossAmountHUF' => $this->invoiceSummary['invoiceGrossAmountHUF']
            ]
        ];
    }

    private function generateInvoiceHead()
    {
        return [
            'supplierInfo' => $this->invoiceSupplier,
            'customerInfo' => $this->invoiceCustomer,
            'invoiceDetail' => $this->invoiceData
        ];
    }

    public function generateInvoiceXml()
    {
        $xml = new SimpleXMLElement('<InvoiceData xmlns:xs= "http://www.w3.org/2001/XMLSchema-instance" xs:schemaLocation = "http://schemas.nav.gov.hu/OSA/2.0/data invoiceData.xsd"/>');
        $xml->addAttribute('xmlns', 'http://schemas.nav.gov.hu/OSA/2.0/data');

        $this->arrayToXml([
            'invoiceNumber' =>  $this->invoiceNumber,
            'invoiceIssueDate' =>  $this->invoiceIssueDate,
            'invoiceMain' => [
                'invoice' => [
                    'invoiceHead' => $this->generateInvoiceHead(),
                    'invoiceLines' => $this->invoiceLines,
                    'invoiceSummary' => $this->getInvoiceSummary()
                ]
            ]      
        ], $xml);

        $xmlString = $xml->asXML();
        //var_dump($xmlString);
       // die();
        return html_entity_decode($xmlString, ENT_NOQUOTES, 'UTF-8');


    }

    /**
     * @param string $action
     * @param string $technicalAnnulment
     * @return SimpleXMLElement
     * @throws Exception
     */
    public function sendInvoice($action =  'CREATE', $technicalAnnulment = 'false')
    {          
        $this->setSignature(strtoupper($this->sha3($action.base64_encode($this->generateInvoiceXml()))));
        $invoiceData = $this->generateXmlHeader()+[
                'exchangeToken' => $this->token,
                'invoiceOperations' => [
                    'compressedContent' => 'false',
                    'invoiceOperation' => [
                        'index' => 1,
                        'invoiceOperation' => $action,
                        'invoiceData' => base64_encode($this->generateInvoiceXml())
                    ]

                ]
            ];
        //Remove comment for testing    
        //header('Content-Type: text/xml; charset=utf-8');echo  $this->generateInvoiceXml();  die();

        $xml = new SimpleXMLElement('<ManageInvoiceRequest/>');
        $xml->addAttribute('xmlns', 'http://schemas.nav.gov.hu/OSA/2.0/api');
        $this->arrayToXml($invoiceData, $xml);
        $xmlString = $xml->asXML();

        $return = $this->callUrl($this->invoiceUrl, $xmlString);
        $returnXml = simplexml_load_string($return);
        if ($returnXml->result->funcCode == 'ERROR') {
            $this->writeLog('Invoice send failed!');
            $this->writeLog(json_encode((array)$returnXml));
            throw new Exception('Invoice send failed!');
        }
        $this->writeLog('Invoice sent success! Transaction Id:'.$returnXml->transactionId);
        return $returnXml->transactionId;

    }

    /**
     * @param $ident
     * @return bool|string
     * @throws Exception
     */
    public function checkInvoiceStatus($ident)
    {
        $this->setSignature();
        $requesData = $this->generateXmlHeader()+[

                'transactionId' => $ident,
                'returnOriginalRequest' => 'false'
            ];

        $xml = new SimpleXMLElement('<QueryTransactionStatusRequest/>');
        $xml->addAttribute('xmlns', 'http://schemas.nav.gov.hu/OSA/2.0/api');
        $this->arrayToXml($requesData, $xml);
        $xmlString = $xml->asXML();
                                           var_dump($this->invoiceQueryUrl);
        $return = $this->callUrl($this->invoiceQueryUrl, $xmlString);

        $returnXml = simplexml_load_string($return);
        if ($returnXml->result->funcCode == 'ERROR') {
            $this->writeLog('Invoice query failed!');
            $this->writeLog(json_encode((array)$returnXml));
            throw new Exception('Invoice query failed!');
        }

        if((string)$returnXml->processingResults->processingResult->invoiceStatus=='ABORTED'){
            $this->writeLog('Invoice processing aborted!');
            $this->writeLog(json_encode((array)$returnXml->processingResults));
            $this->errors[] = json_encode((array)$returnXml->processingResults);
            return false;
        }

        return (string)$returnXml->processingResults->processingResult->invoiceStatus;
    }

    public function callUrl($url, $xml)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_VERBOSE, 1);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER,
            array('Content-Type: application/xml',
                'Accept: application/xml'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
        $result = $this->lastResult = curl_exec($ch);
        curl_close($ch); 
        return $result;

    }


    private function arrayToXml($data, &$xml_data)
    {
        foreach ($data as $key => $value) {

            if (is_numeric($key)) {
                $key = key($value) ; //dealing with <0/>..<n/> issues
                $subnode = $xml_data->addChild($key);
                $this->arrayToXml($value[$key], $subnode);
            }else
                if (is_array($value)) {
                    $subnode = $xml_data->addChild($key);
                    $this->arrayToXml($value, $subnode);
                } else {
                    if($value !== false){
                        $xml_data->addChild($key, $value);
                    }
                }
        }
    }

    public function writeLog($logText)
    {
        if ($this->log) fwrite($this->logFile, date('Y-m-d H:i:s') . ':' . $logText.'
');
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
        $this->password = strtoupper(hash('sha512', $password));
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
     * @param bool $asRaw
     * @return mixed
     */
    public function getLastResult($asRaw = true)
    {
        if ($asRaw) {
            return $this->lastResult;
        } else {
            return simplexml_load_string($this->lastResult);
        }
    }

    /**
     * @param bool $asRaw
     * @return mixed
     */
    public function getErrors()
    {
        return $this->errors;
    }

    public function setLogDir($logDir)
    {
        $this->logDir = $logDir;
        return $this;
    }

}