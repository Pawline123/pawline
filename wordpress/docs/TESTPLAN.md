# Testplan (Abnahme in WordPress)

Jeder Test wird in der lokalen Umgebung und vor dem Live-Gang auf der Testadresse beim Hosting durchgeführt. Ergebnis mit Datum in `docs/TESTSTATUS.md` eintragen. Zahlungen nur im **Testmodus**. E-Mails in Mailpit (http://localhost:8025) prüfen.

| Nr. | Test | Erwartetes Ergebnis | Voraussetzung |
|---|---|---|---|
| T-01 | Startseite, Shop, Kategorie, Produkt, Warenkorb, Kasse, Kontakt aufrufen (Desktop + Handy-Breite 375 px) | Alle Seiten laden ohne Fehler, Bedienelemente ≥ 48 px hoch, kein horizontales Scrollen | Setup |
| T-02 | Website-Editor: jede Vorlage und jedes Pawline-Muster öffnen | Keine Meldung „unerwarteter oder ungültiger Inhalt“ | Setup |
| T-03 | Im Editor Farbe „Moos“, Schriftgröße, Startseitentext und Navigation ändern und speichern | Änderung sichtbar; Theme-Dateien unverändert; in Editor › Templates als „angepasst“ markiert | Setup |
| T-04 | Suche nach Produktnamen; Kategorie wählen; Sortierung ändern | Treffer korrekt; nach Ergänzung des Filter-Blocks: Filter wirken | Demodaten |
| T-05 | Variante „[DEMO] Variantenprodukt – M“ in den Warenkorb, Menge 2 | Richtige Variante, Summe 49,80 €; Versand/Rabatte gemäß konfigurierten Regeln (noch keine konfiguriert) | Demodaten |
| T-06 | Gastbestellung mit Stripe-Testkarte (erfolgreich) und mit abgelehnter Testkarte | Erfolgreich → „In Bearbeitung“; abgelehnt → keine bezahlte Bestellung, Fehlermeldung für Kunden | Stripe-Testschlüssel |
| T-07 | § 19 UStG: Produktseite, Warenkorb, Kasse, Bestellbestätigung, Rechnung, Storno prüfen | Hinweis auf Kleinunternehmerregelung sichtbar; **kein** „inkl. MwSt.“, kein 0-%-Steuersatz, kein USt-Betrag; Endpreise = gezahlte Preise | Germanized (+ Pro für Belege) |
| T-08 | Rechnung wird zur bezahlten Bestellung automatisch erstellt und als Anhang versendet | Ein PDF mit fortlaufender Nummer, korrekten Firmendaten, § 19-Hinweis; derselbe Beleg in der Bestellung herunterladbar | 🔑 Germanized Pro, 📋 Firmendaten |
| T-09 | Bestell-E-Mail erneut senden, Rechnung erneut herunterladen | Keine neue Rechnungsnummer, identisches Dokument | 🔑 |
| T-10 | Produktpreis und Firmenanschrift nach Rechnungserstellung ändern | Bereits erstellte Rechnung bleibt unverändert | 🔑 |
| T-11 | Volle Erstattung einer Bestellung; Teilerstattung (eine Position) einer anderen | Je ein Storno/Korrekturbeleg mit Bezug zur Originalrechnung und richtigem Betrag | 🔑 |
| T-12 | Wiederholte Zahlungsbenachrichtigung (Stripe-Webhook erneut senden) | Keine doppelte Bestellung, keine zweite Rechnung | Stripe CLI oder Testadresse |
| T-13 | Pawline › Überblick zeigt die bezahlte Bestellung unter „Beim Lieferanten bestellen“ | Ja; Button „Lieferantenauftrag erfassen“ füllt die Bestellnummer vor | Plugin |
| T-14 | Lieferantenauftrag mit Nummer, Betrag „24,90“, Status „bestellt“ speichern | Titel `LA-<id> · Lieferant · Nr.`, Betrag 24,90 EUR, Bestelldatum = heute, Bestellnotiz in der Kundenbestellung, Protokolleintrag | Plugin |
| T-15 | Ein Lieferantenauftrag für zwei Bestellungen; eine Bestellung mit zwei Lieferantenaufträgen | Beide Zuordnungen sichtbar; Rohdifferenz je Bestellung bei geteiltem Auftrag als „nicht berechnet“ markiert | Plugin |
| T-16 | Ungültige Bestellnummer (z. B. 999999) und ungültigen Betrag „abc“ eingeben | Hinweis angezeigt; ungültige Werte nicht gespeichert | Plugin |
| T-17 | Zwei Trackingnummern in der Bestellung erfassen, eine wieder entfernen | Beide/eine gespeichert; Bestellnotiz bei neuer Trackingnummer; Kunde **nicht** automatisch benachrichtigt | Plugin |
| T-18 | Eingangsbeleg mit PDF + XML hochladen, einem Lieferantenauftrag zuordnen | Dateien gelistet mit „✔ unverändert“; Download funktioniert für Admin | Plugin |
| T-19 | Download-Link abgemeldet, als Kunde und als Shop-Manager aufrufen; Datei direkt per Pfad aufrufen | Abgemeldet/Kunde: abgewiesen; Shop-Manager: erlaubt; Direktaufruf nicht möglich (Ablage außerhalb Webroot) | Plugin |
| T-20 | PHP-Datei mit Endung `.pdf` hochladen | Abgelehnt mit Hinweis | Plugin |
| T-21 | Eingangsbeleg mit Datei endgültig löschen | In den Papierkorb möglich, endgültiges Löschen verhindert | Plugin |
| T-22 | Netto 10,00 + USt 1,90 ≠ Gesamt 12,50 eingeben | Warnhinweis, Werte bleiben wie eingegeben | Plugin |
| T-23 | Dropshipping-Artikel mit aktivierter Bestandsverwaltung | Warnung im Produkt und im Überblick; nach Bestellung + Lieferantenauftrag **keine** Bestandsänderung in WooCommerce/ATUM | Plugin, ATUM |
| T-24 | Auswertung für den Monat | Erlöse, Erstattungen, Einkauf, Rohdifferenz mit Grundlage; Lücken (fehlender Einkauf, Fremdwährung) aufgelistet; Bezeichnung nie „Gewinn“ | Plugin |
| T-25 | CSV-Export Eingangsbelege/Lieferantenaufträge in Excel öffnen | Umlaute korrekt, Semikolon-getrennt, Beträge mit Komma, SHA-256 enthalten | Plugin |
| T-26 | Sicherung erstellen, Testbestellung anlegen, Sicherung wiederherstellen | Zustand der Sicherung wiederhergestellt | Setup |
| T-27 | `demo-entfernen` | Alle [DEMO]-Produkte und die Demo-Kategorie gelöscht, sonst nichts | Demodaten |
