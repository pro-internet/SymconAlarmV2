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
            $alarmActivated = $this->checkScript("Alarm aktiviert", $this->prefix . "_alarmActivated", true, false); 

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
            $this->hide($alarmActivated);

            $this->RegisterPropertyInteger("Interval", 5);
            $this->RegisterPropertyInteger("EmailInstance", null);
            $this->RegisterPropertyInteger("Camera1", null);
            $this->RegisterPropertyInteger("Camera2", null);
            $this->RegisterPropertyInteger("Camera3", null);
            $this->RegisterPropertyInteger("Camera4", null);
            $this->RegisterPropertyInteger("Camera5", null);


            $this->RegisterPropertyInteger("NotificationInstance", null);
            $this->RegisterPropertyBoolean("PictureLog", false);

            $this->checkOnAlarmChangedEvent();

            $this->checkTempFolder();

 
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

                $noBr = false;

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

                } else if ($type == "picture") {

                    $pic = file_get_contents($message);

                    $pic = base64_encode($pic);

                    if (strpos($acutalContent, "<img") !== false) {

                        $acutalContentNoImg = explode("<img", $acutalContent);

                        $actualContentNoImgEnding = explode("class='none'>", $acutalContent);

                        $rmessage = "<img style='max-width: 20%;' src='data:image/jpg;base64," . $pic ."' class='none'>";

                        $acutalContent = $acutalContentNoImg[0] . $rmessage . $actualContentNoImgEnding[1];

                        $rmessage = "";

                        $noBr = true;

                    } else {

                        $rmessage = "<img style='max-width: 20%;' src='data:image/jpg;base64," . $pic ."' class='none'>";

                    }

                } else if ($type = "endAlarm") {

                    $rmessage = "<span style='color: green;'>" . $rmessage . "</span>";

                }

                if (!$noBr) {

                    $rmessage = $rmessage . "<br />";

                }

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
            $pushBenachrichtigung = GetValue($this->searchObjectByName("Push Benachrichtigung"));
            $pushInstance = $this->ReadPropertyInteger("NotificationInstance");
            $pictureLog = $this->ReadPropertyBoolean("PictureLog");

            $camera1 = $this->ReadPropertyInteger("Camera1");
            $camera2 = $this->ReadPropertyInteger("Camera2");
            $camera3 = $this->ReadPropertyInteger("Camera3");
            
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
            
                $email = $email . "Es wurde ein Alarm ausgelöst! aktueller Log: \n \n";

                $email = $email . $this->getFormattedLog();

                $images = $this->getImages();

                if ($images != null) {

                    SMTP_SendMailAttachment($emailInstance, "Alarm!", $email, $images);

                    unlink($images);

                } else {

                    SMTP_SendMail($emailInstance, "Alarm!", $email);

                }

            }

            if ($pushBenachrichtigung == true) {


                if ($pushInstance != null) {

                    WFC_PushNotification ($pushInstance, "Alarm!", "", "alarm");

                } else {

                    $this->addLogMessage("Keine Notification Instanz ausgewählt! Push Nachricht konnte nicht gesendet werden!", "warning");

                }

            }

            if ($pictureLog && ($camera1 != null || $camera2 != null || $camera3 != null)) {

                $images = $this->getImages();

                $this->addLogMessage($images, "picture");

                unlink($images);

            }


        }

        public function onAlarmChange () {

            $alarmVar = $this->searchObjectByName("Alarm");
            $alarmVal = GetValue($alarmVar);
            $interval = $this->ReadPropertyInteger("Interval");
            $sendEmailVal = $this->ReadPropertyInteger("EmailInstance");

            $pushBenachrichtigung = GetValue($this->searchObjectByName("Push Benachrichtigung"));
            $pushInstance = $this->ReadPropertyInteger("NotificationInstance");


            // Wenn Alarm aktiviert
            if ($alarmVal) {

                IPS_SetScriptTimer($this->searchObjectByName("Alarm aktiviert"), $interval);
                $this->alarmActivated();

            } else {

                IPS_SetScriptTimer($this->searchObjectByName("Alarm aktiviert"), 0);

                if ($sendEmailVal) {

                    SMTP_SendMail($sendEmailVal, "Alarm beendet", "Der Alarm wurde beendet");

                }

                if ($pushBenachrichtigung) {

                    if ($pushInstance != null) {

                        WFC_PushNotification ($pushInstance, "Alarm beendet", "Der Alarm wurde beendet", "bell");
    
                    } else {
    
                        $this->addLogMessage("Keine Notification Instanz ausgewählt! Push Nachricht konnte nicht gesendet werden!", "warning");
    
                    }

                }

                $this->addLogMessage("Der Alarm wurde beendet", "endAlarm");

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
            
            } else {
                return $this->searchObjectByName($name);
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
            IPS_SetIdent($eid, $this->nameToIdent($onChangeEventName));

            return $eid;

        }



        ## Picture function

        protected function getImages () {

            $camera1 = $this->ReadPropertyInteger("Camera1");
            $camera2 = $this->ReadPropertyInteger("Camera2");
            $camera3 = $this->ReadPropertyInteger("Camera3");

            if ($camera1 != null && $camera2 != null && $camera3 == null) {

                $c1obj = IPS_GetMedia($camera1);
                $c2obj = IPS_GetMedia($camera2);
                $c1link = "C:\\IP-Symcon\\" . str_replace("/", "\\",$c1obj['MediaFile']);
                $c2link = "C:\\IP-Symcon\\" . str_replace("/", "\\",$c2obj['MediaFile']);

                $c1img = $this->imagecreatefromauto($c1link);
                $c2img = $this->imagecreatefromauto($c2link);

                $hoehe = 0;

                if (imagesy($c1img) > imagesy($c2img)) {
                    $hoehe = imagesy($c1img);
                } else if (imagesy($c1img) <= imagesy($c2img)) {
                    $hoehe = imagesy($c2img);
                }

                $newImage = imagecreatetruecolor(imagesx($c1img) + imagesx($c2img), $hoehe) ;

                imagecopymerge($newImage,$c1img,0,0,0,0,imagesx($c1img),$hoehe,100);
                imagecopymerge($newImage,$c2img,imagesx($c1img),0,0,0,imagesx($c2img),$hoehe,100);

                $newFilePath = "C:\\IP-Symcon\\ModuleData\\AlarmV2\\" . "tmpimg_" . $this->InstanceID . rand(1000, 10000) . ".jpg";

                // if (imagesx($newImage) > 1200) {

                //     // Breite          // Hoehe
                //     $prop = imagesx($newImage) / imagesy($newImage);
                //     $newHeight = 1200 / $prop;

                //     imagecopyresampled ($newImage, $newImage, 0, 0, 0, 0, 1200, $newHeight, imagesx($newImage), imagesy($newImage));

                // }

                imagejpeg($newImage, $newFilePath);

                //$resized = $this->resizeImage($newFilePath);

                return $newFilePath;

            } else if ($camera1 != null && $camera2 != null && $camera3 != null) {

                $c1obj = IPS_GetMedia($camera1);
                $c2obj = IPS_GetMedia($camera2);
                $c3obj = IPS_GetMedia($camera3);
                $c1link = "C:\\IP-Symcon\\" . str_replace("/", "\\",$c1obj['MediaFile']);
                $c2link = "C:\\IP-Symcon\\" . str_replace("/", "\\",$c2obj['MediaFile']);
                $c3link = "C:\\IP-Symcon\\" . str_replace("/", "\\",$c3obj['MediaFile']);

                $c1img = $this->imagecreatefromauto($c1link);
                $c2img = $this->imagecreatefromauto($c2link);
                $c3img = $this->imagecreatefromauto($c3link);

                $hoehe = 0;

                $allHeights = array(imagesy($c1img), imagesy($c2img), imagesy($c3img));

                $hoehe = max($allHeights);

                $newImage = imagecreatetruecolor(imagesx($c1img) + imagesx($c2img) + imagesx($c3img), $hoehe) ;

                imagecopymerge($newImage,$c1img,0,0,0,0,imagesx($c1img),$hoehe,100);
                imagecopymerge($newImage,$c2img,imagesx($c1img),0,0,0,imagesx($c2img),$hoehe,100);
                imagecopymerge($newImage,$c3img,imagesx($c1img) + imagesx($c2img), 0, 0, 0, imagesx($c3img), $hoehe, 100);

                // if (imagesx($newImage) > 1200) {

                //             // Breite          // Hoehe
                //     $prop = imagesx($newImage) / imagesy($newImage);
                //     $newHeight = 1200 / $prop;

                //     imagecopyresampled ($newImage, $newImage, 0, 0, 0, 0, 1200, $newHeight, imagesx($newImage), imagesy($newImage));

                // }

                $newFilePath = "C:\\IP-Symcon\\ModuleData\\AlarmV2\\" . "tmpimg_" . $this->InstanceID . rand(1000, 10000) . ".jpg";

                imagejpeg($newImage, $newFilePath);

                //$resized = $this->resizeImage($newFilePath);

                return $newFilePath;

            } else if ($camera1 != null && $camera2 == null && $camera3 == null) {

                $c1obj = IPS_GetMedia($camera1);
                $c1link = "C:\\IP-Symcon\\" . str_replace("/", "\\",$c1obj['MediaFile']);
                $c1img = $this->imagecreatefromauto($c1link);

                $newImage = imagecreatetruecolor(imagesx($c1img), imagesy($c1img));

                imagecopymerge($newImage,$c1img,0,0,0,0,imagesx($c1img),imagesy($c1img),100);

                $newFilePath = "C:\\IP-Symcon\\ModuleData\\AlarmV2\\" . "tmpimg_" . $this->InstanceID . rand(1000, 10000) . ".jpg";
                
                // if (imagesx($newImage) > 1200) {

                //     // Breite          // Hoehe
                //     $prop = imagesx($newImage) / imagesy($newImage);
                //     $newHeight = 1200 / $prop;

                //     imagecopyresampled ($newImage, $newImage, 0, 0, 0, 0, 1200, $newHeight, imagesx($newImage), imagesy($newImage));

                // }

                imagejpeg($newImage, $newFilePath);

                //$resized = $this->resizeImage($newFilePath);

                return $newFilePath;

            }

        }

        protected function checkTempFolder () {

            if (!file_exists("C:\\IP-Symcon\\ModuleData")){

                mkdir("C:\\IP-Symcon\\ModuleData");
                mkdir("C:\\IP-Symcon\\ModuleData\\AlarmV2");

            } else if (!file_exists("C:\\IP-Symcon\\ModuleData\\AlarmV2")) {

                mkdir("C:\\IP-Symcon\\ModuleData\\AlarmV2");

            }

        }

        // Wird nicht verwendet, trotzdem für evtl. spätere verwendung drin lassen
        protected function resizeImage ($imagePath) {

            if (filesize($imagePath) >= 1024000) {

                $image = $this->imagecreatefromauto($imagePath);

                $imageWidth = imagesx($image) * 0.8;
                $imageHeight = imagesy($image) * 0.8;

                $newImage = imagecreatetruecolor($imageWidth, $imageHeight);

                imagecopyresampled ($newImage, $image, 0, 0, 0, 0, $imageWidth, $imageHeight, imagesx($image), imagesy($image));

                $newFilePath = "C:\\IP-Symcon\\ModuleData\\AlarmV2\\" . "tmpimg_" . $this->InstanceID . rand(1000, 10000) . ".jpg";

                imagejpeg($newImage, $newFilePath);

                unlink($imagePath);

                $this->resizeImage($newFilePath);

            } else {

                return $imagePath;

            }

        }

        protected function imagecreatefromauto ($filepath) {

            $image_attributes = getimagesize($filepath); 
            $image_filetype = $image_attributes[2]; 

            if ($image_filetype == 1) {
                $img = imagecreatefromgif($filepath);
                return $img;
            } 

            if ($image_filetype == 2) {
                $img = imagecreatefromjpeg($filepath);
                return $img;
            }

            if ($image_filetype == 3) {
                $img = imagecreatefrompng($filepath);
                return $img;
            }

        }

        protected function imageauto ($img, $pt) {

            $image_attributes = getimagesize($img); 
            $image_filetype = $image_attributes[2]; 

            if ($image_filetype == 1) {
                imagegif($img, $pt);
            } 

            if ($image_filetype == 2) {
                imagejpeg($img, $pt);
            }

            if ($image_filetype == 3) {
                imagepng($img, $pt);
            }

        }

    }
?>