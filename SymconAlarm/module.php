<?

require(__DIR__ . "\\pimodule.php");

    // Klassendefinition
    class SymconAlarmV2 extends PISymconModule {

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
            $events = $this->checkFolder("Events", $this->InstanceID, 6);


            $this->hide($targets);


            $this->checkOnAlarmChangedEvent();
            $this->checkOnUeberwachungChangeEvent();

            $this->checkTempFolder();

 
        }
 
        // Überschreibt die intere IPS_ApplyChanges($id) Funktion
        public function ApplyChanges() {
           
            parent::ApplyChanges();

            $this->refreshTargets();

        }

        public function CheckVariables () {

            // Variablen checken -und erstellen
            $ueberwachung = $this->checkBoolean("Überwachung", true, $this->InstanceID, 0, false);
            $alarm = $this->checkBoolean("Alarm", true, $this->InstanceID, 1, false);
            $emailBenachrichtigung = $this->checkBoolean("E-Mail Benachrichtigung", true, $this->InstanceID, 2, false);
            $pushBenachrichtigung = $this->checkBoolean("Push Benachrichtigung", true, $this->InstanceID, 3, false);
            $historie = $this->checkString("Historie", false, $this->InstanceID, 4, false);

            $this->createSwitches("Switch 1", "Ich bin ein Test", "Ich bin default true|true", "Ich nicht");

            // Profile hinzufügen (wenn nicht automatisiert wie bei switch)
            $this->addProfile($historie, "~HTMLBox");

            // Set Icons 
            $this->setIcon($emailBenachrichtigung, "Mail");
            $this->setIcon($historie, "Database");

        }

        public function CheckScripts () {

            // Scripts checken -und erstellen
            $clearLog = $this->checkScript("Historie Löschen", $this->prefix . "_clearLog", true, false); 
            $alarmActivated = $this->checkScript("Alarm aktiviert", $this->prefix . "_alarmActivated", true, false); 

            // Positionen setzen
            $this->setPosition($clearLog, "last");

            $this->hide($alarmActivated);

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


            $this->RegisterPropertyInteger("NotificationInstance", null);
            $this->RegisterPropertyBoolean("PictureLog", false);

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

        public function onUeberwachungChange () {

            $alarm = $this->searchObjectByName("Alarm");
            $ueberwachung = $this->searchObjectByName("Überwachung");

            $alarmVal = GetValue($alarm);
            $ueberwachungVal = $this->searchObjectByName($ueberwachung);

            if (!$ueberwachungVal && $alarmVal) {

                SetValue($alarm, false);

            }
            
        }

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
            $camera1 = $this->ReadPropertyInteger("Camera4");
            $camera2 = $this->ReadPropertyInteger("Camera5");
            $camera3 = $this->ReadPropertyInteger("Camera6");
            
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

                echo "IMAGES";
                echo $images;

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

        protected function checkOnUeberwachungChangeEvent () {

            if (!$this->doesExist($this->searchObjectByName("Überwachung onChange"))) {

                $this->easyCreateOnChangeFunctionEvent("Überwachung onChange", $this->searchObjectByName("Überwachung"), "<?php " . $this->prefix . "_onUeberwachungChange(" . $this->InstanceID . ");", $this->InstanceID);

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

            echo "Kamera 1: " . $camera1 . "\n";
            echo "Kamera 2: " . $camera2 . "\n";
            echo "Kamera 3: " . $camera3 . "\n";
            echo "Kamera 4: " . $camera4 . "\n";
            echo "Kamera 5: " . $camera5 . "\n";
            echo "Kamera 6: " . $camera6 . "\n";

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

                // 1 Kamera
            } else if ($camera1 != null && $camera2 == null && $camera3 == null && $camera4 == null && $camera5 == null && $camera6 == null) {

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

                $newImage = imagecreatetruecolor($maxLen, $hoehe + $hoehe2) ;

                // First Row
                imagecopymerge($newImage,$c1img,0,0,0,0,imagesx($c1img),$hoehe,100);
                imagecopymerge($newImage,$c2img,imagesx($c1img),0,0,0,imagesx($c2img),$hoehe,100);
                imagecopymerge($newImage,$c3img,imagesx($c1img) + imagesx($c2img), 0, 0, 0, imagesx($c3img), $hoehe, 100);

                // Second Row
                imagecopymerge($newImage, $c4img, 0, $hoehe, 0, 0, imagesx($c4img), imagesy($c4img), 100);
                imagecopymerge($newImage, $c5img, imagesx($c4img), $hoehe, 0, 0, imagesx($c4img), imagesy($c5img), 100);
                imagecopymerge($newImage, $c6img, imagesx($c4img) + imagesx($c5img), $hoehe, 0, 0, imagesx($c6img), imagesy($c6img), 100);

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

                $newImage = imagecreatetruecolor($maxLen, $hoehe + $hoehe2) ;

                // First Row
                imagecopymerge($newImage,$c1img,0,0,0,0,imagesx($c1img),$hoehe,100);
                imagecopymerge($newImage,$c2img,imagesx($c1img),0,0,0,imagesx($c2img),$hoehe,100);
               // imagecopymerge($newImage,$c3img,imagesx($c1img) + imagesx($c2img), 0, 0, 0, imagesx($c3img), $hoehe, 100);

                // Second Row
                imagecopymerge($newImage, $c3img, 0, $hoehe, 0, 0, imagesx($c3img), imagesy($c3img), 100);
                imagecopymerge($newImage, $c4img, imagesx($c3img), $hoehe, 0, 0, imagesx($c4img), imagesy($c4img), 100);

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

            if (!file_exists("C:\\IP-Symcon\\ModuleData")){

                mkdir("C:\\IP-Symcon\\ModuleData");
                mkdir("C:\\IP-Symcon\\ModuleData\\AlarmV2");

            } else if (!file_exists("C:\\IP-Symcon\\ModuleData\\AlarmV2")) {

                mkdir("C:\\IP-Symcon\\ModuleData\\AlarmV2");

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

    }

?>