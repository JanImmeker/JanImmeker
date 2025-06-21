<?php
/**
 * Plugin Name: BOTSAUTO Checklist
 * Plugin URI: https://botsauto.app
 * Description: Frontend checklist with admin overview, PDF email confirmation, and edit link.
 * Version: 1.12.10
 * Author: Jan Immeker
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: botsauto-checklist
 * Domain Path: /languages
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
            update_option( $self->style_option, $self->default_style() );
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
        add_action( 'init', array( $this, 'load_textdomain' ) );
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
        add_action( 'admin_post_botsauto_export_style', array( $this, 'export_style' ) );
        add_action( 'admin_post_botsauto_generate_pdf', array( $this, 'admin_generate_pdf' ) );
        add_action( 'admin_post_botsauto_resend_pdf', array( $this, 'admin_resend_pdf' ) );
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
        $headers   = array( 'Content-Type: text/html; charset=UTF-8' );
        $headers   = array_merge( $headers, (array) $extra_headers );
        return wp_mail( $to, $subject, $body, $headers, $attachments );
    }

    private function hex_to_rgb( $hex ) {
        $hex = ltrim( $hex, '#' );
        if ( strlen( $hex ) === 3 ) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        $int = hexdec( $hex );
        return array( ($int >> 16) & 255, ($int >> 8) & 255, $int & 255 );
    }

    public function load_textdomain() {
        load_plugin_textdomain( 'botsauto-checklist', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
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
        } elseif ( strpos( $hook, 'botsauto-style' ) !== false ) {
            wp_enqueue_script( 'botsauto-style-preview', plugin_dir_url( __FILE__ ) . 'js/style-preview.js', array( 'jquery' ), '1.0', true );
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
        add_meta_box( 'botsauto_lines', __( 'Checklist items', 'botsauto-checklist' ), array( $this, 'meta_box_lines' ), $this->list_post_type, 'normal', 'default' );
        add_meta_box( 'botsauto_shortcode', __( 'Shortcode', 'botsauto-checklist' ), array( $this, 'meta_box_shortcode' ), $this->list_post_type, 'side' );
        add_meta_box( 'botsauto_submission', __( 'Inzending', 'botsauto-checklist' ), array( $this, 'meta_box_submission' ), $this->post_type, 'normal' );
    }

    public function meta_box_lines( $post ) {
        $content = get_post_meta( $post->ID, 'botsauto_lines', true );
        if ( ! is_string( $content ) ) {
            $content = '';
        }
        echo '<textarea id="botsauto_content" name="botsauto_content" style="display:none">'.esc_textarea( $content ).'</textarea>';
        echo '<div id="botsauto-editor"></div>';
        echo '<p><button type="button" class="button" id="botsauto-add-phase">'.esc_html__( 'Fase toevoegen', 'botsauto-checklist' ).'</button></p>';
        echo '<input type="hidden" name="botsauto_lines_nonce" value="'.wp_create_nonce('botsauto_lines').'" />';
        echo '<script type="text/template" id="botsauto-phase-template"><div class="botsauto-phase"><details open><summary></summary><p class="phase-line"><label><span>'.esc_html__( 'Fase', 'botsauto-checklist' ).':</span> <input type="text" class="phase-field"></label> <button type="button" class="button botsauto-remove-phase">'.esc_html__( 'Verwijder', 'botsauto-checklist' ).'</button></p><p class="desc-line"><label><span>'.esc_html__( 'Toelichting', 'botsauto-checklist' ).':</span> <input type="text" class="desc-field"></label></p><div class="botsauto-questions"></div><p><button type="button" class="button botsauto-add-question">'.esc_html__( 'Vraag toevoegen', 'botsauto-checklist' ).'</button></p></details></div></script>';
        echo '<script type="text/template" id="botsauto-question-template"><div class="botsauto-question"><p class="question-line"><label><span>'.esc_html__( 'Vraag', 'botsauto-checklist' ).':</span> <input type="text" class="question-field"></label> <button type="button" class="button botsauto-remove-question">'.esc_html__( 'Verwijder', 'botsauto-checklist' ).'</button></p><div class="botsauto-items"></div><p><button type="button" class="button botsauto-add-item">'.esc_html__( 'Item toevoegen', 'botsauto-checklist' ).'</button></p></div></script>';
        echo '<script type="text/template" id="botsauto-item-template"><div class="botsauto-item"><p class="item-line"><label><span>'.esc_html__( 'Checklist item', 'botsauto-checklist' ).':</span> <input type="text" class="item-field"></label> <button type="button" class="button botsauto-remove-item">'.esc_html__( 'Verwijder', 'botsauto-checklist' ).'</button></p></div></script>';
        $s = $this->get_style_options();
        $adv = $this->get_adv_style_options();
        echo '<style>#botsauto-editor p{display:flex;align-items:center;gap:6px;margin:4px 0;}#botsauto-editor label{flex:1;display:flex;align-items:center;min-width:0;color:' . esc_attr($s['primary']) . ';}#botsauto-editor label span{display:inline-block;width:140px;}#botsauto-editor input{flex:1;width:' . esc_attr($adv['field']['width']) . ';max-width:none;border-radius:' . esc_attr($adv['field']['border-radius']) . ';border-style:' . esc_attr($adv['field']['border-style']) . ';border-width:' . esc_attr($adv['field']['border-width']) . ';border-color:' . esc_attr($adv['field']['border-color']) . ';background:' . esc_attr($adv['field']['background-color']) . ';color:' . esc_attr($adv['field']['text-color']) . ';box-sizing:border-box;}#botsauto-editor .question-line{margin-left:2em;}#botsauto-editor .item-line{margin-left:4em;}#botsauto-editor{background:' . esc_attr($s['background']) . ';color:' . esc_attr($s['text']) . ';font-family:' . esc_attr($s['font']) . ';}#botsauto-editor input[type=checkbox]{accent-color:' . esc_attr($s['primary']) . '!important;display:inline-block!important;width:auto!important;height:auto!important;appearance:auto!important;}#botsauto-editor .button{background:' . esc_attr($s['primary']) . ';border-color:' . esc_attr($s['primary']) . ';color:#fff;}#botsauto-editor .botsauto-phase>details>summary{font-weight:bold;cursor:pointer;margin:0;color:' . esc_attr($s['primary']) . ';list-style:none;position:relative;padding-left:1.2em;}#botsauto-editor .botsauto-phase>details>summary::before{content:"▶";position:absolute;left:0;}#botsauto-editor .botsauto-phase>details[open]>summary::before{content:"▼";}</style>';
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
        $notes   = get_post_meta( $post->ID, 'notes', true );
        $history = get_post_meta( $post->ID, 'pdf_history', true );
        if ( ! is_array( $snapshot ) ) return;
        echo '<p><strong>Titel:</strong> '.esc_html( $title ).'<br><strong>Naam:</strong> '.esc_html( $name ).'<br><strong>Email:</strong> '.esc_html( $email ).'<br><strong>Afgerond:</strong> '.$completed.'</p>';
        if ( $url ) {
            echo '<p><strong>URL:</strong> <a href="'.esc_url($url).'">'.esc_html($url).'</a></p>';
        }
        echo '<ul>';
        foreach ( $snapshot as $hash => $item ) {
            $ck = isset( $answers[$hash] ) ? '&#10003;' : '&#10007;';
            $note = isset( $notes[$hash] ) ? ' - '.esc_html( wp_trim_words( strip_tags($notes[$hash]), 10 ) ) : '';
            echo '<li>'.esc_html( $item['item'] ).' '.$ck.$note.'</li>';
        }
        echo '</ul>';
        if ( $history && is_array( $history ) ) {
            echo '<h4>PDF\'s</h4><ul>';
            foreach ( $history as $h ) {
                $file = esc_url( $h['file'] );
                $time = esc_html( date_i18n( 'Y-m-d H:i', $h['time'] ) );
                $link = wp_nonce_url( admin_url('admin-post.php?action=botsauto_resend_pdf&post_id='.$post->ID.'&file='.urlencode($file)), 'botsauto_resend_'.$post->ID );
                echo '<li><a href="'.$file.'">'.basename( $file ).'</a> ('.$time.') - <a href="'.$link.'">'.esc_html__( 'Opnieuw versturen', 'botsauto-checklist' ).'</a></li>';
            }
            echo '</ul>';
        }
        $gen_url = wp_nonce_url( admin_url('admin-post.php?action=botsauto_generate_pdf&post_id='.$post->ID), 'botsauto_gen_'.$post->ID );
        echo '<p><a href="'.$gen_url.'" class="button">'.esc_html__( 'Nieuwe PDF genereren', 'botsauto-checklist' ).'</a></p>';
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
                $new['shortcode'] = __( 'Shortcode', 'botsauto-checklist' );
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
                'font-size'        => '18px',
                'padding'          => '18px',
            ),
            'phase' => array(
                'text-color'       => '#d14292',
                'background-color' => '',
                'font-size'        => '22px',
                'font-weight'      => 'bold',
            ),
            'question' => array(
                'text-color' => '#4d4d4d',
                'font-style' => 'italic',
                'font-size'  => '18px',
            ),
            'item' => array(
                'text-color' => '#00306a',
                'font-size'  => '16px',
            ),
            'button' => array(
                'text-color'       => '#ffffff',
                'background-color' => '#d14292',
                'padding'          => '9px 15px',
                'border-radius'    => '8px',
                'border-color'       => '#D14292',
            ),
            'field' => array(
                'background-color' => '#ffffff',
                'text-color'       => '#00306a',
                'border-color'     => '#4d4d4d',
                'border-radius'    => '8px',
                'border-style'     => 'solid',
                'border-width'     => 'thin',
                'width'            => '100%',
            ),
            'checkbox' => array(
                'color'            => '#d14292',
                'background-color' => '#ffffff',
                'border-color'     => '#4d4d4d',
                'size'             => '20px',
            ),
            'checked' => array(
                'text-color'       => '#6c6c6c',
                'text-decoration'  => 'line-through',
            ),
            'note' => array(
                'text-color'       => '#00306a',
                'background-color' => '#ffffff',
                'font-size'        => '14px',
            ),
            'completed' => array(
                'text-color'       => '#006633',
                'font-size'        => '18px',
            ),
            'title' => array(
                'text-color'       => '#00306a',
                'background-color' => '',
                'font-size'        => '24px',
                'font-weight'      => 'bold',
                'font-style'       => 'normal',
                'padding'          => '10px 0',
            ),
        );
    }

    private function default_style() {
        return array(
            'primary'        => '#00306a',
            'text'           => '#4D4D4D',
            'background'     => '#edf4f7',
            'font'           => 'Oswald, sans-serif',
            'image'          => '',
            'image_align'    => 'center',
            'image_width'    => '75',
            'checklist_title'=> 'BOTSAUTO Checklist',
            'title_position' => 'above',
            'note_icon'  => 'fa-clipboard',
            'note_icon_color' => '#d14292',
            'done_icon'  => 'fa-clipboard-check',
            'done_icon_color' => '#006633',
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
                return __( 'Checklist niet gevonden.', 'botsauto-checklist' );
            }
        } else {
            $first = get_posts( array( 'post_type' => $this->list_post_type, 'numberposts' => 1 ) );
            if ( $first ) {
                $list_id = $first[0]->ID;
            } else {
                return __( 'Checklist niet gevonden.', 'botsauto-checklist' );
            }
        }
        $token = isset( $_GET['botsauto_edit'] ) ? sanitize_text_field( $_GET['botsauto_edit'] ) : '';
        $post_id = $this->get_post_id_by_token( $token );
        $values = array();
        $completed = '';
        $email = '';
        $name = '';
        $title = '';
        $notes = array();
        if ( $post_id ) {
            $values = get_post_meta( $post_id, 'answers', true );
            $completed = get_post_meta( $post_id, 'completed', true );
            $email = get_post_meta( $post_id, 'email', true );
            $name = get_post_meta( $post_id, 'name', true );
            $title = get_the_title( $post_id );
            $list_id = intval( get_post_meta( $post_id, 'checklist_id', true ) );
            $notes   = get_post_meta( $post_id, 'notes', true );
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

        if ( function_exists( 'wp_enqueue_editor' ) ) {
            wp_enqueue_editor();
        }
        ob_start();
        $style = $this->get_style_options();
        $adv   = $this->get_adv_style_options();
        $adv   = $this->get_adv_style_options();
        $adv   = $this->get_adv_style_options();
        $custom = get_option( $this->custom_css_option, '' );
        $wrapper = 'botsauto-' . wp_generate_password(6, false, false);
        echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />';
        $css = $this->build_css( $style, $adv, '#' . $wrapper, $custom );
        if ( strpos( $style['font'], 'Oswald' ) !== false ) {
            $css = '@import url("https://fonts.googleapis.com/css2?family=Oswald&display=swap");' . $css;
        }
        echo '<style>' . $css . '</style>';
        echo '<div id="'.$wrapper.'">';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        echo '<input type="hidden" name="action" value="botsauto_save">';
        echo '<input type="hidden" name="checklist_id" value="' . intval( $list_id ) . '" />';
        if ( $post_id ) {
            echo '<input type="hidden" name="post_id" value="' . intval($post_id) . '" />';
            echo '<input type="hidden" name="items_snapshot" value="' . esc_attr( wp_json_encode( $snapshot ) ) . '" />';
        }
        echo '<div class="botsauto-header">';
        echo '<div class="botsauto-logo-title '.esc_attr($style['title_position']).'">';
        echo '<div class="botsauto-title">'.esc_html($style['checklist_title']).'</div>';
        if ( ! empty( $style['image'] ) ) {
            echo '<div class="botsauto-logo"><img src="' . esc_url( $style['image'] ) . '" /></div>';
        }
        echo '</div>';
        echo '<div class="botsauto-fields">';
        echo '<p><label>'.esc_html__( 'Titel', 'botsauto-checklist' ).': <input type="text" name="entry_title" value="' . esc_attr($title) . '" required></label></p>';
        echo '<p><label>'.esc_html__( 'Naam', 'botsauto-checklist' ).': <input type="text" name="name" value="' . esc_attr($name) . '" required></label></p>';
        echo '<p><label>'.esc_html__( 'E-mail', 'botsauto-checklist' ).': <input type="email" name="email" value="' . esc_attr($email) . '" required></label></p>';
        echo '</div></div>';
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
                echo '<p class="botsauto-question-text">'.esc_html( $data['question'] ).'</p>';
            }
            $cid  = 'cb_'.esc_attr( $hash );
            $note  = isset( $notes[$hash] ) ? esc_textarea( $notes[$hash] ) : '';
            $has  = $note !== '';
            $icon = $has ? esc_attr( $style['done_icon'] ) : esc_attr( $style['note_icon'] );
            $cls  = $has ? 'botsauto-note-btn botsauto-done' : 'botsauto-note-btn';
            echo '<input type="checkbox" id="'.$cid.'" class="botsauto-checkbox" name="answers['.$hash.']" '.$checked.'> <label for="'.$cid.'">'.esc_html( $data['item'] ).'</label> <button type="button" class="'.$cls.'"><i class="fa '.$icon.'"></i></button><div class="botsauto-note" style="display:none"><textarea class="botsauto-rich" name="notes['.$hash.']">'.$note.'</textarea></div>';
            echo '</li>';
        }
        if ( $open_ul ) {
            echo '</ul></details>';
        }
        echo '</div>';
        if ( $show_update ) {
            echo '<p><label><input type="checkbox" class="botsauto-checkbox" name="update_items" value="1"> '.esc_html__( 'De checklist is gewijzigd, nieuwe versie gebruiken', 'botsauto-checklist' ).'</label></p>';
        }
        $c = $completed ? 'checked' : '';
        echo '<p class="botsauto-completed"><label><input type="checkbox" class="botsauto-checkbox" name="completed" value="1" '.$c.'> '.esc_html__( 'Checklist afgerond', 'botsauto-checklist' ).'</label></p>';
        echo '<input type="hidden" name="send_pdf" value="0" />';
        $orig = array( 'answers' => $values, 'notes' => $notes, 'completed' => $completed );
        $label = $post_id ? esc_html__( 'Opslaan', 'botsauto-checklist' ) : esc_html__( 'Checklist verzenden', 'botsauto-checklist' );
        echo '<p><input type="submit" class="button button-primary" value="'.esc_attr($label).'" /></p>';
        echo '</form></div>';
        echo '<script>var botsautoOrig='.wp_json_encode($orig).';</script>';
        echo '<script>var botsautoStyle='.wp_json_encode(array('note'=>$style['note_icon'],'done'=>$style['done_icon'])).';</script>';
        echo '<script>document.addEventListener("click",function(e){var b=e.target.closest(".botsauto-note-btn");if(b){e.preventDefault();var n=b.nextElementSibling;n.style.display=n.style.display==="none"?"block":"none";}});</script>';
        $note_css = "body{color:{$adv['note']['text-color']};background:{$adv['note']['background-color']};font-size:{$adv['note']['font-size']};font-family:{$style['font']};}";
        echo "<script>
document.addEventListener('DOMContentLoaded',function(){
  if(typeof tinymce!=='undefined'){
    tinymce.init({selector:'#{$wrapper} .botsauto-rich',menubar:false,toolbar:'bold italic underline link',branding:false,content_style:'".$note_css."'});
  }
  var form=document.querySelector('#{$wrapper} form');
  if(!form)return;
  function updateBtn(tx){
    var btn=tx.parentElement.previousElementSibling;
    if(!btn)return;
    if(tx.value.trim()!==''){btn.classList.add('botsauto-done');btn.querySelector('i').className='fa '+botsautoStyle.done;}else{btn.classList.remove('botsauto-done');btn.querySelector('i').className='fa '+botsautoStyle.note;}
  }
  form.querySelectorAll('.botsauto-note textarea').forEach(function(t){updateBtn(t);t.addEventListener('input',function(){updateBtn(t);});});
  var isNew=!form.querySelector('input[name=post_id]');
  form.addEventListener('submit',function(e){
    if(typeof tinymce!='undefined') tinymce.triggerSave();
    var title=form.querySelector('input[name=entry_title]').value.trim();
    var name=form.querySelector('input[name=name]').value.trim();
    var email=form.querySelector('input[name=email]').value.trim();
    var emailOk=/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email);
    if(!title){alert('Vul een titel in.');e.preventDefault();return;}
    if(!name){alert('Vul uw naam in.');e.preventDefault();return;}
    if(!emailOk){alert('Voer een geldig e-mailadres in.');e.preventDefault();return;}
    var changed=false;var orig=botsautoOrig||{answers:{},notes:{},completed:''};
    var compNew=form.querySelector(\"input[name=completed]\").checked?'1':'';
    if(orig.completed!==compNew) changed=true;
    form.querySelectorAll(\"input[name^='answers']\").forEach(function(inp){var h=inp.name.match(/answers\\[(.+)\\]/)[1];var v=inp.checked?'1':'';if((orig.answers&&orig.answers[h]?'1':'0')!=v) changed=true;});
    form.querySelectorAll(\"textarea[name^='notes']\").forEach(function(tx){var h=tx.name.match(/notes\\[(.+)\\]/)[1];if((orig.notes&&orig.notes[h]||'')!=tx.value) changed=true;});
    var sendField=form.querySelector('input[name=send_pdf]');
    if(isNew){sendField.value='1';return;}
    if(compNew==='1'){sendField.value='1';return;}
    if(changed){if(confirm('Wil je de bijgewerkte PDF per e-mail ontvangen?')){sendField.value='1';}}
  });
});</script>";
        return ob_get_clean();
    }

    public function handle_submit() {
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $title   = sanitize_text_field( $_POST['entry_title'] );
        $name    = sanitize_text_field( $_POST['name'] );
        $email   = sanitize_email( $_POST['email'] );
        if ( ! is_email( $email ) ) {
            wp_die( __( 'Ongeldig e-mailadres.', 'botsauto-checklist' ) );
        }
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
        $notes   = array();
        if ( isset( $_POST['answers'] ) && is_array( $_POST['answers'] ) ) {
            foreach ( $_POST['answers'] as $hash => $val ) {
                $answers[ sanitize_key( $hash ) ] = true;
            }
        }
        if ( isset( $_POST['notes'] ) && is_array( $_POST['notes'] ) ) {
            foreach ( $_POST['notes'] as $hash => $val ) {
                $notes[ sanitize_key( $hash ) ] = wp_kses_post( $val );
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
        update_post_meta( $post_id, 'notes', $notes );
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
        $send_pdf = $post_id ? ( isset( $_POST['send_pdf'] ) && $_POST['send_pdf'] ) : true;
        if ( $completed === '1' ) {
            $send_pdf = true;
        }
        if ( $send_pdf ) {
            $style = $this->get_style_options();
            $pdf = $this->generate_pdf( $title, $name, $answers, $snapshot, $notes, $style['image'] );
            $body = sprintf( __( 'Bedankt voor het invullen van de checklist. Bewaar deze link om later verder te gaan: %s', 'botsauto-checklist' ), $edit_url );
            $cc = get_option( $this->cc_option, '' );
            $headers = array();
            if ( $cc ) { $headers[] = 'Bcc: '.$cc; }
            $this->send_email( $email, __( 'Checklist bevestiging', 'botsauto-checklist' ), $body, array( $pdf ), $headers );
            $uploads = wp_upload_dir();
            $url = str_replace( $uploads['basedir'], $uploads['baseurl'], $pdf );
            $history = get_post_meta( $post_id, 'pdf_history', true );
            if ( ! is_array( $history ) ) { $history = array(); }
            $history[] = array( 'file' => $url, 'time' => time() );
            update_post_meta( $post_id, 'pdf_history', $history );
        }
        update_post_meta( $post_id, 'completed', $completed );
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

    private function generate_pdf( $title, $name, $answers, $snapshot, $notes = array(), $image = '' ) {
        if ( ! defined( 'FPDF_FONTPATH' ) ) {
            define( 'FPDF_FONTPATH', plugin_dir_path( __FILE__ ) . 'lib/font/' );
        }
        require_once plugin_dir_path(__FILE__).'lib/fpdf.php';
        $pdf = new FPDF();
        $pdf->AddPage();
        $y = 20;
        $style = $this->get_style_options();
        if ( $image ) {
            $uploads = wp_upload_dir();
            $path = $image;
            if ( strpos( $image, $uploads['baseurl'] ) === 0 ) {
                $path = str_replace( $uploads['baseurl'], $uploads['basedir'], $image );
            }
            if ( @file_exists( $path ) ) {
                $width = intval( $style['image_width'] );
                $wmm = $width * 0.26;
                $x = 10;
                if ( $style['image_align'] === 'center' ) {
                    $x = (210 - $wmm) / 2;
                } elseif ( $style['image_align'] === 'right' ) {
                    $x = 210 - 10 - $wmm;
                }
                $pdf->Image( $path, $x, 10, $wmm );
                $y = 10 + $wmm + 10;
            }
        }
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
            list( $r, $g, $b ) = $this->hex_to_rgb( $adv['item']['text-color'] );
            $pdf->SetTextColor( $r, $g, $b );
            if ( isset( $answers[ $hash ] ) ) {
                list( $r, $g, $b ) = $this->hex_to_rgb( $adv['checked']['text-color'] );
                $pdf->SetTextColor( $r, $g, $b );
            }
            $pdf->SetFont( $pdf_font, '', 10 );
            $pdf->MultiCell(0, 6, $this->pdf_string( '- '.$item['item'].' - '.$status ), 0, 'L');
            if ( isset( $notes[$hash] ) && $notes[$hash] !== '' ) {
                $pdf->SetFont( $pdf_font, '', 9 );
                $pdf->MultiCell(0, 6, $this->pdf_string( strip_tags( $notes[$hash] ) ), 0, 'L');
            }
            list( $r, $g, $b ) = $this->hex_to_rgb( $adv['item']['text-color'] );
            $pdf->SetTextColor( $r, $g, $b );
        }
        $uploads = wp_upload_dir();
        $file = trailingslashit( $uploads['path'] ) . 'botsauto-' . uniqid() . '.pdf';
        $pdf->Output( $file, 'F' );
        return $file;
    }

    private function get_style_options() {
        $defaults = $this->default_style();
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

    private function build_css( $style, $adv, $selector, $custom = '' ) {
        $css  = "$selector *,${selector} *::before,${selector} *::after{box-sizing:border-box;margin:0;padding:0;}";
        $css .= "$selector{color:{$style['text']};background:{$style['background']};font-size:{$adv['container']['font-size']};padding:{$adv['container']['padding']};font-family:{$style['font']};}";
        $css .= "$selector .botsauto-phase>summary{color:{$adv['phase']['text-color']}!important;background:{$adv['phase']['background-color']}!important;font-size:{$adv['phase']['font-size']};font-weight:{$adv['phase']['font-weight']};list-style:none!important;position:relative;padding-left:1.2em;padding-top:10px;padding-bottom:10px;}";
        $css .= "$selector .botsauto-phase>summary::-webkit-details-marker{display:none;}";
        $css .= "$selector .botsauto-phase>summary::marker{content:'';font-size:0;}";
        $css .= "$selector .botsauto-phase>summary::before{content:'▶';position:absolute;left:0;}";
        $css .= "$selector .botsauto-phase[open]>summary::before{content:'▼';}";
        $css .= "$selector .botsauto-question-text{color:{$adv['question']['text-color']}!important;font-size:{$adv['question']['font-size']};font-style:{$adv['question']['font-style']};margin:0 0 .2em;flex-basis:100%;}";
        $css .= "$selector .botsauto-header{margin-bottom:1em;font-family:{$style['font']};}";
        $css .= "$selector .botsauto-logo-title{display:flex;justify-content:center;align-items:center;margin-bottom:1em;}";
        $css .= "$selector .botsauto-logo-title.above,.botsauto-logo-title.below{flex-direction:column;}";
        $css .= "$selector .botsauto-logo-title.left,.botsauto-logo-title.right{flex-direction:row;}";
        $css .= "$selector .botsauto-logo-title.left .botsauto-title{margin-right:1em;}";
        $css .= "$selector .botsauto-logo-title.right .botsauto-logo{margin-right:1em;}";
        $css .= "$selector .botsauto-logo-title.below .botsauto-title{order:2;}";
        $css .= "$selector .botsauto-logo-title.below .botsauto-logo{order:1;}";
        $css .= "$selector .botsauto-logo-title.right .botsauto-title{order:2;}";
        $css .= "$selector .botsauto-logo-title.right .botsauto-logo{order:1;}";
        $css .= "$selector .botsauto-header .botsauto-fields{max-width:500px;margin:0 auto;}";
        $css .= "$selector .botsauto-header .botsauto-fields p{margin:0;}";
        $css .= "$selector .botsauto-header label{color:{$style['primary']}!important;display:block;margin-bottom:.5em;}";
        $css .= "$selector .botsauto-logo{text-align:{$style['image_align']};margin-bottom:0;}";
        $css .= "$selector .botsauto-logo img{max-width:{$style['image_width']}px;height:auto;}";
        $css .= "$selector .botsauto-title{color:{$adv['title']['text-color']};background:{$adv['title']['background-color']};font-size:{$adv['title']['font-size']};font-weight:{$adv['title']['font-weight']};font-style:{$adv['title']['font-style']};padding:{$adv['title']['padding']};text-align:center;}";
        $css .= "$selector .botsauto-checklist li{display:flex;flex-wrap:wrap;align-items:flex-start;margin-bottom:.5em;padding-left:1.2em;}";
        $css .= "$selector .botsauto-checklist label{color:{$adv['item']['text-color']}!important;font-size:{$adv['item']['font-size']};margin-left:.25em;flex:1;}";
        $css .= "$selector input:checked+label{color:{$adv['checked']['text-color']}!important;text-decoration:{$adv['checked']['text-decoration']};}";
        $css .= "$selector .botsauto-checkbox{accent-color:{$adv['checkbox']['color']}!important;";
        $css .= "background:{$adv['checkbox']['background-color']}!important;border-color:{$adv['checkbox']['border-color']}!important;border-style:solid;border-width:1px;";
        $css .= "width:{$adv['checkbox']['size']}!important;height:{$adv['checkbox']['size']}!important;appearance:auto!important;flex:0 0 auto;}";
        $css .= "$selector .button-primary{background:{$adv['button']['background-color']}!important;color:{$adv['button']['text-color']}!important;padding:{$adv['button']['padding']};border-radius:{$adv['button']['border-radius']};border-color:{$adv['button']['border-color']}!important;}";
        $css .= "$selector input[type=text],${selector} input[type=email]{background:{$adv['field']['background-color']}!important;color:{$adv['field']['text-color']}!important;border-color:{$adv['field']['border-color']}!important;border-radius:{$adv['field']['border-radius']};border-style:{$adv['field']['border-style']};border-width:{$adv['field']['border-width']};width:{$adv['field']['width']};box-sizing:border-box;}";
        $css .= "$selector .botsauto-note{width:100%;margin-top:.5em;}";
        $css .= "$selector .botsauto-note textarea{width:100%;height:80px;font-size:{$adv['note']['font-size']};color:{$adv['note']['text-color']};background:{$adv['note']['background-color']};}";
        $css .= "$selector .botsauto-note .tox-tinymce{width:100%;}";
        $css .= "$selector .botsauto-note-btn{background:none;border:none;color:{$style['note_icon_color']};cursor:pointer;margin-left:5px;flex:0 0 auto;}";
        $css .= "$selector .botsauto-note-btn.botsauto-done{color:{$style['done_icon_color']};}";
        $css .= "$selector .botsauto-completed label{color:{$adv['completed']['text-color']}!important;font-size:{$adv['completed']['font-size']};font-family:{$style['font']};}";
        if ( $custom ) $css .= $custom;
        return $css;
    }

    public function add_admin_pages() {
        add_menu_page( 'BOTSAUTO', 'BOTSAUTO', 'manage_options', 'botsauto-settings', array( $this, 'main_settings_page' ), 'dashicons-yes', 26 );
        add_submenu_page( 'botsauto-settings', 'Opmaak', 'Opmaak', 'manage_options', 'botsauto-style', array( $this, 'style_page' ) );
        add_submenu_page( 'botsauto-settings', 'E-mail BCC', 'E-mail BCC', 'manage_options', 'botsauto-cc', array( $this, 'cc_page' ) );
    }

    public function register_settings() {
        // All style related options are stored and updated together so one
        // settings group is sufficient. This prevents the hidden option_page
        // field from overriding others when the form is saved.
        register_setting( 'botsauto_style_group', $this->style_option );
        register_setting( 'botsauto_style_group', $this->adv_style_option );
        register_setting( 'botsauto_style_group', $this->custom_css_option );
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

    public function style_page() {
        if ( isset( $_POST['botsauto_reset_adv'] ) && check_admin_referer( 'botsauto_reset_adv' ) ) {
            $current = get_option( $this->style_option, array() );
            $defaults = $this->default_style();
            // Keep the selected logo and its size when resetting other options
            foreach ( array( 'image', 'image_align', 'image_width' ) as $k ) {
                if ( isset( $current[ $k ] ) ) {
                    $defaults[ $k ] = $current[ $k ];
                }
            }
            update_option( $this->style_option, $defaults );
            update_option( $this->adv_style_option, $this->default_adv_style() );
            update_option( $this->custom_css_option, '' );
            echo '<div class="updated"><p>' . esc_html__( 'Opmaak gereset.', 'botsauto-checklist' ) . '</p></div>';
        }

        if ( isset($_POST['botsauto_import_submit']) && check_admin_referer('botsauto_adv_import') ) {
            if ( ! empty( $_FILES['adv_import']['tmp_name'] ) ) {
                $json = file_get_contents( $_FILES['adv_import']['tmp_name'] );
                $data = json_decode( $json, true );
                if ( json_last_error() === JSON_ERROR_NONE && is_array( $data ) ) {
                    if ( isset( $data['style'] ) ) {
                        update_option( $this->style_option, $data['style'] );
                    }
                    if ( isset( $data['adv'] ) ) {
                        update_option( $this->adv_style_option, $data['adv'] );
                    } else {
                        update_option( $this->adv_style_option, $data );
                    }
                    echo '<div class="updated"><p>'.esc_html__( 'Import succesvol.', 'botsauto-checklist' ).'</p></div>';
                } else {
                    echo '<div class="error"><p>'.esc_html__( 'Ongeldig importbestand.', 'botsauto-checklist' ).'</p></div>';
                }
            }
        }
        $opts  = $this->get_style_options();
        $fonts = array(
            'Arial, sans-serif'         => 'Arial',
            'Helvetica, sans-serif'     => 'Helvetica',
            '"Times New Roman", serif' => 'Times New Roman',
            'Georgia, serif'            => 'Georgia',
            'Oswald, sans-serif'        => 'Oswald',
        );
        $adv   = $this->get_adv_style_options();
        $custom = get_option( $this->custom_css_option, '' );
        echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />';
        echo '<div class="wrap"><h1>'.esc_html__( 'Opmaak', 'botsauto-checklist' ).'</h1><form method="post" action="options.php" enctype="multipart/form-data">';
        // Single settings group handles all style options so one call is enough
        settings_fields( 'botsauto_style_group' );
        echo '<h2>'.esc_html__( 'Algemeen', 'botsauto-checklist' ).'</h2><table class="form-table">';
        echo '<tr><th scope="row">'.esc_html__( 'Primaire kleur', 'botsauto-checklist' ).'</th><td><input type="text" class="color-field" name="'.$this->style_option.'[primary]" value="'.esc_attr($opts['primary']).'" /></td></tr>';
        echo '<tr><th scope="row">'.esc_html__( 'Tekstkleur', 'botsauto-checklist' ).'</th><td><input type="text" class="color-field" name="'.$this->style_option.'[text]" value="'.esc_attr($opts['text']).'" /></td></tr>';
        echo '<tr><th scope="row">'.esc_html__( 'Achtergrondkleur', 'botsauto-checklist' ).'</th><td><input type="text" class="color-field" name="'.$this->style_option.'[background]" value="'.esc_attr($opts['background']).'" /></td></tr>';
        echo '<tr><th scope="row">'.esc_html__( 'Lettertype', 'botsauto-checklist' ).'</th><td><select name="'.$this->style_option.'[font]">';
        foreach ( $fonts as $val => $label ) {
            $sel = selected( $opts['font'], $val, false );
            echo '<option value="'.esc_attr($val).'" '.$sel.'>'.esc_html($label).'</option>';
        }
        echo '</select></td></tr>';
        echo '<tr><th scope="row">'.esc_html__( 'Checklist titel', 'botsauto-checklist' ).'</th><td><input type="text" name="'.$this->style_option.'[checklist_title]" value="'.esc_attr($opts['checklist_title']).'" /></td></tr>';
        echo '<tr><th scope="row">'.esc_html__( 'Positie titel', 'botsauto-checklist' ).'</th><td><select name="'.$this->style_option.'[title_position]">'
            .'<option value="above" '.selected($opts['title_position'],'above',false).'>'.esc_html__('Boven','botsauto-checklist').'</option>'
            .'<option value="below" '.selected($opts['title_position'],'below',false).'>'.esc_html__('Onder','botsauto-checklist').'</option>'
            .'<option value="left" '.selected($opts['title_position'],'left',false).'>'.esc_html__('Links van afbeelding','botsauto-checklist').'</option>'
            .'<option value="right" '.selected($opts['title_position'],'right',false).'>'.esc_html__('Rechts van afbeelding','botsauto-checklist').'</option>'
            .'</select></td></tr>';
        echo '<tr><th scope="row">'.esc_html__( 'Afbeelding', 'botsauto-checklist' ).'</th><td><input type="text" id="botsauto-image" name="'.$this->style_option.'[image]" value="'.esc_attr($opts['image']).'" /> <button type="button" class="button" id="botsauto-image-btn">'.esc_html__( 'Selecteer afbeelding', 'botsauto-checklist' ).'</button></td></tr>';
        echo '<tr><th scope="row">'.esc_html__( 'Uitlijning afbeelding', 'botsauto-checklist' ).'</th><td><select name="'.$this->style_option.'[image_align]">'.
             '<option value="left" '.selected($opts['image_align'],'left',false).'>Links</option>'.
             '<option value="center" '.selected($opts['image_align'],'center',false).'>Centraal</option>'.
             '<option value="right" '.selected($opts['image_align'],'right',false).'>Rechts</option>'.
             '</select></td></tr>';
        echo '<tr><th scope="row">'.esc_html__( 'Breedte afbeelding (px)', 'botsauto-checklist' ).'</th><td><input type="number" name="'.$this->style_option.'[image_width]" value="'.esc_attr($opts['image_width']).'" /></td></tr>';
        echo '</table>';
        echo '<h2>'.esc_html__( 'Iconen', 'botsauto-checklist' ).'</h2><table class="form-table">';
        echo '<tr><th scope="row">'.esc_html__( 'Notitie-icoon', 'botsauto-checklist' ).'</th><td><input type="text" name="'.$this->style_option.'[note_icon]" value="'.esc_attr($opts['note_icon']).'" /> <input type="text" class="color-field" name="'.$this->style_option.'[note_icon_color]" value="'.esc_attr($opts['note_icon_color']).'" /> <i class="fa '.esc_attr($opts['note_icon']).'"></i></td></tr>';
        echo '<tr><th scope="row">'.esc_html__( 'Icoon bij notitie aanwezig', 'botsauto-checklist' ).'</th><td><input type="text" name="'.$this->style_option.'[done_icon]" value="'.esc_attr($opts['done_icon']).'" /> <input type="text" class="color-field" name="'.$this->style_option.'[done_icon_color]" value="'.esc_attr($opts['done_icon_color']).'" /> <i class="fa '.esc_attr($opts['done_icon']).'"></i></td></tr>';
        echo '</table>';
        echo '<h2>'.esc_html__( 'Elementen', 'botsauto-checklist' ).'</h2><table class="form-table">';
        $labels = array(
            'container' => 'Container',
            'phase'     => 'Fase',
            'question'  => 'Vraag',
            'item'      => 'Checklist item',
            'button'    => 'Knop',
            'field'     => 'Invulveld',
            'checkbox'  => 'Checkbox',
            'checked'   => 'Aangevinkt item',
            'note'      => 'Notitieveld',
            'completed' => 'Checklist afgerond',
            'title'     => 'Titel'
        );
        $field_labels = array(
            'text-color'       => 'Tekstkleur',
            'background-color' => 'Achtergrondkleur',
            'font-size'        => 'Lettergrootte',
            'font-weight'      => 'Letterdikte',
            'font-style'       => 'Tekststijl',
            'padding'          => 'Padding',
            'border-radius'    => 'Randhoek',
            'border-style'     => 'Randstijl',
            'border-width'     => 'Randdikte',
            'border-color'     => 'Randkleur',
            'width'            => 'Breedte',
            'color'            => 'Kleur',
            'size'             => 'Grootte',
            'text-decoration'  => 'Tekstdecoratie'
        );
        foreach ( $this->default_adv_style() as $key => $vals ) {
            echo '<tr><th colspan="2"><h2>'.esc_html( $labels[$key] ).'</h2></th></tr>';
            foreach ( $vals as $field => $default ) {
                $val = isset( $adv[$key][$field] ) ? $adv[$key][$field] : $default;
                $name = $this->adv_style_option.'['.$key.']['.$field.']';
                $type = strpos( $field, 'color' ) !== false ? 'text' : 'text';
                $class = strpos( $field, 'color' ) !== false ? 'color-field' : '';
                $label = isset($field_labels[$field]) ? $field_labels[$field] : $field;
                echo '<tr><th scope="row">'.esc_html( $label ).'</th><td><input type="'.$type.'" class="'.$class.'" name="'.$name.'" value="'.esc_attr($val).'" /></td></tr>';
            }
        }
        echo '<tr><th scope="row">'.esc_html__( 'Aangepaste CSS', 'botsauto-checklist' ).'</th><td><textarea name="'.$this->custom_css_option.'" rows="5" cols="50">'.esc_textarea($custom).'</textarea></td></tr>';
        echo '</table>';
        submit_button();
        echo '</form>';
        echo '<h2>'.esc_html__( 'Voorbeeld', 'botsauto-checklist' ).'</h2>';
        echo '<button type="button" class="button" id="botsauto-toggle-mobile">'.esc_html__( 'Toon als mobiele gebruiker', 'botsauto-checklist' ).'</button>';
        $static_css = $this->build_css( $opts, $adv, '#botsauto-preview', $custom );
        if ( strpos( $opts['font'], 'Oswald' ) !== false ) {
            $static_css = '@import url("https://fonts.googleapis.com/css2?family=Oswald&display=swap");' . $static_css;
        }
        echo '<style id="botsauto-preview-static">'.$static_css.'</style>';
        echo '<style id="botsauto-preview-style"></style>';
        echo '<style>#botsauto-preview-container{border:1px solid #ddd;padding:10px;margin-top:1em;}#botsauto-preview-container.mobile{max-width:375px;}</style>';
        echo '<div id="botsauto-preview-container"><div id="botsauto-preview"><form><div class="botsauto-header"><div class="botsauto-logo-title '.esc_attr($opts['title_position']).'"><div class="botsauto-title">'.esc_html($opts['checklist_title']).'</div><div class="botsauto-logo">'.($opts['image']?'<img src="'.esc_url($opts['image']).'" />':'').'</div></div><div class="botsauto-fields"><p><label>'.esc_html__( 'Titel', 'botsauto-checklist' ).': <input type="text" value="Demo"></label></p><p><label>'.esc_html__( 'Naam', 'botsauto-checklist' ).': <input type="text" value="Demo"></label></p><p><label>'.esc_html__( 'E-mail', 'botsauto-checklist' ).': <input type="email" value="demo@example.com"></label></p></div></div><div class="botsauto-checklist"><details class="botsauto-phase" open><summary>'.esc_html__('Voorbereiden: Kwalificatie & voorbereiding (KEM – Kwalificatie Expres Methode)','botsauto-checklist').'</summary><p>Kwalificatie, achtergrondinformatie en strategische voorbereiding.</p><ul style="list-style:none"><li><p class="botsauto-question-text">'.esc_html__('Kwalificatie Expres Methode toegepast?','botsauto-checklist').'</p><input type="checkbox" id="preview_cb" class="botsauto-checkbox"> <label for="preview_cb">'.esc_html__('Is de potentiële opdrachtgever gekwalificeerd op basis van de KEM (Kwalificatie Expres Methode)?','botsauto-checklist').'</label> <button type="button" class="botsauto-note-btn"><i class="fa '.esc_attr($opts['note_icon']).'"></i></button><div class="botsauto-note" style="display:none"><textarea class="botsauto-rich"></textarea></div></li></ul></details></div><p><input type="submit" class="button button-primary" value="Submit"></p></form></div></div>';
        echo '<h2>'.esc_html__( 'Import / Export', 'botsauto-checklist' ).'</h2><p><a href="'.admin_url('admin-post.php?action=botsauto_export_style').'" class="button">'.esc_html__( 'Exporteren', 'botsauto-checklist' ).'</a></p>';
        echo '<form method="post" enctype="multipart/form-data"><input type="file" name="adv_import" accept="application/json" />';
        wp_nonce_field('botsauto_adv_import');
        echo '<p><input type="submit" class="button" name="botsauto_import_submit" value="'.esc_attr__( 'Importeren', 'botsauto-checklist' ).'"></p></form>';
        echo '<form method="post" id="botsauto-reset-form" style="margin-top:1em;">';
        wp_nonce_field( 'botsauto_reset_adv' );
        echo '<input type="hidden" name="botsauto_reset_adv" value="1" />';
        submit_button( __( 'Reset naar standaard', 'botsauto-checklist' ), 'delete' );
        echo '</form></div>';
    }

    public function cc_page() {
        $cc = get_option( $this->cc_option, '' );
        echo '<div class="wrap"><h1>E-mail BCC</h1><form method="post" action="options.php">';
        settings_fields( 'botsauto_cc_group' );
        echo '<table class="form-table"><tr><th scope="row">E-mail adres</th><td><input type="email" name="'.$this->cc_option.'" value="'.esc_attr($cc).'" /></td></tr></table>';
        submit_button();
        echo '</form></div>';
    }

    public function export_style() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die();
        }
        $data = array(
            'style' => get_option( $this->style_option, array() ),
            'adv'   => get_option( $this->adv_style_option, array() ),
        );
        header( 'Content-Type: application/json' );
        header( 'Content-Disposition: attachment; filename=botsauto-style.json' );
        echo wp_json_encode( $data );
        exit;
    }

    public function main_settings_page() {
        echo '<div class="wrap"><h1>BOTSAUTO instellingen</h1><p>Gebruik het submenu <strong>Opmaak</strong> voor alle stijlopties en <strong>E-mail BCC</strong> om een kopieadres in te stellen.</p></div>';
    }

    public function output_frontend_style() {
        $o = $this->get_style_options();
        $adv = $this->get_adv_style_options();
        $font_link = '';
        if ( strpos( $o['font'], 'Oswald' ) !== false ) {
            $font_link = '<link href="https://fonts.googleapis.com/css2?family=Oswald&display=swap" rel="stylesheet">';
        }
        echo $font_link;
        echo '<style class="botsauto-style">'
            . '.botsauto-checklist{background:' . esc_attr($o['background']) . ';color:' . esc_attr($o['text']) . ';font-family:' . esc_attr($o['font']) . ';}'
            . '.botsauto-header{margin-bottom:1em;font-family:' . esc_attr($o['font']) . ';}'
            . '.botsauto-logo-title{display:flex;justify-content:center;align-items:center;margin-bottom:1em;}'
            . '.botsauto-logo-title.above,.botsauto-logo-title.below{flex-direction:column;}'
            . '.botsauto-logo-title.left,.botsauto-logo-title.right{flex-direction:row;}'
            . '.botsauto-logo-title.left .botsauto-title{margin-right:1em;}'
            . '.botsauto-logo-title.right .botsauto-logo{margin-right:1em;}'
            . '.botsauto-header .botsauto-fields{max-width:500px;margin:0 auto;}'
            . '.botsauto-header .botsauto-fields p{margin:0;}'
            . '.botsauto-header label{color:' . esc_attr($o['primary']) . ';display:block;margin-bottom:.5em;}'
            . '.botsauto-logo{text-align:' . esc_attr($o['image_align']) . ';margin-bottom:0;}'
            . '.botsauto-logo img{max-width:' . intval($o['image_width']) . 'px;height:auto;}'
            . '.botsauto-title{color:' . esc_attr($adv['title']['text-color']) . ';background:' . esc_attr($adv['title']['background-color']) . ';font-size:' . esc_attr($adv['title']['font-size']) . ';font-weight:' . esc_attr($adv['title']['font-weight']) . ';font-style:' . esc_attr($adv['title']['font-style']) . ';padding:' . esc_attr($adv['title']['padding']) . ';text-align:center;}'
            . '.botsauto-header input[type=text],.botsauto-header input[type=email]{width:' . esc_attr($adv['field']['width']) . ';box-sizing:border-box;border-radius:' . esc_attr($adv['field']['border-radius']) . ';border-style:' . esc_attr($adv['field']['border-style']) . ';border-width:' . esc_attr($adv['field']['border-width']) . ';border-color:' . esc_attr($adv['field']['border-color']) . ';background:' . esc_attr($adv['field']['background-color']) . ';color:' . esc_attr($adv['field']['text-color']) . ';}'
            . '.botsauto-checklist label{color:' . esc_attr($o['primary']) . ';display:inline-block;vertical-align:middle;}'
            . '.botsauto-checklist strong{color:' . esc_attr($o['primary']) . ';}'
            . '.botsauto-phase>summary{font-weight:bold;cursor:pointer;margin:0;color:' . esc_attr($o['primary']) . ';list-style:none;position:relative;padding-left:1.2em;padding-top:10px;padding-bottom:10px;}'
            . '.botsauto-phase>summary::before{content:"▶";position:absolute;left:0;}'
            . '.botsauto-phase[open]>summary::before{content:"▼";}'
            . '.botsauto-checkbox{accent-color:' . esc_attr($adv['checkbox']['color']) . ';background:' . esc_attr($adv['checkbox']['background-color']) . ';border-color:' . esc_attr($adv['checkbox']['border-color']) . ';border-style:solid;border-width:1px;display:inline-block!important;width:' . esc_attr($adv['checkbox']['size']) . '!important;height:' . esc_attr($adv['checkbox']['size']) . '!important;appearance:auto!important;visibility:visible!important;}'
            . '.botsauto-checklist .button-primary{background:' . esc_attr($adv['button']['background-color']) . ';color:' . esc_attr($adv['button']['text-color']) . ';padding:' . esc_attr($adv['button']['padding']) . ';border-radius:' . esc_attr($adv['button']['border-radius']) . ';border-color:' . esc_attr($adv['button']['border-color']) . ';}'
            . '.botsauto-completed{margin-top:2em;}'
            . '.botsauto-completed label{color:' . esc_attr($adv['completed']['text-color']) . '!important;font-size:' . esc_attr($adv['completed']['font-size']) . ';font-family:' . esc_attr($o['font']) . ';}'
            . '</style>';
    }

    public function admin_generate_pdf() {
        if ( ! current_user_can( 'edit_posts' ) ) wp_die();
        $id = intval( $_GET['post_id'] );
        check_admin_referer( 'botsauto_gen_' . $id );
        $title = get_the_title( $id );
        $name  = get_post_meta( $id, 'name', true );
        $email = get_post_meta( $id, 'email', true );
        $answers = get_post_meta( $id, 'answers', true );
        $snapshot = get_post_meta( $id, 'items_snapshot', true );
        $notes    = get_post_meta( $id, 'notes', true );
        $style = $this->get_style_options();
        $pdf = $this->generate_pdf( $title, $name, $answers, $snapshot, $notes, $style['image'] );
        $uploads = wp_upload_dir();
        $url = str_replace( $uploads['basedir'], $uploads['baseurl'], $pdf );
        $history = get_post_meta( $id, 'pdf_history', true );
        if ( ! is_array( $history ) ) { $history = array(); }
        $history[] = array( 'file' => $url, 'time' => time() );
        update_post_meta( $id, 'pdf_history', $history );
        $body = sprintf( __( 'Hierbij de PDF van uw checklist: %s', 'botsauto-checklist' ), get_post_meta( $id, 'edit_url', true ) );
        $cc = get_option( $this->cc_option, '' );
        $headers = array();
        if ( $cc ) { $headers[] = 'Bcc: '.$cc; }
        $this->send_email( $email, __( 'Checklist PDF', 'botsauto-checklist' ), $body, array( $pdf ), $headers );
        wp_redirect( get_edit_post_link( $id, 'url' ) );
        exit;
    }

    public function admin_resend_pdf() {
        if ( ! current_user_can( 'edit_posts' ) ) wp_die();
        $id = intval( $_GET['post_id'] );
        check_admin_referer( 'botsauto_resend_' . $id );
        $file = esc_url_raw( $_GET['file'] );
        $email = get_post_meta( $id, 'email', true );
        $body = sprintf( __( 'Hierbij de PDF van uw checklist: %s', 'botsauto-checklist' ), get_post_meta( $id, 'edit_url', true ) );
        $cc = get_option( $this->cc_option, '' );
        $headers = array();
        if ( $cc ) { $headers[] = 'Bcc: '.$cc; }
        $this->send_email( $email, __( 'Checklist PDF', 'botsauto-checklist' ), $body, array( $file ), $headers );
        wp_redirect( get_edit_post_link( $id, 'url' ) );
        exit;
    }
}

register_activation_hook( __FILE__, array( 'BOTSAUTO_Checklist', 'install' ) );
register_uninstall_hook( __FILE__, array( 'BOTSAUTO_Checklist', 'uninstall' ) );

new BOTSAUTO_Checklist();

