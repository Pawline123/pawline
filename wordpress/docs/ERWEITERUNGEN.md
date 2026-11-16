# Vorbereitung der Erweiterungen: Germanized (Pro), ATUM, Stripe

Das Setup-Skript installiert und aktiviert die kostenlosen Plugins von wordpress.org. Die folgenden Einstellungen werden **im Backend** vorgenommen, weil sie von echten Geschäftsdaten abhängen oder in dieser Umgebung nicht gegen die installierte Version geprüft werden konnten. Menünamen können je nach Version leicht abweichen.

Legende: ✅ vom Setup erledigt · ⚙️ im Backend einstellen · 🔑 erfordert Lizenz oder Zugang · 📋 erfordert Tims echte Angaben

## Germanized for WooCommerce (kostenlos)

| | Schritt |
|---|---|
| ✅ | Installiert und aktiviert. |
| ✅⚠️ | Option `woocommerce_gzd_small_enterprise = yes` gesetzt. **Name nicht gegen die installierte Version geprüft** → unter *WooCommerce › Einstellungen › Germanized › Allgemein* kontrollieren, dass die **Kleinunternehmerregelung** aktiv ist. |
| ⚙️ | Einrichtungsassistent durchlaufen, aber **keine** Steuersätze anlegen (WooCommerce-Steuern bleiben aus). |
| 📋 | Rechtsseiten zuordnen (AGB, Widerruf, Datenschutz, Impressum, Versand/Zahlung). Das Setup legt dafür **Entwürfe** mit Platzhalter an; echte Texte einfügen, erst dann veröffentlichen. |
| 📋 | Lieferzeiten, Versandkostenhinweise und Grundpreise nur mit echten Angaben pflegen. |
| ⚙️ | Prüfen, dass Produktseite, Warenkorb, Kasse und Bestell-E-Mails den Kleinunternehmerhinweis zeigen und **kein** „inkl. MwSt.“ erscheint (siehe TESTPLAN T-07). |
| ⚙️ | Entscheiden: Versand/Tracking über **Germanized „Sendungen“** oder über das Pawline-Feld in der Bestellung. Nur eine Stelle führend pflegen. |

## Germanized Pro (🔑 Lizenz, noch nicht gekauft)

Nach dem Kauf: ZIP nach `private-plugins\`, `setup` erneut ausführen, Lizenz im Backend eingeben.

| | Schritt |
|---|---|
| 📋 | Unternehmensdaten auf Rechnungen: Name, Anschrift, Steuernummer oder USt-IdNr. (falls vorhanden). **Nichts erfinden.** |
| 📋 | **Nummernkreis**: Startnummer und Format so wählen, dass er an die bisherige Rechnungsführung (Shopify) lückenlos anschließt. Vor dem Live-Start klären. |
| ⚙️ | Automatische Rechnungserstellung bei Status „In Bearbeitung“ (= bezahlt) oder nach eigener Festlegung. |
| ⚙️ | Rechnung als E-Mail-Anhang an den Kunden; dasselbe Dokument in der Bestellung herunterladbar (keine zweite Rechnung). |
| ⚙️ | Rechnungen festschreiben/finalisieren, damit spätere Produkt- oder Stammdatenänderungen sie nicht verändern. |
| ⚙️ | Storno/Rechnungskorrektur automatisch bei voller und teilweiser Erstattung, mit Bezug zur Originalrechnung. |
| ⚙️ | Hinweistext auf Rechnung und Storno: „Für diesen Umsatz gilt die Steuerbefreiung für Kleinunternehmer gemäß § 19 UStG.“ |
| ⚙️ | Layout an Pawline anpassen (Logo, Farben Moos `#5F7350` / Rinde `#5B4636`), sobald Logo vorliegt. |
| ⚙️ | E-Rechnung (XRechnung/ZUGFeRD) nur aktivieren, wenn an Unternehmen verkauft wird und es benötigt wird; Privatkunden sind von der Pflicht nicht betroffen. |
| 🔒 | **Live-Rechnungsversand gesperrt**, bis Unternehmensdaten vollständig sind und TESTPLAN T-08 bis T-11 bestanden sind. |

## ATUM Inventory Management (kostenlos)

| | Schritt |
|---|---|
| ✅ | Installiert und aktiviert. |
| ⚙️ | Zuerst den tatsächlichen Funktionsumfang mit der installierten WooCommerce-Version prüfen (Lieferanten, Einkaufspreis, Lieferanten-SKU je Produkt/Variante). Ergebnis in TESTSTATUS eintragen. |
| 📋 | Lieferanten mit echten Kontaktdaten anlegen (*ATUM › Lieferanten*). Sie erscheinen dann als Auswahl im Pawline-Lieferantenauftrag. |
| ⚙️ | Bei Dropshipping-Artikeln: **„Lagerbestand verwalten“ aus**, Lagerstatus manuell („Vorrätig“/„Nicht vorrätig“). Zusätzlich im Produkt „Dropshipping-Artikel“ (Pawline) anhaken. |
| ⚠️ | ATUM-**Einkaufsbestellungen** nicht für Dropshipping nutzen oder nie als „erhalten“ markieren. Das würde einen Lagerzugang buchen, der nicht stattgefunden hat. Dafür gibt es die Pawline-Lieferantenaufträge. |
| ℹ️ | Belegdateien an ATUM-Bestellungen gibt es erst in der kostenpflichtigen Erweiterung *Purchase Orders PRO*. Nicht nötig, dafür sind die Pawline-Eingangsbelege da. |

## Stripe (kostenlos, 🔑 Konto nötig)

| | Schritt |
|---|---|
| ✅ | Plugin „WooCommerce Stripe Gateway“ installiert. |
| 🔑 | Stripe-Konto verbinden bzw. **Test-Schlüssel** im Backend eingeben (*WooCommerce › Einstellungen › Zahlungen › Stripe*). Nicht in Dateien speichern. |
| ⚙️ | Testmodus aktiv lassen. Testkarten aus der Stripe-Dokumentation verwenden. |
| ⚙️ | Webhook für die lokale Umgebung: Stripe erreicht `localhost` nicht direkt. Für Webhook-Tests die Stripe CLI (`stripe listen --forward-to localhost:8080/?wc-api=wc_stripe`) verwenden oder auf der Testadresse beim Hosting prüfen. |
| ⚙️ | PayPal später über die offizielle Erweiterung ergänzen, falls gewünscht. |

## Nicht enthalten (bewusst)

- Vollständige Finanzbuchhaltung, Bankabgleich, Steuererklärungen, GoBD-zertifizierte Archivierung.
- DATEV-Export (der Pawline-CSV-Export ist kein DATEV-Format).
- Automatische Lieferantenanbindung, Bestandsabgleich oder Trackingabfrage.
