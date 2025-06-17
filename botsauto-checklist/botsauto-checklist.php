<?php
/**
 * Plugin Name: BOTSAUTO Checklist
 * Plugin URI: https://example.com
 * Description: Frontend checklist with admin overview, PDF email confirmation, and edit link.
 * Version: 1.0.1
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

    public function __construct() {
        add_action( 'init', array( $this, 'register_post_type' ) );
        add_action( 'admin_menu', array( $this, 'admin_menu' ) );
        add_shortcode( 'botsauto_checklist', array( $this, 'render_form' ) );
        add_action( 'admin_post_nopriv_botsauto_save', array( $this, 'handle_submit' ) );
        add_action( 'admin_post_botsauto_save', array( $this, 'handle_submit' ) );
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
        $content = get_option( $this->option_name, $this->default_checklist() );
        echo '<div class="wrap"><h1>Checklist beheer</h1>';
        echo '<form method="post">';
        echo '<textarea name="checklist_content" style="width:100%;height:300px;">' . esc_textarea( $content ) . '</textarea>';
        submit_button();
        echo '</form></div>';
    }

    private function default_checklist() {
        return "Voorbereiden: Kwalificatie & voorbereiding\nKwalificatie Expres Methode toegepast?\nStakeholder Matrix ingevuld?\nAchtergrondinformatie verzameld?\nHypotheses opgesteld?\nStrategie voor contact bepaald?\nBOTSAUTO Navigatie ingezet?\nUitnodiging tot strategische samenwerking verzonden?\nVerdiepen: Diepgaande analyse van de kernvraag\nEssentievragen gesteld?\nChecklist Socratisch Gesprek toegepast?\nBlinde vlekken geïdentificeerd?\nImpact op organisatie duidelijk?\nObstakels en risico’s geanalyseerd?\nInzicht in interne besluitvorming en budget?\nVerwezenlijken: Oplossing & commitment\n3 W’s volledig in kaart gebracht?\nKernvraag geformuleerd?\nVisie op de kernvraag gedeeld?\nStelling, Steun & Succes uitgewerkt?\nVVV-1 ingevuld?\nVVV-2 afgedwongen?\nKAM ingezet?\nGespreksverslag BOTSAUTO debrief gebruikt?\nActieplan opgesteld?\nVerzilveren: Borging & nazorg\nOpdrachtbevestiging of Vennootakte opgesteld?\nImplementatievoorwaarden vastgelegd?\nMonitoring- en evaluatieplan opgesteld?\nKPI’s gedefinieerd?\n4 R’s correct toegepast?\nKIM ingezet?\nExtra controle & optimalisatie\nInterne debriefing gehouden?\nKlantgegevens vastgelegd in CRM?\nFeedbackmoment met de klant gepland?\nAlle interne stakeholders op de hoogte?\nGrondige evaluatie van het proces gedaan?\nAlgemene verankeringen en documentatie\nAccount Plan Canvas ingevuld?\nPijlers en BOTSAUTO navigatie toegepast?";
    }

    private function checklist_items() {
        $content = get_option( $this->option_name, $this->default_checklist() );
        $lines = array_filter( array_map( 'trim', explode( "\n", $content ) ) );
        return $lines;
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

        $items = $this->checklist_items();
        ob_start();
        echo '<form method="post" action="' . esc_url( admin_url('admin-post.php') ) . '">';
        echo '<input type="hidden" name="action" value="botsauto_save">';
        if ( $post_id ) {
            echo '<input type="hidden" name="post_id" value="' . intval($post_id) . '" />';
        }
        echo '<p><label>Naam: <input type="text" name="name" value="' . esc_attr($name) . '" required></label></p>';
        echo '<p><label>Email: <input type="email" name="email" value="' . esc_attr($email) . '" required></label></p>';
        echo '<ul style="list-style:none">';
        foreach ( $items as $index => $item ) {
            $checked = isset( $values[$index] ) ? 'checked' : '';
            echo '<li><label><input type="checkbox" name="answers['.$index.']" '.$checked.'> '.esc_html($item).'</label></li>';
        }
        echo '</ul>';
        $c = $completed ? 'checked' : '';
        echo '<p><label><input type="checkbox" name="completed" value="1" '.$c.'> Checklist afgerond</label></p>';
        $label = $post_id ? 'Opslaan' : 'Checklist verzenden';
        echo '<p><input type="submit" class="button button-primary" value="'.esc_attr($label).'"></p>';
        echo '</form>';
        return ob_get_clean();
    }

    public function handle_submit() {
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $name = sanitize_text_field( $_POST['name'] );
        $email = sanitize_email( $_POST['email'] );
        $answers = isset($_POST['answers']) ? array_map('sanitize_text_field', $_POST['answers']) : array();
        $completed = isset($_POST['completed']) ? '1' : '';
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
        $pdf = $this->generate_pdf( $name, $answers );
        $headers = array('Content-Type: text/html; charset=UTF-8');
        $attachments = array( $pdf );
        $body = 'Bedankt voor het invullen van de checklist. Bewaar deze link om later verder te gaan: '.$edit_url;
        wp_mail( $email, 'Checklist bevestiging', $body, $headers, $attachments );
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

    private function generate_pdf( $name, $answers ) {
        if ( ! defined( 'FPDF_FONTPATH' ) ) {
            define( 'FPDF_FONTPATH', plugin_dir_path( __FILE__ ) . 'lib/font/' );
        }
        require_once plugin_dir_path(__FILE__).'lib/fpdf.php';
        $pdf = new FPDF();
        $pdf->AddPage();
        $pdf->SetFont('Arial','',12);
        $pdf->Cell(0,10,'BOTSAUTO Checklist',0,1);
        $pdf->Cell(0,10,'Naam: '.$name,0,1);
        foreach ( $this->checklist_items() as $i => $question ) {
            $status = isset($answers[$i]) ? 'Ja' : 'Nee';
            $pdf->Cell(0,8,($i+1).'. '.$question.' - '.$status,0,1);
        }
        $tmp = wp_tempnam('botsauto');
        $pdf->Output($tmp,'F');
        return $tmp;
    }
}

new BOTSAUTO_Checklist();

