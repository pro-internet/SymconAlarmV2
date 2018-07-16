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

            $ueberwachung = $this->checkVar(0, "Überwachung", true, 0, 0, false);
            $alarm = $this->checkVar(0, "Alarm", true, 0, 0, false);
            $emailBenachrichtigung = $this->checkVar(0, "E-Mail Benachrichtigung", true, 0, 0, false);
            $pushBenachrichtigung = $this->checkVar(0, "Push Benachrichtigung", true, 0, 0, false);
            //$historie = $this->checkVar(0, "E-Mail Benachrichtigung", true, 0, 0, false);
 
        }
 
        // Überschreibt die intere IPS_ApplyChanges($id) Funktion
        public function ApplyChanges() {
           
            parent::ApplyChanges();

        }






        ##
        ##  Grundfunktionen
        ##  

        // PI GRUNDFUNKTIONEN
        private function easyCreateVariable ($type = 1, $name = "Variable", $position = "", $index = 0, $defaultValue = null) {

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

        // Prüft ob Variable bereits existiert und erstellt diese wenn nicht
        private function checkVar ($var, $type = 1, $profile = false , $position = "", $index = 0, $defaultValue = null) {

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

        private function searchObjectByName ($name, $searchIn = null, $objectType = null) {

            if ($searchIn == null) {

                $searchIn = $this->InstanceID;
            
            }
            
            $childs = IPS_GetChildrenIDs($searchIn);
            
            $returnId = 0;
            
            foreach ($childs as $child) {

                $childObject = IPS_GetObject($child);

                if ($childObject['ObjectIdent'] == $name) {
                    
                    $returnId = $childObject['ObjectID'];

                }

                if ($objectType == null) {
                    
                    if ($childObject['ObjectIdent'] == $name) {
                        
                        $returnId = $childObject['ObjectID'];
    
                    }

                } else {
                    
                    if ($childObject['ObjectIdent'] == $name && $childObject['ObjectType'] == $objectType) {
                        
                        $returnId = $childObject['ObjectID'];
    
                    }
                }
            }

            return $returnId;

        }

        private function addSwitch ($vid) {

            if(IPS_VariableProfileExists("Switch"))
            {
                IPS_SetVariableCustomProfile($vid,"Switch");
                IPS_SetVariableCustomAction($vid, $this->searchObjectByName("MySetValue"));
            
            }

        }

        private function addTime ($vid) {

            if (IPS_VariableProfileExists("~UnixTimestampTime")) {

                IPS_SetVariableCustomProfile($vid, "~UnixTimestampTime");
                IPS_SetVariableCustomAction($vid, $this->searchObjectByName("~UnixTimestampTime"));
            
            }
        }

        private function doesExist ($id) {

            if (IPS_ObjectExists($id) && $id != 0) {
                
                return true;

            } else {

                return false;
            
            }
        }

        private function nameToIdent ($name) {

            $name = str_replace(" ", "", $name);
            $name = str_replace("ä", "ae", $name);
            $name = str_replace("ü", "ue", $name);
            $name = str_replace("ö", "oe", $name);
            $name = str_replace("Ä", "Ae", $name);
            $name = str_replace("Ö", "Oe", $name);
            $name = str_replace("Ü", "Ue", $name);
            $name = preg_replace ( '/[^a-z0-9 ]/i', '', $name);

            return $name;

        }
 
    }
?>