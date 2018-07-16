<?
    // Klassendefinition
    class SymconAlarmNoah extends IPSModule {
 
        public $prefix = "PIALRMNW";

        // Der Konstruktor des Moduls
        // Überschreibt den Standard Kontruktor von IPS
        public function __construct($InstanceID) {
            // Diese Zeile nicht löschen
            parent::__construct($InstanceID);
 
            // Selbsterstellter Code
        }
 
        // Überschreibt die interne IPS_Create($id) Funktion
        public function Create() {

            parent::Create();

            // Scripts checken -und erstellen
            $setValueScript = $this->checkScript("MySetValue", "<?php SetValue(\$IPS_VARIABLE, \$IPS_VALUE); ?>", false);
            $clearLog = $this->checkScript("Historie Löschen", $this->prefix . "_clearLog", true, false); 
            $alarmActivated = $this->checkScript("Alarm aktiviert", $this->prefix . "_clearLog", false, false); 

            // Variablen checken -und erstellen
            $ueberwachung = $this->checkVar("Überwachung", 0, true, $this->InstanceID, 0, false);
            $alarm = $this->checkVar("Alarm", 0, true, $this->InstanceID, 1, false);
            $emailBenachrichtigung = $this->checkVar("E-Mail Benachrichtigung", 0, true, $this->InstanceID, 2, false);
            $pushBenachrichtigung = $this->checkVar("Push Benachrichtigung", 0, true, $this->InstanceID, 3, false);
            $historie = $this->checkVar("Historie", 3, false, $this->InstanceID, 4, false);

            // Targets Ordner checken -und erstellen
            $targets = $this->checkFolder("Targets", $this->InstanceID, 5);
            $events = $this->checkFolder("Events", $this->InstanceID, 6);

            // Profile hinzufügen (wenn nicht automatisiert wie bei switch)
            $this->addProfile($historie, "~HTMLBox");

            // Positionen setzen
            $this->setPosition($clearLog, "last");

            $this->hide($targets);

            $this->RegisterPropertyInteger("Interval", 5);
            $this->RegisterPropertyInteger("EmailInstance", null);

            $this->checkOnAlarmChangedEvent();

 
        }
 
        // Überschreibt die intere IPS_ApplyChanges($id) Funktion
        public function ApplyChanges() {
           
            parent::ApplyChanges();

            $this->refreshTargets();

        }


        ##
        ## Von Außen aufrufbare Funktionen
        ##

        public function clearLog () {

            $logVar = $this->searchObjectByName("Historie");

            SetValue($logVar, "");

        }

        public function addLogMessage ($message, $type = "regular") {

            if ($this->doesExist($this->searchObjectByName("Historie"))) {

                $acutalContent = GetValue($this->searchObjectByName("Historie"));
                $alarmAktiv = GetValue($this->searchObjectByName("Alarm"));
                $timestamp = time();
                $datum = date("d.m.Y - H:i", $timestamp);

                $rmessage = "[" . $datum . "]: " . $message;

                if ($type == "error") {

                    $rmessage = $rmessage . "<span style='color: red;'>" . $rmessage . "</span>";

                } else if ($type == "warning") {

                    $rmessage = "<span style='color: #406fbc;'>" . "WARNING: " . $message . "</span>";

                } else if ($type == "alarm") {

                
                    $rmessage = "<div style='color: red; font-size: 20px; padding: 15px; border: 2px solid red; margin-top: 10px;'><span>" . "[$datum]" . $message . "</span></div>";

                
                } else if ($type == "regular") {

                    $rmessage = "<span style='color: white;'>" . $rmessage . "</span>";

                } else if ($type == "endAlarm") {

                    $rmessage = "</div>";

                }

                $rmessage = $rmessage . "<br />";


                SetValue($this->searchObjectByName("Historie"), $rmessage . $acutalContent);

            }

        }

        public function refreshTargets () {

            $targetsFolder = IPS_GetObject($this->searchObjectByName("Targets"));
            $eventsFolder = IPS_GetObject($this->searchObjectByName("Events"));

            foreach ($targetsFolder['ChildrenIDs'] as $chld) {

                $child = IPS_GetObject($chld);

                if ($child['ObjectType'] == 6) {

                    $child = IPS_GetLink($child['ObjectID']);
                    $link = $child['TargetID'];
                    $eventExists = false;
                    $tgObjName = "";

                    foreach ($eventsFolder['ChildrenIDs'] as $cchild) {

                        $cchildObj = IPS_GetObject($cchild);
                        $tgObjName = $cchildObj['ObjectName'];

                        if ($cchildObj['ObjectType'] == 4) {

                            $cchildObj = IPS_GetEvent($cchildObj['ObjectID']);

                            if ($cchildObj['TriggerVariableID'] == $link) {

                                $eventExists = true;

                            }

                        }

                    }

                    if (!$eventExists) {

                        $this->easyCreateOnChangeFunctionEvent($tgObjName . " " . $child['TargetID'] . " onChange Event", $link, "<?php " . $this->prefix . "_onTargetChange(" . $this->InstanceID . "," . $link . ");", $eventsFolder['ObjectID']);

                    }

                } else {

                    $this->addLogMessage("Objekt " . $child['ObjectName'] . "(#" . $chld['ObjectID'] . ") ist kein Link!", "warning");

                }

            }

        }


        ##
        ##  Set Funktionen
        ## 

        public function onTargetChange ($senderID) {

            $ueberwachung = GetValue($this->searchObjectByName("Überwachung"));
            $senderObj = IPS_GetObject($senderID);
            $senderVal = GetValue($senderID);
            $alarmVal = GetValue($this->searchObjectByName("Alarm"));

            if (!$ueberwachung) {

                return;

            }

            if ($senderVal == true) {
                
                if (!$alarmVal) {

                    $this->startAlarm();
                    $this->addLogMessage(" ALARM ausgelöst von " . $senderObj['ObjectName'] . "!", "alarm");

                } else {

                    $this->addLogMessage($senderObj['ObjectName'] . " hat seinen Zustand verändert!", "regular");

                }

            } else {

                if ($alarmVal) {

                    $this->addLogMessage($senderObj['ObjectName'] . " hat seinen Zustand verändert!", "regular");

                }

            }

        }

        public function startAlarm () {

            $alarmVar = $this->searchObjectByName("Alarm");
            $alarmVal = GetValue($alarmVar);

            if ($alarmVal == false) {

                SetValue($alarmVar, true);

            }

        }

        public function alarmActivated () {

            $ueberwachung = GetValue($this->searchObjectByName("Überwachung"));

            if (!$ueberwachung) {

                return;

            }

            $emailBenachrichtigung = GetValue($this->searchObjectByName("E-Mail Benachrichtigung"));

            if ($emailBenachrichtigung) {

                $emailInstance = $this->ReadPropertyInteger("EmailInstance");

                if ($emailInstance == null) {

                    $this->addLogMessage("Verschicken der E-Mail fehlgeschlagen, keine Instanz gefunden!", "error");

                }

                $email = "Alarm ausgelöst! \n";
            
                $email = $email . "Es wurde ein Alarm ausgelöst! Aktueller Log: \n";

                $email = $email . $this->getFormattedLog();

                SMTP_SendMail($emailInstance, "Alarm!", $email);

            }

        }

        public function onAlarmChange () {

            $alarmVar = $this->searchObjectByName("Alarm");
            $alarmVal = GetValue($alarmVar);
            $interval = $this->ReadPropertyInteger("Interval");

            // Wenn Alarm aktiviert
            if ($alarmVal) {

                IPS_SetScriptTimer($this->searchObjectByName("Alarm aktiviert"), $interval);

            } else {

                IPS_SetScriptTimer($this->searchObjectByName("Alarm aktiviert"), 0);

            }

        }


        public function getFormattedLog () {

            $email = "";

            $logContent = GetValue($this->searchObjectByName("Historie"));

            $logContent = str_replace("<br />", "\n", $logContent);

            $logContent = strip_tags($logContent);

            return $logContent;

        }

        protected function checkOnAlarmChangedEvent () {

            if (!$this->doesExist($this->searchObjectByName("Alarm onChange"))) {

                $this->easyCreateOnChangeFunctionEvent("Alarm onChange", $this->searchObjectByName("Alarm"), "<?php " . $this->prefix . "_onAlarmChange(" . $this->InstanceID . ");", $this->InstanceID);

            }

        }

        ##
        ##  Grundfunktionen
        ##  

        // PI GRUNDFUNKTIONEN
        protected function easyCreateVariable ($type = 1, $name = "Variable", $position = "", $index = 0, $defaultValue = null) {

            if ($position == "") {

                $position = $this->InstanceID;

            }

            $newVariable = IPS_CreateVariable($type);
            IPS_SetName($newVariable, $name);
            IPS_SetParent($newVariable, $position);
            IPS_SetPosition($newVariable, $index);
            IPS_SetIdent($newVariable, $this->nameToIdent($name));
            
            if ($defaultValue != null) {
                SetValue($newVariable, $defaultValue);
            }

            return $newVariable;
        }

        protected function easyCreateScript ($name, $script, $function = true ,$parent = "", $onlywebfront = false) {

            if ($parent == "") {

                $parent = $this->InstanceID;
            
            }

            $newScript = IPS_CreateScript(0);
            
            IPS_SetName($newScript, $name);
            IPS_SetIdent($newScript, $this->nameToIdent($name));
            
            if ($function == true) {

                if ($onlywebfront) {

                    IPS_SetScriptContent($newScript, "<?php if(\$\_IPS['SENDER'] == 'WebFront') { " . $script . "(" . $this->InstanceID . ");" . "} ?>");
                
                } else {

                    IPS_SetScriptContent($newScript, "<?php " . $script . "(" . $this->InstanceID . ");" . " ?>");
                
                }
            } else {

                IPS_SetScriptContent($newScript, $script);
            
            }
            
            IPS_SetParent($newScript, $parent);
            
            return $newScript;
        }

        // Prüft ob Variable bereits existiert und erstellt diese wenn nicht
        protected function checkVar ($var, $type = 1, $profile = false , $position = "", $index = 0, $defaultValue = null) {

            if ($this->searchObjectByName($var) == 0) {
                
                $nVar = $this->easyCreateVariable($type, $var ,$position, $index, $defaultValue);
                
                if ($type == 0 && $profile == true) {
                    $this->addSwitch($nVar);
                }
                
                if ($type == 1 && $profile == true) {
                    $this->addTime($nVar);
                }
                
                if ($position != "") {
                    IPS_SetParent($nVar, $position);
                }
                
                if ($index != 0) {
                    IPS_SetPosition($nVar, $index);
                }
                
                return $nVar;

            } else {

                return $this->searchObjectByName($var);
            
            }
        }

        protected function searchObjectByName ($name, $searchIn = null, $objectType = null) {

            if ($searchIn == null) {

                $searchIn = $this->InstanceID;
            
            }
            
            $childs = IPS_GetChildrenIDs($searchIn);
            
            $returnId = 0;
            
            foreach ($childs as $child) {

                $childObject = IPS_GetObject($child);

                if ($childObject['ObjectIdent'] == $this->nameToIdent($name)) {
                    
                    $returnId = $childObject['ObjectID'];

                }

                if ($objectType == null) {
                    
                    if ($childObject['ObjectIdent'] == $this->nameToIdent($name)) {
                        
                        $returnId = $childObject['ObjectID'];
    
                    }

                } else {
                    
                    if ($childObject['ObjectIdent'] == $this->nameToIdent($name) && $childObject['ObjectType'] == $objectType) {
                        
                        $returnId = $childObject['ObjectID'];
    
                    }
                }
            }

            return $returnId;

        }

        protected function addSwitch ($vid) {

            if(IPS_VariableProfileExists("Switch"))
            {
                IPS_SetVariableCustomProfile($vid,"Switch");
                IPS_SetVariableCustomAction($vid, $this->searchObjectByName("MySetValue"));
            
            }

        }

        protected function addTime ($vid) {

            if (IPS_VariableProfileExists("~UnixTimestampTime")) {

                IPS_SetVariableCustomProfile($vid, "~UnixTimestampTime");
                IPS_SetVariableCustomAction($vid, $this->searchObjectByName("~UnixTimestampTime"));
            
            }
        }

        protected function doesExist ($id) {

            if (IPS_ObjectExists($id) && $id != 0) {
                
                return true;

            } else {

                return false;
            
            }
        }

        protected function nameToIdent ($name) {

            $name = str_replace(" ", "", $name);
            $name = str_replace("ä", "ae", $name);
            $name = str_replace("ü", "ue", $name);
            $name = str_replace("ö", "oe", $name);
            $name = str_replace("Ä", "Ae", $name);
            $name = str_replace("Ö", "Oe", $name);
            $name = str_replace("Ü", "Ue", $name);
            $name = preg_replace ( '/[^a-z0-9 ]/i', '', $name);

            return $name . $this->InstanceID;

        }

        protected function addProfile ($id, $profile, $useSetValue = true) {

            if (IPS_VariableProfileExists($profile)) {

                IPS_SetVariableCustomProfile($id, $profile);
                
                if ($useSetValue) {

                    IPS_SetVariableCustomAction($id, $this->searchObjectByName("MySetValue"));
                
                }
            }
        }

        protected function checkScript ($name, $script, $function = true, $hide = true) {

            if ($this->searchObjectByName($name) == 0) {
                
                $script = $this->easyCreateScript($name, $script, $function);
                
                if ($hide) {

                    $this->hide($script);

                }

                return $script;
            
            }
        }
 
        protected function hide ($id) {

            IPS_SetHidden($id, true);

        }

        protected function setPosition ($id, $position) {

            if ($this->doesExist($id)) {

                if (gettype($position) == "string") {

                    if ($position == "last" || $position == "Last") {

                        $own = IPS_GetObject($this->InstanceID);

                        $lastChildPosition = 0;
                        $highestChildPositon = 0;

                        foreach ($own['ChildrenIDs'] as $child) {

                            $chld = IPS_GetObject($child);

                            if ($chld['ObjectPosition'] >= $highestChildPositon) {

                                $highestChildPositon = $chld['ObjectPosition'];

                            }

                        }

                        IPS_SetPosition($id, $highestChildPositon + 1);

                    }

                } else {

                    IPS_SetPosition($id, $position);

                }

            }

        }

        protected function checkFolder ($name, $parent ,$index = 100000) {
            
            if ($this->doesExist($this->searchObjectByName($name, $parent)) == false) {
                
                $targets = $this->createFolder($name);
                
                $this->hide($targets);
                
                if ($index != null ) {
                    
                    IPS_SetPosition($targets, $index);
                
                }
                
                if ($parent != null) {
                    
                    IPS_SetParent($targets, $parent);
                
                }
                
                return $targets;

            } else {

                return $this->searchObjectByName($name, $parent);

            }
        }

        protected function createFolder ($name) {

            $units = IPS_CreateInstance($this->getModuleGuidByName());
            IPS_SetName($units, $name);
            IPS_SetIdent($units, $this->nameToIdent($name));
            IPS_SetParent($units, $this->InstanceID);
            return $units;

        }

        protected function getModuleGuidByName ($name = "Dummy Module") {
            
            $allModules = IPS_GetModuleList();
            $GUID = ""; 
            
            foreach ($allModules as $module) {

                if (IPS_GetModule($module)['ModuleName'] == $name) {
                    $GUID = $module;
                    break;
                }

            }

            return $GUID;
        } 

        protected function easyCreateOnChangeFunctionEvent ($onChangeEventName, $targetId, $function, $parent = null) {

            if ($parent == null) {

                $parent = $this->InstanceID;
            }

            $eid = IPS_CreateEvent(0);
            IPS_SetEventTrigger($eid, 0, $targetId);
            IPS_SetParent($eid, $parent);
            IPS_SetEventScript($eid, $function);
            IPS_SetName($eid, $onChangeEventName);
            IPS_SetEventActive($eid, true);

            return $eid;

        }

    }
?>