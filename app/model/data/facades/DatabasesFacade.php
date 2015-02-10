<?php
namespace App\Model\Data\Facades;
use App\Model\Data\Databases\IDatabase;
use App\Model\Data\Entities\DbColumn;
use App\Model\Data\Entities\DbColumnValuesStatistic;
use App\Model\Data\Entities\DbConnection;
use Nette\Application\ApplicationException;
use Nette\Utils\Strings;

/**
 * Class DatabasesFacade - model zajišťující práci se dvěma databázemi najednou
 * @package App\Model\Data\Facades
 */
class DatabasesFacade {
  /** @var  IDatabase $database */
  private $database1;
  /** @var  IDatabase $database */
  private $database2;

  const FIRST_DB='database1';
  const SECOND_DB='database2';

  const MYSQL_COLUMNS_MAX_COUNT=50;
  const DB_TYPE_MYSQL='mysql';
  const DB_TYPE_CASSANDRA='cassandra';
  const DB_CLASS_MYSQL='\App\Model\Data\Databases\MySQLDatabase';
  const DB_CLASS_CASSANDRA='\App\Model\Data\Databases\CassandraDatabase';

  /**
   * Funkce vracející přehled podporovaných typů databází
   * @return string[]
   */
  public static function getDatabaseTypes(){
    return array(self::DB_TYPE_MYSQL,self::DB_TYPE_CASSANDRA);
  }

  /**
   * Fukce pro založení uživatelského účtu s databází
   * @param DbConnection $dbConnection
   * @param string $databaseProperty = self::FIRST_DB
   * @return bool
   */
  public function createUserDatabase(DbConnection $dbConnection, $databaseProperty=self::FIRST_DB){
    return $this->$databaseProperty->createUserDatabase($dbConnection);
  }

  /**
   * @param DbConnection $dbConnection1
   * @param DbConnection|null $dbConnection2
   * @throws ApplicationException
   */
  public function openDatabase(DbConnection $dbConnection1,DbConnection $dbConnection2=null){
    $this->openDatabaseProperty($dbConnection1,self::FIRST_DB);
    if ($dbConnection2!=null){
      $this->openDatabaseProperty($dbConnection2,self::SECOND_DB);
    }
  }

  /**
   * @param DbConnection $dbConnection
   * @param string $databasePropertyName
   * @throws ApplicationException
   */
  private function openDatabaseProperty(DbConnection $dbConnection,$databasePropertyName){
    if ($dbConnection->type==self::DB_TYPE_MYSQL){
      /** @var IDatabase|string $class */
      $class=self::DB_CLASS_MYSQL;
    }elseif($dbConnection->type==self::DB_TYPE_CASSANDRA){
      /** @var IDatabase|string $class */
      $class=self::DB_TYPE_CASSANDRA;
    }else{
      throw new ApplicationException('Unknown database type!');
    }
    $this->$databasePropertyName=$class::getInstance($dbConnection);
  }

  /**
   * @param DbColumn[]|int $dbColumns
   * @return string
   */
  public static function prefferedDatabaseType($dbColumns){
    if (is_numeric($dbColumns)){
      $dbColumnsCount=$dbColumns;
    }else{
      $dbColumnsCount=count($dbColumns);
    }
    if ($dbColumnsCount>self::MYSQL_COLUMNS_MAX_COUNT){
      return self::DB_TYPE_CASSANDRA;
    }else{
      return self::DB_TYPE_MYSQL;
    }
  }

  /**
   * Funkce pro vytvoření databázové tabulky na základě zadaného jména a informace o sloupcích
   * @param string $tableName
   * @param DbColumn[] $columns
   * @param string $databaseProperty = self::FIRST_DB
   * @return bool
   */
  public function createTable($tableName, $columns, $databaseProperty=self::FIRST_DB){
    $this->checkDatabase();
    return $this->$databaseProperty->createTable($tableName,$columns);
  }

  /**
   * Funkce pro vložení řádku do databáze
   * @param $tableName
   * @param array $data
   * @param string $databaseProperty = self::FIRST_DB
   * @return bool
   */
  public function insertRow($tableName, array $data, $databaseProperty=self::FIRST_DB){
    $this->checkDatabase();
    $this->$databaseProperty->selectTable($tableName);
    try{
      return $this->$databaseProperty->insertRow($data);
    }catch (\Exception $e){
      return false;
    }
  }

  /**
   * Funkce pro připravení nového jména tabulky, kterou je možné vytvořit...
   * @param string $tableName
   * @param bool $checkExistence = true - pokud je true, je v DB zkontrolována existence tabulky s daným názvem a je vrácen první neobsazený název
   * @param string $databaseProperty = self::FIRST_DB
   * @return string
   */
  public function prepareNewTableName($tableName,$checkExistence=true, $databaseProperty=self::FIRST_DB){
    $tableName=Strings::webalize($tableName,'_',true);
    $tableName=Strings::replace($tableName,'/-/','_');
    $tableName=Strings::replace($tableName,'/__/','_');
    $firstLetter=Strings::substring($tableName,1,1);
    if (!Strings::match($firstLetter,'/\w/')){
      $tableName='tbl_'.$tableName;
    }
    if (!$checkExistence){
      return $tableName;
    }
    $result=$tableName;
    $counter=1;
    while($this->$databaseProperty->tableExists($result)){
      $counter++;
      $result=$tableName.'_'.$counter;
    }
    return $result;
  }

  /**
   * Funkce pro kontrolu, jestli v DB existuje tabulka se zadaným jménem
   * @param string $tableName
   * @param string $databaseProperty = self::FIRST_DB
   * @return bool
   */
  public function checkTableExists($tableName, $databaseProperty=self::FIRST_DB){
    return $this->$databaseProperty->tableExists($tableName);
  }

  /**
   * Funkce pro kontrolu, jestli je dostupná databáze, se kterou máme pracovat...
   * @param string $databaseProperty = self::FIRST_DB
   * @return bool
   */
  private function checkDatabase($databaseProperty=self::FIRST_DB){
    return ($this->$databaseProperty instanceof \PDO);//TODO ???
  }

  /**
   * Funkce pro vypočtení statistik na základě databázového sloupce
   * @param string $tableName - jméno databázové tabulky
   * @param string|DbColumn $column - sloupec, ze kterého má být získána statistika
   * @param bool $includeValues = true
   * @param string $databaseProperty = self::FIRST_DB
   * @return DbColumnValuesStatistic
   */
  public function getColumnValuesStatistic($tableName, $column, $includeValues = true, $databaseProperty=self::FIRST_DB){
    $this->$databaseProperty->selectTable($tableName);
    if ($column instanceof DbColumn){
      return $this->$databaseProperty->getColumnValuesStatistic($column->name, $includeValues);
    }else{
      return $this->$databaseProperty->getColumnValuesStatistic($column, $includeValues);
    }
  }

  /**
   * Funkce vracející přehled DB sloupců v datové tabulce
   * @param string $tableName
   * @param string $databaseProperty = self::FIRST_DB
   * @return DbColumn[]
   */
  public function getColumns($tableName, $databaseProperty=self::FIRST_DB) {
    $this->$databaseProperty->selectTable($tableName);
    return $this->$databaseProperty->getColumns();
  }

  /**
   * Funkce pro kontrolu, jestli existuje v DB tabulce sloupec se zadaným názvem
   * @param string $tableName
   * @param string $column
   * @param string $databaseProperty = self::FIRST_DB
   * @return bool
   */
  public function checkColumnExists($tableName,$column, $databaseProperty=self::FIRST_DB){
    try {
      $this->$databaseProperty->selectTable($tableName);
      $dbColumn=$this->$databaseProperty->getColumn($column);
      return (!($dbColumn instanceof DbColumn));
    }catch (\Exception $e){
      return false;
    }
  }

  /**
   * Funkce pro přejmenování DB sloupce
   * @param string $tableName
   * @param string $column
   * @param string $columnNewName
   * @param string $databaseProperty = self::FIRST_DB
   * @return bool
   */
  public function renameColumn($tableName,$column,$columnNewName, $databaseProperty=self::FIRST_DB){
    try{
      $this->$databaseProperty->selectTable($tableName);
      return $this->$databaseProperty->renameColumn($column,$columnNewName);
    }catch (\Exception $e){
      return false;
    }
  }

  /**
   * Funkce pro smazání sloupce z DB tabulky
   * @param string $tableName
   * @param string $column
   * @param string $databaseProperty = self::FIRST_DB
   * @return bool
   */
  public function deleteColumn($tableName,$column, $databaseProperty=self::FIRST_DB){
    try{
      $this->$databaseProperty->selectTable($tableName);
      return $this->$databaseProperty->deleteColumn($column);
    }catch (\Exception $e){
      return false;
    }
  }

  /**
   * Funkce vracející počet řádků v tabulce
   * @param $tableName
   * @param string $databaseProperty = self::FIRST_DB
   * @return int
   */
  public function getRowsCount($tableName, $databaseProperty=self::FIRST_DB){
    $this->$databaseProperty->selectTable($tableName);
    return $this->$databaseProperty->getRowsCount();
  }

  /**
   * Funkce pro vytvoření nového sloupce v DB tabulce
   * @param string $tableName
   * @param DbColumn $dbColumn
   * @param string $databaseProperty = self::FIRST_DB
   * @return bool
   */
  public function createColumn($tableName,DbColumn $dbColumn,$databaseProperty=self::FIRST_DB){
    $this->$databaseProperty->selectTable($tableName);
    return $this->$databaseProperty->createColumn($dbColumn);
  }

  /**
   * Funkce pro aktualizaci hodnoty v DB sloupci
   * @param string $tableName
   * @param string $column
   * @param int $id
   * @param string $value
   * @param string $databaseProperty = self::FIRST_DB
   * @return bool
   */
  public function updateColumnValueById($tableName, $column, $id, $value,$databaseProperty=self::FIRST_DB){
    $this->$databaseProperty->selectTable($tableName);
    return $this->$databaseProperty->updateRow(array($column=>$value),$id);
  }

  /**
   * Funkce vracející pole hodnot z daného sloupce (indexované podle ID)
   * @param string $tableName
   * @param string $column
   * @param string $databaseProperty = self::FIRST_DB
   * @return array
   */
  public function getColumnValuesWithId($tableName, $column,$databaseProperty=self::FIRST_DB){
    $this->$databaseProperty->selectTable($tableName);
    return $this->$databaseProperty->getColumnValuesWithId($column);
  }
} 