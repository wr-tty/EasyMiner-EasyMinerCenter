<?php

namespace EasyMinerCenter\Model\Data\Databases\DataService;

use EasyMinerCenter\Model\Data\Databases\IDatabase;
use EasyMinerCenter\Model\Data\Entities\DbField;
use EasyMinerCenter\Model\Data\Entities\DbDatasource;
use EasyMinerCenter\Model\Data\Entities\DbConnection;
use Nette\Utils\Json;

/**
 * Class DataServiceDatabase - třída zajišťující přístup k databázím dostupným prostřednictvím služby EasyMiner-Data
 *
 * @package EasyMinerCenter\Model\Data\Databases
 * @author Stanislav Vojíř
 */
/*TODO add: abstract*/ class DataServiceDatabase implements IDatabase {
  /** @var  string $apiKey */
  private $apiKey;
  /** @var  DbConnection $dbConnection */
  private $dbConnection;

  /**
   * Funkce vracející seznam datových zdrojů v DB
   *
   * @return DbDatasource[]
   */
  public function getDbDatasources() {
    $responseData=$this->curlRequestResponse($this->getRequestUrl('/datasource'),null,'GET',['Accept'=>'application/json; charset=utf8']);
    $responseData=Json::decode($responseData, Json::FORCE_ARRAY);

    $result=[];
    if (!empty($responseData)){
      exit(var_dump($responseData));
      foreach($responseData as $item){
        $result[]=new DbDatasource($item['id'],$item['name'],$item['type'],$item['size']);
      }
    }
    return $result;
  }

  /**
   * Funkce vracející seznam sloupců v datovém zdroji
   *
   * @param DbDatasource $dbDatasource
   * @return DbField[]
   */
  public function getDbFields(DbDatasource $dbDatasource) {
    // TODO: Implement getDbFields() method.
  }

  /**
   * Konstruktor zajišťující připojení k databázi
   *
   * @param DbConnection $dbConnection
   * @param string $apiKey
   */
  public function __construct(DbConnection $dbConnection, $apiKey) {
    $this->dbConnection=$dbConnection;
    $this->dbConnection->dbServer=rtrim($this->dbConnection->dbServer,'/');//nechceme lomítko na konci
    $this->apiKey=$apiKey;
  }

  #region funkce pro práci s RESTFUL API
  /**
   * Funkce vracející URL pro odeslání požadavku na datovou službu
   *
   * @param string $relativeUrl
   * @return string
   */
  private function getRequestUrl($relativeUrl){
    return $this->dbConnection->dbServer.$relativeUrl;
  }

  /**
   * Funkce pro práci s RESTFUL API
   *
   * @param string $url
   * @param string $postData = ''
   * @param string|null $method = 'GET'
   * @param array $headersArr=[]
   * @param int|null &$responseCode - proměnná pro vrácení stavového kódu odpovědi
   * @return string - response data
   * @throws \Exception - curl error
   */
  private function curlRequestResponse($url, $postData='', $method='GET', $headersArr=[], &$responseCode=null){
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch,CURLOPT_MAXREDIRS,0);
    curl_setopt($ch,CURLOPT_FOLLOWLOCATION,false);
    if (empty($headersArr['Content-Type'])){
      $headersArr['Content-Type']='application/xml; charset=utf-8';
    }
    if (!empty($this->apiKey)){
      $headersArr['Authorization']='ApiKey '.$this->apiKey;
    }
    if ($postData!=''){
      curl_setopt($ch,CURLOPT_POST,true);
      curl_setopt($ch, CURLOPT_CUSTOMREQUEST, ($method?$method:"POST"));
      curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
      $headersArr['Content-length']=strlen($postData);
    }

    $httpHeadersArr=[];
    if (!empty($headersArr)){
      foreach($headersArr as $header=>$value){
        $headersArr[]=$header.': '.$value;
      }
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $httpHeadersArr);

    $responseData = curl_exec($ch);
    $responseCode=curl_getinfo($ch,CURLINFO_HTTP_CODE);

    if(curl_errno($ch)){
      $exception=curl_error($ch);
      curl_close($ch);
      throw new \Exception($exception);
    }
    curl_close($ch);
    return $responseData;
  }
  #endregion funkce pro práci s RESTFUL API
}