# Glossar-Einzelbeiträge mit Direktlink – FredKI CourseImport 1.6.8-rc1

## Ziel
Bei der Aktivitätsauswahl **Vollständig** für ein Moodle-Glossar wird jeder **freigegebene** Eintrag als getrenntes FredKI-Dokument synchronisiert. Das Feld **Referenz** bleibt unverändert.

## Beispiel
- Glossar: Mitgliedschaft
- Eintrag: **Beitrittserklärung für Mitglieder**
- Direktlink: `https://<Ihre-Moodle-Adresse>/mod/glossary/showentry.php?eid=<ECHTE-ID>`
- Erwartetes FredKI-Dokument: **Beitrittserklärung für Mitglieder**, inklusive Definition, Glossarname, Direktlink und Kurskontext.

## Installation und Test (zuerst Staging)
1. Moodle und Datenbank sichern. Plugin auf **1.6.8-rc1** aktualisieren.
2. Eine CourseImport-Aktivität öffnen, deren FredKI-Verbindung bereits funktioniert.
3. Das gewünschte Glossar markieren und **Vollständig** wählen; Auswahl speichern und synchronisieren. Alternativ die bestehende Auswahl mit **Aktuelle Auswahl synchronisieren** erneut übertragen.
4. In FredKI nachsehen: Pro freigegebenem Glossareintrag **ein** Dokument, Name = Glossarbegriff.
5. Den Eintrag „Beitrittserklärung für Mitglieder“ öffnen: er muss den Originaltitel, die Definition und den *Direktlink* enthalten.
6. Link in einer angemeldeten Moodle-Sitzung öffnen: muss direkt zum Eintrag führen (Moodle-Zugriffsrechte gelten weiterhin).
7. Einen Eintrag umbenennen und erneut synchronisieren: **kein Duplikat**, sondern aktualisierter Titel im selben Mapping.
8. Einen Eintrag nicht freigeben oder löschen, dann synchronisieren: zugehöriges FredKI-Dokument soll entfernt werden.
9. Referenz für ein Glossar wählen: weiterhin nur ein Glossar-Übersichtsdokument.
10. Mit dem Agenten fragen: „Wo finde ich die Beitrittserklärung für Mitglieder?“ Die Antwort sollte die Überschrift und den Direktlink nennen. Ob die KI den Link tatsächlich in der Antwort ausgibt, hängt zusätzlich von Agentenprompt und Retrieval ab.

## Sicherheits- und Betriebsgrenzen
- Nur freigegebene Einträge werden übertragen, und die Freigabe wird unmittelbar vor Upload erneut geprüft. Die vollständige **personenbezogene** Moodle-Berechtigungsprüfung bei jeder Chatabfrage ist im Gesamtprojekt noch offen.
- Bestehende Aggregate werden beim **ersten erfolgreichen erneuten Import** zur Löschung vorgemerkt; verbleibende Fehler müssen im Synchronisierungsstatus geprüft werden. Die Installation allein ändert keine FredKI-Dokumente.
- Kein echter Verbindungstest gegen den produktiven FredKI-Server wurde durchgeführt.
- Diese Version basiert auf dem Auth-Fix-Zweig; die ältere separate Security-Preview ist noch nicht integriert.
