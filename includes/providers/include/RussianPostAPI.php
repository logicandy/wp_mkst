<?php
/**
 * Russian Post tracking API PHP library
 * @author InJapan Corp. <max@injapan.ru>
 *
 ************************************************************************
 * You MUST request usage access for this API through request mailed to *
 * fc@russianpost.ru                                                    *
 ************************************************************************
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @link https://tracking.russianpost.ru/specification
 *
 */

class RussianPostAPI {
  /**
   * SOAP service URL
   */
  const SOAPEndpoint = 'https://tracking.russianpost.ru/rtm34';

  public $serror = false;
  public $error;
  
  protected $SOAPUser;
  protected $SOAPPassword; 

  protected $proxyHost;
  protected $proxyPort;
  protected $proxyAuthUser;
  protected $proxyAuthPassword;

  protected $cacheDir;
  protected $cacheExpire;
  const     CACHEfnPFX = 'RUpostAPI-';
  const     CACHEfnSFX = '';

  /**
   * Constructor.
   */
  public function __construct($SOAPUser = "", $SOAPPassword = "") {
    $russianpostRequiredExtensions = array('SimpleXML', 'curl', 'pcre');
    foreach($russianpostRequiredExtensions as $russianpostExt) {
      if (!extension_loaded($russianpostExt)) {
        throw new RussianPostSystemException('Required extension ' . $russianpostExt . ' is missing', 100);
      }
    }
    $this->error = new RPError();
    $this->SOAPUser     = $SOAPUser;
    $this->SOAPPassword = $SOAPPassword;
  }

  /**
   * Pass proxy config here.
   * @param string $proxyHost
   * @param string $proxyPort
   * @param string $proxyAuthUser
   * @param string $proxyAuthPassword
   */
  public function setProxy($proxyHost = "", $proxyPort = "", $proxyAuthUser = "", $proxyAuthPassword = "") {
    $this->proxyHost         = $proxyHost;
    $this->proxyPort         = $proxyPort;
    $this->proxyAuthUser     = $proxyAuthUser;
    $this->proxyAuthPassword = $proxyAuthPassword;
  }

  /**
   * Pass cache config here.
   * @param string $cacheDir
   * @param string $cacheExpire
   */
  public function setCache($cacheDir = "", $cacheExpire = "") {
    if (!is_dir($cacheDir)) {
      throw new RussianPostSystemException('Cache directory ' . $cacheDir . ' does not exists', 100);
    }
    if (!is_writable($cacheDir)) {
      throw new RussianPostSystemException('Cache directory ' . $cacheDir . ' not writable', 100);
    }
    $this->cacheDir  = rtrim($cacheDir, '/') . '/';
    $this->cacheExpire = (int)$cacheExpire * 60*60;
  }

  /**
   * Returns tracking data
   * @param string $trackingNumber tracking number
   * @param string $language language for output strings
   * @return array of RussianPostTrackingRecord
   */
  public function getOperationHistory($trackingNumber, $language = 'RUS') {
    $trackingNumber = $this->checkTrackingNumber($trackingNumber);

    if (($records = $this->getCache($trackingNumber)) == false) {

      $client_parms = array();
      $client_parms['soap_version'] = SOAP_1_2;
      $client_parms['trace'] = 1;
      $client_parms['exceptions'] = 0;
      if (!empty($this->proxyHost)) {
        $client_parms['proxy_host'] = $this->proxyHost;
        if (!empty($this->proxyPort)) {
          $client_parms['proxy_port'] = $this->proxyPort;
        }
        if (!empty($this->proxyAuthUser)) {
          $client_parms['proxy_login'] = $this->proxyAuthUser;
        }
        if (!empty($this->proxyAuthPassword)) {
          $client_parms['proxy_password'] = $this->proxyAuthPassword;
        }
      }
      $client = new SoapClient('https://tracking.russianpost.ru/rtm34?wsdl', $client_parms);
      $data = $client->getOperationHistory(array(
                                                 'OperationHistoryRequest' => array('Barcode' => $trackingNumber, 'MessageType' => 0, 'Language' => $language, ),
                                                 'AuthorizationHeader' => array('login' => $this->SOAPUser, 'password' => $this->SOAPPassword, 'mustUnderstand' => 1, ),
                                                 )
                                           );
      if (!empty($data->SoapFault) && defined('ORDER_TRACKING_DEBUG_LOG')) {
        error_log('$data->SoapFault=' . var_export($data->SoapFault, true) . "\n", 3, ORDER_TRACKING_DEBUG_LOG);
        error_log('$data->SoapFault=' . var_export($data->SoapFault['message'], true) . "\n", 3, ORDER_TRACKING_DEBUG_LOG);
        error_log('$data->SoapFault=' . var_export($data->SoapFault['faultstring'], true) . "\n", 3, ORDER_TRACKING_DEBUG_LOG);
        error_log('$data->SoapFault=' . var_export($data->SoapFault['detail'], true) . "\n", 3, ORDER_TRACKING_DEBUG_LOG);
        error_log('$data->SoapFault=' . var_export($data->SoapFault['detail']->AuthorizationFaultReason, true) . "\n", 3, ORDER_TRACKING_DEBUG_LOG);
        throw new RussianPostDataException("SoapFault", 10001);
      }
      if (empty($data->OperationHistoryData) || empty($data->OperationHistoryData->historyRecord)) {
      }
      if (defined('ORDER_TRACKING_DEBUG_LOG')) {
        error_log('$trackingNumber=' . var_export($trackingNumber, true) . "\n", 3, ORDER_TRACKING_DEBUG_LOG);
        error_log('$data=' . var_export($data, true) . "\n", 3, ORDER_TRACKING_DEBUG_LOG);
      }
      /*echo "<pre>"; print_r($data->OperationHistoryData->historyRecord); echo "</pre>";*/
      if (is_array($data->OperationHistoryData->historyRecord)){
        $records = $data->OperationHistoryData->historyRecord;
      } else {
        $records[] = $data->OperationHistoryData->historyRecord;
      }

      if (empty($records))
        throw new RussianPostDataException("There is no tracking data in XML response", 101);
      $out = array();
      /*foreach($records as $rec) {
        $outRecord = new RussianPostTrackingRecord();
        $outRecord->operationType            = (string) $rec->OperationParameters->OperType->Name;
        $outRecord->operationTypeId          = (int) $rec->OperationParameters->OperType->Id;

        $outRecord->operationAttribute       = (string) $rec->OperationParameters->OperAttr->Name;
        $outRecord->operationAttributeId     = (int) $rec->OperationParameters->OperAttr->Id;

        $outRecord->operationPlacePostalCode = (string) $rec->AddressParameters->OperationAddress->Index;
        $outRecord->operationPlaceName       = (string) $rec->AddressParameters->OperationAddress->Description;

        $outRecord->destinationPostalCode    = (string) $rec->AddressParameters->DestinationAddress->Index;
        $outRecord->destinationAddress       = (string) $rec->AddressParameters->DestinationAddress->Description;

        $outRecord->operationDate            = (string) $rec->OperationParameters->OperDate;

        $outRecord->itemWeight               = round(floatval($rec->ItemParameters->Mass)/1000, 3);
        $outRecord->declaredValue            = floatval($rec->FinanceParameters->Value)/100;
        $outRecord->collectOnDeliveryPrice   = floatval($rec->FinanceParameters->Payment)/100;

        $out[] = $outRecord;
      }*/
      $i = 0;
      //echo "<pre>"; print_r($records[0]); echo "</pre>";
      foreach($records as $rec) {
        $i++;
        $outRecord = new RussianPostTrackingRecord();

        $outRecord->operid                   = $i;
        $outRecord->operationType            = isset($rec->OperationParameters->OperType->Name)?(string) $rec->OperationParameters->OperType->Name:"";
        $outRecord->operationTypeId          = isset($rec->OperationParameters->OperType->Id)?(int) $rec->OperationParameters->OperType->Id:"";
        $outRecord->operationAttribute       = isset($rec->OperationParameters->OperAttr->Name)?(string) $rec->OperationParameters->OperAttr->Name:"";
        $outRecord->operationAttributeId     = isset($rec->OperationParameters->OperAttr->Id)?(int) $rec->OperationParameters->OperAttr->Id:0;
        $outRecord->operationDate            = isset($rec->OperationParameters->OperDate)?(string) $rec->OperationParameters->OperDate:"";
        $outRecord->operationPlacePostalCode = isset($rec->AddressParameters->OperationAddress->Index)?(string) $rec->AddressParameters->OperationAddress->Index:"";
        $outRecord->operationPlaceName       = isset($rec->AddressParameters->OperationAddress->Description)?(string) $rec->AddressParameters->OperationAddress->Description:"";
        $outRecord->destinationPostalCode    = isset($rec->AddressParameters->DestinationAddress->Index)?(string) $rec->AddressParameters->DestinationAddress->Index:"";
        $outRecord->destinationAddress       = isset($rec->AddressParameters->DestinationAddress->Description)?(string) $rec->AddressParameters->DestinationAddress->Description:"";
        $outRecord->mailDirectId             = isset($rec->AddressParameters->MailDirect->Id)?(string) $rec->AddressParameters->MailDirect->Id:"";
        $outRecord->mailDirectName           = isset($rec->AddressParameters->MailDirect->NameRU)?(string) $rec->AddressParameters->MailDirect->NameRU:"";
        $outRecord->countryOper              = isset($rec->AddressParameters->CountryOper->Id)?(string) $rec->AddressParameters->CountryOper->Id:"";
        $outRecord->itemWeight               = isset($rec->ItemParameters->Mass)?round(floatval($rec->ItemParameters->Mass) / 1000, 3):0;
        $outRecord->declaredValue            = isset($rec->FinanceParameters->Value)?round(floatval($rec->FinanceParameters->Value) / 100, 2):0;
        $outRecord->collectOnDeliveryPrice   = isset($rec->FinanceParameters->Payment)?round(floatval($rec->FinanceParameters->Payment) / 100, 2):0;
        $outRecord->financeMassRate          = isset($rec->FinanceParameters->MassRate)?(string) $rec->FinanceParameters->MassRate:"";
        $outRecord->financeInsrRate          = isset($rec->FinanceParameters->InsrRate)?(string) $rec->FinanceParameters->InsrRate:"";
        $outRecord->financeAirRate           = isset($rec->FinanceParameters->AirRate)?(string) $rec->FinanceParameters->AirRate:"";
        $outRecord->financeRate              = isset($rec->FinanceParameters->Rate)?(string) $rec->FinanceParameters->Rate:"";
        $outRecord->validRuType              = isset($rec->ItemParameters->ValidRuType)?(string) $rec->ItemParameters->ValidRuType:"";
        $outRecord->validEnType              = isset($rec->ItemParameters->ValidEnType)?(string) $rec->ItemParameters->ValidEnType:"";
        $outRecord->cmplxItemName            = isset($rec->ItemParameters->ComplexItemName)?(string) $rec->ItemParameters->ComplexItemName:"";
        $outRecord->mailRank                 = isset($rec->ItemParameters->MailRank->Name)?(string) $rec->ItemParameters->MailRank->Name:"";
        $outRecord->postRank                 = isset($rec->ItemParameters->PostRank->Name)?(string) $rec->ItemParameters->PostRank->Name:"";
        $outRecord->mailType                 = isset($rec->ItemParameters->MailType->Name)?(string) $rec->ItemParameters->MailType->Name:"";
        $outRecord->mailCtg                  = isset($rec->ItemParameters->MailCtg->Name)?(string) $rec->ItemParameters->MailCtg->Name:"";
        $outRecord->rcpn                     = isset( $rec->UserParameters->Rcpn)?(string) $rec->UserParameters->Rcpn:"";

        $out[] = $outRecord;
      }

      $this->putCache($trackingNumber, $out);

    }
    if (defined('ORDER_TRACKING_DEBUG_LOG')) {
      error_log('$out=' . var_export($out, true) . "\n", 3, ORDER_TRACKING_DEBUG_LOG);
    }
    return $out;
  }

  /**
   * Returns tracking data
   * @param string $trackingNumber tracking number
   * @param string $language language for output strings
   * @return array of RussianPostTrackingRecord
   */
  public function getOperationHistory_curl($trackingNumber, $language = 'RUS') {
    $message = <<<EOD
<soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope" xmlns:oper="http://russianpost.org/operationhistory" xmlns:data="http://russianpost.org/operationhistory/data" xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/">
   <soap:Header/>
   <soap:Body>
      <oper:getOperationHistory>
         <data:OperationHistoryRequest>
            <data:Barcode>$trackingNumber</data:Barcode>
            <data:MessageType>0</data:MessageType>
            <data:Language>$language</data:Language>
         </data:OperationHistoryRequest>
         <data:AuthorizationHeader soapenv:mustUnderstand="1">
            <data:login>$this->SOAPUser</data:login>
            <data:password>$this->SOAPPassword</data:password>
         </data:AuthorizationHeader>
      </oper:getOperationHistory>
   </soap:Body>
</soap:Envelope>
EOD;

      $data = $this->makeRequest($message);

      $data = $this->parseResponse($data);

      $records = $data->getOperationHistoryResponse->OperationHistoryData->historyRecord;

      return $records;
  }

  /**
   * Returns cash-on-delivery payment data
   * @param string $trackingNumber tracking number
   * @param string $language language for output strings
   * @return array of RussianPostCODRecord
   */
  public function getCODHistory($trackingNumber, $language = 'RUS') {
    $trackingNumber = $this->checkTrackingNumber($trackingNumber);

    $message = <<<EOD
<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/" xmlns:data="http://www.russianpost.org/RTM/DataExchangeESPP/Data" xmlns:data1="http://russianpost.org/operationhistory/data">
   <s:Header>
     <data1:AuthorizationHeader s:mustUnderstand="1">
       <data1:login>$this->SOAPUser</data1:login>
       <data1:password>$this->SOAPPassword</data1:password>
     </data1:AuthorizationHeader>
   </s:Header>
   <s:Body xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
       <data:PostalOrderEventsForMailInput Barcode="$trackingNumber" Language="$language" />
   </s:Body>
</s:Envelope>
EOD;

    $data = $this->makeRequest($message);
    $data = $this->parseResponse($data);

    $records = $data->PostalOrderEventsForMaiOutput;

    if (empty($records))
      throw new RussianPostDataException("There is no COD data in XML response", 102);

    $out = array();
    foreach($records->children() as $rec) {
      $rec = $rec->attributes();

      $outRecord = new RussianPostCODRecord();

      $outRecord->paymentNumber         = (int)    $rec->Number;
      $outRecord->eventDate             = (string) $rec->EventDateTime;
      $outRecord->eventTypeId           = (int)    $rec->EventType;
      $outRecord->eventName             = (string) $rec->EventName;
      $outRecord->destinationPostalCode = (string) $rec->IndexTo;
      $outRecord->eventPostalCode       = (string) $rec->IndexEvent;
      $outRecord->paymentAmount         = round(intval($rec->SumPaymentForward) / 100, 2);
      $outRecord->destinationContryCode = (string) $rec->CountryToCode;
      $outRecord->eventCountryCode      = (string) $rec->CountryEventCode;

      $out[] = $outRecord;
    }

    return $out;
  }

  protected function checkTrackingNumber($trackingNumber) {
    $trackingNumber = trim($trackingNumber);
    if (!preg_match('/^[0-9]{14}|[A-Z]{2}[0-9]{9}[A-Z]{2}$/', $trackingNumber)) {
      throw new RussianPostArgumentException('Incorrect format of tracking number: ' . $trackingNumber, 103);
    }

    return $trackingNumber;
  }

  protected function parseResponse($raw) {
    $raw = str_replace('ns7:', 'ns3:', $raw);
    $xml = @simplexml_load_string($raw);

    if (!is_object($xml))
      throw new RussianPostDataException("Failed to parse XML response", 104);
    $ns = $xml->getNamespaces(true);

    foreach($ns as $key => $dummy) {
      if (strpos($key, 'ns') === 0) {
        $nsKey = $key;
        break;
      }
    }

    if (empty($nsKey)) {
      throw new RussianPostDataException("Failed to detect correct namespace in XML response", 105);
    }

    if (!(
      $xml->children($ns['S'])->Body &&
      $data = $xml->children($ns['S'])->Body->children($ns[$nsKey])
    ))
      throw new RussianPostDataException("There is no tracking data in XML response", 106);

    return $data;
  }

  protected function makeRequest($message) {

    if (($result = $this->getCache($message)) == false) {

      $channel = curl_init(self::SOAPEndpoint);

      curl_setopt_array($channel, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $message,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER     => array(
          'Content-Type: application/soap+xml',
        ),
      ));

      if (!empty($this->proxyHost) && !empty($this->proxyPort)) {
        curl_setopt($channel, CURLOPT_PROXY, $this->proxyHost . ':' . $this->proxyPort);
      }

      if (!empty($this->proxyAuthUser)) {
        curl_setopt($channel, CURLOPT_PROXYUSERPWD, $this->proxyAuthUser . ':' . $this->proxyAuthPassword);
      }

      $result = curl_exec($channel);
      if ($errorCode = curl_errno($channel)) {
        throw new RussianPostChannelException(curl_error($channel), $errorCode);
      }

      $info = curl_getinfo($channel);
      if ($info['http_code'] != '200') {
        throw new RussianPostChannelException('Error http code ' . $info['http_code'], $info['http_code']);
      }

      $this->putCache($message, $result);

    }

    return $result;
  }

  protected function getCache($message) {
    if (!$this->checkCache()) return false;
    $filename = $this->cacheFN($message);
    if (!is_file($filename) || $this->cacheExpire <= 0 || (time() - filemtime($filename) >= $this->cacheExpire) || !($szdata = file_get_contents($filename))) {
      if (is_file($filename)) {
        @unlink($filename);
      }
      return false;
    }
    $data = unserialize($szdata);
    return $data;
  }

  protected function putCache($message, $data) {
    if (!$this->checkCache()) return false;
    $filename = $this->cacheFN($message);
    $szdata = serialize($data);
    $ret = file_put_contents($filename, $szdata, LOCK_EX);
    return $ret;
  }

  protected function checkCache() {
    if (empty($this->cacheDir) || empty($this->cacheExpire)) {
      return false;
    }
    return true;
  }

  protected function cacheFN($message) {
    $md5 = md5($message);
    $fn = $this->cacheDir . 'RUpostAPI-' . $md5 . '.txt';
    return $fn;
  }

}

class RussianPostTrackingRecord {
  public $operid;
  public $operationType;
  public $operationTypeId;
  public $operationAttribute;
  public $operationAttributeId;
  public $operationPlacePostalCode;
  public $operationPlaceName;
  public $operationDate;
  public $itemWeight;
  public $declaredValue;
  public $collectOnDeliveryPrice;
  public $destinationPostalCode;
  public $destinationAddress;

  public $mailDirectId;
  public $mailDirectName;
  public $countryOper;
  public $financeMassRate;
  public $financeInsrRate;
  public $financeAirRate;
  public $financeRate;
  public $validRuType;
  public $validEnType;
  public $cmplxItemName;
  public $mailRank;
  public $postRank;
  public $mailType;
  public $mailCtg;
  public $mailRcpn;
}


class RussianPostException         extends Exception { }
class RussianPostArgumentException extends RussianPostException { }
class RussianPostSystemException   extends RussianPostException { }
class RussianPostChannelException  extends RussianPostException { }
class RussianPostDataException     extends RussianPostException { }

class RPError {
  public $string;
  public $id;

  /**
    *id = 1 - no tracking data
    *id = 2 - failed to parse data
    *id = 3 - incorect barcode syntax
    *id = 4 - service unavailable
    *id = 5 - connection failed
  **/

  public function __construct(){

  }

  public function noerror(){
    return empty($this->id);
  }

  public function get_error(){
    return $this->string;
  }

  public function get_error_id(){
    return $this->id;
  }
}

class mmysqli_result extends mysqli_result{
  
  public function fetch_all($resulttype = MYSQLI_NUM){
    if (method_exists('mysqli_result', 'fetch_all')) # Compatibility layer with PHP < 5.3
        $res = parent::fetch_all($resulttype);
    else
        for ($res = array(); $tmp = $this->fetch_array($resulttype);) $res[] = $tmp;
  }

}
?>