<?php
/**
 * Plugin Name: BOTSAUTO Checklist
 * Plugin URI: https://example.com
 * Description: Frontend checklist with admin overview, PDF email confirmation, and edit link.
 * Version: 1.4.0
 * Author: OpenAI Codex
 * Author URI: https://openai.com
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: botsauto-checklist
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class BOTSAUTO_Checklist {
    private $option_name = 'botsauto_checklist_items';
    private $post_type = 'botsauto_submission';

    public static function install() {
        $self = new self;
        if ( false === get_option( $self->option_name, false ) ) {
            add_option( $self->option_name, $self->default_checklist() );
        }
    }

    public static function uninstall() {
        $self = new self;
        delete_option( $self->option_name );
    }

    public function __construct() {
        add_action( 'init', array( $this, 'register_post_type' ) );
        add_action( 'admin_menu', array( $this, 'admin_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
        add_shortcode( 'botsauto_checklist', array( $this, 'render_form' ) );
        add_action( 'admin_post_nopriv_botsauto_save', array( $this, 'handle_submit' ) );
        add_action( 'admin_post_botsauto_save', array( $this, 'handle_submit' ) );
    }

    public function mail_from( $orig ) {
        return get_option( 'admin_email' );
    }

    public function mail_from_name( $orig ) {
        return get_bloginfo( 'name' );
    }

    private function send_email( $to, $subject, $body, $attachments = array() ) {
        $headers   = array();
        $headers[] = 'Content-Type: text/html; charset=UTF-8';
        $headers[] = 'From: ' . $this->mail_from_name( '' ) . ' <' . $this->mail_from( '' ) . '>';
        return wp_mail( $to, $subject, $body, $headers, $attachments );
    }

    public function enqueue_admin_assets( $hook ) {
        if ( $hook !== 'toplevel_page_botsauto-checklist' ) {
            return;
        }
        wp_enqueue_script( 'botsauto-admin', plugin_dir_url( __FILE__ ) . 'js/admin.js', array( 'jquery' ), '1.0', true );
    }

    private function pdf_string( $text ) {
        if ( function_exists( 'iconv' ) ) {
            $converted = @iconv( 'UTF-8', 'ISO-8859-1//TRANSLIT', $text );
            if ( $converted !== false ) {
                return $converted;
            }
        }
        return utf8_decode( $text );
    }

    public function register_post_type() {
        register_post_type( $this->post_type, array(
            'public' => false,
            'label'  => 'BOTSAUTO Submissions',
            'supports' => array('title'),
            'show_ui' => true,
        ));
    }

    public function admin_menu() {
        add_menu_page( 'BOTSAUTO Checklist', 'BOTSAUTO Checklist', 'manage_options', 'botsauto-checklist', array( $this, 'admin_page' ) );
    }

    public function admin_page() {
        if ( isset( $_POST['checklist_content'] ) ) {
            update_option( $this->option_name, wp_unslash( $_POST['checklist_content'] ) );
            echo '<div class="updated"><p>Checklist opgeslagen.</p></div>';
        }
        $items   = $this->checklist_items();
        $content = get_option( $this->option_name, $this->default_checklist() );
        echo '<div class="wrap"><h1>Checklist beheer</h1>';
        echo '<form method="post" id="botsauto-form">';
        echo '<textarea id="botsauto_content" name="checklist_content" style="display:none;">' . esc_textarea( $content ) . '</textarea>';
        echo '<div id="botsauto-editor">';
        $current_phase = null;
        foreach ( $items as $item ) {
            if ( $item['phase'] !== $current_phase ) {
                if ( $current_phase !== null ) {
                    echo '<p><button type="button" class="button botsauto-add-item">Item toevoegen</button></p></div></details>';
                }
                echo '<div class="botsauto-phase"><details open><summary></summary>';
                echo '<p>Fase: <input type="text" class="phase-field" value="' . esc_attr( $item['phase'] ) . '"></p>';
                echo '<p>Toelichting: <input type="text" class="desc-field" value="' . esc_attr( $item['desc'] ) . '"></p>';
                echo '<div class="botsauto-items">';
                $current_phase = $item['phase'];
            }
            echo '<div class="botsauto-item">';
            echo '<p>Vraag: <input type="text" class="question-field" value="' . esc_attr( $item['question'] ) . '"></p>';
            echo '<p>Checklist item: <input type="text" class="item-field" value="' . esc_attr( $item['item'] ) . '"></p>';
            echo '<p><button type="button" class="button botsauto-remove-item">Verwijder</button></p>';
            echo '</div>';
        }
        if ( $current_phase !== null ) {
            echo '<p><button type="button" class="button botsauto-add-item">Item toevoegen</button></p></div></details></div>';
        }
        echo '</div>'; // editor
        echo '<p><button type="button" class="button" id="botsauto-add-phase">Fase toevoegen</button></p>';
        submit_button();
        echo '</form>';

        // templates
        echo '<script type="text/template" id="botsauto-phase-template">';
        echo '<div class="botsauto-phase"><details open><summary></summary><p>Fase: <input type="text" class="phase-field"></p><p>Toelichting: <input type="text" class="desc-field"></p><div class="botsauto-items"></div><p><button type="button" class="button botsauto-add-item">Item toevoegen</button></p></details></div>';
        echo '</script>';
        echo '<script type="text/template" id="botsauto-item-template">';
        echo '<div class="botsauto-item"><p>Vraag: <input type="text" class="question-field"></p><p>Checklist item: <input type="text" class="item-field"></p><p><button type="button" class="button botsauto-remove-item">Verwijder</button></p></div>';
        echo '</script>';

        echo '</div>';
    }

    private function default_checklist() {
        return <<<CHECKLIST
Voorbereiden: Kwalificatie & voorbereiding (KEM – Kwalificatie Expres Methode)|Kwalificatie, achtergrondinformatie en strategische voorbereiding.|Kwalificatie Expres Methode toegepast?|Is de potentiële opdrachtgever gekwalificeerd op basis van de KEM (Kwalificatie Expres Methode)?
Voorbereiden: Kwalificatie & voorbereiding (KEM – Kwalificatie Expres Methode)||Stakeholder Matrix ingevuld?|Zijn de juiste stakeholders en besluitvormers volledig geïdentificeerd en in kaart gebracht?
Voorbereiden: Kwalificatie & voorbereiding (KEM – Kwalificatie Expres Methode)||Achtergrondinformatie verzameld?|Zijn de belangrijkste gegevens en relevante achtergrondinformatie over de potentiële opdrachtgever verzameld?
Voorbereiden: Kwalificatie & voorbereiding (KEM – Kwalificatie Expres Methode)||Hypotheses opgesteld?|Zijn er hypotheses over mogelijke behoeften en uitdagingen van de potentiële opdrachtgever geformuleerd?
Voorbereiden: Kwalificatie & voorbereiding (KEM – Kwalificatie Expres Methode)||Strategie voor contact bepaald?|Is de strategie bepaald om contact te leggen met de juiste stakeholders en effectief in gesprek te komen?
Voorbereiden: Kwalificatie & voorbereiding (KEM – Kwalificatie Expres Methode)|||Heeft de salesprofessional de communicatiestijl en persona van de opdrachtgever in kaart gebracht?
Voorbereiden: Kwalificatie & voorbereiding (KEM – Kwalificatie Expres Methode)|||Is er een ‘start met vertrouwen’-benadering toegepast, waarbij wordt getest of de opdrachtgever ruimte biedt voor een verdiepende dialoog?
Voorbereiden: Kwalificatie & voorbereiding (KEM – Kwalificatie Expres Methode)||BOTSAUTO Navigatie ingezet?|Zijn de relevante Pijlers toegepast om richting te geven aan de salesstrategie?
Voorbereiden: Kwalificatie & voorbereiding (KEM – Kwalificatie Expres Methode)||Uitnodiging tot strategische samenwerking verzonden?|Heb je de ‘Samenwerking template’ gebruikt?
Verdiepen: Diepgaande analyse van de kernvraag (KAM – Kernvraag Analyse Methode)|Focus: De échte kernvraag en impact ontrafelen met de 2 O’s (Oplossing en Originaliteit & Opbrengst).|Essentievragen gesteld?|Zijn de juiste essentievragen gesteld om de werkelijke kern van de klantbehoefte bloot te leggen?
Verdiepen: Diepgaande analyse van de kernvraag (KAM – Kernvraag Analyse Methode)||Checklist Socratisch Gesprek toegepast?|Is het gesprek gevoerd volgens de socratische gespreksmethodiek?
Verdiepen: Diepgaande analyse van de kernvraag (KAM – Kernvraag Analyse Methode)||Blinde vlekken geïdentificeerd?|Zijn eventuele blinde vlekken of verborgen behoeften samen met de opdrachtgever naar boven gehaald?
Verdiepen: Diepgaande analyse van de kernvraag (KAM – Kernvraag Analyse Methode)||Impact op organisatie duidelijk?|Is er helderheid over de impact van het probleem op de opdrachtgever en diens organisatie?
Verdiepen: Diepgaande analyse van de kernvraag (KAM – Kernvraag Analyse Methode)||Obstakels en risico’s geanalyseerd?|Zijn mogelijke obstakels en risico’s in het besluitvormingsproces geanalyseerd?
Verdiepen: Diepgaande analyse van de kernvraag (KAM – Kernvraag Analyse Methode)||Inzicht in interne besluitvorming en budget?|Is er inzicht in de interne besluitvorming en het budget?
Verwezenlijken: Oplossing & commitment (KAM – Kernvraag Analyse Methode)||3 W’s volledig in kaart gebracht?|Zijn de 3 W’s (Ware Aard, Weerslag, Waarde) van de klantuitdaging volledig in kaart gebracht?
Verwezenlijken: Oplossing & commitment (KAM – Kernvraag Analyse Methode)||Kernvraag geformuleerd?|Is de vraag van de klant volledig geanalyseerd en geformuleerd?
Verwezenlijken: Oplossing & commitment (KAM – Kernvraag Analyse Methode)||Visie op de kernvraag gedeeld?|Is de visie op de kernvraag van de opdrachtgever duidelijk gedeeld?
Verwezenlijken: Oplossing & commitment (KAM – Kernvraag Analyse Methode)||Stelling, Steun & Succes uitgewerkt?|Is de waardepropositie geformuleerd met de SSS-methode (Stelling, Steun, Succes)?
Verwezenlijken: Oplossing & commitment (KAM – Kernvraag Analyse Methode)||VVV-1 ingevuld?|Is vastgesteld of er Verdere Verkenning Vereist is (VVV-1)?
Verwezenlijken: Oplossing & commitment (KAM – Kernvraag Analyse Methode)||VVV-2 afgedwongen?|Zijn de Voorwaarden voor Vervolg (VVV-2) duidelijk gedefinieerd, inclusief benodigde interne goedkeuringen?
Verwezenlijken: Oplossing & commitment (KAM – Kernvraag Analyse Methode)|||Heeft de opdrachtgever expliciet commitment uitgesproken voor de volgende stap?
Verwezenlijken: Oplossing & commitment (KAM – Kernvraag Analyse Methode)|||Zijn er naast interne goedkeuringen ook organisatorische consequenties besproken (zoals resources of implementatietijd)?
Verwezenlijken: Oplossing & commitment (KAM – Kernvraag Analyse Methode)||KAM ingezet?|Heb je voor alle bovenstaande punten (in de fase Verdiepen) de Kernvraag Analyse Methode ingezet?
Verwezenlijken: Oplossing & commitment (KAM – Kernvraag Analyse Methode)||Gespreksverslag BOTSAUTO debrief gebruikt?|Heb je het gespreksverslag volgens de BOTSAUTO-methode gemaakt, met focus op de klantbehoeften en uitdagingen. er een concreet actieplan met taken, deadlines en mijlpalen om het traject vlekkeloos te laten verlopen?
Verwezenlijken: Oplossing & commitment (KAM – Kernvraag Analyse Methode)||Actieplan opgesteld?|Is er een concreet actieplan met taken, deadlines en mijlpalen opgesteld om het traject vlekkeloos te laten verlopen? (wellicht heb je dat al in het gespreksverslag opgenomen)
Verzilveren: Borging & nazorg (KIM – Klant Informatie Methode)|Nakomen: De formele afronding, implementatie en borging van de samenwerking.|Opdrachtbevestiging of Vennootakte opgesteld?|Is de samenwerking bevestigd met een duidelijke opdrachtbevestiging of contract?
Verzilveren: Borging & nazorg (KIM – Klant Informatie Methode)||Implementatievoorwaarden vastgelegd?|Zijn de implementatievoorwaarden en eerste vervolgstappen concreet vastgelegd?
Verzilveren: Borging & nazorg (KIM – Klant Informatie Methode)||Monitoring- en evaluatieplan opgesteld?|Is er een monitoring- en evaluatieplan opgesteld om de voortgang te borgen?
Verzilveren: Borging & nazorg (KIM – Klant Informatie Methode)||KPI’s gedefinieerd?|Zijn er heldere KPI’s geformuleerd om het succes van de implementatie te meten?
Verzilveren: Borging & nazorg (KIM – Klant Informatie Methode)|||Zijn er KPI’s vastgesteld niet alleen voor implementatie, maar ook voor toekomstige optimalisaties?
Verzilveren: Borging & nazorg (KIM – Klant Informatie Methode)||4 R’s correct toegepast?|Zijn de 4 R’s (Resultaat, Rust, Regie, Respect) correct toegepast en bewaakt?
Verzilveren: Borging & nazorg (KIM – Klant Informatie Methode)||KIM ingezet?|Heb je voor alle bovenstaande punten (in de fase Verzilveren) de Klant Inzicht Methode ingezet?
Extra controle & optimalisatie: Interne & externe borging|Terugkijken, leren en continu verbeteren – intern én extern.|Interne debriefing gehouden?|Is er een interne debriefing gehouden om leerpunten en verbeteringen te identificeren? En heb je daar ook de Commerciële debrief template gebruikt?
Extra controle & optimalisatie: Interne & externe borging|||Is de klant uitgenodigd voor een verdiepingssessie of referentiecase-evaluatie om de relatie verder uit te bouwen?
Extra controle & optimalisatie: Interne & externe borging||Klantgegevens vastgelegd in CRM?|Zijn alle klantgegevens en actiepunten correct vastgelegd in CRM en andere systemen?
Extra controle & optimalisatie: Interne & externe borging||Feedbackmoment met de klant gepland?|Is er een feedbackmoment met de klant gepland om de samenwerking te evalueren en optimaliseren?
Extra controle & optimalisatie: Interne & externe borging||Alle interne stakeholders op de hoogte?|Zijn alle interne stakeholders op de hoogte van de status en de vervolgstappen?
Extra controle & optimalisatie: Interne & externe borging||Grondige evaluatie van het proces gedaan?|Is er een grondige evaluatie van het totale proces uitgevoerd om toekomstige trajecten te verbeteren?
Algemene verankeringen en documentatie: Structuur & borging|Versterking van strategische keuzes en borging van de samenwerking.|Account Plan Canvas (standaard of 2.0) ingevuld?|Is het Account Plan Canvas volledig ingevuld?
Algemene verankeringen en documentatie: Structuur & borging||Pijlers en BOTSAUTO navigatie toegepast?|Zijn alle relevante Pijlers uitgewerkt middels de BOTSAUTO Navigatie?
CHECKLIST;
    }

    private function checklist_items() {
        $content = get_option( $this->option_name, $this->default_checklist() );
        $lines   = array_filter( array_map( 'trim', explode( "\n", $content ) ) );
        $items   = array();
        foreach ( $lines as $line ) {
            $parts = array_map( 'trim', explode( '|', $line, 4 ) );
            $items[] = array(
                'phase'    => $parts[0] ?? '',
                'desc'     => $parts[1] ?? '',
                'question' => $parts[2] ?? '',
                'item'     => $parts[3] ?? '',
            );
        }
        return $items;
    }

    private function associate_items( $items ) {
        $assoc = array();
        foreach ( $items as $item ) {
            $key          = md5( wp_json_encode( $item ) );
            $assoc[ $key ] = $item;
        }
        return $assoc;
    }

    public function render_form( $atts ) {
        $token = isset( $_GET['botsauto_edit'] ) ? sanitize_text_field( $_GET['botsauto_edit'] ) : '';
        $post_id = $this->get_post_id_by_token( $token );
        $values = array();
        $completed = '';
        $email = '';
        $name = '';
        if ( $post_id ) {
            $values = get_post_meta( $post_id, 'answers', true );
            $completed = get_post_meta( $post_id, 'completed', true );
            $email = get_post_meta( $post_id, 'email', true );
            $name = get_post_meta( $post_id, 'name', true );
        }

        $current_items = $this->associate_items( $this->checklist_items() );
        $snapshot      = $post_id ? get_post_meta( $post_id, 'items_snapshot', true ) : array();
        if ( ! is_array( $snapshot ) || empty( $snapshot ) ) {
            $snapshot = $current_items;
        }
        $items_to_use = $snapshot;
        $show_update  = wp_json_encode( $current_items ) !== wp_json_encode( $snapshot );

        if ( isset( $_POST['update_items'] ) && $_POST['update_items'] ) {
            $items_to_use = $current_items;
            $show_update  = false;
        }

        // Convert old numeric answers to associative if needed
        if ( $values && array_values( $values ) === $values ) {
            $tmp = array();
            $i   = 0;
            foreach ( $snapshot as $hash => $item ) {
                if ( isset( $values[ $i ] ) ) {
                    $tmp[ $hash ] = true;
                }
                $i++;
            }
            $values = $tmp;
        }

        ob_start();
        echo '<form method="post" action="' . esc_url( admin_url('admin-post.php') ) . '">';
        echo '<input type="hidden" name="action" value="botsauto_save">';
        if ( $post_id ) {
            echo '<input type="hidden" name="post_id" value="' . intval($post_id) . '" />';
            echo '<input type="hidden" name="items_snapshot" value="' . esc_attr( wp_json_encode( $snapshot ) ) . '" />';
        }
        echo '<p><label>Naam: <input type="text" name="name" value="' . esc_attr($name) . '" required></label></p>';
        echo '<p><label>Email: <input type="email" name="email" value="' . esc_attr($email) . '" required></label></p>';
        echo '<div class="botsauto-checklist">';
        $last_phase = null;
        $open_ul    = false;
        foreach ( $items_to_use as $hash => $data ) {
            if ( $data['phase'] !== $last_phase ) {
                if ( $open_ul ) {
                    echo '</ul></details>';
                }
                if ( $data['phase'] ) {
                    echo '<details class="botsauto-phase"><summary>'.esc_html( $data['phase'] ).'</summary>';
                }
                if ( $data['desc'] ) {
                    echo '<p>'.esc_html( $data['desc'] ).'</p>';
                }
                echo '<ul style="list-style:none">';
                $open_ul    = true;
                $last_phase = $data['phase'];
            }
            $checked = isset( $values[ $hash ] ) ? 'checked' : '';
            echo '<li>';
            if ( $data['question'] ) {
                echo '<strong>'.esc_html( $data['question'] ).'</strong><br>';
            }
            echo '<label><input type="checkbox" name="answers['.$hash.']" '.$checked.'> '.esc_html( $data['item'] ).'</label>';
            echo '</li>';
        }
        if ( $open_ul ) {
            echo '</ul></details>';
        }
        echo '</div>';
        if ( $show_update ) {
            echo '<p><label><input type="checkbox" name="update_items" value="1"> De checklist is gewijzigd, nieuwe versie gebruiken</label></p>';
        }
        $c = $completed ? 'checked' : '';
        echo '<p><label><input type="checkbox" name="completed" value="1" '.$c.'> Checklist afgerond</label></p>';
        $label = $post_id ? 'Opslaan' : 'Checklist verzenden';
        echo '<p><input type="submit" class="button button-primary" value="'.esc_attr($label).'"></p>';
        echo '</form>';
        return ob_get_clean();
    }

    public function handle_submit() {
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $name    = sanitize_text_field( $_POST['name'] );
        $email   = sanitize_email( $_POST['email'] );
        $completed = isset($_POST['completed']) ? '1' : '';

        $current_items  = $this->associate_items( $this->checklist_items() );
        $snapshot_field = isset( $_POST['items_snapshot'] ) ? json_decode( stripslashes( $_POST['items_snapshot'] ), true ) : array();
        if ( ! is_array( $snapshot_field ) || empty( $snapshot_field ) ) {
            $snapshot_field = $current_items;
        }
        $use_current = isset( $_POST['update_items'] ) && $_POST['update_items'];
        $snapshot    = $use_current ? $current_items : $snapshot_field;

        $answers = array();
        if ( isset( $_POST['answers'] ) && is_array( $_POST['answers'] ) ) {
            foreach ( $_POST['answers'] as $hash => $val ) {
                $answers[ sanitize_key( $hash ) ] = true;
            }
        }
        if ( $post_id ) {
            wp_update_post( array( 'ID' => $post_id, 'post_title' => $name ) );
        } else {
            $token = wp_generate_password(20,false,false);
            $post_id = wp_insert_post( array(
                'post_type' => $this->post_type,
                'post_status' => 'publish',
                'post_title' => $name,
                'meta_input' => array('token'=>$token)
            ));
        }
        update_post_meta( $post_id, 'name', $name );
        update_post_meta( $post_id, 'email', $email );
        update_post_meta( $post_id, 'answers', $answers );
        update_post_meta( $post_id, 'items_snapshot', $snapshot );
        update_post_meta( $post_id, 'completed', $completed );
        $token = get_post_meta( $post_id, 'token', true );
        $referer = wp_get_referer();
        if ( ! $referer ) {
            $referer = home_url( '/' );
        }
        $referer = remove_query_arg( 'botsauto_edit', $referer );
        $edit_url = add_query_arg( 'botsauto_edit', $token, $referer );
        if ( ! empty( $_POST['post_id'] ) ) {
            wp_redirect( $edit_url );
            exit;
        }
        $pdf = $this->generate_pdf( $name, $answers, $snapshot );
        $body = 'Bedankt voor het invullen van de checklist. Bewaar deze link om later verder te gaan: '.$edit_url;
        $this->send_email( $email, 'Checklist bevestiging', $body, array( $pdf ) );
        unlink( $pdf );
        wp_redirect( $edit_url );
        exit;
    }

    private function get_post_id_by_token( $token ) {
        if ( ! $token ) return 0;
        $post = get_posts( array(
            'post_type' => $this->post_type,
            'meta_key' => 'token',
            'meta_value' => $token,
            'numberposts' => 1
        ) );
        if ( $post ) return $post[0]->ID;
        return 0;
    }

    private function generate_pdf( $name, $answers, $snapshot ) {
        if ( ! defined( 'FPDF_FONTPATH' ) ) {
            define( 'FPDF_FONTPATH', plugin_dir_path( __FILE__ ) . 'lib/font/' );
        }
        require_once plugin_dir_path(__FILE__).'lib/fpdf.php';
        $pdf = new FPDF();
        $pdf->AddPage();
        $pdf->SetFont('Arial','',12);
        $pdf->Cell(0,10,$this->pdf_string('BOTSAUTO Checklist'),0,1);
        $pdf->Cell(0,10,$this->pdf_string('Naam: '.$name),0,1);
        $i = 0;
        foreach ( $snapshot as $hash => $item ) {
            $status = isset( $answers[ $hash ] ) ? 'Ja' : 'Nee';
            $line   = ($i + 1) . '. ' . $item['item'] . ' - ' . $status;
            $pdf->Cell(0,8,$this->pdf_string($line),0,1);
            $i++;
        }
        $uploads = wp_upload_dir();
        $file = trailingslashit( $uploads['path'] ) . 'botsauto-' . uniqid() . '.pdf';
        $pdf->Output( $file, 'F' );
        return $file;
    }
}

register_activation_hook( __FILE__, array( 'BOTSAUTO_Checklist', 'install' ) );
register_uninstall_hook( __FILE__, array( 'BOTSAUTO_Checklist', 'uninstall' ) );

new BOTSAUTO_Checklist();

