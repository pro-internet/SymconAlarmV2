# SymconAlarmV2
SymconAlarmV2 ist eine einfache Alarmanlage, welche anspringt wenn sich der Zustand einer der im Targets Ordner befindenen Variablen (bitte als Links hinzufügen) von false auf true setzt. Dabei kann eine SMTP -und eine Webfront Instanz angegeben werden um optionale E-Mail und/oder Push Benachrichtigungen zu verschicken. Dabei ist es möglich bis zu 6 Bilder auszuwählen, welche sich anhänglich in der E-Mail befinden (hier am besten einen Image Grabber verwenden, der sich aktuelle Bilder der Überwachungskameras holt). 
## Mögliche Einstellungen
### Intervall
* (In Sekunden eingeben) In dieser Zeit werden neue E-Mails / Push-Benachrichtigungen versendet
### E-Mail Benachrichtigung
* Benötigt SMTP -oder Multivar-Instanz (!)
* Enthält den aktuellen Log
* Benachrichtigung wenn Alarm beendet wurde
* (Optional) Bilder von bis zu 6 Kameras (werden Serverseitig zu einer Datei zusammengefügt, gecached und verschickt)
### Push-Benachrichtigung
* Sendet einfachen Alarm
