# README

Dit repository bevat een eenvoudige WordPress plugin voor de BOTSAUTO Sales Checklist.

Plaats de map `botsauto-checklist` in de map `wp-content/plugins` van je WordPress installatie en activeer de plugin.

Elke checklist is een apart bericht van het type **BOTSAUTO Checklist**. De plugin installeert standaard één checklist met alle BOTSAUTO vragen maar de beheerder kan extra checklists aanmaken. Iedere checklist heeft een eigen shortcode:

```
[botsauto_checklist id="123"]
```

waarbij `123` het ID van de checklist is. In de lijstweergave van checklists wordt het juiste shortcode getoond. Een checklist kan de inhoud van een andere checklist importeren via het zijpaneel in het bewerkscherm.

De plugin genereert een PDF van de ingevulde checklist via de meegeleverde FPDF-bibliotheek. Alleen het fontbestand `helvetica.php` is nodig en meegeleverd in `botsauto-checklist/lib/font`.

Checklistregels hebben vier velden gescheiden door `|`: fase, toelichting, korte vraag en het daadwerkelijke checklist‑item. Een regel kan velden leeg laten. Bijvoorbeeld:

```
Voorbereiden|Korte uitleg fase|KEM toegepast?|Is de potentiële opdrachtgever gekwalificeerd?
```

Deze structuur wordt zowel in de admin als op de frontend getoond.
In het beheerscherm verschijnen de checklistregels gegroepeerd per fase in inklapbare secties.
Met knoppen kan de beheerder eenvoudig fases, vragen en checklistitems toevoegen of verwijderen. De verwijderknoppen staan direct naast de invoervelden zodat alles netjes is uitgelijnd. Een fase kan direct worden verwijderd en verdwijnt automatisch wanneer alle onderliggende vragen worden weggehaald. Bij een vraag kunnen meerdere checklistitems worden toegevoegd.

Ingezonden formulieren worden opgeslagen als custom post type **BOTSAUTO inzendingen**. De gebruiker geeft een titel, naam en e‑mailadres op. In de lijst met inzendingen staan de kolommen Titel, Naam, Email, Afgerond, Datum, Checklist en URL. De URL is de unieke link waarmee de gebruiker later verder kan gaan. In het bewerkscherm van een inzending worden deze gegevens samen met de ingevulde checklist getoond.

Na het versturen ontvangt de gebruiker een e‑mail met de PDF in de bijlage en een unieke link om de checklist later te bewerken. De plugin stuurt de bezoeker na het opslaan automatisch terug naar dezelfde pagina met deze link in de URL. Speciale tekens worden voor de PDF geconverteerd naar Latin‑1 zodat woorden zoals "geïdentificeerd" correct worden weergegeven.

Wanneer de beheerder de checklist wijzigt terwijl een gebruiker al een ingevulde versie heeft, ziet de gebruiker bij het openen een melding met de keuze om de nieuwe checklist te gebruiken. De antwoorden blijven gekoppeld aan de oorspronkelijke vragen zodat er geen vinkjes verspringen.

Voor het versturen van e‑mail gebruikt de plugin de standaard `wp_mail` functie. De afzender wordt expliciet gezet op het beheerdersadres van de site zodat SMTP-plugins de mail correct afleveren.

Wanneer je de plugin verwijdert via het WordPress pluginoverzicht worden alle opgeslagen checklistregels eveneens verwijderd. Bij een nieuwe installatie wordt zo altijd de standaard checklist van de plugin geladen. Via het importmenu in het bewerkscherm kan deze originele checklist altijd opnieuw worden geïmporteerd onder de optie **BOTSAUTO standaard**.
De standaardinstallatie bevat de complete BOTSAUTO Sales Checklist met alle fases, vragen en checklistitems, zodat je direct aan de slag kunt.

### Stijl aanpassen

Na activering verschijnt onder **Instellingen → BOTSAUTO** een hoofdmenu met twee submenu's. In **Stijl** kies je de primaire kleur, tekstkleur, achtergrondkleur, het lettertype (waaronder Google Font *Oswald*) en kun je een afbeelding selecteren die rechtsboven in de checklist wordt getoond. Deze afbeelding komt ook in de PDF terecht. In het submenu **E-mail CC** kun je een adres instellen dat alle inzendingen in cc ontvangt.
De primaire kleur bepaalt tevens de kleur van de checkboxen en de labels van titel, naam, e-mail en checklistitems.
