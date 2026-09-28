# NABU Unofficial — WordPress-Block-Theme für NABU-Ortsgruppen

Ein Block-Theme samt Begleit-Plugin für die Website einer lokalen
NABU-Gruppe auf selbst gehostetem WordPress, im Stil des offiziellen
NABU-Webauftritts: zweistufiger Kopfbereich mit Logoreiter, die offizielle
Farbpalette, Source Sans 3 selbst gehostet, keine Aufrufe fremder Server,
Termine als eigener Inhaltstyp, Bildnachweise am Bild.

**Inoffiziell.** Dieses Projekt ist nicht vom NABU herausgegeben oder
freigegeben. Der NABU stellt Ortsgruppen einen Jimdo-Webbaukasten bereit; wer
stattdessen selbst hosten will, findet hier eine Alternative. Das Logo der
Gruppe ist nicht Teil dieses Repositories — es wird von NABU-Publishing
freigegeben und von der Gruppe selbst hochgeladen.

Kein Elternthema, keine Build-Kette, kein `node_modules`: Was hier liegt, ist
das, was auf den Server kommt.

## Aufbau

| Pfad | Inhalt |
|---|---|
| `themes/nabu-unofficial/` | Das Theme: `theme.json`, Templates und Template-Teile, Vorlagen (Patterns), Stylesheet, zwei kleine Skripte und die eigenen Blöcke (`inc/`). |
| `plugins/nabu-unofficial-content/` | Das Plugin: Inhaltstyp Termin (`nabu_event`) mit Berichten, Zusatzfelder (Termindetails, Bildnachweis, Einstellungen zum Beitragsbild, Unterüberschrift), die Gruppen-Einstellungen und der Abruf der Verbandsmeldungen. Getrennt vom Theme, damit die Inhalte einen Theme-Wechsel überleben. |
| `scripts/scaffold.php` | Seitengerüst für jede Installation, auch Produktion: Seiten, Startseite, Terminseite, Rechtsseiten, Grundeinstellungen. Legt nur an, was fehlt; bestehende Seiten und Einstellungen bleiben, wie sie sind. Nur eine mitgegebene `group.json` überschreibt Gruppendaten, Titel und Untertitel. |
| `scripts/demo-content.php` | Testinhalte für die lokale Instanz: Aktivitäten, Schutzgebiete, Termine, Meldungen, Bilder. Ruft `scaffold.php` auf. |
| `scripts/setup.sh`, `teardown.sh` | Lokale Testinstanz aufsetzen und entfernen. |
| `scripts/staging-refresh.sh`, `deploy.sh` | Kopie von Produktion holen; Git-Stand auf den Server bringen. |
| `scripts/export-content.php`, `import-content.php` | Von Hand angelegte Inhalte samt Bildern zwischen Instanzen übertragen. |
| `scripts/fetch-sample-data.py`, `placeholder-images.py` | Echte Beispieltexte von einer Jimdo-Seite holen (nur Testmaterial); Platzhalterbilder erzeugen. |
| `docker-compose.yml`, `.env.example` | Zwei lokale Instanzen, siehe unten. |
| `docs/redaktion.md` | Anleitung für die Redaktion. |

Code und Kommentare sind Englisch, alles Sichtbare — Oberfläche, Adressen wie
`/termine/`, Dokumentation für die Redaktion — ist Deutsch.

## Gruppendaten

Alles, was eine Gruppe von der nächsten unterscheidet, steht in
*Einstellungen → NABU Gruppe*: Name, Anschrift, Kontakt, Landesverband,
Vereinsregister, Spendenkonto, Treffen, soziale Netzwerke, die VW-Nummer
für das Beitrittsformular und die Adresse der Terminübersicht. Kopf- und
Fußbereich, Kontaktblock und Rechtsseiten lesen diese Werte über die
Block-Bindings-Quelle `nabu/group`; in den Theme-Dateien steht kein Ortsname
und keine Anschrift.

Für das erste Aufsetzen: `scripts/group.example.json` nach
`scripts/group.json` kopieren, ausfüllen, `scaffold.php` ausführen. Die
Werte lassen sich danach jederzeit im Backend ändern; ein weiterer Lauf mit
`group.json` schriebe sie wieder zurück.

## Lokale Instanzen

```bash
cp .env.example .env
NABU_INSTANCE=clean ./scripts/setup.sh    # ~2 Minuten beim ersten Mal
```

Zwei Instanzen teilen sich `docker-compose.yml` und unterscheiden sich nur
in Projektname und Port (`.env`):

| | Port | Inhalt |
|---|---|---|
| **clean** | 8091, im Netz erreichbar | Aus dem Repo erzeugt (`NABU_INSTANCE=clean ./scripts/setup.sh`), mit Demo-Inhalten und `admin`/`admin`; zeigt genau den Git-Stand |
| **staging** (Standard) | 8090, nur `127.0.0.1` | Kopie von Produktion (`./scripts/staging-refresh.sh`), um Änderungen gegen echte Inhalte zu prüfen |

Beide mounten dieselben Theme- und Plugin-Verzeichnisse — eine Codeänderung
ist sofort in beiden sichtbar. `setup.sh` und `teardown.sh` lassen staging
ohne `NABU_FORCE=1` in Ruhe. Beide Instanzen laufen auf derselben
WordPress- und PHP-Version wie Produktion (Image-Tag in
`docker-compose.yml`); nach einem neuen Tag bringt `wp core update`
bestehende Instanzen nach.

`setup.sh` holt drei Plugins von wordpress.org: **Nested Pages** (Seitenbaum
zum Ziehen, `NABU_PAGE_TREE=0` lässt es weg), **Embed Privacy**
(Zwei-Klick-Freigabe für Karten und Videos, `NABU_EMBEDS=0`) und **Safe SVG**
(SVG-Logo in der Mediathek, `NABU_SVG=0`).

Die Testinstanz wird mit echten Inhalten einer NABU-Gruppe gefüllt
(`scripts/fetch-sample-data.py`, Ergebnis in `scripts/sample-data/`, nicht im
Git). **Diese Inhalte dürfen nicht online gehen** — Texte und Fotos gehören
der Quellgruppe. Ohne sie bleiben generierte Platzhalter stehen.

## Produktion

Vorausgesetzt sind SSH-Zugang und wp-cli auf dem Server. Theme und Plugin
kommen als Kopie aus Git; WordPress selbst und die drei Plugins aktualisieren
sich automatisch.

Einmalig, nach der WordPress-Installation beim Hoster:

1. In `.env` die Werte `NABU_PROD_*` eintragen und `./scripts/deploy.sh`
   ausführen. Das Skript legt das Repository neben den Dokumentenstamm,
   kopiert Theme und Plugin nach `wp-content` und aktiviert beide.
2. Auf dem Server Sprache und Plugins einrichten, Deutsch (Sie) wie die
   Texte des Themes:
   `wp language core install de_DE_formal --activate`,
   `wp plugin install wp-nested-pages embed-privacy safe-svg --activate`,
   `wp plugin auto-updates enable wp-nested-pages embed-privacy safe-svg` und
   `wp language plugin install embed-privacy de_DE_formal`.
3. `scripts/group.example.json` als `group.json` auf den Server kopieren,
   außerhalb des Repositorys (das `deploy.sh` bei jedem Lauf ersetzt), etwa
   nach `/nabu-config/group.json`, ausfüllen und
   `wp eval-file <repository>/scripts/scaffold.php /nabu-config/group.json`
   ausführen.
4. Im Backend unter *Einstellungen → Permalinks* einmal speichern: Erst dann
   schreibt WordPress auf Apache die `.htaccess`, ohne die jede Unterseite
   mit 404 antwortet.

Danach bringt `./scripts/deploy.sh` jeden weiteren Stand auf den Server
(`./scripts/deploy.sh v1.1.0` einen Tag, `--dry-run` zeigt nur, was käme);
Inhalte, Uploads und `wp-config.php` bleiben unberührt.
`./scripts/staging-refresh.sh` holt Datenbank und Uploads in die lokale
staging-Instanz, ohne auf dem Server etwas zu ändern.

Templates und Template-Teile, die im Site-Editor gespeichert wurden, liegen
danach als Kopie in der Datenbank und bekommen keine Änderungen aus dem
Theme mehr — bis jemand dort die Anpassungen zurücksetzt.

## Vor dem Livegang

1. **Gruppendaten** unter *Einstellungen → NABU Gruppe* prüfen.
2. **Logo** hochladen (SVG von NABU-Publishing), über den Link im Hinweis,
   den das Backend zeigt, solange keins gesetzt ist; bis dahin steht auf der
   Website ein Platzhalter. Das Website-Icon steht unter *Einstellungen →
   Allgemein*.
3. **Alle Bilder und Beispieltexte** ersetzen. Echte Fotos kommen aus der
   NABU-Medienbibliothek oder von Mitgliedern — nicht von nabu.de.
4. **Impressum und Datenschutzerklärung**: Die Gerüste lesen Anschrift und
   Kontakt aus den Gruppendaten; die Stellen in eckigen Klammern hängen vom
   Hoster ab. Juristisch prüfen lassen.
5. **Vorstand** auf *Über uns* und in den Gruppendaten (*Vertreten durch*).
6. Unter *Einstellungen → Lesen* prüfen, dass Suchmaschinen die Website
   indexieren dürfen.

## Lizenz

GPL-2.0-or-later, Wortlaut in `LICENSE`. Die Pfeilglyphen stammen aus Font
Awesome, die Schrift ist Source Sans 3 von Adobe; beide stehen unter der SIL
Open Font License (`themes/nabu-unofficial/assets/fonts/LICENSE-*.txt`). Die
NABU-Wort-Bild-Marke und die Farbwerte sind Eigentum des NABU; das Theme
verwendet die Farben, bringt aber kein Logo mit.
