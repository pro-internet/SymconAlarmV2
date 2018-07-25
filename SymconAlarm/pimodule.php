<?php

abstract class PISymconModule extends IPSModule {

    public $moduleID = null;
    public $libraryID = null;
    public $prefix = null;
    public $instanceName = null;
    public $SperreVar = null;
    public $AutomatikVar = null;

    public function __construct($InstanceID) {
        // Diese Zeile nicht löschen
        parent::__construct($InstanceID);

        $className = get_class($this);

        $moduleGUID = $this->getModuleGuidByName($className);

        $module = IPS_GetModule($moduleGUID);
        $ownInstance = IPS_GetObject($InstanceID);

        $this->instanceName = $ownInstance['ObjectName'];

        $this->moduleID = $module['ModuleID'];
        $this->libraryID = $module['LibraryID'];

        $moduleJsonPath = __DIR__ . "\\module.json";

        $moduleJson = json_decode(file_get_contents($moduleJsonPath));

        $this->prefix = $moduleJson->prefix;

        if ($this->doesExist($this->searchObjectByName("Automatik"))) {
            
            $this->AutomatikVar = $this->searchObjectByName("Automatik");
        
        }

        if ($this->doesExist($this->searchObjectByName("Sperre"))) {

            $this->SperreVar = $this->searchObjectByName("Sperre");
        
        }
        
    }

    // Überschreibt die interne IPS_Create($id) Funktion
    public function Create() {

        parent::Create();

        $this->CheckVariables();

        $this->RegisterProperties();

        $this->CheckScripts();

    }


    public function ApplyChanges() {

        parent::ApplyChanges();

    }

    public function CheckVariables () {

        // Hier werden alle nötigen Variablen erstellt

    }

    public function RegisterProperties () {

        // Hier werden ale Properties registriert

    }

    public function CheckScripts () {

        // Hier werden alle nötigen Scripts erstellt

    }



    ##                      ##
    ##  Grundfunktionen     ##
    ##                      ##

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

    protected function checkVar  ($var, $type = 1, $profile = false , $position = "", $index = 0, $defaultValue = null) {

        if ($this->searchObjectByName($var) == 0) {
            
            $type = $this->varTypeByName($type);

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

    protected function checkBoolean ($name, $setProfile = false, $position = "", $index = 0, $defaultValue = null) {

        if ($name != null) {

            //echo "INDEX " . $index . " FOR " . $name . " \n";
            return $this->checkVar($name, 0, $setProfile, $position, $index, $defaultValue);

        }

    }

    protected function checkInteger ($name, $setProfile = false, $position = "", $index = 0, $defaultValue = null) {

        if ($name != null) {

            return $this->checkVar($name, 1, $setProfile, $position, $index, $defaultValue);

        }

    }

    protected function checkFloat ($name, $setProfile = false, $position = "", $index = 0, $defaultValue = null) {

        if ($name != null) {

            return $this->checkVar($name, 2, $setProfile, $position, $index, $defaultValue);

        }

    }

    protected function checkString ($name, $setProfile = false, $position = "", $index = 0, $defaultValue = null) {

        if ($name != null) {

            return $this->checkVar($name, 3, $setProfile, $position, $index, $defaultValue);

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
                
                if ($childObject['ObjectIdent'] == $this->nameToIdent($name) && $childObject['ObjectType'] == $this->objectTypeByName($objectType)) {
                    
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
            $this->addSetValue($vid);
        
        } else {

            $this->checkVariableProfile("Switch", 0, 0, 1, 0, array("Aus" => 0, "An" => 1));
            $this->addSwitch($vid);

        }

    }

    protected function checkVariableProfile ($name, $type, $min = 0, $max = 100, $steps = 1, $associations = null, $prefix = "", $suffix = "") {
        if (!IPS_VariableProfileExists($name)) {
            $newProfile = IPS_CreateVariableProfile($name, $type);
            IPS_SetVariableProfileValues ($name, $min, $max, $steps);
            IPS_SetVariableProfileText($name, $prefix, $suffix);
            
            if ($associations != null) {
                foreach ($associations as $assocName => $assocValue) {
                    $color = -1;
                    if (gettype("string")) {
                        if (strpos($assocValue, "|") !== false) {
                            $color = hexdec(explode("|", $assocValue)[1]);
                            $assocValue = explode("|", $assocValue)[0];
                            if ($assocValue == "true") {
                                $assocValue = true;
                            } else if ($assocValue == "false") {
                                $assocValue = false;
                            }
    
                        }
                    } else if (gettype("integer")) {
                        $assocValue = (int) $assocValue;
                    }
                    IPS_SetVariableProfileAssociation($name, $assocValue, $assocName, "", $color);
                }
            }
        }
    }

    protected function addSetValue ($id) { 

        if (!$this->doesExist($this->searchObjectByName("SetValue"))) {

            $setValueScript = $this->checkScript("SetValue", "<?php SetValue(\$IPS_VARIABLE, \$IPS_VALUE); ?>", false);
            $this->hide($setValueScript);

            IPS_SetVariableCustomAction($id, $this->searchObjectByName("SetValue"));

        } else {

            IPS_SetVariableCustomAction($id, $this->searchObjectByName("SetValue"));

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

                $this->addSetValue($id);
            
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
        
        } else {
            return $this->searchObjectByName($name);
        }
    }

    protected function hide ($id) {

        IPS_SetHidden($id, true);

    }

    // Ausbaufähig
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

                } else if ($position == "first" || $position == "First") {

                    $own = IPS_GetObject($this->InstanceID);

                    IPS_SetPosition($id, 0);

                    if (IPS_HasChildren($this->InstanceID)) {

                        $isfirst = true;

                        foreach ($own['ChildrenIDs'] as $child) {

                            $child = IPS_GetObject($child);

                            if ($child['ObjectPosition'] != "0" && $isfirst) {
                                break;
                            } else {
                                $isfirst = false;
                                IPS_SetPosition($child['ObjectID'], $child['ObjectPosition'] + 1);
                            }

                        }

                    }


                } else if (strpos($position, "|AFTER|") !== false) {

                    $own = IPS_GetObject($this->InstanceID);

                    $expString = explode("|AFTER|", $position);

                    $afterThisElement = $expString[1];

                    $elementFound = false;

                    foreach ($own['ChildrenIDs'] as $child) {

                        $obj = IPS_GetObject($child);

                        if ($child == $afterThisElement) {

                            $elementFound = true;
                            $this->setPosition($child, $obj['ObjectPosition'] - 1);
                            $this->setPosition($id, $obj['ObjectPosition']);

                        } else {

                            if ($elementFound) {

                                $this->setPosition($child, $obj['ObjectPosition'] + 1);

                            } else {

                                $this->setPosition($child, $obj['ObjectPosition']);

                            }

                        }

                    }

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
        IPS_SetIdent($eid, $this->nameToIdent($onChangeEventName));

        return $eid;

    }

    protected function setIcon ($objectId, $iconName) {

        $object = IPS_GetObject($objectId);

        if ($object['ObjectIcon'] != $iconName) {

            $iconList = $this->getIconList();

            if (in_array($iconName, $iconList)) {

                IPS_SetIcon($objectId, $iconName);

            } else {

                echo "Icon existiert nicht!";

            }

        }

    }

    protected function getIconList () {

        $ary =  array("Aircraft", "Alert", "ArrowRight", "Backspace", "Basement", "Bath", "Battery", "Bed", "Bike", "Book", "Bulb", "Calendar", "Camera", "Car", "Caret", "Cat", "Climate", "Clock", "Close", "CloseAll", "Cloud", "Cloudy", "Cocktail", "Cross", "Database", "Dining", "Distance", "DoctorBag", "Dog", "Dollar", "Door", "Download", "Drops", "Duck", "Edit", "Electricity", "EnergyProduction", "EnergySolar", "EnergyStorage", "ErlenmeyerFlask", "Euro", "Execute", "Eyes", "Factory", "Favorite", "Female", "Fitness", "Flag", "Flame", "FloorLamp", "Flower", "Fog", "Garage", "Gas", "Gauge", "Gear", "Graph", "GroundFloor", "Handicap", "Heart", "Help", "HollowArrowDown", "HollowArrowLeft", "HollowArrowRight", "HollowArrowUp", "HollowDoubleArrowDown", "HollowDoubleArrowLeft", "HollowDoubleArrowRight", "HollowDoubleArrowUp", "HollowLargeArrowDown", "HollowLargeArrowLeft", "HollowLargeArrowRight", "HollowLargeArrowUp", "Hourglass", "HouseRemote", "Image", "Information", "Intensity", "Internet", "IPS", "Jalousie", "Key", "Keyboard", "Kitchen", "Leaf", "Light", "Lightning", "Link", "Lock", "LockClosed", "LockOpen", "Macro", "Mail", "Male", "Melody", "Menu", "Minus", "Mobile", "Moon", "Motion", "Move", "Music", "Network", "Notebook", "Ok", "Pacifier", "Paintbrush", "Pants", "Party", "People", "Plug", "Plus", "Popcorn", "Power", "Presence", "Radiator", "Raffstore", "Rainfall", "Recycling", "Remote", "Repeat", "Return", "Robot", "Rocket", "Script", "Shift", "Shower", "Shuffle", "Shutter", "Sink", "Sleep", "Snow", "Snowflake", "Sofa", "Speaker", "Speedo", "Stars", "Sun", "Sunny", "Talk", "Tap", "Teddy", "Tee", "Telephone", "Temperature", "Thunder", "Title", "TopFloor", "Tree", "TurnLeft", "TurnRight", "TV", "Umbrella", "Unicorn", "Ventilation", "Warning", "Wave", "Wellness", "WindDirection", "WindSpeed", "Window", "WC", "XBMC");
        return $ary;

    }

    protected function varTypeByName ($name) {

        $booleanAlias = array("Boolean", "boolean", "bool", "Bool", "b", "B", 0);
        $integerAlias = array("Integer", "integer", "Int", "int", "i", "I", 1);
        $floatAlias = array("Float", "float", "fl", "Fl", 2);
        $stringAlias = array("String", "string", "str", "Str", "s", "S", 3);

        if (in_array($name, $booleanAlias)) {

            return 0; 

        } else if (in_array($name, $integerAlias)) {

            return 1;

        } else if (in_array($name, $floatAlias)) {

            return 2;

        } else if (in_array($name, $stringAlias)) {

            return 3;

        }

    }

    protected function objectTypeByName ($name) {

        //0: Kategorie, 1: Instanz, 2: Variable, 3: Skript, 4: Ereignis, 5: Media, 6: Link)
        $kategorieAlias = array("Kategorie", "kategorie", "Category", "category", "Kat", "kat", "Cat", "cat");
        $instanzAlias = array("Instanz", "instanz", "Instance", "instance", "Module", "module", "Modul", "modul");
        $variableAlias = array("Variable", "variable", "var", "Var");
        $scriptAlias = array("Script", "script", "Skript", "Skript");
        $ereignisAlias = array("Ereignis", "ereignis", "Event", "event", "Trigger", "trigger");
        $mediaAlias = array("Media", "media", "File", "file");
        $linkAlias = array("Link", "link", "Verknüpfung", "verknüpfung");

        if (in_array($name, $kategorieAlias)) {

            return 0;

        } else if (in_array($name, $instanzAlias)) {

            return 1;

        } else if (in_array($name, $variableAlias)) {

            return 2;

        } else if (in_array($name, $scriptAlias)) {

            return 3;

        } else if (in_array($name, $ereignisAlias)) {

            return 4;

        } else if (in_array($name, $mediaAlias)) {

            return 5;

        } else if (in_array($name, $linkAlias)) {

            return 6;

        }

    } 

    protected function createSwitches ($varNames, $position = null) {

        if ($position == null) {

            $position = $this->InstanceID;

        } else {
            $position = "";
        }

        $index;

        $IDs = null;

        foreach ($varNames as $varName) {

            if (strpos($varName, '|') !== false) {

                $completeName = $varName;

                $expl = explode("|", $varName);
                $defaultValue = $expl[1];
                $varName = $expl[0];

                if ($defaultValue == "true") {
                    $defaultValue = true;
                } else {
                    $defaultValue = false;
                }

                //print_r($expl);

                if (count($expl) > 1){

                    $index = intval($expl[2]);

                } else {
                    $index = 0;
                }

            } else {
                $defaultValue = null;
                $index = 0;
            }

            $newBool = $this->checkBoolean($varName, true, $position, $index, $defaultValue);
            $this->setIcon($newBool, "Power");
            $IDs[] = $newBool;

        }

        return $IDs;

    }

    protected function linkVar ($target, $linkName = "Unnamed Link", $parent = null, $linkPosition = 0) {

        if ($parent == null) {
            $parent = $this->InstanceID;
        }

        if ($this->doesExist($target)) {

            if (!$this->doesExist($linkName)) {

                $link = IPS_CreateLink();
                IPS_SetName($link, $linkName);
                IPS_SetIdent($link, $this->nameToIdent($linkName));
                IPS_SetParent($link, $parent);
                IPS_SetLinkTargetID($link, $target);
                IPS_SetHidden($link, false);
                $this->setPosition($link, $linkPosition);

            }

        }

    }

    protected function isLink ($id) {

        if ($id != 0 && $id != null) {
            
            $obj = IPS_GetObject($id);
            
            if ($obj['ObjectType'] == 6) {
                
                return true;

            } else {

                return false;
            
            }
        } else {

            return false;

        }

    }

    protected function sortChildrenByPosition (&$children) {

        usort($children, function ($a, $b) {

            $u1 = IPS_GetObject($a);
            $u2 = IPS_GetObject($b);

            return $u1['ObjectPosition'] > $u2['ObjectPosition'];

        });

    }

    protected function setAllInLinkList ($linkListId, $value) {
        $linkListObj = IPS_GetObject($linkListId);
        if (count($linkListObj['ChildrenIDs']) > 0) {
            foreach ($linkListObj['ChildrenIDs'] as $child) {
                $child = IPS_GetObject($child);
                if ($child['ObjectType'] == $this->objectTypeByName("Link")) {
                    $child = IPS_GetLink($child['ObjectID']);
                    $tg = $child['TargetID'];
                    $this->setDevice($tg, $value);
                }
            }
        }
    }

    protected function linkListToTargetList ($id) {

        if (IPS_HasChildren($id)) {

            $children = IPS_GetObject($id);
            $children = $children['ChildrenIDs'];

            $this->sortChildrenByPosition($children);

            $list = array();

            foreach ($children as $child) {

                if ($this->isLink($child)) {

                    $lnk = IPS_GetLink($child);
                    
                    $list[] = $lnk['TargetID'];

                }

            }

            return $list;

        } else {
            return array();
        }

    }

    protected function setDevice ($deviceID, $wert){
        if ($this->SperreVar != null){
            $sperre = GetValue($this->SperreVar);
            if ($sperre) {
                return;
            }
        }
        $device = IPS_GetObject($deviceID);
    
        switch($device['ObjectType']){
        
            case 1:
                    $instance = IPS_GetInstance($device['ObjectID']);
                    
                    //wenn EIB Groub
                    if ($instance['ModuleInfo']['ModuleName'] == "EIB Group"){
                        
                        if(IPS_HasChildren($device['ObjectID']) == 1) {
                            
                            foreach(IPS_GetChildrenIDs($device['ObjectID']) as $child){	
                            
                                $childVar = IPS_GetVariable($child);
                                
                                //wenn bool / Switch
                                if($childVar['VariableType'] == 0){
                                    
                                    EIB_Switch($device['ObjectID'], $wert);
                                
                                }
                                
                                //wenn int / Dim / float
                                if($childVar['VariableType'] == 1 || $childVar['VariableType'] == 2) {
                                    if(is_int($wert) || is_float($wert)){
                                        
                                        EIB_DimValue($device['ObjectID'], $wert);								
                                    
                                    }else{
                                        
                                        if(is_bool($wert)){
                                        
                                            if($wert === true){
                                                $wert = 100;
                                                EIB_DimValue($device['ObjectID'], $wert);
                                                
                                            }
                                            else{
                                                $wert = 0;
                                                EIB_DimValue($device['ObjectID'], $wert);
                                                
                                            }
                                        }
                                    }	
                                    
                                }
                        
                            }
                    
                        }
                    
                    }else{
                      
                        //Homematic Support (Aktuell: Switch)
                        if ($instance['ModuleInfo']['ModuleName'] == "HomeMatic Device") {
                
                             HM_WriteValueBoolean($device['ObjectID'], "STATE", $wert);
        
                        }
                    }
                break;
            
            case 2:
                    $getVar = IPS_GetVariable($device['ObjectID']);
                
                    $parent = IPS_GetParent($device['ObjectID']);
                    
                    if(IPS_GetObject($parent)['ObjectType'] == 1){
                    
                        $parentInstanz = IPS_GetInstance($parent);
                    
                        if($parentInstanz['ModuleInfo']['ModuleName'] == "EIB Group" ||  $parentInstanz['ModuleInfo']['ModuleName'] == "HomeMatic Device" ){
                    
                            $this->setDevice($parent, $wert);
                        
                        } else if ($parentInstanz['ModuleInfo']['ModuleName'] == "Dummy Module") {
                        
                            if (gettype($wert) == "boolean") {
                            
                                if ($wert) {
                                    $wert = 100;
                                } else {
                                    $wert = 0;
                                }
                                
                                SetValue($deviceID, $wert);
                                
                            } else {
                            
                                SetValue($deviceID, $wert);
                                
                            }
                            
                        }
                    } else {
                    
                    // wenn bool
                    if ($getVar['VariableType'] == 0) {
                
                        SetValue($device['ObjectID'], $wert);
                    }
                        
                    // wenn int oder floar
                    if($getVar['VariableType'] == 1 || $getVar['VariableType'] == 2) { 
                        
                        if(is_int($wert) || is_float($wert)){
                            
                            SetValue($device['ObjectID'], $wert);
                        }
                        else {
                            if(is_bool($wert)){              
                                if($wert == true){
                                
                                    $wert = 100;
                            
                                    SetValue($device['ObjectID'], $wert);
                                }
                                if($wert == false){
                                    
                                    $wert = 0;
                                    SetValue($device['ObjectID'], $wert);
                                }
                            }
                        
                        }
                    }	
                    
                    }
                    
                break;
                
        
        }
        
    }


    // "is" Funktionen
    protected function isBaseFunction ($id, $is) {
        if ($id != 0 && $id != null) {
            $obj = IPS_GetObject($id);
            if ($obj['ObjectType'] == $is) {
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    protected function isCategory ($id) {
        return $this->isBaseFunction($id, 0);
    }

    protected function isInstance ($id) {
        return $this->isBaseFunction($id, 1);
    }

    protected function isVariable ($id) {
        return $this->isBaseFunction($id, 2);
    }

    protected function isScript ($id) {
        return $this->isBaseFunction($id, 3);
    }

    protected function isEvent ($id) {
        return $this->isBaseFunction($id, 4);
    }

    protected function isMedia ($id) {
        return $this->isBaseFunction($id, 5);
    }
    
    protected function isLink ($id) {
        return $this->isBaseFunction($id, 6);
    }


    // Kern Instanzen bekommen

    protected function getArchiveControlInstance () {

        $all = IPS_GetObject(0);

        if (IPS_HasChildren($all['ObjectID'])) {

            $found = false;

            foreach ($all['ChildrenIDs'] as $child) {

                if ($this->isInstance($child)) {

                    $child = IPS_GetInstance($child['ObjectID']);

                    if ($child['ModuleInfo']['ModuleName'] == "Archive Control") {

                        $found = true;
                        return $child['InstanceID'];

                    }

                }

            }

            if (!$found) {

                return null;

            }

        }

    }

    protected function activateVariableLogging ($id) {

        if ($id == 0 || $id == null) {
            return;
        }

        $archiveInstance = $this->getArchiveControlInstance();

        if ($archiveInstance != null && $archiveInstance != 0) {

            AC_SetLoggingStatus ($archiveInstance, $id, true);

        }

    }


}


?>