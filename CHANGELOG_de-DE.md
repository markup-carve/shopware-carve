# 0.1.6
- Installation und Update des Plugins brechen nicht mehr beim Lesen der Konfiguration ab, und der Service-Container kompiliert in einem Standard-Shop.
- Die Endpunkte des Editors für Vorschau, Import und gemeinsam genutzte Dateien sind jetzt registriert, sodass die Vorschau im Administrationsbereich rendert. Sie funktioniert auch bei aktivem Debug-Modus.
- `:legal[privacy]`, `tos`, `imprint` und `withdrawal` verlinken das in den Stammdaten hinterlegte Layout. Bisher blieben sie reiner Text.
- Eine einzelne Produktkarte behält eine kartengerechte Breite.
- Kategorietexte stehen bündig mit dem Rest der Seite, statt von Rand zu Rand zu laufen, und Karten sowie Raster halten Abstand zum nächsten Block.
- Die Bildauswahl im Editor zeigt Dateinamen. Bisher waren die Einträge leer.
- Spezifikationslisten erscheinen als zweispaltige Liste mit Trennlinien.

# 0.1.5
- Ein gemeinsamer Carve-Editor für das CMS-Element sowie Produkt-, Kategorie- und Herstellerfelder, mit serverseitiger Vorschau, Diagnosen, Produkt- und Medienauswahl sowie geprüftem HTML- und Markdown-Import.
- Neue Commerce-Elemente für die Redaktion: Verkaufskanalpreise, Bestandsangaben, Karten, Raster, Spezifikationslisten, Datenblätter, Medienbilder, Textbausteine und interne Links.
- Gemeinsam genutzte Inhalts-Includes: Auswahl aus einer Include-Bibliothek, Prüfungen beim Veröffentlichen, Cache-Tags für Abhängigkeiten und der Befehl `carve:includes:invalidate` für Deployment-Jobs.
- Konfigurierbare Zuordnung von CMS-Feldern, Platzierung von Kopien und Verhalten externer Links, automatische Typografie-Locale sowie Maskierung literaler Daten für den Mailversand.
- Produktreferenzen berücksichtigen jetzt die Sichtbarkeit im Verkaufskanal und verwenden SEO-URLs. Abfragen werden gebündelt, sodass eine Seite mit vielen Referenzen deutlich weniger Datenbankabfragen auslöst.
- Die Vorschau im Administrationsbereich verwirft veraltete Antworten und zeigt Fehler in einem abgeschotteten Rahmen, statt die vorherige Darstellung beizubehalten.
- Fragment-IDs und Tab-Radiogruppen bleiben unabhängig, wenn mehrere Carve-Blöcke auf einer Seite stehen.

# 0.1.4
- Datei-Includes mit Freigabe hinzugefügt. Ein Administrator legt ein absolutes Basisverzeichnis fest, standardmäßig leer; ohne dieses bleibt jede Include-Direktive überall wörtlich stehen.
- Mit gesetztem Basisverzeichnis lösen der Befehl carve:render und das Carve-CMS-Element Includes auf; ein CMS-Redakteur benötigt zusätzlich das Recht „Carve-Datei-Includes expandieren“. Produkt-, Kategorie- und Herstellerfelder behalten Direktiven wörtlich, unabhängig davon, wer sie geschrieben hat.
- Die Live-Vorschau im Admin rendert jetzt über eine Server-Route mit den Rechten des jeweiligen Redakteurs und zeigt damit das Ergebnis der Storefront.
- Erfordert carve-php 0.1.9; die Engine der Admin-Vorschau ist auf carve-js 0.1.7 festgeschrieben.
- Die deutsche Plugin-Bezeichnung lautet wieder „Carve für Shopware“ statt einer ASCII-Umschrift.

# 0.1.3
- Erfordert carve-php 0.1.5: Bei einem URL-Attribut mit Werteliste wird nun jeder Kandidat geprüft, statt dem führenden Schema des Werts zu vertrauen. Aktualisieren, wenn nicht vertrauenswürdiges Carve gerendert oder nicht vertrauenswürdiges HTML importiert wird.
- Konfigurierbare `:name:`-Symbol-Kurzbefehle mit vertrauenswürdigen rohen HTML-Ersatzwerten hinzugefügt.
- Eine Kopfzelle in Listentabellen wird jetzt als `<th scope="col">` statt als bloßes `<th>` gerendert. Theme-Overrides oder Tests, die auf das bloße Tag prüfen, müssen angepasst werden.
- Die Engine der Admin-Live-Vorschau wird im Plugin-ZIP festgeschrieben, sodass ein Build die angegebene `@markup-carve/carve`-Version auflöst und nicht die jeweils aktuelle aus der Registry.
- Das Release-ZIP wird auf carve-php 0.1.6 und carve-js 0.1.5 festgeschrieben; die JavaScript-Abhängigkeit erfordert mindestens die sicherheitskorrigierte Linie `^0.1.5`.
- Mermaid-, Chart- und PlantUML-Platzhalter erhalten eine Bildrolle und einen zugänglichen Namen.

# 0.1.2
- Nie veröffentlicht. Diese Version wurde nie im Store bereitgestellt, kein Shop hat sie erhalten; alle Inhalte sind unter der Version darüber aufgeführt.

# 0.1.1
- Optionales Rendering von PlantUML- und Puml-Blöcken über Kroki, standardmäßig deaktiviert.
- Carve-Engine auf 0.1.4 aktualisiert für die aktuelle Sicherheitshärtung und Rendering-Korrekturen.
- Plugin-Service-Konfiguration aktualisiert und Entwicklungswerkzeuge auf getaggte stabile Releases festgelegt.

# 0.1.0
- Erste Veröffentlichung: rendert die Carve-Markup-Sprache als sicheres HTML in Shopware 6.6 und 6.7.
- Twig-Filter (carve, carve_text, carve_md), ein Carve-CMS-Element und -Block, Produkt- und Kategorie-Zusatzfelder, Live-Vorschau im Admin, Rendering in transaktionalen Mails und Inline-Produktreferenzen.
- Immer aktive Erweiterungen: Admonitions, Details, Listentabellen, Fußnoten, Autolink, Absicherung externer Links, Inhaltsverzeichnis und Spoiler.
- Konfigurierbarer Roh-HTML-Durchlass (standardmäßig aus), typografische Anführungszeichen mit 20 Sprachen sowie optionales Rendering von Mermaid und Chart.js.
- Immer aktive Sicherheitsbasis: Sperrliste für URL-Schemata und Attribut-Absicherung, unabhängig von jeder Einstellung.
