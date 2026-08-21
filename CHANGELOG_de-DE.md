# Nächste Version
- Die Engine der Admin-Live-Vorschau wird im Plugin-ZIP festgeschrieben, sodass ein Build die angegebene `@markup-carve/carve`-Version auflöst und nicht die jeweils aktuelle aus der Registry.

# 0.1.2
- Erfordert carve-php 0.1.5: Bei einem URL-Attribut mit Werteliste wird nun jeder Kandidat geprueft, statt dem fuehrenden Schema des Werts zu vertrauen. Aktualisieren, wenn nicht vertrauenswuerdiges Carve gerendert oder nicht vertrauenswuerdiges HTML importiert wird.
- Konfigurierbare `:name:`-Symbol-Kurzbefehle mit vertrauenswürdigen rohen HTML-Ersatzwerten hinzugefügt.
- Eine Kopfzelle in Listentabellen wird jetzt als `<th scope="col">` statt als blosses `<th>` gerendert. Theme-Overrides oder Tests, die auf das blosse Tag pruefen, muessen angepasst werden.

# 0.1.1
- Optionales Rendering von PlantUML- und Puml-Bloecken ueber Kroki, standardmaessig deaktiviert.
- Carve-Engine auf 0.1.4 aktualisiert fuer die aktuelle Sicherheitshaertung und Rendering-Korrekturen.
- Plugin-Service-Konfiguration aktualisiert und Entwicklungswerkzeuge auf getaggte stabile Releases festgelegt.

# 0.1.0
- Erste Veroeffentlichung: rendert die Carve-Markup-Sprache als sicheres HTML in Shopware 6.6 und 6.7.
- Twig-Filter (carve, carve_text, carve_md), ein Carve-CMS-Element und -Block, Produkt- und Kategorie-Zusatzfelder, Live-Vorschau im Admin, Rendering in transaktionalen Mails und Inline-Produktreferenzen.
- Immer aktive Erweiterungen: Admonitions, Details, Listentabellen, Fussnoten, Autolink, Absicherung externer Links, Inhaltsverzeichnis und Spoiler.
- Konfigurierbarer Roh-HTML-Durchlass (standardmaessig aus), typografische Anfuehrungszeichen mit 20 Sprachen sowie optionales Rendering von Mermaid und Chart.js.
- Immer aktive Sicherheitsbasis: Sperrliste fuer URL-Schemata und Attribut-Absicherung, unabhaengig von jeder Einstellung.
