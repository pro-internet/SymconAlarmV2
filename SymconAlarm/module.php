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

            // Variablen checken -und erstellen
            $ueberwachung = $this->checkVar("Überwachung", 0, true, $this->InstanceID, 0, false);
            $alarm = $this->checkVar("Alarm", 0, true, $this->InstanceID, 0, false);
            $emailBenachrichtigung = $this->checkVar("E-Mail Benachrichtigung", 0, true, $this->InstanceID, 0, false);
            $pushBenachrichtigung = $this->checkVar("Push Benachrichtigung", 0, true, $this->InstanceID, 0, false);
            $historie = $this->checkVar("Historie", 3, false, $this->InstanceID, 0, false);


            // Profile hinzufügen (wenn nicht automatisiert wie bei switch)
            $this->addProfile($historie, "~HTMLBox");

            // Objekte verstecken
            $this->hide($setValueScript);
 
        }
 
        // Überschreibt die intere IPS_ApplyChanges($id) Funktion
        public function ApplyChanges() {
           
            parent::ApplyChanges();

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

        protected function checkScript ($name, $script, $function = true) {

            if ($this->searchObjectByName($name) == 0) {
                
                $script = $this->easyCreateScript($name, $script, $function);
                $this->hide($script);
            
            }
        }
 
        protected function hide ($id) {

            IPS_SetHidden($id, true);

        }

    }
?>