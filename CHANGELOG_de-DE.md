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
