# README

Dit repository bevat een eenvoudige WordPress plugin voor de BOTSAUTO Sales Checklist.

Plaats de map `botsauto-checklist` in de map `wp-content/plugins` van je WordPress installatie en activeer de plugin.

Gebruik de shortcode `[botsauto_checklist]` om de checklist op een pagina weer te geven. In de beheeromgeving verschijnt een menu item "BOTSAUTO Checklist" waar de checklist kan worden aangepast en ingezonden formulieren worden opgeslagen als custom post type.

De plugin genereert een PDF van de ingevulde checklist via de meegeleverde FPDF-bibliotheek. Alleen het fontbestand `helvetica.php` is nodig en meegeleverd in `botsauto-checklist/lib/font`.

Checklistregels hebben vier velden gescheiden door `|`: fase, toelichting, korte vraag en het daadwerkelijke checklist‑item. Een regel kan velden leeg laten. Bijvoorbeeld:

```
Voorbereiden|Korte uitleg fase|KEM toegepast?|Is de potentiële opdrachtgever gekwalificeerd?
```

Deze structuur wordt zowel in de admin als op de frontend getoond.
In het beheerscherm verschijnen de checklistregels gegroepeerd per fase in inklapbare secties.
Met knoppen kan de beheerder eenvoudig fases, vragen en checklistitems toevoegen of verwijderen. De verwijderknoppen staan direct naast de invoervelden zodat alles netjes is uitgelijnd. Een fase kan direct worden verwijderd en verdwijnt automatisch wanneer alle onderliggende vragen worden weggehaald. Bij een vraag kunnen meerdere checklistitems worden toegevoegd.

Na het versturen ontvangt de gebruiker een e‑mail met de PDF in de bijlage en een unieke link om de checklist later te bewerken. De plugin stuurt de bezoeker na het opslaan automatisch terug naar dezelfde pagina met deze link in de URL. Speciale tekens worden voor de PDF geconverteerd naar Latin‑1 zodat woorden zoals "geïdentificeerd" correct worden weergegeven.

Wanneer de beheerder de checklist wijzigt terwijl een gebruiker al een ingevulde versie heeft, ziet de gebruiker bij het openen een melding met de keuze om de nieuwe checklist te gebruiken. De antwoorden blijven gekoppeld aan de oorspronkelijke vragen zodat er geen vinkjes verspringen.

Voor het versturen van e‑mail gebruikt de plugin de standaard `wp_mail` functie. De afzender wordt expliciet gezet op het beheerdersadres van de site zodat SMTP-plugins de mail correct afleveren.

Wanneer je de plugin verwijdert via het WordPress pluginoverzicht worden alle opgeslagen checklistregels eveneens verwijderd. Bij een nieuwe installatie wordt zo altijd de standaard checklist van de plugin geladen.
De standaardinstallatie bevat de complete BOTSAUTO Sales Checklist met alle fases, vragen en checklistitems, zodat je direct aan de slag kunt.
