# README

Dit repository bevat een eenvoudige WordPress plugin voor de BOTSAUTO Sales Checklist.

Plaats de map `botsauto-checklist` in de map `wp-content/plugins` van je WordPress installatie en activeer de plugin.

Gebruik de shortcode `[botsauto_checklist]` om de checklist op een pagina weer te geven. In de beheeromgeving verschijnt een menu item "BOTSAUTO Checklist" waar de checklist kan worden aangepast en ingezonden formulieren worden opgeslagen als custom post type.

De plugin genereert een PDF van de ingevulde checklist via de meegeleverde FPDF-bibliotheek. Alleen het fontbestand `helvetica.php` is nodig en meegeleverd in `botsauto-checklist/lib/font`.

Na het versturen ontvangt de gebruiker een e‑mail met de PDF in de bijlage en een unieke link om de checklist later te bewerken. De plugin stuurt de bezoeker na het opslaan automatisch terug naar dezelfde pagina met deze link in de URL.
