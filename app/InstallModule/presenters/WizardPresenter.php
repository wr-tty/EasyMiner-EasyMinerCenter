<?php

namespace EasyMinerCenter\InstallModule\Presenters;

use EasyMinerCenter\InstallModule\Model\ConfigManager;
use EasyMinerCenter\InstallModule\Model\DatabaseManager;
use EasyMinerCenter\InstallModule\Model\FilesManager;
use Nette\Application\UI\Form;
use Nette\Application\UI\Presenter;
use Nette\Forms\Controls\SubmitButton;
use Nette\Forms\Controls\TextInput;
use Nette\Neon\Neon;

/**
 * Class WizardPresenter
 *
 * @package EasyMinerCenter\InstallModule\Presenters
 */
class WizardPresenter extends Presenter {
  /** @var null|ConfigManager $configManager */
  private $configManager=null;
  /** @var array $wizardSteps - pole s definicí jednotlivých kroků instalačního wizardu */
  private $wizardSteps=[
    'default',
    'files',        //kontrola přístupů k souborům a adresářům
    'timezone',     //nastavení časové zóny
    'database',     //inicializace aplikační databáze
    'dataMysql',    //přístupy k databázi pro uživatelská data
    'logins',       //typy přihlašování
    'miners',       //zadání přístupů k dolovacím serverům
    'details',      //zadání dalších parametrů/detailů
    'setPassword',  //kontrola zadání přístupového hesla
    'finish'        //zpráva o dokončení
  ];

  /**
   * Tento presenter nemá výchozí view => přesměrování na vhodný krok
   */
  public function actionDefault() {
    $this->redirect($this->getNextStep('default'));
  }

  /**
   * Akce pro kontrolu přístupů ke složkám a souborům
   */
  public function actionFiles() {
    $filesManager=$this->createFilesManager();
    $writableDirectories=$filesManager->checkWritableDirectories();
    $writableFiles=$filesManager->checkWritableFiles();
    $stateError=false;
    $statesArr=[];
    if(!empty($writableDirectories)) {
      foreach($writableDirectories as $directory=>$state) {
        $statesArr['Directory: '.$directory]=$state;
        if(!$state) {
          $stateError=true;
        }
      }
    }
    if(!empty($writableFiles)) {
      foreach($writableFiles as $file=>$state) {
        $statesArr['File: '.$file]=$state;
        if(!$state) {
          $stateError=true;
        }
      }
    }

    if($stateError) {
      //vyskytla se chyba
      $this->setView('statesTable');
      $this->template->title='Writable files and directories';
      $this->template->text='Following files and directories has to been created and writable. If you are not sure in user access credentials, please modify them to 777. The paths are written from the content root, where the EasyMinerCenter is located. In case of non-existing files, you can also make writable the appropriate directory and the file will be created automatically.';
      $this->template->states=$statesArr;
    } else {
      $this->redirect($this->getNextStep('files'));
    }
  }

  /**
   * Akce pro zadání přístupového hesla pro instalaci
   * @param string $backlink
   */
  public function actionCheckPassword($backlink="") {
    /** @var Form $form */
    $form=$this->getComponent('checkPasswordForm');
    $form->setDefaults(['backlink'=>$backlink]);
  }
  
  /**
   * Akce pro nastavení vyžadované časové zóny
   */
  public function actionTimezone() {
    $this->checkAccessRights();
    $configManager=$this->createConfigManager();
    if(!empty($configManager->data['php']['date.timezone'])) {
      $currentTimezone=$configManager->data['php']['date.timezone'];
    } else {
      $currentTimezone=@date_default_timezone_get();
    }
    if(!empty($currentTimezone)) {
      /** @var Form $form */
      $form=$this->getComponent('timezoneForm');
      $form->setDefaults(['timezone'=>$currentTimezone]);
    }
  }

  /**
 * Akce pro zadání přístupu k hlavní aplikační databázi
 */
  public function actionDatabase() {
    $this->checkAccessRights();
    $configManager=$this->createConfigManager();
    if(!empty($configManager->data['parameters']['mainDatabase'])) {
      //set currently defined database connection params
      try{
        /** @var Form $form */
        $form=$this->getComponent('mainDatabaseForm');
        $form->setDefaults($configManager->data['parameters']['mainDatabase']);
      }catch (\Exception $e){/*ignore error*/}
    }
  }

  /**
   * Akce pro zadání přístupu k MySQL databázi pro uživatelská data
   */
  public function actionDataMysql() {
    $this->checkAccessRights();
    $configManager=$this->createConfigManager();
    if(!empty($configManager->data['parameters']['databases']['mysql'])) {
      //set currently defined database connection params
      try{
        /** @var Form $form */
        $form=$this->getComponent('dataMysqlForm');
        $form->setDefaults($configManager->data['parameters']['databases']['mysql']);
      }catch (\Exception $e){/*ignore error*/}
    }
  }

  /**
   * Akce pro zadání způsobů přihlašování
   */
  public function actionLogins() {
    $this->checkAccessRights();
    //naplnění výchozích parametrů
    $configManager=$this->createConfigManager();
    /** @var Form $form */
    $form=$this->getComponent('loginsForm');

    if(!empty($configManager->data['facebook'])) {
      //set currently defined params
      try{
        $form->setDefaults([
          'allow_facebook'=>1,
          'facebookAppId'=>$configManager->data['facebook']['appId'],
          'facebookAppSecret'=>$configManager->data['facebook']['appSecret']
        ]);
      }catch (\Exception $e){/*ignore error*/}
    }

    if(!empty($configManager->data['google'])) {
      //set currently defined params
      try{
        $form->setDefaults([
          'allow_google'=>1,
          'googleClientId'=>$configManager->data['google']['clientId'],
          'googleClientSecret'=>$configManager->data['google']['clientSecret']
        ]);
      }catch (\Exception $e){/*ignore error*/}
    }

  }

  /**
   * Akce pro volbu podporovaných typů minerů
   */
  public function actionMiners() {
    $this->checkAccessRights();
    $configManager=$this->createConfigManager();
    if(!empty($configManager->data['parameters']['miningDriverFactory'])) {
      /** @var array $configManagerValuesArr */
      $configManagerValuesArr=$configManager->data['parameters']['miningDriverFactory'];
      //set currently defined database connection params
      try{
        /** @var Form $form */
        $form=$this->getComponent('minersForm');
        $defaultsArr=[];
        if (empty($configManagerValuesArr['driver_r'])){
          $defaultsArr['allow_driverR']=0;
        }else{
          $defaultsArr['allow_driverR']=1;
          $defaultsArr['driverRServer']=$configManagerValuesArr['driver_r']['server'].@$configManagerValuesArr['driver_r']['minerUrl'];
        }
        if (empty($configManagerValuesArr['driver_lm'])){
          $defaultsArr['allow_driverLM']=0;
        }else{
          $defaultsArr['allow_driverLM']=1;
          $defaultsArr['driverLMServer']=$configManagerValuesArr['driver_lm']['server'];
        }
        $form->setDefaults($defaultsArr);
      }catch (\Exception $e){/*ignore error*/}
    }
  }

  /**
   * Akce pro zadání doplňujících parametrů
   */
  public function actionDetails() {
    $this->checkAccessRights();
    $configManager=$this->createConfigManager();
    if(!empty($configManager->data['parameters']['mail_from'])) {
      //set currently defined database connection params
      try{
        /** @var Form $form */
        $form=$this->getComponent('detailsForm');
        $form->setDefaults(['mail_from'=>$configManager->data['parameters']['mail_from']]);
      }catch (\Exception $e){/*ignore error*/}
    }
    //TODO configure automatically filled-in params
  }
  
  /**
   * Akce pro ukončení průvodce - smazání cache, přesměrování
   */
  public function actionFinish() {
    //TODO actionFinish
  }


  /**
   * Formulář pro nastavení časové zóny
   * @return Form
   */
  public function createComponentTimezoneForm() {
    $form=new Form();
    $timezonesIdentifiersArr=\DateTimeZone::listIdentifiers();
    $valuesArr=[];
    foreach($timezonesIdentifiersArr as $identifier) {
      $valuesArr[$identifier]=$identifier;
    }
    $form->addSelect('timezone', 'Use time zone:', $valuesArr);
    $form->addSubmit('save', 'Save & continue...')->onClick[]=function (SubmitButton $submitButton) {
      //save the timezone
      $configManager=$this->createConfigManager();
      $timezoneIdentifier=$submitButton->getForm()->getValues()->timezone;
      $configManager->data['php']['date.timezone']=$timezoneIdentifier;
      $configManager->saveConfig();
      //redirect to the next step
      $this->redirect($this->getNextStep('timezone'));
    };
    return $form;
  }

  /**
   * Formulář pro zadání přístupů k databázi pro aplikační data
   * @return Form
   */
  public function createComponentMainDatabaseForm() {
    $form=new Form();
    $form->addSelect('driver', 'Database type:', ['mysqli'=>'MySQL']);
    $form->addText('host', 'Server:')
      ->setRequired('Input the server address!');
    $form->addText('username', 'Database username:')
      ->setRequired('Input the database username!');
    $form->addText('password', 'Database password:');
    $form->addText('database', 'Database name:')
      ->setRequired('Input the name of a database for the application data!');
    $form->addSubmit('submit', 'Save & continue...')
      ->onClick[]=function (SubmitButton $submitButton) {
        $form=$submitButton->form;
        $databaseConfigArr=$form->getValues(true);
        $error=false;
        try {
          $databaseManager=new DatabaseManager($databaseConfigArr);
          if(!$databaseManager->isConnected()) {
            $error=true;
          }
        } catch(\Exception $e) {
          $error=true;
        }
        if($error || empty($databaseManager)) {
          $form->addError('Database connection failed! Please check, if the database exists and if it is accessible.');
          return;
        }
        //create database
        try{
          /** @noinspection PhpUndefinedVariableInspection */
          $databaseManager->createDatabase();
        }catch (\Exception $e){
          $form->addError('Database structure creation failed! Please check, if the database exists and if it is empty.');
          return;
        }
        //save config and redirect
        $configManager=$this->createConfigManager();
        $configManager->data['parameters']['mainDatabase']=$databaseConfigArr;
        $configManager->saveConfig();
        $this->redirect($this->getNextStep('database'));
      };
    return $form;
  }

  /**
   * Formulář pro zadání přístupů k MySQL pro uživatelská data
   * @return Form
   */
  public function createComponentDataMysqlForm() {
    $form=new Form();
    $form->addText('server', 'Server:')
      ->setRequired('Input the server address!');
    $form->addText('username', 'Database administrator username:')
      ->setRequired('Input the database administrator username!');
    $form->addText('password', 'Database administrator password:');
    $form->addText('_username', 'Database users´ names mask:')
      ->setRequired("You have to input the mask for names of the user accounts!")
      ->setDefaultValue('emc_*')
      ->addRule(Form::PATTERN,'The mask has to contain only lower case letters, numbers and _, has to start with a letter and has to contain the char *.','^[a-z_]+[a-z0-9_]*\*[a-z0-9_]*$')
      ->addRule(Form::MAX_LENGTH,'The max length of username pattern is %s characters.',12);
    $form->addText('_database', 'User databases name mask:')
      ->setRequired("You have to input the mask for names of the user databases!")
      ->setDefaultValue('emc_*')
      ->addRule(Form::PATTERN,'The mask has to contain only lower case letters, numbers and _, has to start with a letter and has to contain the char *.','^[a-z_]+[a-z0-9_]*\*[a-z0-9_]*$')
      ->addRule(Form::MAX_LENGTH,'The max length of username pattern is %s characters.',60);
    $form->addSelect('allowFileImport','Allow file imports:',[
      0=>'no',
      1=>'yes',
    ]);

    $form->addSubmit('submit', 'Save & continue...')
      ->onClick[]=function (SubmitButton $submitButton) {
      $form=$submitButton->form;
      $values=$form->getValues(true);
      /*TODO doplnění kontroly správnosti přístupových údajů
      try {
        $databaseManager=new DatabaseManager($databaseConfigArr);
        if(!$databaseManager->isConnected()) {
          $error=true;
        }
      } catch(\Exception $e) {
        $error=true;
      }
      if($error || empty($databaseManager)) {
        $form->addError('Database connection failed! Please check, if the database exists and if it is accessible.');
        return;
      }
      //create database
      try{
        $databaseManager->createDatabase();
      }catch (\Exception $e){
        $form->addError('Database structure creation failed! Please check, if the database exists and if it is empty.');
        return;
      }
      */
      //save config and redirect
      $configManager=$this->createConfigManager();
      if ($values['allowFileImport']){
        $values['allowFileImport']=true;
      }else{
        $values['allowFileImport']=false;
      }
      $configManager->data['parameters']['databases']['mysql']=$values;
      $configManager->saveConfig();
      $this->redirect($this->getNextStep('dataMysql'));
    };
    return $form;
  }

  /**
   * Formulář pro zadání údajů pro přihlašování přes sociální sítě
   * @return Form
   */
  public function createComponentLoginsForm() {
    $form=new Form();
    $form->addSelect('allow_local','Allow local user accounts:',[1=>'yes'])
      ->setAttribute('readonly')
      ->setAttribute('class','withSpace')
      ->setDisabled(true);
    $allowFacebook=$form->addSelect('allow_facebook','Allow Facebook login:',[0=>'no',1=>'yes']);
    $allowFacebook->addCondition(Form::EQUAL,1)
      ->toggle('facebookAppId',true)
      ->toggle('facebookAppSecret',true);
    $form->addText('facebookAppId','Facebook App ID:')
      ->setOption('id','facebookAppId')
      ->addConditionOn($allowFacebook,Form::EQUAL,1)
        ->setRequired('You have to input Facebook App ID!');
    $form->addText('facebookAppSecret','Facebook Secret Key:',null,32)
      ->setAttribute('class','withSpace')
      ->setOption('id','facebookAppSecret')
      ->addConditionOn($allowFacebook,Form::EQUAL,1)
        ->setRequired('You have to input Facebook Secret Key!')
        ->addRule(Form::LENGTH,'Secret Key length has to be %s chars.',32);

    $allowGoogle=$form->addSelect('allow_google','Allow Google login:',[0=>'no',1=>'yes']);
    $allowGoogle->addCondition(Form::EQUAL,1)
      ->toggle('googleClientId',true)
      ->toggle('googleClientSecret',true);
    $form->addText('googleClientId','Google Client ID:')
      ->setOption('id','googleClientId')
      ->addConditionOn($allowGoogle,Form::EQUAL,1)
        ->setRequired('You have to input Google Client ID!');
    $form->addText('googleClientSecret','Google Secret Key:',null,24)
      ->setOption('id','googleClientSecret')
      ->addConditionOn($allowGoogle,Form::EQUAL,1)
        ->setRequired('You have to input Google Secret Key!')
        ->addRule(Form::LENGTH,'Secret Key length has to be %s chars.',24);;

    $form->addSubmit('submit','Save & continue...')
      ->onClick[]=function(SubmitButton $submitButton){
        $values=$submitButton->form->getValues(true);
        $configManager=$this->createConfigManager();
        if ($values['allow_facebook']==1){
          $configManager->data['facebook']=[
            'appId'=>$values['facebookAppId'],
            'appSecret'=>$values['facebookAppSecret']
          ];
        }else{
          $configManager->data['facebook']=[
            'appId'=>'',
            'appSecret'=>'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx'
          ];
        }
        if ($values['allow_google']==1){
          $configManager->data['google']=[
            'clientId'=>$values['googleClientId'],
            'clientSecret'=>$values['googleClientSecret']
          ];
        }else{
          $configManager->data['google']=[
            'clientId'=>'',
            'clientSecret'=>'xxxxxxxxxxxxxxxxxxxxxxxx'
          ];
        }
        $this->redirect($this->getNextStep('logins'));
      };
    return $form;
  }

  /**
   * Formulář pro zadání údajů pro přístup k minerům
   * @return Form
   */
  public function createComponentMinersForm() {
    $form=new Form();

    $allowDriverR=$form->addSelect('allow_driverR','Allow R backend:',[0=>'no',1=>'yes']);
    $allowDriverR->addCondition(Form::EQUAL,1)
      ->toggle('driverRServer',true);
    $form->addText('driverRServer','R backend URL:')
      ->setOption('id','driverRServer')
      ->setAttribute('class','withSpace')
      ->addConditionOn($allowDriverR,Form::EQUAL,1)
        ->setRequired('You have to input the address of R backend!')
        ->addRule(function(TextInput $textInput){
          //TODO doplnění kontroly funkčnosti daného backendu
          return true;
        },'The R backend is not accessible! Please check the configuration.');

    $allowDriverLM=$form->addSelect('allow_driverLM','Allow LISp-Miner backend (LM-Connect):',[0=>'no',1=>'yes']);
    $allowDriverLM->addCondition(Form::EQUAL,1)
      ->toggle('driverLMServer',true);
    $form->addText('driverLMServer','LM-Connect backend URL:')
      ->setOption('id','driverLMServer')
      ->setAttribute('class','withSpace')
      ->addConditionOn($allowDriverLM,Form::EQUAL,1)
        ->setRequired('You have to input the address of LISp-Miner backend!')
        ->addRule(function(TextInput $textInput){
          //TODO doplnění kontroly funkčnosti daného backendu
          return true;
        },'The R backend is not accessible! Please check the configuration.');


    $form->addSubmit('submit','Save & continue')
      ->onClick[]=function(SubmitButton $submitButton){
        $values = $submitButton->form->getValues(true);
        $filesManager=$this->createFilesManager();
        $configManager=$this->createConfigManager();

        if (!($values['allow_driverLM']||$values['allow_driverR'])){
          $submitButton->form->addError('You have to configure at least one data mining backend!');
          return;
        }

        //region R backend
        if ($values['allow_driverR']){
          $urlArr=parse_url($values['driverRServer']);
          if (function_exists('http_build_url')){
            $serverUrl=http_build_url($urlArr,HTTP_URL_STRIP_PATH | HTTP_URL_STRIP_QUERY);
            $minerPath=$urlArr['path'].(!empty($urlArr['query'])?'?'.$urlArr['query']:'');
          }else{
            exit(var_dump($urlArr));
          }

          exit(var_dump($url));
          exit(var_dump($urlArr));
          return;
          $configArr=[
            'server'=>null,
            'minerUrl'=>null,
            'importsDirectory'=>$filesManager->getPath('writable','directories','importsR',true)
          ];
        }else{
          $configArr=['server'=>''];
        }
        $configManager->data['miningDriverFactory']['driver_r']=$configArr;
        //endregion R backend

        //region LM backend
        if ($values['allow_driverLM']){
          $configArr=[
            'server'=>$values['driverLMServer'],
            'importsDirectory'=>$filesManager->getPath('writable','directories','importsLM',true)
          ];
        }else{
          $configArr=['server'=>''];
        }
        $configManager->data['miningDriverFactory']['driver_lm']=$configArr;
        //endregion LM backend

        $configManager->saveConfig();
        $this->redirect($this->getNextStep('miners'));
      };

    return $form;
  }

  /**
   * Formulář pro zadání doplňujících parametrů
   * @return Form
   */
  public function createComponentDetailsForm() {
    $form = new Form();
    $form->addText('mail_from','Send application e-mails from:')
      ->setAttribute('placeholder','sender@server.tld')
      ->setRequired(true)
      ->addRule(Form::EMAIL,'Input valid e-mail address!');
    $form->addSubmit('submit','Save & continue')->onClick[]=function(SubmitButton $submitButton){
      //uložení e-mailu
      $configManager=$this->createConfigManager();
      $values=$submitButton->form->getValues(true);
      $configManager->data['parameters']['mail_from']=$values['mail_from'];
      $configManager->saveConfig();
      $this->redirect($this->getNextStep('details'));
    };
    return $form;
  }

  /**
   * Formulář pro zadání hesla pro opětovnou instalaci
   * @return Form
   */
  public function createComponentSetPasswordForm() {
    $form = new Form();
    $password=$form->addPassword('password','Installation password:')
      ->setRequired("You have to input installation password!");
    $form->addPassword('rePassword','Installation password again:')
      ->setRequired("You have to input installation password!")
      ->addRule(Form::EQUAL,'Passwords does not match!',$password);
    $form->addSubmit('submit','Save & continue')
      ->onClick[]=function(SubmitButton $submitButton){
        //uložení instalačního hesla
        $values=$submitButton->form->getValues(true);
        $configManager=$this->createConfigManager();
        $configManager->saveInstallationPassword($values['password']);
      };
    return $form;
  }

  /**
   * Formulář pro kontrolu přístupového instalačního hesla
   * @return Form
   */
  public function createComponentCheckPasswordForm() {
    $form=new Form();
    $form->addHidden('backlink','');
    $form->addPassword('password','Installation password')
      ->setRequired('You have to input the installation password!');
    $form->addSubmit('submit','OK')
      ->onClick[]=function(SubmitButton $submitButton){
        $values=$submitButton->form->getValues(true);
        $configManager=$this->createConfigManager();
        if ($configManager->checkInstallationPassword($values['password'])){
          //heslo je správně
          if (!empty($values['backlink'])){
            $this->restoreRequest($values['backlink']);
          }
          $this->redirect($this->getNextStep('default'));
        }else{
          //heslo je chybně
          $submitButton->form->addError('The installation password is invalid!');
        }
      };
    return $form;
  }

  /**
   * Funkce pro kontrolu, jestli je aktuální uživatel oprávněn pracovat s wizardem
   */
  private function checkAccessRights() {
    $session=$this->getSession('InstallModule');
    if (@$session->passwordChecked!=true){
      $configManager=$this->createConfigManager();
      if ($configManager->isSetInstallationPassword()){
        //redirect na zadání hesla pro přístup k instalaci
        $this->redirect('checkPassword', array('backlink' => $this->storeRequest()));
      }
    }
  }

  /**
   * Funkce vracející URL dalšího kroku wizardu
   *
   * @param string $currentStep
   * @return string
   */
  private function getNextStep($currentStep) {
    $index=array_search($currentStep, $this->wizardSteps);
    return $this->wizardSteps[$index+1];
  }

  /**
   * Funkce vracející instanci config manageru
   * @return ConfigManager
   */
  private function createConfigManager() {
    if (!($this->configManager instanceof ConfigManager)){
      $this->configManager=new ConfigManager(FilesManager::getRootDirectory().'/app/config/config.local.neon');
    }
    return $this->configManager;
  }

  /**
   * Funkce vracející instanci files manageru
   * @return FilesManager
   */
  private function createFilesManager(){
    return new FilesManager(Neon::decode(file_get_contents(__DIR__.'/../data/files.neon')));
  }
}