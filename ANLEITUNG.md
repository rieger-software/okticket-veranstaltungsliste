# OKTicket Veranstaltungsliste

Mit diesem Plugin können Sie Veranstaltungen von OKTicket auf Ihrer WordPress-Website anzeigen – als Übersicht, als Detailansicht oder in einer Seitenleiste.

## Grundprinzip in WordPress

Die Inhalte werden mit WordPress-Blöcken eingefügt. Einen Block fügen Sie im Editor über das **+** oben links oder direkt im Inhalt ein. Die Einstellungen eines ausgewählten Blocks finden Sie rechts in der Seitenleiste. Ist sie nicht sichtbar, klicken Sie oben rechts auf das Symbol für die Einstellungen.

Änderungen sind zunächst nur in der Editor-Vorschau sichtbar. Mit **Aktualisieren** speichern Sie eine bestehende Seite, mit **Veröffentlichen** machen Sie eine neue Seite öffentlich. Über **Vorschau** können Sie die Seite vor der Veröffentlichung in einem neuen Browser-Tab ansehen.

## Eine neue Veranstaltungsseite anlegen

1. Öffnen Sie **Seiten → Erstellen**.
2. Geben Sie der Seite einen Titel, zum Beispiel den Namen der Veranstaltungsreihe.
3. Fügen Sie darunter den gewünschten OKTicket-Block ein.
4. Prüfen Sie die Darstellung über **Vorschau**.
5. Klicken Sie auf **Veröffentlichen**.

Bereits vorhandene Seiten bearbeiten Sie über **Seiten → Alle Seiten** und anschließend über den Namen der gewünschten Seite.

## 1. OKTicket verbinden

Öffnen Sie in WordPress **Einstellungen → OKTicket**.

### API-Token bei OKTicket finden

1. Melden Sie sich mit Ihrem Veranstalter-Zugang bei OKTicket an.
2. Öffnen Sie dort **Ihre Daten → API**.
3. Kopieren Sie den angezeigten **API-Token**.
4. Wechseln Sie zurück zu WordPress und fügen Sie den Token in das Feld **API-Token** ein.

Der Token ist ein Zugangsschlüssel für Ihre Veranstaltungsdaten. Geben Sie ihn nicht weiter und veröffentlichen Sie ihn nicht auf einer Website, in E-Mails oder Screenshots. Falls in Ihrem OKTicket-Account kein Bereich **API** angezeigt wird, wenden Sie sich bitte an Ihren Ansprechpartner bei OKTicket.

1. Tragen Sie Ihren API-Token ein.
2. Klicken Sie auf **„Verbindung zu OKTicket prüfen“**.
3. Ist die Verbindung erfolgreich, können Sie mit **„Daten jetzt aktualisieren“** die aktuellen Veranstaltungen sofort abrufen.

Die Daten werden anschließend automatisch regelmäßig aktualisiert. Sie müssen dies im Alltag nicht selbst erledigen.

## 2. Veranstaltungsübersicht auf einer Seite einfügen

1. Öffnen Sie die gewünschte Seite im WordPress-Editor.
2. Klicken Sie auf **+** und suchen Sie nach **„OKTicket Veranstaltungsübersicht“**.
3. Wählen Sie rechts die gewünschten Veranstaltungsreihen aus.
4. Legen Sie fest, welche Informationen gezeigt werden sollen, zum Beispiel Bild, Titel, Ort, Kurzbeschreibung und nächste Termine.
5. Aktualisieren oder veröffentlichen Sie die Seite.

Die Veranstaltungsübersicht eignet sich zum Beispiel für eine Seite wie „Programm“, „Veranstaltungen“ oder „Tickets“.

## 3. Details einer Veranstaltungsreihe anzeigen

Für eine eigene Veranstaltungsseite verwenden Sie den Block **„OKTicket Veranstaltungsdetails“**.

1. Fügen Sie den Block auf einer Seite ein.
2. Wählen Sie rechts bei **„Veranstaltungsreihe“** die gewünschte Veranstaltung aus.
3. Aktivieren Sie nur die Informationen, die auf der Seite erscheinen sollen.

Eine Veranstaltungsreihe fasst alle Termine derselben Veranstaltung zusammen.

Die rechte Block-Seitenleiste zeigt nur Einstellungen für den gerade ausgewählten Block. Klicken Sie deshalb zuerst in die Vorschau des gewünschten OKTicket-Blocks, bevor Sie Einstellungen ändern.

### Vorverkaufsstellen einblenden

Im Bereich **„Vorverkaufsstellen“** können Sie:

- die Anzeige von Vorverkaufsstellen aktivieren,
- auswählen, welche Angaben erscheinen sollen, zum Beispiel Name, Adresse, Telefon oder Öffnungszeiten,
- die gewünschte Anzahl festlegen,
- Vorverkaufsstellen nach der Nähe zum Veranstaltungsort sortieren.

Bei aktivierter Sortierung werden die nächstgelegenen Vorverkaufsstellen zuerst gezeigt.

## 4. Termine in einer Seitenleiste anzeigen

Öffnen Sie **Design → Widgets** und wählen Sie die gewünschte Seitenleiste oder Footer-Spalte.

1. Fügen Sie das Widget **„OKTicket Termine“** hinzu.
2. Geben Sie eine Überschrift ein, zum Beispiel „Aufführungen 2026“.
3. Wählen Sie die Veranstaltungsreihe aus.
4. Bestimmen Sie die Anzahl der Termine oder aktivieren Sie **„Alle Termine anzeigen“**.
5. Auf Wunsch aktivieren Sie die Ticket-Ampel.
6. Speichern Sie das Widget.

Ein Widget erscheint nicht im Seiteninhalt, sondern in einer vom Theme vorgesehenen Widget-Fläche, zum Beispiel in der rechten Seitenleiste oder im Footer. Welche Fläche auf welcher Seite sichtbar ist, hängt vom verwendeten WordPress-Theme ab.

Zusätzlich können Sie vergangene Termine ausblenden und den nächsten noch anstehenden Termin fett hervorheben.

Auf dieser Website ist das Plugin **Classic Widgets** aktiv. Deshalb sehen Sie die Ansicht **„Verfügbare Widgets“**. Dort ziehen Sie „OKTicket Termine“ in die gewünschte Widget-Fläche oder klicken es an und wählen die Ziel-Fläche aus.

Die Ticket-Ampel wird als farbiger Punkt angezeigt:

- Grün: ausreichend Tickets verfügbar
- Gelb: nur noch begrenzt Tickets verfügbar
- Rot: nur noch sehr wenige Tickets verfügbar
- Grau: ausverkauft

## 5. Wenn eine Veranstaltung oder Änderung nicht sofort sichtbar ist

Öffnen Sie **Einstellungen → OKTicket** und klicken Sie auf **„Daten jetzt aktualisieren“**. Laden Sie anschließend die betreffende Seite neu.

Wenn Sie eine Änderung an einer Seite oder einem Widget gespeichert haben, aber noch die alte Darstellung sehen, leeren Sie bei Bedarf zusätzlich den Browser-Cache bzw. den Cache eines eventuell verwendeten Caching-Plugins.

## 6. Updates installieren

Wenn in der WordPress-Pluginübersicht ein Update für **OKTicket Veranstaltungsliste** angezeigt wird, können Sie es dort wie jedes andere Plugin-Update installieren.

Vor einem Update empfiehlt sich wie bei allen WordPress-Änderungen eine aktuelle Sicherung der Website.
