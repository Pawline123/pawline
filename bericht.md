# Pawline – WordPress-Prüfung (GitHub Actions)

Commit `86d19e1` · Lauf 43862322607

<details><summary>Installierte Versionen</summary>


Erzeugt: 2026-11-16T11:59Z durch scripts/setup-wp.sh

- WordPress: 7.1.4
- PHP: 8.3.36
- Datenbank: 11.4.13-MariaDB-ubu2404

| Plugin | Version | Status |
|---|---|---|
| akismet | 5.7.2 | inactive |
| atum-stock-manager-for-woocommerce | 2.0.7 | active |
| woocommerce-germanized | 4.1.7 | active |
| hello | 1.7.2 | inactive |
| pawline-dropship | 0.1.0 | active |
| woocommerce | 11.3.0 | active |
| woocommerce-gateway-stripe | 11.2.0 | active |
| pawline-dev-mail |  | must-use |

Theme: pawline 0.1.0

</details>

## Schritte

| Schritt | Ergebnis |
|---|---|
| Container starten | ✅ |
| WordPress installieren | ✅ |
| Theme-/Plugin-Aktivierung | ✅ |
| Demodaten | ✅ |
| Logiktests | ✅ |
| wp-smoke.php | ✅ |
| Block-Prüfung | ✅ |
| Kaufablauf vorbereiten | ✅ |
| Kaufablauf im Browser | ✅ |
| Bestellung prüfen | ✅ |
| Handy-Ansicht | ✅ |
| Verwaltung im Browser | ✅ |
| Verwaltung in der Datenbank | ✅ |
| WordPress-Editor | ✅ |
| Demodaten entfernen (T-27) | ✅ |

## WordPress-Integrationsprüfung (`tests/wp-smoke.php`)

| | Prüfung |
|---|---|
| ⚠️ | Germanized Pro aktiv (Voraussetzung für Rechnungstests) |

**Ergebnis: 73 ok, 1 Warnungen, 0 Fehler** (nur Warnungen und Fehler aufgelistet)

## Kaufablauf im Browser (`tests/e2e/checkout.mjs`)

| | Bereich | Prüfung | Detail |
|---|---|---|---|
| ✅ | Produktübersicht | Shopseite lädt | http://localhost:8080/shop/ → HTTP 200 |
| ✅ | Produktübersicht | Demoprodukt mit 19,90 € gelistet |  |
| ✅ | Produktübersicht | Warenkorb-Schaltfläche direkt in der Übersicht | „In den Warenkorb“ |
| ✅ | Produktübersicht | § 19-Hinweis direkt unter dem Preis der Produktkarte | „Umsatzsteuerbefreit gemäß § 19 UStG.“ (12 px darunter; Bezug: „19,90 €“) |
| ✅ | Produktübersicht | „zzgl. Versandkosten“ beim Preis der Produktkarte | „Versandkosten“ (49 px darunter; Bezug: „19,90 €“) |
| ✅ | Produktübersicht | § 19-Hinweis genau einmal je Produktkarte | 1× gefunden |
| ✅ | Produktübersicht | Kein Umsatzsteuerausweis |  |
| ✅ | Produktübersicht | Kein abweichender alter Hinweistext |  |
| ✅ | Produktseite | Demoprodukt lädt | http://localhost:8080/product/demo-einfaches-produkt/ → HTTP 200 |
| ✅ | Produktseite | Titel „[DEMO] Einfaches Produkt“ |  |
| ✅ | Produktseite | Preis 19,90 € |  |
| ✅ | Produktseite | § 19-Hinweis direkt unter dem Preis | „Umsatzsteuerbefreit gemäß § 19 UStG.“ (20 px darunter; Bezug: „19,90 €“) |
| ✅ | Produktseite | „zzgl. Versandkosten“ beim Preis | „Versandkosten“ (65 px darunter; Bezug: „19,90 €“) |
| ✅ | Produktseite | Kein Umsatzsteuerausweis |  |
| ✅ | Produktseite | Kein abweichender alter Hinweistext |  |
| ✅ | Warenkorb | „In den Warenkorb“-Schaltfläche vorhanden |  |
| ✅ | Warenkorb | Demoprodukt im Warenkorb |  |
| ✅ | Warenkorb | Summe 19,90 € |  |
| ✅ | Warenkorb | § 19-Hinweis beim Gesamtbetrag | „Umsatzsteuerbefreit gemäß § 19 UStG.“ (46 px darunter; Bezug: „Veranschlagte Gesamtsumme19,90 €“) |
| ✅ | Warenkorb | Kein Umsatzsteuerausweis |  |
| ✅ | Warenkorb | Kein abweichender alter Hinweistext |  |
| ✅ | Kasse | Kasse lädt | Block-Kasse |
| ✅ | Kasse | § 19-Hinweis beim Gesamtbetrag | „Umsatzsteuerbefreit gemäß § 19 UStG.“ (32 px darunter; Bezug: „Gesamtsumme“) |
| ✅ | Kasse | Kein Umsatzsteuerausweis |  |
| ✅ | Kasse | Kein abweichender alter Hinweistext |  |
| ✅ | Kasse | Pflichtfelder ausgefüllt |  |
| ✅ | Kasse | Zahlart „Überweisung (nur CI-Test)“ angeboten |  |
| ✅ | Kasse | Versand „nur CI-Test“ angeboten |  |
| ✅ | Kasse | Rechtliche Bestätigungen | angehakt: Mit deiner Bestellung erklärst du dich mit unseren Allgemeinen Geschäftsbedingungen und Widerrufsbestimmungen einverstan |
| ✅ | Kasse | Summe 19,90 € |  |
| ✅ | Bestellabschluss | Bestell-Schaltfläche vorhanden | „Jetzt kaufen“ |
| ✅ | Bestellabschluss | Bestellbestätigung angezeigt | http://localhost:8080/kasse/order-received/28/?key=… |
| ✅ | Bestellabschluss | Bestellbestätigung nennt Produkt und Summe |  |
| ✅ | Bestellbestätigung | § 19-Hinweis beim Gesamtbetrag | „Umsatzsteuerbefreit gemäß § 19 UStG.“ (18 px darunter; Bezug: „Gesamt: 19,90 €“) |
| ✅ | Bestellbestätigung | Kein Umsatzsteuerausweis |  |
| ✅ | Bestellbestätigung | Kein abweichender alter Hinweistext |  |
| ✅ | Browser | Keine JavaScript-Fehler |  |
| ✅ | Bestell-E-Mail | E-Mail an Kunden versendet (Mailpit) | Betreff: „Bestätigung deiner Bestellung 28“ |
| ✅ | Bestell-E-Mail | Kleinunternehmerhinweis sichtbar | „Zahlungsart: Überweisung (nur CI-Test) Umsatzsteuerbefreit gemäß § 19 UStG. *Rechnungsadresse* CI Testkunde Teststraße“ |
| ✅ | Bestell-E-Mail | Kein Umsatzsteuerausweis |  |
| ✅ | Bestell-E-Mail | Kein abweichender alter Hinweistext |  |
| ⚠️ | Bestell-E-Mail | Rechnung als Anhang | kein Anhang (erwartet ohne Germanized Pro) |

## Bestellung in der Datenbank (`tests/ci/verify-order.php`)

| | Bereich | Prüfung | Detail |
|---|---|---|---|
| ✅ | Bestellung (Datenbank) | Bestellung vorhanden | #28 |
| ✅ | Bestellung (Datenbank) | Status „In Wartestellung“ (Überweisung, unbezahlt) | on-hold |
| ✅ | Bestellung (Datenbank) | Summe 19,90 EUR | 19.90 EUR |
| ✅ | Bestellung (Datenbank) | Kein Steuerbetrag (§ 19 UStG) | 0 |
| ✅ | Bestellung (Datenbank) | Keine Steuerposten |  |
| ✅ | Bestellung (Datenbank) | Position DEMO-001 | DEMO-001 |
| ✅ | Bestellung (Datenbank) | Zahlart bacs (nur CI-Test) | bacs |

## Handy-Ansicht (`tests/e2e/mobile.mjs`)

| | Bereich | Prüfung | Detail |
|---|---|---|---|
| ✅ | Startseite | lädt | HTTP 200 |
| ✅ | Startseite | Kein seitliches Scrollen |  |
| ✅ | Startseite | Grundschrift ≥ 16 px | 17 px |
| ✅ | Startseite | Menü-Schaltfläche (☰) auf dem Handy |  |
| ✅ | Startseite | Menü öffnet sich | Kasse · Kontakt · Mein Konto · Shop · Warenkorb |
| ✅ | Produktübersicht | lädt | HTTP 200 |
| ✅ | Produktübersicht | Kein seitliches Scrollen |  |
| ✅ | Produktübersicht | Grundschrift ≥ 16 px | 17 px |
| ✅ | Produktübersicht | § 19-Hinweis im Inhalt sichtbar | „Umsatzsteuerbefreit gemäß § 19 UStG.“ |
| ✅ | Produktübersicht | Schaltfläche „In den Warenkorb“ gut antippbar (≥ 44 px) | 160 × 78 px |
| ✅ | Produktübersicht | Kopfbereich: Menü → Konto → Warenkorb in einer Zeile | Menü x=230 y=22, Konto x=278 y=22, Warenkorb x=322 y=22; Kopfhöhe 45 px |
| ✅ | Produktübersicht | Kopfbereich: Tippflächen ≥ 44 × 44 px | Menü 44×44, Konto 44×44, Warenkorb 44×44 |
| ✅ | Produktübersicht | Abstand Kopfbereich → Brotkrumen 12–28 px | 20 px |
| ✅ | Produktseite | lädt | HTTP 200 |
| ✅ | Produktseite | Kein seitliches Scrollen |  |
| ✅ | Produktseite | Grundschrift ≥ 16 px | 17 px |
| ✅ | Produktseite | § 19-Hinweis im Inhalt sichtbar | „Umsatzsteuerbefreit gemäß § 19 UStG.“ |
| ✅ | Produktseite | Schaltfläche „In den Warenkorb“ gut antippbar (≥ 44 px) | 191 × 51 px |
| ✅ | Produktseite | Kopfbereich: Menü → Konto → Warenkorb in einer Zeile | Menü x=230 y=22, Konto x=278 y=22, Warenkorb x=322 y=22; Kopfhöhe 45 px |
| ✅ | Produktseite | Kopfbereich: Tippflächen ≥ 44 × 44 px | Menü 44×44, Konto 44×44, Warenkorb 44×44 |
| ✅ | Produktseite | Abstand Kopfbereich → Brotkrumen 12–28 px | 20 px |
| ✅ | Warenkorb | Produkt im Warenkorb |  |
| ✅ | Warenkorb | Kein seitliches Scrollen |  |
| ✅ | Warenkorb | Grundschrift ≥ 16 px | 17 px |
| ✅ | Warenkorb | § 19-Hinweis im Inhalt sichtbar | „Umsatzsteuerbefreit gemäß § 19 UStG.“ |
| ✅ | Warenkorb | Schaltfläche „Weiter zur Kasse“ gut antippbar (≥ 44 px) | 342 × 51 px |
| ✅ | Warenkorb | Kopfbereich: Menü → Konto in einer Zeile | Menü x=230 y=22, Konto x=278 y=22; Kopfhöhe 45 px |
| ✅ | Warenkorb | Kopfbereich: Tippflächen ≥ 44 × 44 px | Menü 44×44, Konto 44×44 |
| ✅ | Warenkorb | Abstand Kopfbereich → Seitentitel 12–28 px | 20 px |
| ✅ | Kasse | Kein seitliches Scrollen |  |
| ✅ | Kasse | Grundschrift ≥ 16 px | 17 px |
| ✅ | Kasse | § 19-Hinweis im Inhalt sichtbar | „Umsatzsteuerbefreit gemäß § 19 UStG.“ |
| ✅ | Kasse | Schaltfläche „Jetzt kaufen“ gut antippbar (≥ 44 px) | 342 × 48 px |
| ✅ | Kasse | Schaltfläche „E-Mail-Adresse“ gut antippbar (≥ 44 px) | 342 × 50 px |
| ✅ | Kasse | § 19-Hinweis direkt beim Gesamtbetrag (≤ 80 px) | 46 px unter „Bestellübersicht19,90 €“ |
| ✅ | Kasse | Kopfbereich: Menü → Konto in einer Zeile | Menü x=230 y=22, Konto x=278 y=22; Kopfhöhe 45 px |
| ✅ | Kasse | Kopfbereich: Tippflächen ≥ 44 × 44 px | Menü 44×44, Konto 44×44 |
| ✅ | Kasse | Abstand Kopfbereich → Seitentitel 12–28 px | 20 px |
| ✅ | Browser | Keine JavaScript-Fehler |  |

## Verwaltung im Browser (`tests/e2e/admin.mjs`)

| | Bereich | Prüfung | Detail |
|---|---|---|---|
| ✅ | Anmeldung | Verwaltung erreichbar | /wp-admin/admin.php?page=wc-gzd-setup |
| ✅ | Überblick | Bestellung #28 unter „Beim Lieferanten bestellen“ |  |
| ✅ | Überblick | „Lieferantenauftrag erfassen“ vorhanden |  |
| ✅ | Lieferantenauftrag | Bestellnummer vorbefüllt | 28 |
| ✅ | Lieferantenauftrag | gespeichert | LA-29; Titel: – |
| ✅ | Lieferantenauftrag | Betrag 8,50 und Status übernommen |  |
| ✅ | Lieferantenauftrag | Änderungsprotokoll-Eintrag |  |
| ✅ | Eingangsbeleg | Upload-Feld vorhanden (Ablage sicher konfiguriert) | Die Verbindung wurde unterbrochen. Das Speichern wurde deaktiviert, bis die Verbindung wiederhergestellt ist. Dieser Beitrag wird sicherheitshalber in deinem Browser gesichert. \| Das Backup dieses Beitrags in deinem Browser unterscheidet sich von der unten stehenden Version.Backup wiederherstellenDies ersetzt den aktuellen Editor-Inhalt durch die zuletzt gespeicherte Version. Du kannst im Editor „Rückgängig machen“ oder „Wiederherstellen“ verwenden, um den vorherigen Inhalt zurückzuholen oder zu der wiederhergestellten Version zurückzukehren.Diesen Hinweis verwerfen |
| ✅ | Eingangsbeleg | gespeichert | ER-30 |
| ✅ | Eingangsbeleg | PDF und XML abgelegt, Prüfsumme „unverändert“ | 2× „unverändert“; Hinweise: Der Beitrag wurde veröffentlicht. Diesen Hinweis verwerfen \| Die Verbindung wurde unterbrochen. Das Speichern wurde deaktiviert, bis die Verbindung wiederhergestellt ist. Dieser Beitrag wird sicherheitshalber in deinem Browser gesichert. \| Das Backup dieses Beitrags in deinem Browser unterscheidet sich von der unten stehenden Version.Backup wiederherstellenDies ersetzt den aktuellen Editor-Inhalt durch die zuletzt gespeicherte Version. Du kannst im Editor „Rückgängig machen“ oder „Wiederherstellen“ verwenden, um den vorherigen Inhalt zurückzuholen oder zu der wiederhergestellten Version zurückzukehren.Diesen Hinweis verwerfen |
| ✅ | Eingangsbeleg | Download als Admin liefert die Originaldatei | DEMO-Rechnung.pdf, beginnt mit „%PDF-“ |
| ✅ | Eingangsbeleg | Getarnte PHP-Datei abgelehnt (T-20) | „DEMO-getarnt.pdf“: Nur PDF, XML (E-Rechnung), JPG oder PNG mit passendem Inhalt sind erlaubt. Diesen Hinweis verwerfen \| Der Beitrag wurde veröffentlicht. Dies |
| ✅ | Bestellung | Pawline-Box „Dropshipping“ sichtbar |  |
| ✅ | Bestellung | Lieferantenauftrag LA-29 in der Bestellung angezeigt |  |
| ✅ | Bestellung | Tracking gespeichert |  |
| ✅ | Bestellung | Bestellnotiz „Versandinfo erfasst“ |  |
| ✅ | Auswertung | lädt mit Rohdifferenz und Grundlage |  |
| ✅ | Auswertung | Wort „Gewinn“ nicht als Ergebnis verwendet |  |
| ✅ | CSV-Export | Lieferantenaufträge als CSV: Datei pawline-purchases-2026-11-15-2026-11-17.csv | UTF-8 mit BOM, Semikolon |
| ✅ | CSV-Export | Lieferantenaufträge als CSV: enthält DEMO-LA-0001 mit Betrag 8,50 | Auftrag;Lieferant;"Auftragsnr. Lieferant";Bestelldatum;"Einkaufsbetrag brutto";Währung;Status;Kundenbestellungen;Eingangsbelege ⏎ LA-29;"[DEMO] Lieferant";DEMO-LA-0001;2026-11-16;8,50;EUR;"Beim Lieferanten bestellt";#28; |
| ✅ | CSV-Export | Eingangsbelege als CSV: Datei pawline-invoices-2026-11-15-2026-11-17.csv | UTF-8 mit BOM, Semikolon |
| ✅ | CSV-Export | Eingangsbelege als CSV: enthält DEMO-RE-0001 mit Betrag 8,50 | Beleg-ID;Lieferant;Rechnungsnummer;Rechnungsdatum;Netto;Umsatzsteuer;Gesamt;Währung;Steuerangabe;Lieferantenaufträge;Dateien;SHA-256 ⏎ ER-30;"[DEMO] Lieferant";DEMO-RE-0001;2026-11-16;7,14;1,36;8,50;EUR;"19 % USt (DEMO)" |
| ✅ | CSV-Export | Eingangsbelege: SHA-256 enthalten |  |

## Verwaltung in der Datenbank (`tests/ci/verify-admin.php`)

| | Bereich | Prüfung | Detail |
|---|---|---|---|
| ✅ | Verwaltung (Datenbank) | Lieferantenauftrag gespeichert | LA-29 |
| ✅ | Verwaltung (Datenbank) | Lieferantenauftrag ↔ Kundenbestellung verknüpft | Bestellungen: 28 |
| ✅ | Verwaltung (Datenbank) | Eingangsbeleg gespeichert | ER-30 |
| ✅ | Verwaltung (Datenbank) | Eingangsbeleg ↔ Lieferantenauftrag verknüpft |  |
| ✅ | Verwaltung (Datenbank) | Zwei Originaldateien abgelegt (PDF + XML) | 2 Datei(en) |
| ✅ | Verwaltung (Datenbank) | Datei in geschützter Ablage, Prüfsumme ok: DEMO-Rechnung.pdf | /var/pawline-private/2026/8a60d574e34d9654658116f394f79772.pdf |
| ✅ | Verwaltung (Datenbank) | Datei in geschützter Ablage, Prüfsumme ok: DEMO-E-Rechnung.xml | /var/pawline-private/2026/4a87d13d945ad7dc5e80e0ede6b78c8d.xml |
| ✅ | Verwaltung (Datenbank) | Keine Belegdatei im öffentlichen Uploadordner |  |
| ✅ | Verwaltung (Datenbank) | Tracking an der Bestellung gespeichert | [{"carrier":"DHL (DEMO)","tracking":"DEMO00012345","url":"","date":"2026-11-16"}] |
| ✅ | Verwaltung (Datenbank) | Bestellnotiz zum Lieferantenauftrag/Tracking vorhanden | 6 Notizen |
| ✅ | Verwaltung (Datenbank) | Kunde nicht automatisch benachrichtigt (keine Kundennotiz) |  |

## WordPress-Editor (`tests/e2e/editor.mjs`)

| | Bereich | Prüfung | Detail |
|---|---|---|---|
| ✅ | Website-Editor | Vorlage front-page: alle Blöcke gültig | 66 Blöcke |
| ✅ | Website-Editor | Vorlage index: alle Blöcke gültig | 41 Blöcke |
| ✅ | Website-Editor | Vorlage page: alle Blöcke gültig | 32 Blöcke |
| ✅ | Website-Editor | Vorlage page-wide: alle Blöcke gültig | 31 Blöcke |
| ✅ | Website-Editor | Vorlage page-cart: alle Blöcke gültig | 32 Blöcke |
| ✅ | Website-Editor | Vorlage page-checkout: alle Blöcke gültig | 32 Blöcke |
| ✅ | Website-Editor | Vorlage single-product: alle Blöcke gültig | 44 Blöcke |
| ✅ | Website-Editor | Vorlage archive-product: alle Blöcke gültig | 56 Blöcke |
| ✅ | Website-Editor | Vorlage taxonomy-product_cat: alle Blöcke gültig | 56 Blöcke |
| ✅ | Website-Editor | Vorlage taxonomy-product_tag: alle Blöcke gültig | 56 Blöcke |
| ✅ | Website-Editor | Vorlage product-search-results: alle Blöcke gültig | 56 Blöcke |
| ✅ | Website-Editor | Vorlage search: alle Blöcke gültig | 38 Blöcke |
| ✅ | Website-Editor | Vorlage 404: alle Blöcke gültig | 33 Blöcke |
| ✅ | Website-Editor | Template-Teil header: alle Blöcke gültig | 16 Blöcke |
| ✅ | Website-Editor | Template-Teil footer: alle Blöcke gültig | 11 Blöcke |
| ✅ | Muster | Pawline-Muster gefunden | pawline/hero, pawline/kategorien, pawline/kontakt, pawline/neue-produkte, pawline/rechtstext-platzhalter, pawline/text-bild, pawline/vorteile |
| ✅ | Muster | Nach Speichern und Neuladen alle Blöcke gültig | 45 Blöcke |
| ✅ | Bearbeiten | Titel und Absatz über die Tastatur eingegeben | Editor-Inhalt im iframe |
| ✅ | Bearbeiten | Bild in die Mediathek hochgeladen und eingefügt | Medien-ID 37 |
| ✅ | Bearbeiten | Block verschoben (Reihenfolge Absatz → Bild → Spalten) | core/paragraph → core/image → core/columns |
| ✅ | Bearbeiten | Nach Speichern und Neuladen alle Blöcke gültig | 9 Blöcke |
| ✅ | Bearbeiten | Änderungen im Frontend sichtbar (Text, Bild, Spalten) |  |
| ✅ | Website-Editor | Startseiten-Text geändert und gespeichert | core/heading |
| ✅ | Website-Editor | Startseiten-Vorlage nach Speichern gültig | 67 Blöcke |
| ✅ | Website-Editor | Änderung auf der Startseite sichtbar |  |
| ✅ | Konsole | Keine Meldungen zur Blockvalidierung |  |

## ⏸️ Ausdrücklich NICHT geprüft

| Bereich | Grund |
|---|---|
| Kundenrechnung (PDF, Nummernkreis, § 19-Hinweis auf dem Beleg) | Germanized Pro nicht installiert (Lizenz fehlt) |
| Storno-/Korrekturbelege bei Erstattung | Germanized Pro nicht installiert |
| Rechnung als E-Mail-Anhang | Germanized Pro nicht installiert |
| Zahlung mit Stripe (Testkarte erfolgreich/abgelehnt) | Keine Stripe-Testschlüssel; im Kaufablauf wurde nur die Offline-Zahlart „Überweisung (nur CI-Test)“ verwendet |
| Stripe-Webhooks, doppelte Zahlungsbenachrichtigung | Keine Stripe-Testschlüssel |
| Versandkosten | Noch keine echten Versandregeln; im Test nur „Versand (nur CI-Test)“ ohne Kosten |
| Gestalterische Beurteilung (Farben, Abstände) | Nur Screenshots; Beurteilung durch Menschen |
| Hosting-Server (nginx/Apache, wp-config.php) | Nur Docker-Testumgebung (Apache) |
