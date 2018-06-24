<?php
header('Content-Type: text/xml; charset=utf-8');
require_once('navInvoice.class.php');
require_once('aes.class.php');
$api = new NavInvoice();

$api
    ->setUser('xxxxxxxxxxxxxxxxx')
    ->setPassword('xxxxxxxxxxxxxxx')
    ->setCompany('Total Studio Kft')
    ->setTaxNumber('14410615')
    ->setXmlKey('xxxxxxxxxxxxxxxx')
    ->setXmlChangeKey('xxxxxxxxxxxxxxxxx')
    ->setRequestIdPrefix('TSCRM')
    ->setSoftwareId('HU14410615TSCRM100')
    ->setSoftwareName('TSCRM')
    ->setSoftwareOperation('LOCAL_SOFTWARE')
    ->setSoftwareMainVersion('1.0')
    ->setSoftwareDevName('Total Studio')
    ->setSoftwareDevContact('info@totalstudio.hu')
    ->setSoftwareDevCountryCode('HU')
    ->setSoftwareDevTaxNumber('14410615-2-41');

$api->generateRequestId();
$api->setSignature();
try{
    $api->getNewToken();
}catch(Exception $e){
    //send email for example
}

$api->addSupplierData('14410615-2-41', 'Total Studio Kft', false, '1043', 'Budapest', 'Kassai', 'utca', '11', '4', '24');
$api->addCustomerData('28651729-3-41', 'AGIMAR Cooperation Bt',false, '1037', 'Budapest', 'Erdőalja', 'út', '36');
$api->addInvoiceData('TS00007/2018','2018-06-23','2018-06-23','2018-06-30');
$api->addInvoiceLine(1, 'Weboldal készítés', 1, 'db', '100000', '100000', '127000', 27000);
$api->addInvoiceLine(2, 'Doamin', 1, 'db', '1000', '1000', '1270', 270);

try{
    $transactionID = $api->sendInvoice();
}catch(Exception $e){
    //send email for example
}

$api->generateRequestId();
try{
    if(!$api->checkInvoiceStatus($transactionID)){
        //send email for example
    }
}catch(Exception $e){
    //send email for example
}

