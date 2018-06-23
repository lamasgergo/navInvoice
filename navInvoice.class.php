<?php

class NavInvoice{
    private $user;
    private $password;
    private $company;
    private $taxNumber;
    private $requestId;
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
        $this->password = $password;
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
     * @param mixed $requestId
     * @return NavInvoice
     */
    public function setRequestId($requestId)
    {
        $this->requestId = $requestId;
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

}