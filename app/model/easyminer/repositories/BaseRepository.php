<?php

namespace App\Model\EasyMiner\Repositories;

abstract class BaseRepository extends \LeanMapper\Repository
{
  public function find($id)
  {
    $row = $this->connection->select('*')
      ->from($this->getTable())
      ->where( $this->mapper->getPrimaryKey($this->getTable()).'= %i', $id)
      ->fetch();

    if ($row === false) {
      throw new \Exception('Entity was not found.');
    }
    return $this->createEntity($row);
  }

  public function findAll()
  {
    return $this->createEntities(
      $this->connection->select('*')
        ->from($this->getTable())
        ->fetchAll()
    );
  }

  public function findBy($whereArr=null){
    $query=$this->connection->select('*')->from($this->getTable());
    if ($whereArr!=null){
      $query=$query->where($whereArr);
    }
    $row=$query->fetch();
    if ($row === false) {
      throw new \Exception('Entity was not found.');
    }
    return $this->createEntity($row);
  }

  public function findAllBy($whereArr=null,$offset=null,$limit=null){
    $query=$this->connection->select('*')->from($this->getTable());
    if (isset($whereArr['order'])){
      $query->orderBy($whereArr['order']);
      unset($whereArr['order']);
    }
    if ($whereArr!=null && count($whereArr)>0){
      $query=$query->where($whereArr);
    }
    return $this->createEntities($query->fetchAll($offset,$limit));
  }

  public function findCountBy($whereArr=null){
    $query=$this->connection->select('count(*) as pocet')->from($this->getTable());
    if ($whereArr!=null){
      $query=$query->where($whereArr);
    }
    return $query->fetchSingle();
    //exit(var_dump($item));
    //return $item->pocet;
  }

}

