# SymconAlarmV2
SymconAlarmV2 ist eine einfache Alarmanlage, welche anspringt wenn sich der Zustand einer der im Targets Ordner befindenen Variablen (bitte als Links hinzufügen) von false auf true setzt. Dabei kann eine SMTP -und eine Webfront Instanz angegeben werden um optionale E-Mail und/oder Push Benachrichtigungen zu verschicken. Dabei ist es möglich bis zu 6 Bilder auszuwählen, welche sich anhänglich in der E-Mail befinden (hier am besten einen Image Grabber verwenden, der sich aktuelle Bilder der Überwachungskameras holt). Im Fall eines Alarms werden alle sich als Links im "Targets Alarm" Ordner befindenen Geräte auf ihren Maximalwert geschaltet. 
## Mögliche Einstellungen
### Kameras
* Die Bilder werden in den E-Mails versendet.
* :exclamation: Unter Linux darf keine Kamera angegeben werden :exclamation:
### Intervall
* (In Sekunden eingeben) In dieser Zeit werden neue E-Mails / Push-Benachrichtigungen versendet
### E-Mail Benachrichtigung
* Benötigt SMTP -oder MultiMail-Instanz (!)
* Enthält den aktuellen Log
* Benachrichtigung wenn Alarm beendet wurde
* (Optional) Bilder von bis zu 6 Kameras (werden Serverseitig zu einer Datei zusammengefügt, gecached und verschickt)
### Push-Benachrichtigung
* Sendet einfachen Alarm
### Bilder speichern
* Die geschossenen Bilder werden so nicht nach dem versenden der E-Mail gelöscht. Sie bleiben unter C:\IP-Symcon\ModuleData\AlarmV2 gespeichert
