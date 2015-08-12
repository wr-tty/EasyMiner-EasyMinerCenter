<?php

namespace EasyMinerCenter\Model\Mining;
use EasyMinerCenter\Model\EasyMiner\Entities\Miner;
use EasyMinerCenter\Model\EasyMiner\Entities\Task;
use EasyMinerCenter\Model\EasyMiner\Entities\TaskState;
use EasyMinerCenter\Model\EasyMiner\Facades\MetaAttributesFacade;
use EasyMinerCenter\Model\EasyMiner\Facades\MinersFacade;
use EasyMinerCenter\Model\EasyMiner\Facades\RulesFacade;

/**
 * Class IMiningDriver - rozhraní pro unifikaci práce s dataminingovými nástroji
 * @package EasyMinerCenter\Model\mining
 */
interface IMiningDriver {

  /**
   * Funkce pro definování úlohy na základě dat z EasyMineru
   * @return TaskState
   */
  public function startMining();

  /**
   * Funkce pro zastavení dolování
   * @return TaskState
   */
  public function stopMining();

  /**
   * Funkce vracející info o aktuálním stavu dané úlohy
   * @return TaskState
   */
  public function checkTaskState();

  /**
   * Funkce pro načtení výsledků z DM nástroje a jejich uložení do DB

  public function importResults();
*/
  /**
   * @param Task $task
   * @param MinersFacade $minersFacade
   * @param RulesFacade $rulesFacade
   * @param MetaAttributesFacade $metaAttributesFacade
   * @param array $params = array() - parametry výchozí konfigurace
   */
  public function __construct(Task $task=null, MinersFacade $minersFacade, RulesFacade $rulesFacade,MetaAttributesFacade $metaAttributesFacade, $params = array());

  /**
   * Funkce pro kontrolu, jestli je dostupný dolovací server
   * @param string $serverUrl
   * @throws \Exception
   * @return bool
   */
  public static function checkMinerServerState($serverUrl);

  /**
   * Funkce pro nastavení aktivní úlohy
   * @param Task $task
   */
  public function setTask(Task $task);

  /**
   * Funkce pro kontrolu konfigurace daného mineru (včetně konfigurace atributů...)
   */
  public function checkMinerState();

  /**
   * Funkce volaná před smazáním konkrétního mineru
   * @return mixed
   */
  public function deleteMiner();

} 