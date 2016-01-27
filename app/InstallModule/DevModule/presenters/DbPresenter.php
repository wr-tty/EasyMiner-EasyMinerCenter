<?php

namespace EasyMinerCenter\InstallModule\DevModule\Presenters;
use EasyMinerCenter\InstallModule\DevModule\Model\Git;
use EasyMinerCenter\InstallModule\DevModule\Model\MysqlDump;
use EasyMinerCenter\InstallModule\Model\ConfigManager;
use EasyMinerCenter\InstallModule\Model\FilesManager;

/**
 * Class DbDumpPresenter - presenter pro možnost exportu struktury databáze z vývojového serveru na GITHUB
 *
 * @package EasyMinerCenter\DevModule\Presenters
 * @author Stanislav Vojíř
 */
class DbPresenter extends BasePresenter{
  /** @var  ConfigManager $configManager */
  private $configManager;

  /**
   * Akce pro export struktury databáze a jeho kontrola oproti uloženému souboru
   * @throws \Nette\Application\AbortException
   */
  public function actionUpdateOnGit() {
    $message='';
    if (!MysqlDump::checkRequirements($message)){
      $this->sendJson(['state'=>'error','message'=>$message]);
      return;
    }

    #region reset git repository
    $git = new Git(FilesManager::getRootDirectory());
    $git->reset();
    #endregion

    #region export struktury databáze
    $mainDatabaseConfig=$this->configManager->data['parameters']['mainDatabase'];
    $dumpContent=MysqlDump::dumpStructureToFile($mainDatabaseConfig['host'],!empty($mainDatabaseConfig['port'])?$mainDatabaseConfig['port']:null,$mainDatabaseConfig['username'],$mainDatabaseConfig['password'],$mainDatabaseConfig['database']);
    $dump='';
    if (!empty($dumpContent)){
      foreach ($dumpContent as $row){
        $dump.=$row."\r\n";
      }
    }
    if ($dump==MysqlDump::getExistingSqlFileContent()){
      $this->sendJson(['state'=>'OK','message'=>'Files are identical.']);
      return;
    }
    //uložení obsahu
    MysqlDump::saveSqlFileContent($dump);
    #endregion export struktury databáze

    #region commit
    $git->addFileToCommit(realpath('../../data/mysql.sql'));

    #endregion commit
    $this->sendJson(['state'=>'OK','message'=>'Updated.']);
  }


  /**
   * Konstruktor, který zároveň získává přístup k lokální konfiguraci
   */
  public function __construct() {
    parent::__construct();
    $this->configManager=new ConfigManager(FilesManager::getRootDirectory().'/app/config/config.local.neon');
  }

}