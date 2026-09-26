# Für die Redaktion

Was die Website kann und wie sie gepflegt wird.

## Gruppendaten

Name, Anschrift, Kontakt, Landesverband, Vorstand, Spendenkonto, Treffen und
soziale Netzwerke stehen unter *Einstellungen → NABU Gruppe*. Von dort lesen
sie Kopf- und Fußbereich, der Kontaktblock, Impressum, Datenschutzerklärung
und das Spendenkonto auf *Mitmachen*. Der Editor zeigt diese Absätze an,
ändern lassen sie sich dort aber nicht — geändert wird in den Einstellungen,
dann stimmt es überall. Ein leeres Feld lässt seinen Absatz weg, im Editor
steht an seiner Stelle grau „NABU Gruppe“; fehlt etwas, das ins Impressum
gehört, weist das Backend darauf hin.

## Seitenstruktur und Navigation

Die **Seitenspalte** zeigt die Hauptseiten der Website und klappt den
Bereich auf, in dem man gerade ist, samt seiner Unterseiten. Sie baut sich
aus dem **Seitenbaum** (*Seiten*, Plugin Nested Pages): Wer eine Seite unter
eine andere zieht, findet sie dort auch in der Navigation, in derselben
Reihenfolge. Das Feld *Nav Title* gibt einer Seite eine kürzere Beschriftung
für die Navigation.

**Aus der Navigation nehmen:** In der Schnellbearbeitung des Seitenbaums
*Hide in Nav Menu* setzen. Die Seite bleibt erreichbar, auffindbar und in den
Brotkrumen; sie steht nur nicht mehr in der Seitenspalte. Seiten der obersten
Ebene, die so versteckt sind — Impressum und Datenschutzerklärung —, stehen
stattdessen in der Rechtszeile des Fußbereichs. *Versteckte einblenden* oben
im Baum zeigt, was gerade draußen ist.

Die **Hauptnavigation** im blauen Balken und die kleine Navigation darüber
stehen im Kopfbereich: *Design → Editor → Vorlagen → Header → Kopfbereich*,
dort den Navigationsblock auswählen. Der letzte Punkt der Hauptnavigation ist
orange (Klasse `nabu-nav-cta`). Der Link zum Landesverband in der kleinen
Navigation folgt den Gruppendaten, mit der kurzen Form des Namens
(*Landesverband, kurz*, sonst etwa „NABU BW“); der Fußbereich nennt ihn voll.

Unter 782 Pixeln Breite wird die Seitenspalte zum Menü: Der Schalter „Menü“
im blauen Balken blendet sie ein, samt Suchfeld.

Die Reihenfolge ohne Nested Pages: *Seiten → Schnellbearbeitung → Reihenfolge*.
Im Blockeditor gibt es das Feld nicht.

## Bereiche mit Unterseiten

*Aktivitäten* listet seine Unterseiten als Kacheln, mit Beitragsbild und
Textauszug. Für einen weiteren Bereich — Schutzgebiete, Exkursionen — eine
Seite anlegen, die Einträge als Unterseiten darunter hängen und die Kacheln
einfügen: den Block *Abfrage-Loop* mit *Filter → Übergeordnete Seiten* auf
die eigene Seite, sortiert nach *Reihenfolge*. Am einfachsten kopiert man ihn
von *Aktivitäten*.

## Templates

Jede Seite hat rechts die Seitenspalte mit Navigation, den nächsten Terminen
und dem Kasten „Aktiv werden“. Die Spalte ist ein Template-Teil (*Design →
Editor → Vorlagen → Allgemein → Seitenspalte*): Eine Änderung dort gilt auf
allen Seiten. Für Seiten ohne Spalte und ohne Titel, wie die Startseite, gibt
es das Template *Seite ohne Titel (volle Breite)*, zu wählen in der
Seitenleiste unter *Template*.

## Die Startseite

Von oben nach unten: Titelbild, die nächsten drei Termine mit dem
regelmäßigen Treffen darunter, die neuesten Meldungen, die Aktivitäten,
*Neues vom NABU* und der Aufruf zur Mitgliedschaft. Termine, Meldungen und
Verbandsmeldungen füllen sich von selbst.

**Titelbild.** Es reicht über die ganze Fensterbreite und ist etwa 40 % der
Fensterhöhe hoch. Das Foto erscheint vollständig; ist es schmaler als das
Fenster, setzt es sich links und rechts unscharf fort. Am besten passt ein
flaches Querformat um 3 : 1 mit mindestens 1920 Pixeln Breite. Auf dem Handy
ist das Titelbild 300 Pixel hoch und das Foto seitlich beschnitten. In den
Einstellungen des Blocks *Fester Hintergrund* und *Mindesthöhe* nicht setzen,
beides schaltet diese Größe ab.

**Regelmäßig.** Die Zeile unter den Terminen kommt aus dem Feld *Treffen* der
Gruppendaten; Gruppenabende ohne besonderes Thema müssen nicht einzeln
eingetragen werden.

**Neues vom NABU.** Die Meldungen von nabu.de und vom Landesverband holt die
Website alle paar Stunden selbst, samt Bild; beim Landesverband nur
Meldungen, keine Themenseiten. Besucher laden dabei nichts von nabu.de, es
braucht also keine Einwilligung.

## Termine

*Termine → Neuen Termin anlegen*. Beginn, Ende, Ort und Treffpunkt stehen
rechts im Kasten *Termindetails*. Das Datum beim Beginn ist Pflicht, danach
wird sortiert; die Uhrzeit darf leer bleiben, solange sie nicht feststeht.
Für einen Treffpunkt ohne Anschrift, etwa im Wald, nimmt *Kartenposition*
Koordinaten oder einen Link aus Google Maps oder OpenStreetMap. Die Karte
lädt erst, wenn jemand sie anklickt.

Vergangene Termine wandern von selbst aus den Listen nach „Was war“ unter der
Terminübersicht.

**Adressen.** Jeder Termin steht unter seinem Jahr, etwa
`/termine/2026/mahd-fruehjahr/`; das Jahr kommt aus dem Beginn. Derselbe Name
darf jedes Jahr wiederkommen, nur zweimal im selben Jahr bekommt er eine
Nummer. Wird ein veröffentlichter Termin verschoben oder umbenannt, führt die
alte Adresse zur neuen.

**Die Terminseite.** Die Übersicht unter `/termine/` ist die Liste aller
Termine. Für sie steht die Seite **Termine** (*Einstellungen → Lesen →
Terminseite*, in der Seitenliste mit dem Zusatz „Terminseite“) — wie
*Aktuelles* für die Meldungen: Ihr Titel ist die Überschrift der Übersicht,
ihr Text die Einleitung darüber, und wo sie im Seitenbaum hängt, stehen die
Termine in der Navigation.

**Flyer.** Den Flyer, etwa für den WhatsApp-Status, als Bildblock in den
Termin setzen, mit einem Alternativtext, der das Wichtigste nennt.

**Bericht nach dem Termin.** Einen ganz normalen *Beitrag* schreiben und
rechts unter *Bericht zu Termin* den Termin wählen — oder im Termin auf
*Bericht zu diesem Termin schreiben* klicken. Der Bericht steht dann auf der
Seite des Termins über der Ankündigung, unter derselben Adresse, die vorher
schon verschickt wurde; unter „Aktuelles“ erscheint er mit „Bericht · Datum“.
Wird der Bericht zurückgezogen oder der Termin gelöscht, ist alles wieder wie
vorher.

> Termine zusätzlich an die zentrale Termindatenbank des NABU melden
> (`termine@NABU.de` oder das Formular auf nabu.de/termine). Nur dann
> erscheinen sie auf nabu.de und beim Landesverband; die Website liest von
> dort nichts zurück.

## Meldungen

*Beiträge → Neuen Beitrag erstellen*. Die *Unterüberschrift* rechts steht
unter dem Titel. Die Adresse trägt das Jahr, `/aktuelles/2026/waldwanderung/`,
sodass jährliche Meldungen wie die zur Jahreshauptversammlung ohne Nummer
auskommen.

**Beitragsbild.** Es erscheint in den Kacheln im Format 16 : 9 und über dem
Text im Format 3 : 2, beide Male beschnitten. Unter dem Bild in der
Seitenleiste legt *Bildausschnitt* fest, welcher Punkt dabei zu sehen bleibt;
ein neues Beitragsbild beginnt wieder mit der Mitte. Die Kachelbilder schneidet
die Website beim Speichern selbst in passenden Größen zu; für sie genügt ein
Foto ab 960 Pixeln Breite, im Hochformat ebenso. Ohne Haken bei *Über dem
Text zeigen* steht es nur in den Kacheln — richtig, wenn dasselbe Foto im
Text steht, etwa in einer Galerie. Das gilt ebenso für Seiten und Termine.

**Kategorie.** Eine Kategorie pro Beitrag genügt, etwa *Biotoppflege*. Sie
steht hinter dem Datum, über dem Titel und in den Kacheln: „14. August 2026
· Biotoppflege“; über dem Titel führt sie zu allen Meldungen des Themas. Kategorien sind freiwillig; die
Standardkategorie „Allgemein“ zeigt die Website nicht an.

**Meldungen auf einer Seite.** Um auf einer Seite wie *Aktivitäten →
Biotoppflege* deren Berichte zu zeigen, die Vorlage *Meldungen zu einem
Thema* einfügen und im Abfrage-Loop unter *Filter → Taxonomien* die
Kategorie wählen. Sie zeigt die neuesten sechs, darunter wird geblättert;
in diesen Kacheln entfällt die Kategorie, sie ist ja das Thema der Seite.

## Bilder

In der Mediathek hat jedes Bild ein Feld **Bildnachweis** („Foto: NABU/F.
Mustermann“). Die Website hängt ihn an jede Bildunterschrift; in den Kacheln
steht er nicht, dort gehört er zum Artikel. Fehlen Alternativtext oder
Bildnachweis, weist die Mediathek darauf hin.

## Beschreibung für Suchmaschinen

Suchmaschinen zeigen unter dem Seitentitel ein paar Zeilen Text. Die Website
gibt ihnen dafür den **Textauszug** mit, einzutragen rechts in der
Seitenleiste bei Beiträgen, Terminen und Seiten. Ohne Textauszug nimmt sie
die ersten Absätze, gekürzt auf etwa 155 Zeichen; die Startseite bekommt
„Name der Gruppe – Untertitel der Website“ (*Einstellungen → Allgemein*). Der
Textauszug einer Unterseite erscheint auch in ihrer Kachel.

## Vorlagen und Blöcke

Im Block-Inserter unter *Vorlagen → NABU* liegen fertige Abschnitte: Startseite,
Titelbild, Teaser-Karten, farbige Kacheln, Termine auf Sandband, Meldungen
und Termine als Reiter, Meldungen zu einem Thema, Handlungsband, Kontaktblock, Navigationskasten,
Spendenleiste. Eingefügt sind sie normale Blöcke und frei bearbeitbar.

Blockstile: bei Buttons *NABU Aktion (orange)*, *NABU Kontur hell* und
*NABU Kontur blau*, bei Überschriften *NABU Dachzeile* und *NABU Untertitel*.
Orange steht für Handlung; sparsam einsetzen, damit es auffällt.

Eigene Blöcke:

* **NABU Termine** — Anzahl, Zeitraum, Darstellung als Liste oder Kacheln,
  Überschriftenebene. Die Ebene muss zur Umgebung passen: nach einer H1
  folgt H2.
* **NABU Verbandsmeldungen** — die neuesten Meldungen des Bundes- oder
  Landesverbands als Kacheln.
* **NABU Seitennavigation**, **NABU Brotkrumen**, **NABU Seitentitel**,
  **NABU Termindetails** und die Blöcke des Fußbereichs stecken in den
  Vorlagen und müssen nicht eingefügt werden.
