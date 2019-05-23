<?

require(__DIR__ . "/pimodule.php");

    // Klassendefinition
    class SymconAlarmV2 extends PISymconModule {

        public $Details = true;

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


            // Targets Ordner checken -und erstellen
            $targets = $this->checkFolder("Targets", $this->InstanceID, 5);
            $targetsAlarm = $this->checkFolder("Targets Alarm", $this->InstanceID, 5);
            $events = $this->checkFolder("Events", $this->InstanceID, 6);


            $this->hide($targets);
            $this->hide($targetsAlarm);


            $this->checkOnAlarmChangedEvent();
            $this->checkOnUeberwachungChangeEvent();

            $this->checkTempFolder();
 
        }
 
        // Überschreibt die intere IPS_ApplyChanges($id) Funktion
        public function ApplyChanges() {
           
            parent::ApplyChanges();

            $this->refreshTargets();

            $this->deleteUnusedEvents();

        }

        public function getConfigurationForm () {

            return "{
                \"elements\":
                [
                    { \"type\": \"SelectInstance\", \"name\":\"EmailInstance\", \"caption\": \"SMTP Instanz\"},
                    ". $this->buildNotificationSelector() . ",
                    { \"type\": \"ValidationTextBox\", \"name\": \"Interval\", \"caption\": \"Invervall (in Sek)\" },
                    { \"type\": \"SelectMedia\", \"name\":\"Camera1\", \"caption\": \"Kamera 1\"},
                    { \"type\": \"SelectMedia\", \"name\":\"Camera2\", \"caption\": \"Kamera 2\"},
                    { \"type\": \"SelectMedia\", \"name\":\"Camera3\", \"caption\": \"Kamera 3\"},
                    { \"type\": \"SelectMedia\", \"name\":\"Camera4\", \"caption\": \"Kamera 4\"},
                    { \"type\": \"SelectMedia\", \"name\":\"Camera5\", \"caption\": \"Kamera 5\"},
                    { \"type\": \"SelectMedia\", \"name\":\"Camera6\", \"caption\": \"Kamera 6\"},
                    { \"type\": \"Label\", \"label\": \"Zusätzliche Funktionen\" },
                    { \"type\": \"CheckBox\", \"name\": \"SavePictures\", \"caption\": \"Bilder speichern\" },
                    { \"type\": \"Button\", \"label\": \"Targets updaten\", \"onClick\": \"PIAlarm_refreshAll(\$id);\" }
                ]
              }";

        }

        protected function buildNotificationSelector () {

            $allWebfronts = $this->getAllCoreInstancesBase("WebFront Configurator");

            $nObj = new StdClass;
            $nObj->type = "Select";
            $nObj->name = "NotificationInstance";
            $nObj->caption = "Push (Webfront)";

            $isFirst = false;

            if (count($allWebfronts) > 0) {

                foreach ($allWebfronts as $webfront) {

                    if ($isFirst == true) {

                        $nObj->value = $webfront;
                        $isFirst = false;

                    }

                    $cObj = new StdClass;
                    $cObj->caption = IPS_GetName($webfront);
                    $cObj->value = $webfront;

                    $nObj->options[] = $cObj;

                }

            }

            return json_encode($nObj);

        }

        protected function setExcludedHide () {

            return array($this->searchObjectByName("Einstellungen"), $this->searchObjectByName("Überwachung"), $this->searchObjectByName("Alarm"), $this->searchObjectByName("E-Mail Benachrichtigung"), $this->searchObjectByName("Push Benachrichtigung"), $this->searchObjectByName("Historie"), $this->searchObjectByName("Historie Löschen"));

        }

        protected function setExcludedShow () {

            return array("script", "instance", $this->searchObjectByName("Aktueller Alarm"));

        }

        protected function onDetailsChangeHide () {

            $prnt = IPS_GetParent($this->InstanceID);
            $name = IPS_GetName($this->InstanceID);
            
            $this->deleteObject($this->searchObjectByName($name . " Geräte Sensoren", $prnt));
            $this->deleteObject($this->searchObjectByName($name . " Geräte Alarm", $prnt));

        }

        protected function onDetailsChangeShow () {

            $name = IPS_GetName($this->InstanceID);
            $prnt = IPS_GetParent($this->InstanceID);

            ##$this->linkVar($this->searchObjectByName("Targets"), "Geräte Sensoren", $prnt, 0, true);
            ##$this->linkVar($this->searchObjectByName("Targets Alarm"), "Geräte Alarm", $prnt, 0, true);

            //linkFolderMobile ($folder, $newFolderName, $parent = null, $index = null)
            $this->linkFolderMobile($this->searchObjectByName("Targets"), $name . " Geräte Sensoren", $prnt);
            $this->linkFolderMobile($this->searchObjectByName("Targets Alarm"), $name . " Geräte Alarm", $prnt);

        }


        public function CheckVariables () {

            // Variablen checken -und erstellen

            $switches = $this->createSwitches(array("Überwachung||0", "Alarm||1", "E-Mail Benachrichtigung||2", "Push Benachrichtigung||3"));
            $historie = $this->checkString("Historie", false, $this->InstanceID, 4, false);

            $currentAlarm = $this->checkString("Aktueller Alarm", false, $this->InstanceID, 5);

            $this->hide($currentAlarm);

            $this->activateVariableLogging($switches[0]);
            $this->activateVariableLogging($switches[1]);
            $this->activateVariableLogging($switches[2]);
            $this->activateVariableLogging($switches[3]);

            // Profile hinzufügen (wenn nicht automatisiert wie bei switch)
            $this->addProfile($historie, "~HTMLBox");

            // Set Icons 
            $this->setIcon($switches[2], "Mail");
            $this->setIcon($historie, "Database");

        }

        public function CheckScripts () {

            // Scripts checken -und erstellen
            $clearLog = $this->checkScript("Historie Löschen", $this->prefix . "_clearLog", true, false); 
            $alarmActivated = $this->checkScript("Alarm aktiviert", $this->prefix . "_alarmActivated", true, false); 

            // Positionen setzen
            $this->setPosition($clearLog, 5);
            $this->hide($alarmActivated);

        }

        public function refreshAll () {
            $this->refreshTargets();

            $this->deleteUnusedEvents();
        }

        public function RegisterProperties () {

            $this->RegisterPropertyInteger("Interval", 60);
            $this->RegisterPropertyInteger("EmailInstance", null);
            $this->RegisterPropertyInteger("Camera1", null);
            $this->RegisterPropertyInteger("Camera2", null);
            $this->RegisterPropertyInteger("Camera3", null);
            $this->RegisterPropertyInteger("Camera4", null);
            $this->RegisterPropertyInteger("Camera5", null);
            $this->RegisterPropertyInteger("Camera6", null);

            //$this->RegisterPropertyString("OwnSubject", "");

            $fst = $this->getWebfrontInstance();

            $this->RegisterPropertyInteger("NotificationInstance", $fst);
            $this->RegisterPropertyBoolean("PictureLog", false);
            $this->RegisterPropertyBoolean("SavePictures", false);

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
                $datum = date("d.m.y - H:i", $timestamp);

                $noBr = false;

                $rmessage = $datum . ": " . $message;

                if ($type == "error") {

                    $rmessage = $rmessage . "<span style='color: red;'>" . $rmessage . "</span>";

                } else if ($type == "warning") {

                    $rmessage = "<span style='color: #406fbc;'>" . "WARNING: " . $message . "</span>";

                } else if ($type == "alarm") {

                
                    $rmessage = "<span style='color: red;'>" . $rmessage . "</span>";

                
                } else if ($type == "regular") {

                    $rmessage = "<span style='color: white;'>" . $rmessage . "</span>";

                } else if ($type == "picture") {

                    // $pic = file_get_contents($message);

                    // $pic = base64_encode($pic);

                    // if (strpos($acutalContent, "<img") !== false) {

                    //     $acutalContentNoImg = explode("<img", $acutalContent);

                    //     $actualContentNoImgEnding = explode("class='none'>", $acutalContent);

                    //     $rmessage = "<img style='max-width: 20%;' src='data:image/jpg;base64," . $pic ."' class='none'>";

                    //     $acutalContent = $acutalContentNoImg[0] . $rmessage . $actualContentNoImgEnding[1];

                    //     $rmessage = "";

                    //     $noBr = true;

                    // } else {

                    //     $rmessage = "<img style='max-width: 20%;' src='data:image/jpg;base64," . $pic ."' class='none'>";

                    // }

                } else if ($type = "endAlarm") {

                    $rmessage = "<span style='color: green;'>" . $rmessage . "</span>";

                }

                if (substr($acutalContent, 0, 5) == "<br />") {

                    $noBr = true;

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

                        $this->createRealOnChangeEvents(array($child['TargetID'] . "|onTargetChange"), $this->searchObjectByName("Events"));

                    }

                } else {

                    $this->addLogMessage("Objekt " . $child['ObjectName'] . "(#" . $chld['ObjectID'] . ") ist kein Link!", "warning");

                }

            }

        }


        ##
        ##  Set Funktionen
        ## 

        public function onUeberwachungChange () {

            $alarm = $this->searchObjectByName("Alarm");
            $ueberwachung = $this->searchObjectByName("Überwachung");

            $alarmVal = GetValue($alarm);
            $ueberwachungVal = GetValue($ueberwachung);

            if (!$ueberwachungVal && $alarmVal) {

                SetValue($alarm, false);

            }
            
        }

        protected function getLinkName ($id) {

            $allTargets = IPS_GetObject($this->searchObjectByName("Targets"));

            if (IPS_HasChildren($allTargets['ObjectID'])) {

                foreach ($allTargets['ChildrenIDs'] as $tg) {

                    $tg = IPS_GetObject($tg);

                    if ($tg['ObjectType'] == 6) {

                        $lnk = IPS_GetLink($tg['ObjectID']);

                        if ($lnk['TargetID'] == $id) {

                            return $tg['ObjectName'];

                        }

                    }

                }

            }

        }

        public function onTargetChange () {

            $ueberwachung = GetValue($this->searchObjectByName("Überwachung"));
            $senderID = $_IPS['VARIABLE'];
            $senderObj = IPS_GetObject($senderID);
            $senderVal = GetValue($senderID);
            $alarmVal = GetValue($this->searchObjectByName("Alarm"));

            if (!$ueberwachung) {

                return;

            }

            if ($senderVal == true) {
                
                if (!$alarmVal) {

                    $linkName = $this->getLinkName($senderObj['ObjectID']);

                    //echo "SenderOBJ: " . $senderObj['ObjectID'] . " \\n LinkName: " . $linkName . "\\n";
                    
                    if (strpos($linkName, "|") !== false) {

                        $sec = explode("|", $linkName)[1];
                        $sec = intval($sec);

                        sleep($sec);

                    }
                    
                    $newName = $this->getNameExtended($senderObj['ObjectID']);

                    $this->addLogMessage( $newName . " Alarm ausgelöst", "alarm");

                    SetValue($this->searchObjectByName("Aktueller Alarm"), $senderObj['ObjectID']);

                    $this->startAlarm();
                    
                    if (IPS_HasChildren($this->searchObjectByName("Targets Alarm"))) {

                        $this->setAllInLinkList($this->searchObjectByName("Targets Alarm"), true);

                    } 
                    

                } else {

                    $newVal = GetValue($senderObj['ObjectID']);

                    if (gettype($newVal) == "boolean") {

                        if ($newVal) {

                            $newVal = "TRUE";
    
                        } else {
    
                            $newVal = "FALSE";
    
                        }

                    }

                    $newName = $this->getNameExtended($senderObj['ObjectID']);

                    $vval = GetValue($senderObj['ObjectID']);

                    if ($vval == true) {

                        $this->addLogMessage($newName . " ausgelöst", "regular");

                    }

                }

            } else {

                if ($alarmVal) {

                    $newName = $this->getNameExtended($senderObj['ObjectID']);
                    $newVal = GetValue($senderObj['ObjectID']);

                    if ($newVal == true) {

                        $this->addLogMessage($newName . " ausgelöst", "regular");

                    }

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

        protected function updateIfImageGrabber ($id) {

            if ($id != null && $id != 0) {

                $parent = IPS_GetParent($id);

                if ($this->isInstance($parent)) {

                    $parentObj = IPS_GetInstance($parent);

                    if ($parentObj['ModuleInfo']['ModuleName'] == "Image Grabber") {

                        IG_UpdateImage($parent);

                    }

                }

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
            $camera4 = $this->ReadPropertyInteger("Camera4");
            $camera5 = $this->ReadPropertyInteger("Camera5");
            $camera6 = $this->ReadPropertyInteger("Camera6");

            $this->updateIfImageGrabber($camera1);
            $this->updateIfImageGrabber($camera2);
            $this->updateIfImageGrabber($camera3);
            $this->updateIfImageGrabber($camera4);
            $this->updateIfImageGrabber($camera5);
            $this->updateIfImageGrabber($camera6);

            
            if (!$ueberwachung) {

                return;

            }

            $emailBenachrichtigung = GetValue($this->searchObjectByName("E-Mail Benachrichtigung"));

            if ($emailBenachrichtigung) {

                $emailInstance = $this->ReadPropertyInteger("EmailInstance");

                // $customSubject = $this->ReadPropertyString("OwnSubject");

                if ($emailInstance == null) {

                    $this->addLogMessage("Verschicken der E-Mail fehlgeschlagen, keine Instanz gefunden!", "warning");

                }

                $emailTitle = IPS_GetName($this->InstanceID) ." ausgelöst von " . $this->getNameExtended(GetValue($this->searchObjectByName("Aktueller Alarm"))) . "\n";

                // if ($customSubject != "") {

                //     $emailTitle = $customSubject;

                // }

                $email = "Alarm ausgelöst von " . $this->getNameExtended(GetValue($this->searchObjectByName("Aktueller Alarm"))) . "\n";
            
                $email = $email . "Es wurde ein Alarm ausgelöst! Aktueller Log: \n \n";

                $email = $email . $this->getFormattedLog();

                $images = $this->getImages();

                /*echo "IMAGES";
                echo $images;*/

                if ($images != null) {

                    //SMTP_SendMailAttachment($emailInstance, "Alarm!", $email, $images);

                    $this->SendMailAttachment($emailInstance, "Alarm!", $email, $images);

                    if ($this->ReadPropertyBoolean("SavePictures") == false) {

                        unlink($images);

                    }

                } else {

                    //SMTP_SendMail($emailInstance, "Alarm!", $email);
                    $this->SendMail($emailInstance, $emailTitle, $email);

                }

            }

            if ($pushBenachrichtigung == true) {


                if ($pushInstance != null) {

                    //$customSubject = $this->ReadPropertyString("OwnSubject");

                    $subJ = IPS_GetName($this->InstanceID) ." ausgelöst von " . $this->getNameExtended(GetValue($this->searchObjectByName("Aktueller Alarm")));

                    if(strlen($subJ) >= 32) {

                        $subJ = IPS_GetName($this->InstanceID) . " ausgelöst";
                        $pushText = IPS_GetName($this->InstanceID) . " ausgelöst von " . $this->getNameExtended(GetValue($this->searchObjectByName("Aktueller Alarm")));

                        if (strlen($subJ) >= 32) {

                            $subJ = "Alarm ausgelöst";

                        }

                    } else {

                        $pushText = "";

                    } 

                    // if ($customSubject != "") {

                    //     $subJ = $customSubject;

                    // }

                    WFC_PushNotification ($pushInstance, $subJ, $pushText, null, $this->InstanceID);

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

            $sendMailActivated = GetValue($this->searchObjectByName("E-Mail Benachrichtigung"));

            $pushBenachrichtigung = GetValue($this->searchObjectByName("Push Benachrichtigung"));
            $pushInstance = $this->ReadPropertyInteger("NotificationInstance");


            // Wenn Alarm aktiviert
            if ($alarmVal) { 

                IPS_SetScriptTimer($this->searchObjectByName("Alarm aktiviert"), $interval);
                $this->alarmActivated();

            } else {

                SetValue($this->searchObjectByName("Aktueller Alarm"), "");

                IPS_SetScriptTimer($this->searchObjectByName("Alarm aktiviert"), 0);

                if (IPS_HasChildren($this->searchObjectByName("Targets Alarm"))) {

                    $this->setAllInLinkList($this->searchObjectByName("Targets Alarm"), false);

                } 

                if ($pushBenachrichtigung) {

                    if ($pushInstance != null) {

                        // WFC_PushNotification ($pushInstance, "Alarm beendet", "Alarm beendet", null, null);
    
                    } else {
    
                        $this->addLogMessage("Keine Notification Instanz ausgewählt! Push Nachricht konnte nicht gesendet werden!", "warning");
    
                    }

                }

                $this->addLogMessage("Alarm beendet", "endAlarm");

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

                //$this->easyCreateOnChangeFunctionEvent("Alarm onChange", $this->searchObjectByName("Alarm"), "onAlarmChange(" . $this->InstanceID . ");", $this->InstanceID);
                $this->createOnChangeEvents(array($this->searchObjectByName("Alarm") . "|onAlarmChange"));

            }

        }

        protected function checkOnUeberwachungChangeEvent () {

            if (!$this->doesExist($this->searchObjectByName("Überwachung onChange"))) {

                //$this->easyCreateOnChangeFunctionEvent("Überwachung onChange", $this->searchObjectByName("Überwachung"), "<?php " . $this->prefix . "_onUeberwachungChange(" . $this->InstanceID . ");", $this->InstanceID);
                $this->createOnChangeEvents(array($this->searchObjectByName("Überwachung") . "|onUeberwachungChange"));

            }

        }
        
        protected function deleteUnusedEvents () {

            $targets = $this->searchObjectByName("Targets");
            $events = $this->searchObjectByName("Events");

            $targetsObj = IPS_GetObject($targets);
            $eventsObj = IPS_GetObject($events);

            if ($eventsObj['ChildrenIDs'] != null) {

                foreach ($eventsObj['ChildrenIDs'] as $event) {

                    $event = IPS_GetObject($event);

                    if ($event['ObjectType'] == 4) {

                        $event = IPS_GetEvent($event['ObjectID']);
                        $eventTarget = $event['TriggerVariableID'];
                        $found = false;

                        foreach ($targetsObj['ChildrenIDs'] as $child) {

                            $child = IPS_GetObject($child);

                            if ($child['ObjectType'] == 6) {

                                $child = IPS_GetLink($child['ObjectID']);

                                if ($child['TargetID'] == $eventTarget) {

                                    $found = true;

                                }

                            }

                        }

                        if (!$found) {

                            if (IPS_GetName($event['EventID']) != "onChange Details") {

                                IPS_DeleteEvent($event['EventID']);

                            }

                        }

                    }

                }

            }

        }



        ## Picture function

        protected function getImages () {

            $camera1 = $this->ReadPropertyInteger("Camera1");
            $camera2 = $this->ReadPropertyInteger("Camera2");
            $camera3 = $this->ReadPropertyInteger("Camera3");
            $camera4 = $this->ReadPropertyInteger("Camera4");
            $camera5 = $this->ReadPropertyInteger("Camera5");
            $camera6 = $this->ReadPropertyInteger("Camera6");

            /*echo "Kamera 1: " . $camera1 . "\n";
            echo "Kamera 2: " . $camera2 . "\n";
            echo "Kamera 3: " . $camera3 . "\n";
            echo "Kamera 4: " . $camera4 . "\n";
            echo "Kamera 5: " . $camera5 . "\n";
            echo "Kamera 6: " . $camera6 . "\n";*/

                // 2 Kameras
            if ($camera1 != null && $camera2 != null && $camera3 == null && $camera4 == null && $camera5 == null && $camera6 == null) {

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

                $newImage = imagecreatetruecolor(imagesx($c1img) + imagesx($c2img), $hoehe);

                imagecopymerge($newImage,$c1img,0,0,0,0,imagesx($c1img),$hoehe,100);
                imagecopymerge($newImage,$c2img,imagesx($c1img),0,0,0,imagesx($c2img),$hoehe,100);

                $this->addTimestamp($newImage);

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

                // 3 Kameras
            } else if ($camera1 != null && $camera2 != null && $camera3 != null && $camera4 == null && $camera5 == null && $camera6 == null) {

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

                $newImage = imagecreatetruecolor(imagesx($c1img) + imagesx($c2img) + imagesx($c3img), $hoehe);

                imagecopymerge($newImage,$c1img,0,0,0,0,imagesx($c1img),$hoehe,100);
                imagecopymerge($newImage,$c2img,imagesx($c1img),0,0,0,imagesx($c2img),$hoehe,100);
                imagecopymerge($newImage,$c3img,imagesx($c1img) + imagesx($c2img), 0, 0, 0, imagesx($c3img), $hoehe, 100);

                $this->addTimestamp($newImage);

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

                // 1 Kamera
            } else if ($camera1 != null && $camera2 == null && $camera3 == null && $camera4 == null && $camera5 == null && $camera6 == null) {

                $c1obj = IPS_GetMedia($camera1);
                $c1link = "C:\\IP-Symcon\\" . str_replace("/", "\\",$c1obj['MediaFile']);
                $c1img = $this->imagecreatefromauto($c1link);

                $newImage = imagecreatetruecolor(imagesx($c1img), imagesy($c1img));

                imagecopymerge($newImage,$c1img,0,0,0,0,imagesx($c1img),imagesy($c1img),100);

                $newFilePath = "C:\\IP-Symcon\\ModuleData\\AlarmV2\\" . "tmpimg_" . $this->InstanceID . rand(1000, 10000) . ".jpg";
                
                $this->addTimestamp($newImage);

                // if (imagesx($newImage) > 1200) {

                //     // Breite          // Hoehe
                //     $prop = imagesx($newImage) / imagesy($newImage);
                //     $newHeight = 1200 / $prop;

                //     imagecopyresampled ($newImage, $newImage, 0, 0, 0, 0, 1200, $newHeight, imagesx($newImage), imagesy($newImage));

                // }

                imagejpeg($newImage, $newFilePath);

                //$resized = $this->resizeImage($newFilePath);

                return $newFilePath;

                // 6 Kameras
            } else if ($camera1 != null && $camera2 != null && $camera3 != null && $camera4 != null && $camera5 != null && $camera6 != null) {

                $c1obj = IPS_GetMedia($camera1);
                $c2obj = IPS_GetMedia($camera2);
                $c3obj = IPS_GetMedia($camera3);
                $c4obj = IPS_GetMedia($camera4);
                $c5obj = IPS_GetMedia($camera5);
                $c6obj = IPS_GetMedia($camera6);

                $c1link = "C:\\IP-Symcon\\" . str_replace("/", "\\",$c1obj['MediaFile']);
                $c2link = "C:\\IP-Symcon\\" . str_replace("/", "\\",$c2obj['MediaFile']);
                $c3link = "C:\\IP-Symcon\\" . str_replace("/", "\\",$c3obj['MediaFile']);
                $c4link = "C:\\IP-Symcon\\" . str_replace("/", "\\",$c4obj['MediaFile']);
                $c5link = "C:\\IP-Symcon\\" . str_replace("/", "\\",$c5obj['MediaFile']);
                $c6link = "C:\\IP-Symcon\\" . str_replace("/", "\\",$c6obj['MediaFile']);

                $c1img = $this->imagecreatefromauto($c1link);
                $c2img = $this->imagecreatefromauto($c2link);
                $c3img = $this->imagecreatefromauto($c3link);
                $c4img = $this->imagecreatefromauto($c4link);
                $c5img = $this->imagecreatefromauto($c5link);
                $c6img = $this->imagecreatefromauto($c6link);

                $hoehe = 0;

                $allHeights = array(imagesy($c1img), imagesy($c2img), imagesy($c3img));
                $secondHeights = array(imagesy($c4img), imagesy($c5img), imagesy($c6img));
                $lens = array(imagesx($c1img) + imagesx($c2img) + imagesx($c3img), imagesx($c4img) + imagesx($c5img) + imagesx($c6img));

                $hoehe = max($allHeights);
                $hoehe2 = max($secondHeights);
                $maxLen = max($lens);

                $newImage = imagecreatetruecolor($maxLen, $hoehe + $hoehe2);

                // First Row
                imagecopymerge($newImage,$c1img,0,0,0,0,imagesx($c1img),$hoehe,100);
                imagecopymerge($newImage,$c2img,imagesx($c1img),0,0,0,imagesx($c2img),$hoehe,100);
                imagecopymerge($newImage,$c3img,imagesx($c1img) + imagesx($c2img), 0, 0, 0, imagesx($c3img), $hoehe, 100);

                // Second Row
                imagecopymerge($newImage, $c4img, 0, $hoehe, 0, 0, imagesx($c4img), imagesy($c4img), 100);
                imagecopymerge($newImage, $c5img, imagesx($c4img), $hoehe, 0, 0, imagesx($c4img), imagesy($c5img), 100);
                imagecopymerge($newImage, $c6img, imagesx($c4img) + imagesx($c5img), $hoehe, 0, 0, imagesx($c6img), imagesy($c6img), 100);

                $this->addTimestamp($newImage);

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

                // 5 Kameras
            } else if ($camera1 != null && $camera2 != null && $camera3 != null && $camera4 != null && $camera5 != null && $camera6 == null) {

                $c1obj = IPS_GetMedia($camera1);
                $c2obj = IPS_GetMedia($camera2);
                $c3obj = IPS_GetMedia($camera3);
                $c4obj = IPS_GetMedia($camera4);
                $c5obj = IPS_GetMedia($camera5);

                $c1link = "C:\\IP-Symcon\\" . str_replace("/", "\\",$c1obj['MediaFile']);
                $c2link = "C:\\IP-Symcon\\" . str_replace("/", "\\",$c2obj['MediaFile']);
                $c3link = "C:\\IP-Symcon\\" . str_replace("/", "\\",$c3obj['MediaFile']);
                $c4link = "C:\\IP-Symcon\\" . str_replace("/", "\\",$c4obj['MediaFile']);
                $c5link = "C:\\IP-Symcon\\" . str_replace("/", "\\",$c5obj['MediaFile']);

                $c1img = $this->imagecreatefromauto($c1link);
                $c2img = $this->imagecreatefromauto($c2link);
                $c3img = $this->imagecreatefromauto($c3link);
                $c4img = $this->imagecreatefromauto($c4link);
                $c5img = $this->imagecreatefromauto($c5link);

                $hoehe = 0;

                $allHeights = array(imagesy($c1img), imagesy($c2img), imagesy($c3img));
                $secondHeights = array(imagesy($c4img), imagesy($c5img));
                $lens = array(imagesx($c1img) + imagesx($c2img) + imagesx($c3img), imagesx($c4img) + imagesx($c5img));

                $hoehe = max($allHeights);
                $hoehe2 = max($secondHeights);
                $maxLen = max($lens);

                $newImage = imagecreatetruecolor($maxLen, $hoehe + $hoehe2) ;

                // First Row
                imagecopymerge($newImage,$c1img,0,0,0,0,imagesx($c1img),$hoehe,100);
                imagecopymerge($newImage,$c2img,imagesx($c1img),0,0,0,imagesx($c2img),$hoehe,100);
                imagecopymerge($newImage,$c3img,imagesx($c1img) + imagesx($c2img), 0, 0, 0, imagesx($c3img), $hoehe, 100);

                // Second Row
                imagecopymerge($newImage, $c4img, 0, $hoehe, 0, 0, imagesx($c4img), imagesy($c4img), 100);
                imagecopymerge($newImage, $c5img, imagesx($c4img), $hoehe, 0, 0, imagesx($c4img), imagesy($c5img), 100);

                $this->addTimestamp($newImage);

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

            } else if ($camera1 != null && $camera2 != null && $camera3 != null && $camera4 != null && $camera5 == null && $camera6 == null) {

                $c1obj = IPS_GetMedia($camera1);
                $c2obj = IPS_GetMedia($camera2);
                $c3obj = IPS_GetMedia($camera3);
                $c4obj = IPS_GetMedia($camera4);

                $c1link = "C:\\IP-Symcon\\" . str_replace("/", "\\",$c1obj['MediaFile']);
                $c2link = "C:\\IP-Symcon\\" . str_replace("/", "\\",$c2obj['MediaFile']);
                $c3link = "C:\\IP-Symcon\\" . str_replace("/", "\\",$c3obj['MediaFile']);
                $c4link = "C:\\IP-Symcon\\" . str_replace("/", "\\",$c4obj['MediaFile']);

                $c1img = $this->imagecreatefromauto($c1link);
                $c2img = $this->imagecreatefromauto($c2link);
                $c3img = $this->imagecreatefromauto($c3link);
                $c4img = $this->imagecreatefromauto($c4link);

                $hoehe = 0;

                $allHeights = array(imagesy($c1img), imagesy($c2img));
                $secondHeights = array(imagesy($c3img), imagesy($c4img));
                $lens = array(imagesx($c1img) + imagesx($c2img), imagesx($c3img) + imagesx($c4img));

                $hoehe = max($allHeights);
                $hoehe2 = max($secondHeights);
                $maxLen = max($lens);

                $newImage = imagecreatetruecolor($maxLen, $hoehe + $hoehe2);

                // First Row
                imagecopymerge($newImage,$c1img,0,0,0,0,imagesx($c1img),$hoehe,100);
                imagecopymerge($newImage,$c2img,imagesx($c1img),0,0,0,imagesx($c2img),$hoehe,100);
               // imagecopymerge($newImage,$c3img,imagesx($c1img) + imagesx($c2img), 0, 0, 0, imagesx($c3img), $hoehe, 100);

                // Second Row
                imagecopymerge($newImage, $c3img, 0, $hoehe, 0, 0, imagesx($c3img), imagesy($c3img), 100);
                imagecopymerge($newImage, $c4img, imagesx($c3img), $hoehe, 0, 0, imagesx($c4img), imagesy($c4img), 100);

                $this->addTimestamp($newImage);

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

            }

        }

        protected function checkTempFolder () {

            $laufwerk = substr(__DIR__, 0, 3);

            if (!file_exists($laufwerk . "IP-Symcon\\ModuleData")){

                mkdir($laufwerk . "IP-Symcon\\ModuleData");
                mkdir($laufwerk . "IP-Symcon\\ModuleData\\AlarmV2");

            } else if (!file_exists($laufwerk . "IP-Symcon\\ModuleData\\AlarmV2")) {

                mkdir($laufwerk . "IP-Symcon\\ModuleData\\AlarmV2");

            }

        }

        //-------------------------------------------------------------------------------------------------
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
        //-------------------------------------------------------------------------------------------------

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

        // SymconMultiMail Support 
        protected function SendMail ($instID, $betreff, $text) {

            $obj = IPS_GetObject($instID);

            if ($obj["ObjectType"] == 1) {

                $obj = IPS_GetInstance($obj['ObjectID']);

                // Normale SMTP Instanz
                if ($obj['ModuleInfo']['ModuleName'] == "SMTP") {

                    SMTP_SendMail($instID, $betreff, $text);

                }

                // SymconMultiMail Instanz
                if ($obj['ModuleInfo']['ModuleName'] == "SymconMultiMail") {

                    MultiMail_SendMail($instID, $betreff, $text);

                }

            }

        }

        protected function addTimestamp (&$newImage) {

            $schwarz = ImageColorAllocate ($newImage, 255,255,255);
            $gross = "5";        // Schriftgröße 
            //$gross = "7";        // Schriftgröße 
            $randl = "3";        // Ausrichtung von Links 
            $rando = imagesy($newImage) - 25;        // Ausrichtung von Obén 
            $t1 = "prointernet Alarm|" . date("Y-m-d H:i:s");            // Text der Angezeigt werden soll 
            $t1 = "prointernet Alarm | " . date("Y-m-d H:i:s");            // Text der Angezeigt werden soll 
        
            ImageString ($newImage, $gross, $randl, $rando, "$t1", $schwarz); 
        }

        protected function SendMailAttachment ($instID, $betreff, $text, $attachment) {

            $obj = IPS_GetObject($instID);

            if ($obj["ObjectType"] == 1) {

                $obj = IPS_GetInstance($obj['ObjectID']);

                // Normale SMTP Instanz
                if ($obj['ModuleInfo']['ModuleName'] == "SMTP") {

                    SMTP_SendMailAttachment($instID, $betreff, $text, $attachment);

                }

                // SymconMultiMail Instanz
                if ($obj['ModuleInfo']['ModuleName'] == "SymconMultiMail") {

                    MultiMail_SendMailAttachment($instID, $betreff, $text, $attachment);

                }

            }

        }

        protected function getNameExtended ($id) {

            $obj = IPS_GetObject($id);

            if ($obj['ObjectType'] == $this->objectTypeByName("variable")) {

                $prnt = IPS_GetParent($id);
                $prnt = IPS_GetObject($prnt);

                if ($prnt['ObjectType'] == $this->objectTypeByName("instance")) {

                    $inst = IPS_GetInstance($prnt['ObjectID']);

                    if ($inst['ModuleInfo']['ModuleName'] == "HomeMatic Device") {

                        return $prnt['ObjectName'];

                    }

                }

            }

            return $obj['ObjectName'];

        }

    }

?>