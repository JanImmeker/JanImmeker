<?php
/**
 * Plugin Name: BOTSAUTO Checklist
 * Plugin URI: https://example.com
 * Description: Frontend checklist with admin overview, PDF email confirmation, and edit link.
 * Version: 1.10.0
 * Author: OpenAI Codex
 * Author URI: https://openai.com
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: botsauto-checklist
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class BOTSAUTO_Checklist {
    private $post_type      = 'botsauto_submission';
    private $list_post_type = 'botsauto_list';
    private $style_option    = 'botsauto_style';
    private $adv_style_option = 'botsauto_adv_style';
    private $custom_css_option = 'botsauto_custom_css';
    private $cc_option       = 'botsauto_cc_email';

    public static function install() {
        $self = new self();
        // create default checklist post if none exist
        $exists = get_posts( array(
            'post_type'   => $self->list_post_type,
            'numberposts' => 1,
        ) );
        if ( ! $exists ) {
            wp_insert_post( array(
                'post_type'   => $self->list_post_type,
                'post_title'  => 'BOTSAUTO Checklist',
                'post_status' => 'publish',
                'meta_input'  => array( 'botsauto_lines' => $self->default_checklist() ),
            ) );
        }

        if ( ! get_option( $self->style_option ) ) {
            update_option( $self->style_option, array(
                'primary'    => '#d14292',
                'text'       => '#00306a',
                'background' => '#d1eaf8',
                'font'       => 'Arial, sans-serif',
                'image'      => '',
            ) );
        }

        if ( ! get_option( $self->adv_style_option ) ) {
            update_option( $self->adv_style_option, $self->default_adv_style() );
        }

        if ( ! get_option( $self->custom_css_option ) ) {
            update_option( $self->custom_css_option, '' );
        }
    }

    public static function uninstall() {
        $self = new self();
        $lists = get_posts( array( 'post_type' => $self->list_post_type, 'numberposts' => -1 ) );
        foreach ( $lists as $list ) {
            wp_delete_post( $list->ID, true );
        }
    }

    public function __construct() {
        add_action( 'init', array( $this, 'register_post_types' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
        add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
        add_action( 'save_post', array( $this, 'save_post' ) );
        add_filter( 'manage_' . $this->post_type . '_posts_columns', array( $this, 'submission_columns' ) );
        add_action( 'manage_' . $this->post_type . '_posts_custom_column', array( $this, 'submission_column_content' ), 10, 2 );
        add_filter( 'manage_' . $this->list_post_type . '_posts_columns', array( $this, 'checklist_columns' ) );
        add_action( 'manage_' . $this->list_post_type . '_posts_custom_column', array( $this, 'checklist_column_content' ), 10, 2 );
        add_shortcode( 'botsauto_checklist', array( $this, 'render_form' ) );
        // Ensure shortcodes work on posts even if themes removed the default filter
        if ( ! has_filter( 'the_content', 'do_shortcode' ) ) {
            add_filter( 'the_content', 'do_shortcode', 11 );
        }
        add_action( 'admin_post_nopriv_botsauto_save', array( $this, 'handle_submit' ) );
        add_action( 'admin_post_botsauto_save', array( $this, 'handle_submit' ) );
        add_action( 'wp_ajax_botsauto_import', array( $this, 'ajax_import' ) );
        add_action( 'admin_menu', array( $this, 'add_admin_pages' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_color_picker_assets' ) );
    }

    public function mail_from( $orig ) {
        return get_option( 'admin_email' );
    }

    public function mail_from_name( $orig ) {
        return get_bloginfo( 'name' );
    }

    private function send_email( $to, $subject, $body, $attachments = array(), $extra_headers = array() ) {
        $headers   = array();
        $headers[] = 'Content-Type: text/html; charset=UTF-8';
        $headers[] = 'From: ' . $this->mail_from_name( '' ) . ' <' . $this->mail_from( '' ) . '>';
        $headers   = array_merge( $headers, (array) $extra_headers );
        return wp_mail( $to, $subject, $body, $headers, $attachments );
    }

    public function enqueue_admin_assets( $hook ) {
        global $post_type;
        if ( $hook === 'post-new.php' || $hook === 'post.php' ) {
            if ( $post_type === $this->list_post_type ) {
                wp_enqueue_script( 'botsauto-admin', plugin_dir_url( __FILE__ ) . 'js/admin.js', array( 'jquery' ), '1.0', true );
                wp_localize_script( 'botsauto-admin', 'botsautoAjax', array(
                    'ajaxurl' => admin_url( 'admin-ajax.php' ),
                ) );
            }
        }
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

    public function register_post_types() {
        register_post_type( $this->post_type, array(
            'public'       => false,
            'label'        => 'BOTSAUTO inzendingen',
            'labels'       => array(
                'name'          => 'BOTSAUTO inzendingen',
                'singular_name' => 'BOTSAUTO Inzending',
                'edit_item'     => 'Inzending bewerken',
                'add_new_item'  => 'Nieuwe inzending',
            ),
            'supports'     => array('title'),
            'show_ui'      => true,
            'capabilities' => array('create_posts' => 'do_not_allow'),
            'map_meta_cap' => true,
        ));
        register_post_type( $this->list_post_type, array(
            'public'       => false,
            'label'        => 'BOTSAUTO Checklists',
            'labels'       => array(
                'name'               => 'BOTSAUTO Checklists',
                'singular_name'      => 'BOTSAUTO Checklist',
                'add_new_item'       => 'Checklist toevoegen',
                'edit_item'          => 'Checklist bewerken',
            ),
            'supports'     => array('title'),
            'show_ui'      => true,
        ));
    }

    public function add_meta_boxes() {
        add_meta_box( 'botsauto_lines', 'Checklist items', array( $this, 'meta_box_lines' ), $this->list_post_type, 'normal', 'default' );
        add_meta_box( 'botsauto_shortcode', 'Shortcode', array( $this, 'meta_box_shortcode' ), $this->list_post_type, 'side' );
        add_meta_box( 'botsauto_submission', 'Inzending', array( $this, 'meta_box_submission' ), $this->post_type, 'normal' );
    }

    public function meta_box_lines( $post ) {
        $content = get_post_meta( $post->ID, 'botsauto_lines', true );
        if ( ! is_string( $content ) ) {
            $content = '';
        }
        echo '<textarea id="botsauto_content" name="botsauto_content" style="display:none">'.esc_textarea( $content ).'</textarea>';
        echo '<div id="botsauto-editor"></div>';
        echo '<p><button type="button" class="button" id="botsauto-add-phase">Fase toevoegen</button></p>';
        echo '<input type="hidden" name="botsauto_lines_nonce" value="'.wp_create_nonce('botsauto_lines').'" />';
        echo '<script type="text/template" id="botsauto-phase-template"><div class="botsauto-phase"><details open><summary></summary><p class="phase-line"><label><span>Fase:</span> <input type="text" class="phase-field"></label> <button type="button" class="button botsauto-remove-phase">Verwijder</button></p><p class="desc-line"><label><span>Toelichting:</span> <input type="text" class="desc-field"></label></p><div class="botsauto-questions"></div><p><button type="button" class="button botsauto-add-question">Vraag toevoegen</button></p></details></div></script>';
        echo '<script type="text/template" id="botsauto-question-template"><div class="botsauto-question"><p class="question-line"><label><span>Vraag:</span> <input type="text" class="question-field"></label> <button type="button" class="button botsauto-remove-question">Verwijder</button></p><div class="botsauto-items"></div><p><button type="button" class="button botsauto-add-item">Item toevoegen</button></p></div></script>';
        echo '<script type="text/template" id="botsauto-item-template"><div class="botsauto-item"><p class="item-line"><label><span>Checklist item:</span> <input type="text" class="item-field"></label> <button type="button" class="button botsauto-remove-item">Verwijder</button></p></div></script>';
        $s = $this->get_style_options();
        echo '<style>#botsauto-editor p{display:flex;align-items:center;gap:6px;margin:4px 0;}#botsauto-editor label{flex:1;display:flex;align-items:center;min-width:0;color:' . esc_attr($s['primary']) . ';}#botsauto-editor label span{display:inline-block;width:140px;}#botsauto-editor input{flex:1;width:100%;max-width:none;}#botsauto-editor .question-line{margin-left:2em;}#botsauto-editor .item-line{margin-left:4em;}#botsauto-editor{background:' . esc_attr($s['background']) . ';color:' . esc_attr($s['text']) . ';font-family:' . esc_attr($s['font']) . ';}#botsauto-editor input[type=checkbox]{accent-color:' . esc_attr($s['primary']) . ';display:inline-block!important;width:auto!important;height:auto!important;}#botsauto-editor .button{background:' . esc_attr($s['primary']) . ';border-color:' . esc_attr($s['primary']) . ';color:#fff;}#botsauto-editor .botsauto-phase>details>summary{font-weight:bold;cursor:pointer;margin:0;color:' . esc_attr($s['primary']) . ';list-style:none;position:relative;padding-left:1.2em;}#botsauto-editor .botsauto-phase>details>summary::before{content:"\25B6";position:absolute;left:0;}#botsauto-editor .botsauto-phase>details[open]>summary::before{content:"\25BC";}</style>';
    }

    public function meta_box_shortcode( $post ) {
        echo '<p>[botsauto_checklist id="'.$post->ID.'"]</p>';
        $lists = get_posts( array( 'post_type' => $this->list_post_type, 'exclude' => array( $post->ID ), 'numberposts' => -1 ) );
        echo '<p><select id="botsauto-import-select"><option value="">Checklist importeren...</option>';
        echo '<option value="default">BOTSAUTO standaard</option>';
        foreach ( $lists as $l ) {
            echo '<option value="'.$l->ID.'">'.esc_html( $l->post_title ).'</option>';
        }
        echo '</select> <button class="button" id="botsauto-import-btn">Import</button></p>';
    }

    public function meta_box_submission( $post ) {
        $name = get_post_meta( $post->ID, 'name', true );
        $email = get_post_meta( $post->ID, 'email', true );
        $completed = get_post_meta( $post->ID, 'completed', true ) ? 'Ja' : 'Nee';
        $title = get_the_title( $post->ID );
        $url   = get_post_meta( $post->ID, 'edit_url', true );
        $snapshot = get_post_meta( $post->ID, 'items_snapshot', true );
        $answers = get_post_meta( $post->ID, 'answers', true );
        if ( ! is_array( $snapshot ) ) return;
        echo '<p><strong>Titel:</strong> '.esc_html( $title ).'<br><strong>Naam:</strong> '.esc_html( $name ).'<br><strong>Email:</strong> '.esc_html( $email ).'<br><strong>Afgerond:</strong> '.$completed.'</p>';
        if ( $url ) {
            echo '<p><strong>URL:</strong> <a href="'.esc_url($url).'">'.esc_html($url).'</a></p>';
        }
        echo '<ul>';
        foreach ( $snapshot as $hash => $item ) {
            $ck = isset( $answers[$hash] ) ? '&#10003;' : '&#10007;';
            echo '<li>'.esc_html( $item['item'] ).' '.$ck.'</li>';
        }
        echo '</ul>';
    }

    public function save_post( $post_id ) {
        if ( get_post_type( $post_id ) === $this->list_post_type ) {
            if ( isset( $_POST['botsauto_lines_nonce'] ) && wp_verify_nonce( $_POST['botsauto_lines_nonce'], 'botsauto_lines' ) ) {
                if ( isset( $_POST['botsauto_content'] ) ) {
                    update_post_meta( $post_id, 'botsauto_lines', wp_unslash( $_POST['botsauto_content'] ) );
                }
            }
        }
    }

    public function submission_columns( $cols ) {
        $new = array();
        $new['cb']        = $cols['cb'];
        $new['title']     = 'Titel';
        $new['name']      = 'Naam';
        $new['email']     = 'Email';
        $new['completed'] = 'Afgerond';
        $new['date']      = 'Datum';
        $new['checklist'] = 'Checklist';
        $new['url']       = 'URL';
        return $new;
    }

    public function submission_column_content( $column, $post_id ) {
        switch ( $column ) {
            case 'name':
                echo esc_html( get_post_meta( $post_id, 'name', true ) );
                break;
            case 'email':
                echo esc_html( get_post_meta( $post_id, 'email', true ) );
                break;
            case 'completed':
                $c = get_post_meta( $post_id, 'completed', true ) ? 'Ja' : 'Nee';
                echo $c;
                break;
            case 'checklist':
                $list_id = get_post_meta( $post_id, 'checklist_id', true );
                if ( $list_id ) {
                    echo esc_html( get_the_title( $list_id ) );
                }
                break;
            case 'url':
                $url = get_post_meta( $post_id, 'edit_url', true );
                if ( $url ) {
                    echo '<a href="' . esc_url( $url ) . '">link</a>';
                }
                break;
        }
    }

    public function checklist_columns( $cols ) {
        $new = array();
        foreach ( $cols as $key => $val ) {
            $new[ $key ] = $val;
            if ( $key === 'title' ) {
                $new['shortcode'] = 'Shortcode';
            }
        }
        return $new;
    }

    public function checklist_column_content( $column, $post_id ) {
        if ( $column === 'shortcode' ) {
            $sc = '[botsauto_checklist id="' . $post_id . '"]';
            echo '<span class="botsauto-shortcode" data-shortcode="' . esc_attr( $sc ) . '" style="cursor:pointer;">' . esc_html( $sc ) . '</span>';
        }
    }

    public function ajax_import() {
        if ( ! current_user_can( 'edit_posts' ) ) wp_die();
        $id = $_GET['id'];
        if ( $id === 'default' ) {
            $content = $this->default_checklist();
        } else {
            $content = get_post_meta( intval( $id ), 'botsauto_lines', true );
        }
        wp_send_json_success( $content );
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

    private function default_adv_style() {
        return array(
            'container' => array(
                'text-color'       => '#00306a',
                'background-color' => '#ffffff',
                'font-size'        => '16px',
                'padding'          => '10px',
            ),
            'phase' => array(
                'text-color'       => '#d14292',
                'background-color' => 'transparent',
                'font-size'        => '16px',
                'font-weight'      => 'bold',
            ),
            'question' => array(
                'text-color' => '#00306a',
                'font-style' => 'italic',
                'font-size'  => '14px',
            ),
            'item' => array(
                'text-color' => '#00306a',
                'font-size'  => '14px',
            ),
            'button' => array(
                'text-color'       => '#ffffff',
                'background-color' => '#d14292',
                'padding'          => '6px 12px',
                'border-radius'    => '4px',
            ),
            'field' => array(
                'background-color' => '#ffffff',
                'text-color'       => '#00306a',
                'border-color'     => '#cccccc',
            ),
            'checkbox' => array(
                'color' => '#d14292',
                'size'  => '16px',
            ),
        );
    }

    private function get_checklist_items( $list_id ) {
        $content = get_post_meta( $list_id, 'botsauto_lines', true );
        if ( ! $content ) {
            $content = $this->default_checklist();
        }
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
        $atts = shortcode_atts( array( 'id' => 0 ), $atts, 'botsauto_checklist' );
        $list_id = absint( $atts['id'] );
        if ( $list_id ) {
            $post = get_post( $list_id );
            if ( ! $post || $post->post_type !== $this->list_post_type ) {
                return 'Checklist niet gevonden.';
            }
        } else {
            $first = get_posts( array( 'post_type' => $this->list_post_type, 'numberposts' => 1 ) );
            if ( $first ) {
                $list_id = $first[0]->ID;
            } else {
                return 'Checklist niet gevonden.';
            }
        }
        $token = isset( $_GET['botsauto_edit'] ) ? sanitize_text_field( $_GET['botsauto_edit'] ) : '';
        $post_id = $this->get_post_id_by_token( $token );
        $values = array();
        $completed = '';
        $email = '';
        $name = '';
        $title = '';
        if ( $post_id ) {
            $values = get_post_meta( $post_id, 'answers', true );
            $completed = get_post_meta( $post_id, 'completed', true );
            $email = get_post_meta( $post_id, 'email', true );
            $name = get_post_meta( $post_id, 'name', true );
            $title = get_the_title( $post_id );
            $list_id = intval( get_post_meta( $post_id, 'checklist_id', true ) );
        }
        $current_items = $this->associate_items( $this->get_checklist_items( $list_id ) );
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
        $style = $this->get_style_options();
        $adv   = $this->get_adv_style_options();
        $custom = get_option( $this->custom_css_option, '' );
        $wrapper = 'botsauto-' . wp_generate_password(6, false, false);
        echo '<style>';
        echo '#'.$wrapper.'{color:'.$adv['container']['text-color'].';background:'.$adv['container']['background-color'].';font-size:'.$adv['container']['font-size'].';padding:'.$adv['container']['padding'].';font-family:'.$style['font'].';}' .
             '#'.$wrapper.' .botsauto-phase>summary{color:'.$adv['phase']['text-color'].';background:'.$adv['phase']['background-color'].';font-size:'.$adv['phase']['font-size'].';font-weight:'.$adv['phase']['font-weight'].';}' .
             '#'.$wrapper.' .botsauto-question{color:'.$adv['question']['text-color'].';font-size:'.$adv['question']['font-size'].';font-style:'.$adv['question']['font-style'].';}' .
             '#'.$wrapper.' label{color:'.$style['primary'].';}' .
             '#'.$wrapper.' .botsauto-checkbox{accent-color:'.$adv['checkbox']['color'].';width:'.$adv['checkbox']['size'].';height:'.$adv['checkbox']['size'].';}' .
             '#'.$wrapper.' .button-primary{background:'.$adv['button']['background-color'].';color:'.$adv['button']['text-color'].';padding:'.$adv['button']['padding'].';border-radius:'.$adv['button']['border-radius'].';}' .
             '#'.$wrapper.' input[type=text],#'.$wrapper.' input[type=email]{background:'.$adv['field']['background-color'].';color:'.$adv['field']['text-color'].';border-color:'.$adv['field']['border-color'].';}' ;
        if ( strpos( $style['font'], 'Oswald' ) !== false ) {
            echo '@import url("https://fonts.googleapis.com/css2?family=Oswald&display=swap");';
        }
        if ( $custom ) { echo $custom; }
        echo '</style>';
        echo '<div id="'.$wrapper.'">';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        echo '<input type="hidden" name="action" value="botsauto_save">';
        echo '<input type="hidden" name="checklist_id" value="' . intval( $list_id ) . '" />';
        if ( $post_id ) {
            echo '<input type="hidden" name="post_id" value="' . intval($post_id) . '" />';
            echo '<input type="hidden" name="items_snapshot" value="' . esc_attr( wp_json_encode( $snapshot ) ) . '" />';
        }
        echo '<div class="botsauto-header"><div class="botsauto-fields">';
        echo '<p><label>Titel: <input type="text" name="entry_title" value="' . esc_attr($title) . '" required></label></p>';
        echo '<p><label>Naam: <input type="text" name="name" value="' . esc_attr($name) . '" required></label></p>';
        echo '<p><label>E-mail: <input type="email" name="email" value="' . esc_attr($email) . '" required></label></p>';
        echo '</div>';
        if ( ! empty( $style['image'] ) ) {
            echo '<div class="botsauto-logo"><img src="' . esc_url( $style['image'] ) . '" style="max-width:150px;height:auto;" /></div>';
        }
        echo '</div>';
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
            echo '<label><input type="checkbox" class="botsauto-checkbox" name="answers['.$hash.']" '.$checked.'> '.esc_html( $data['item'] ).'</label>';
            echo '</li>';
        }
        if ( $open_ul ) {
            echo '</ul></details>';
        }
        echo '</div>';
        if ( $show_update ) {
            echo '<p><label><input type="checkbox" class="botsauto-checkbox" name="update_items" value="1"> De checklist is gewijzigd, nieuwe versie gebruiken</label></p>';
        }
        $c = $completed ? 'checked' : '';
        echo '<p><label><input type="checkbox" class="botsauto-checkbox" name="completed" value="1" '.$c.'> Checklist afgerond</label></p>';
        $label = $post_id ? 'Opslaan' : 'Checklist verzenden';
        echo '<p><input type="submit" class="button button-primary" value="'.esc_attr($label).'"></p>';
        echo '</form></div>';
        return ob_get_clean();
    }

    public function handle_submit() {
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $title   = sanitize_text_field( $_POST['entry_title'] );
        $name    = sanitize_text_field( $_POST['name'] );
        $email   = sanitize_email( $_POST['email'] );
        $completed = isset($_POST['completed']) ? '1' : '';

        $list_id       = isset( $_POST['checklist_id'] ) ? intval( $_POST['checklist_id'] ) : 0;
        $current_items  = $this->associate_items( $this->get_checklist_items( $list_id ) );
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
            wp_update_post( array( 'ID' => $post_id, 'post_title' => $title ) );
        } else {
            $token = wp_generate_password(20,false,false);
            $post_id = wp_insert_post( array(
                'post_type' => $this->post_type,
                'post_status' => 'publish',
                'post_title' => $title,
                'meta_input' => array('token'=>$token)
            ));
        }
        update_post_meta( $post_id, 'name', $name );
        update_post_meta( $post_id, 'email', $email );
        update_post_meta( $post_id, 'answers', $answers );
        update_post_meta( $post_id, 'items_snapshot', $snapshot );
        update_post_meta( $post_id, 'completed', $completed );
        update_post_meta( $post_id, 'checklist_id', $list_id );
        $token = get_post_meta( $post_id, 'token', true );
        $referer = wp_get_referer();
        if ( ! $referer ) {
            $referer = home_url( '/' );
        }
        $referer = remove_query_arg( 'botsauto_edit', $referer );
        $edit_url = add_query_arg( 'botsauto_edit', $token, $referer );
        update_post_meta( $post_id, 'edit_url', $edit_url );
        if ( ! empty( $_POST['post_id'] ) ) {
            wp_redirect( $edit_url );
            exit;
        }
        $style = $this->get_style_options();
        $pdf = $this->generate_pdf( $title, $name, $answers, $snapshot, $style['image'] );
        $body = 'Bedankt voor het invullen van de checklist. Bewaar deze link om later verder te gaan: '.$edit_url;
        $cc = get_option( $this->cc_option, '' );
        $headers = array();
        if ( $cc ) { $headers[] = 'Bcc: '.$cc; }
        $this->send_email( $email, 'Checklist bevestiging', $body, array( $pdf ), $headers );
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

    private function generate_pdf( $title, $name, $answers, $snapshot, $image = '' ) {
        if ( ! defined( 'FPDF_FONTPATH' ) ) {
            define( 'FPDF_FONTPATH', plugin_dir_path( __FILE__ ) . 'lib/font/' );
        }
        require_once plugin_dir_path(__FILE__).'lib/fpdf.php';
        $pdf = new FPDF();
        $pdf->AddPage();
        $y = 20;
        if ( $image ) {
            $uploads = wp_upload_dir();
            $path = $image;
            if ( strpos( $image, $uploads['baseurl'] ) === 0 ) {
                $path = str_replace( $uploads['baseurl'], $uploads['basedir'], $image );
            }
            if ( @file_exists( $path ) ) {
                $pdf->Image( $path, 150, 10, 40 );
                $y = 50;
            }
        }
        $style = $this->get_style_options();
        $font_map = array(
            'Arial, sans-serif'         => 'Arial',
            'Helvetica, sans-serif'     => 'Helvetica',
            '"Times New Roman", serif' => 'Times',
            'Georgia, serif'            => 'Times',
            'Oswald, sans-serif'        => 'Helvetica',
        );
        $pdf_font = isset( $font_map[ $style['font'] ] ) ? $font_map[ $style['font'] ] : 'Helvetica';
        $pdf->SetFont( $pdf_font, '', 12 );
        $pdf->SetXY(10, $y);
        $pdf->MultiCell(0, 8, $this->pdf_string('BOTSAUTO Checklist'), 0, 'L');
        $pdf->MultiCell(0, 8, $this->pdf_string('Titel: '.$title), 0, 'L');
        $pdf->MultiCell(0, 8, $this->pdf_string('Naam: '.$name), 0, 'L');
        $current_phase = '';
        foreach ( $snapshot as $hash => $item ) {
            $status = isset( $answers[ $hash ] ) ? 'Ja' : 'Nee';
            if ( $item['phase'] !== $current_phase ) {
                $pdf->Ln(4);
                if ( $item['phase'] ) {
                    $pdf->SetFont( $pdf_font, 'B', 12 );
                    $pdf->MultiCell(0, 7, $this->pdf_string( $item['phase'] ), 0, 'L');
                }
                if ( $item['desc'] ) {
                    $pdf->SetFont( $pdf_font, '', 10 );
                    $pdf->MultiCell(0, 6, $this->pdf_string( $item['desc'] ), 0, 'L');
                }
                $current_phase = $item['phase'];
            }
            if ( $item['question'] ) {
                $pdf->SetFont( $pdf_font, 'I', 10 );
                $pdf->MultiCell(0, 6, $this->pdf_string( $item['question'] ), 0, 'L');
            }
            $pdf->SetFont( $pdf_font, '', 10 );
            $pdf->MultiCell(0, 6, $this->pdf_string( '- '.$item['item'].' - '.$status ), 0, 'L');
        }
        $uploads = wp_upload_dir();
        $file = trailingslashit( $uploads['path'] ) . 'botsauto-' . uniqid() . '.pdf';
        $pdf->Output( $file, 'F' );
        return $file;
    }

    private function get_style_options() {
        $defaults = array(
            'primary'    => '#d14292',
            'text'       => '#00306a',
            'background' => '#d1eaf8',
            'font'       => 'Arial, sans-serif',
            'image'      => '',
        );
        $opt = get_option( $this->style_option, array() );
        return wp_parse_args( $opt, $defaults );
    }

    private function get_adv_style_options() {
        $defaults = $this->default_adv_style();
        $opt = get_option( $this->adv_style_option, array() );
        if ( ! is_array( $opt ) ) {
            $opt = json_decode( $opt, true );
        }
        return wp_parse_args( $opt, $defaults );
    }

    public function add_admin_pages() {
        add_menu_page( 'BOTSAUTO', 'BOTSAUTO', 'manage_options', 'botsauto-settings', array( $this, 'main_settings_page' ), 'dashicons-yes', 26 );
        add_submenu_page( 'botsauto-settings', 'BOTSAUTO stijl', 'Stijl', 'manage_options', 'botsauto-style', array( $this, 'settings_page' ) );
        add_submenu_page( 'botsauto-settings', 'Geavanceerde Opmaak', 'Geavanceerde Opmaak', 'manage_options', 'botsauto-advanced', array( $this, 'advanced_page' ) );
        add_submenu_page( 'botsauto-settings', 'E-mail BCC', 'E-mail BCC', 'manage_options', 'botsauto-cc', array( $this, 'cc_page' ) );
    }

    public function register_settings() {
        register_setting( 'botsauto_style_group', $this->style_option );
        register_setting( 'botsauto_adv_style_group', $this->adv_style_option );
        register_setting( 'botsauto_css_group', $this->custom_css_option );
        register_setting( 'botsauto_cc_group', $this->cc_option, array( 'sanitize_callback' => 'sanitize_email' ) );
    }

    public function enqueue_color_picker_assets( $hook ) {
        if ( strpos( $hook, 'botsauto-style' ) !== false || strpos( $hook, 'botsauto-advanced' ) !== false ) {
            wp_enqueue_style( 'wp-color-picker' );
            wp_enqueue_script( 'wp-color-picker' );
            wp_enqueue_media();
            wp_add_inline_script( 'wp-color-picker', 'jQuery(function($){$(".color-field").wpColorPicker();});' );
        }
    }

    public function settings_page() {
        $opts  = $this->get_style_options();
        $fonts = array(
            'Arial, sans-serif'         => 'Arial',
            'Helvetica, sans-serif'     => 'Helvetica',
            '"Times New Roman", serif' => 'Times New Roman',
            'Georgia, serif'            => 'Georgia',
            'Oswald, sans-serif'        => 'Oswald',
        );
        echo '<div class="wrap"><h1>BOTSAUTO stijl</h1><form method="post" action="options.php">';
        settings_fields( 'botsauto_style_group' );
        echo '<table class="form-table">';
        echo '<tr><th scope="row">Primaire kleur</th><td><input type="text" class="color-field" name="'.$this->style_option.'[primary]" value="'.esc_attr($opts['primary']).'" /></td></tr>';
        echo '<tr><th scope="row">Tekstkleur</th><td><input type="text" class="color-field" name="'.$this->style_option.'[text]" value="'.esc_attr($opts['text']).'" /></td></tr>';
        echo '<tr><th scope="row">Achtergrondkleur</th><td><input type="text" class="color-field" name="'.$this->style_option.'[background]" value="'.esc_attr($opts['background']).'" /></td></tr>';
        echo '<tr><th scope="row">Lettertype</th><td><select name="'.$this->style_option.'[font]">';
        foreach ( $fonts as $val => $label ) {
            $sel = selected( $opts['font'], $val, false );
            echo '<option value="'.esc_attr($val).'" '.$sel.'>'.esc_html($label).'</option>';
        }
        echo '</select></td></tr>';
        echo '<tr><th scope="row">Afbeelding</th><td><input type="text" id="botsauto-image" name="'.$this->style_option.'[image]" value="'.esc_attr($opts['image']).'" /> <button type="button" class="button" id="botsauto-image-btn">Selecteer afbeelding</button></td></tr>';
        echo '</table>';
        submit_button();
        echo '</form></div>';
        echo '<script>jQuery(function($){$("#botsauto-image-btn").on("click",function(e){e.preventDefault();var frame=wp.media({title:"Selecteer afbeelding",multiple:false});frame.on("select",function(){var url=frame.state().get("selection").first().toJSON().url;$("#botsauto-image").val(url);});frame.open();});});</script>';
    }

    public function advanced_page() {
        $opts = $this->get_adv_style_options();
        $custom = get_option( $this->custom_css_option, '' );
        echo '<div class="wrap"><h1>Geavanceerde Opmaak</h1><form method="post" action="options.php">';
        settings_fields( 'botsauto_adv_style_group' );
        echo '<table class="form-table">';
        foreach ( $this->default_adv_style() as $key => $vals ) {
            echo '<tr><th colspan="2"><h2>'.esc_html( ucfirst($key) ).'</h2></th></tr>';
            foreach ( $vals as $field => $default ) {
                $val = isset( $opts[$key][$field] ) ? $opts[$key][$field] : $default;
                $name = $this->adv_style_option.'['.$key.']['.$field.']';
                $type = strpos( $field, 'color' ) !== false ? 'text' : 'text';
                $class = strpos( $field, 'color' ) !== false ? 'color-field' : '';
                echo '<tr><th scope="row">'.esc_html( $field ).'</th><td><input type="'.$type.'" class="'.$class.'" name="'.$name.'" value="'.esc_attr($val).'" /></td></tr>';
            }
        }
        echo '<tr><th scope="row">Custom CSS</th><td><textarea name="'.$this->custom_css_option.'" rows="5" cols="50">'.esc_textarea($custom).'</textarea></td></tr>';
        echo '</table>';
        submit_button();
        echo '</form>';
        echo '<h2>Export</h2><textarea readonly rows="5" style="width:100%">'.esc_textarea( wp_json_encode( $opts ) )."</textarea>";
        echo '<form method="post" style="margin-top:1em;"><input type="hidden" name="botsauto_reset_adv" value="1" />';
        submit_button( 'Reset naar standaard', 'delete' );
        echo '</form></div>';

        if ( isset($_POST['botsauto_reset_adv']) ) {
            update_option( $this->adv_style_option, $this->default_adv_style() );
            update_option( $this->custom_css_option, '' );
            echo '<div class="updated"><p>Opmaak gereset.</p></div>';
        }
    }

    public function cc_page() {
        $cc = get_option( $this->cc_option, '' );
        echo '<div class="wrap"><h1>E-mail BCC</h1><form method="post" action="options.php">';
        settings_fields( 'botsauto_cc_group' );
        echo '<table class="form-table"><tr><th scope="row">E-mail adres</th><td><input type="email" name="'.$this->cc_option.'" value="'.esc_attr($cc).'" /></td></tr></table>';
        submit_button();
        echo '</form></div>';
    }

    public function main_settings_page() {
        echo '<div class="wrap"><h1>BOTSAUTO instellingen</h1><p>Gebruik de submenu\'s om de stijl en e-mail opties in te stellen.</p></div>';
    }

    public function output_frontend_style() {
        $o = $this->get_style_options();
        $font_link = '';
        if ( strpos( $o['font'], 'Oswald' ) !== false ) {
            $font_link = '<link href="https://fonts.googleapis.com/css2?family=Oswald&display=swap" rel="stylesheet">';
        }
        echo $font_link;
        echo '<style class="botsauto-style">'
            . '.botsauto-checklist{background:' . esc_attr($o['background']) . ';color:' . esc_attr($o['text']) . ';font-family:' . esc_attr($o['font']) . ';}'
            . '.botsauto-header{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:1em;font-family:' . esc_attr($o['font']) . ';}'
            . '.botsauto-header .botsauto-fields{flex:1;margin-right:1em;max-width:500px;}'
            . '.botsauto-header .botsauto-fields p{margin:0;}'
            . '.botsauto-header label{color:' . esc_attr($o['primary']) . ';display:block;margin-bottom:.5em;}'
            . '.botsauto-header input[type=text],.botsauto-header input[type=email]{width:100%;box-sizing:border-box;}'
            . '.botsauto-checklist label{color:' . esc_attr($o['primary']) . ';}'
            . '.botsauto-checklist strong{color:' . esc_attr($o['primary']) . ';}'
            . '.botsauto-phase>summary{font-weight:bold;cursor:pointer;margin:0;color:' . esc_attr($o['primary']) . ';list-style:none;position:relative;padding-left:1.2em;}'
            . '.botsauto-phase>summary::before{content:"\25B6";position:absolute;left:0;}'
            . '.botsauto-phase[open]>summary::before{content:"\25BC";}'
            . '.botsauto-checkbox{accent-color:' . esc_attr($o['primary']) . ';display:inline-block!important;width:auto!important;height:auto!important;appearance:auto!important;visibility:visible!important;}'
            . '.botsauto-checklist .button-primary{background:' . esc_attr($o['primary']) . ';border-color:' . esc_attr($o['primary']) . ';}'
            . '.botsauto-completed{margin-top:1.5em;}'
            . '</style>';
    }
}

register_activation_hook( __FILE__, array( 'BOTSAUTO_Checklist', 'install' ) );
register_uninstall_hook( __FILE__, array( 'BOTSAUTO_Checklist', 'uninstall' ) );

new BOTSAUTO_Checklist();

