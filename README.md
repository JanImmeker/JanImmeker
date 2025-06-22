# README

Hier zal ik belangrijke informatie plaatsen over mijn GitHub pagina.
Dit repository bevat een eenvoudige WordPress plugin voor de BOTSAUTO Sales Checklist.

Plaats de map `botsauto-checklist` in de map `wp-content/plugins` van je WordPress installatie en activeer de plugin.

Elke checklist is een apart bericht van het type **BOTSAUTO Checklist**. De plugin installeert standaard één checklist met alle BOTSAUTO vragen maar de beheerder kan extra checklists aanmaken. Iedere checklist heeft een eigen shortcode:

```
[botsauto_checklist id="123"]
```

waarbij `123` het unieke ID van de checklist is. Dit ID is niet altijd oplopend, gebruik daarom het getal dat in de kolom **Shortcode** van het overzicht staat. Klik op die waarde om het volledige shortcode naar het klembord te kopiëren. Een checklist kan de inhoud van een andere checklist importeren via het zijpaneel in het bewerkscherm.
Bestaat het opgegeven ID niet, dan verschijnt de melding *Checklist niet gevonden.* op de plaats van het shortcode.

De plugin genereert een PDF van de ingevulde checklist via de meegeleverde FPDF-bibliotheek. Om vet en cursief te kunnen gebruiken zijn de standaard Helvetica-fontbestanden `helvetica.php`, `helveticab.php`, `helveticai.php` en `helveticabi.php` opgenomen in `botsauto-checklist/lib/font`.

Checklistregels hebben vier velden gescheiden door `|`: fase, toelichting, korte vraag en het daadwerkelijke checklist‑item. Een regel kan velden leeg laten. Bijvoorbeeld:

```
Voorbereiden|Korte uitleg fase|KEM toegepast?|Is de potentiële opdrachtgever gekwalificeerd?
```

Deze structuur wordt zowel in de admin als op de frontend getoond.
In het beheerscherm verschijnen de checklistregels gegroepeerd per fase in inklapbare secties.
Met knoppen kan de beheerder eenvoudig fases, vragen en checklistitems toevoegen of verwijderen. De verwijderknoppen staan direct naast de invoervelden zodat alles netjes is uitgelijnd. Een fase kan direct worden verwijderd en verdwijnt automatisch wanneer alle onderliggende vragen worden weggehaald. Bij een vraag kunnen meerdere checklistitems worden toegevoegd.

Ingezonden formulieren worden opgeslagen als custom post type **BOTSAUTO inzendingen**. De gebruiker geeft een titel, naam en e‑mailadres op. Het e‑mailadres wordt gecontroleerd op geldigheid; bij een fout verschijnt een Nederlandse melding dat een geldig adres vereist is. In de lijst met inzendingen staan de kolommen Titel, Naam, Email, Afgerond, Datum, Checklist en URL. De URL is de unieke link waarmee de gebruiker later verder kan gaan. In het bewerkscherm van een inzending worden deze gegevens samen met de ingevulde checklist getoond.

Na het versturen ontvangt de gebruiker een e‑mail met de PDF in de bijlage en een unieke link om de checklist later te bewerken. De plugin stuurt de bezoeker na het opslaan automatisch terug naar dezelfde pagina met deze link in de URL. Speciale tekens worden voor de PDF geconverteerd naar Latin‑1 zodat woorden zoals "geïdentificeerd" correct worden weergegeven.
Wanneer een gebruiker de checklist voor het eerst verzendt, wordt de PDF altijd automatisch gemaild.

De inzending wordt standaard verwerkt via `admin-post.php`. Controleer bij problemen of dit pad niet door een beveiligingsplugin wordt geblokkeerd.
Is dit het geval, schakel dan de optie **Alternatieve submit-URL** in onder *E-mail BCC*. De checklist wordt dan naar de huidige pagina gepost, of naar de URL die je daar invult.
Zorg dat deze URL het queryargument `botsauto_submit=1` bevat; de plugin onderschept dat verzoek en slaat de inzending ook zonder toegang tot `/wp-admin/` op.
Via de filter `botsauto_form_action` kun je dit verder aanpassen.

Wanneer de beheerder de checklist wijzigt terwijl een gebruiker al een ingevulde versie heeft, ziet de gebruiker bij het openen een melding met de keuze om de nieuwe checklist te gebruiken. De antwoorden blijven gekoppeld aan de oorspronkelijke vragen zodat er geen vinkjes verspringen.

Voor het versturen van e‑mail gebruikt de plugin de standaard `wp_mail` functie. De afzender wordt expliciet gezet op het beheerdersadres van de site zodat SMTP-plugins de mail correct afleveren.

Wanneer je de plugin verwijdert via het WordPress pluginoverzicht worden alle opgeslagen checklistregels en inzendingen eveneens verwijderd. Ook de ingestelde opties worden dan gewist. Bij een nieuwe installatie wordt zo altijd de standaard checklist van de plugin geladen. Via het importmenu in het bewerkscherm kan deze originele checklist altijd opnieuw worden geïmporteerd onder de optie **BOTSAUTO standaard**.
De standaardinstallatie bevat de complete BOTSAUTO Sales Checklist met alle fases, vragen en checklistitems, zodat je direct aan de slag kunt.

### Stijl aanpassen

Onder **Instellingen → BOTSAUTO** staat nu één submenu **Opmaak**. Hier kies je de primaire kleur, tekstkleur, achtergrondkleur, het lettertype (waaronder Google Font *Oswald*) en kun je een afbeelding selecteren die rechtsboven in de checklist verschijnt en ook in de PDF wordt opgenomen. Je bepaalt ook hoe groot de afbeelding is en of hij links, rechts of gecentreerd staat. In hetzelfde scherm stel je tevens de geavanceerde opmaak per element in (fase, vraag, item, knoppen, velden en checkboxen). Bij installatie gebruikt de plugin standaard de kleuren #00306a (primair), #4D4D4D (tekst) en #edf4f7 (achtergrond) met lettertype Oswald. De submitknop heeft randkleur #D14292.
Je kunt nu ook een vaste titel voor de checklist ingeven. Standaard is dit **BOTSAUTO Checklist**, maar je kunt er bijvoorbeeld "Sales Checklist" of "Vakantie Checklist" van maken. De titel kan boven, onder, links of rechts van de afbeelding staan en heeft eigen instellingen voor lettertype, kleur, achtergrond, grootte en padding. De afbeelding wordt altijd boven de invoervelden getoond.
De primaire kleur bepaalt ook de kleur van checkboxen en labels. Voor de geavanceerde stijl worden standaard onder meer een lettergrootte van **18px** voor de container en **22px** voor fasekoppen gebruikt, terwijl checklistitems in **#00306a** worden weergegeven. Invulvelden hebben standaard een afgeronde rand van **5px** met een dunne, solide lijn en nemen de volledige breedte in. Checkboxen hebben naast kleur en grootte ook een instelbare randkleur en standaard achtergrond. Al deze waarden kun je aanpassen.

Alle stijlopties kunnen worden geëxporteerd naar een JSON-bestand en later weer geïmporteerd. Een klik op *Reset naar standaard* herstelt zowel de basis- als geavanceerde stijlwaarden. De eventueel geselecteerde afbeelding en breedte blijven daarbij behouden. Via het submenu **E-mail BCC** kan een adres ingesteld worden dat elke inzending in bcc ontvangt. Hier vind je tevens de optie om een alternatieve submit‑URL te gebruiken wanneer `/wp-admin/` is afgeschermd.

Alle elementen van de checklist krijgen hun eigen CSS-class. De fases gebruiken nu een eigen pijl-icoon via CSS zodat de driehoekjes altijd zichtbaar zijn, ook als een thema de standaard marker van `<summary>` verbergt. De plugin dwingt tevens de weergave van checkboxes af zodat thema-stijlen geen ongewenste invloed hebben.
### Live preview en conditional styling

Op de pagina **Opmaak** staat nu een voorbeeldweergave van de checklist. Deze gebruikt meteen de huidige instellingen zodat je direct ziet hoe de checklist er op de website uitziet. Wanneer je kleuren of lettertypes wijzigt verschijnt het resultaat direct in deze preview, zonder de pagina te herladen. Met de knop *Toon als mobiele gebruiker* verklein je de weergave zodat je ziet hoe alles er op een smartphone uitziet.

Naast de normale opmaak kun je instellen hoe een checklist‑item eruitziet zodra de checkbox is aangevinkt. Deze styling wordt zowel op de website als in het gegenereerde PDF‑bestand toegepast.
Ook het label **Checklist afgerond** heeft een eigen kleur en grootte die hier kan worden aangepast. Het lettertype volgt het gekozen **Lettertype**.


Alle labels in de plugin zijn vertaalbaar. Gebruik het bestand `languages/botsauto-checklist.pot` om eigen vertalingen aan te maken.
### Nieuwe mogelijkheden

De checklist werkt nu ook op gewone Berichten. De velden **Titel**, **Naam** en **E‑mail** zijn gelijk uitgelijnd en hebben dezelfde breedte tot aan een eventuele afbeelding. Zowel deze velden als de uitklapbare fasetitel gebruiken het gekozen lettertype en de primaire kleur.

Elke vraag kan nu een extra toelichting meekrijgen. Klik op het icoon naast een checklist‑item om een notitieveld te openen. Het gebruikte Font Awesome icoon is instelbaar op de pagina **Opmaak**. Wanneer er een notitie is ingevuld, verschijnt een ander icoon. In de rubriek **Iconen** kies je zelf welke pictogrammen gebruikt worden en welke kleuren ze hebben.
De notitievelden ondersteunen rich text en hun lettertype en kleuren stel je in onder **Geavanceerde Opmaak**.
De gekozen opmaak voor **Notitieveld** wordt tevens toegepast in het rich-text venster zodat de gebruiker exact ziet hoe zijn notities eruit komen te zien.
Je kunt per vraag ook een knop **Meer info** tonen. Vul onder de vraag optioneel een veld **Tekst** of **URL** in met de toelichting. Op de website staat dan direct achter de vraag een kleine knop die een venster opent met deze tekst of een link. Het venster volgt de kleuren uit de **Opmaak**‑instellingen zodat het overal past.
Ook invoer met speciale tekens wordt nu correct opgeslagen dankzij UTF-8 basiskoppeling.

Wanneer je een bestaande checklist bijwerkt krijg je de vraag of je een nieuwe PDF wilt ontvangen. Vink je **Checklist afgerond** aan, dan wordt altijd automatisch een bijgewerkte PDF gemaild.
Het uiterlijk van het label **Checklist afgerond** (kleur en grootte) stel je hier eveneens in. Het lettertype volgt automatisch de gekozen algemene instelling.

Bij elke inzending wordt een PDF-bestand bewaard. In de beheerder zie je een overzicht van alle gegenereerde PDF’s, met knoppen om het bestand opnieuw te versturen of een nieuwe versie te genereren. Alle PDF’s blijven bewaard in een log.

Versie 1.12.19 gebruikt een geldige pluginheader en toont overal het label "E-mail".
Versie 1.12.22 gebruikt een simpele knop die een verborgen blok toont. Deze methode werkt op elke site zonder conflicten.
