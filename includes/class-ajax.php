<?php
/**
 * Gestionnaire AJAX pour OSDS3D Customizer Pro
 *
 * Gère :
 * - sauvegarde d’un design
 * - chargement d’un design existant
 *
 * @package OSDS3D_Customizer_Pro
 */

if (!defined('ABSPATH')) {
    exit;
}

class OSDS3D_Ajax
{
    /**
     * Instance unique
     *
     * @var OSDS3D_Ajax|null
     */
    private static $instance = null;

    /**
     * Initialiser l'instance et enregistrer les hooks.
     *
     * @return OSDS3D_Ajax
     */
    public static function init()
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Constructeur privé.
     *
     * @return void
     */
    private function __construct()
    {
        add_action('wp_ajax_osds3d_save_design', array($this, 'save_design'));
        add_action('wp_ajax_nopriv_osds3d_save_design', array($this, 'save_design'));

        add_action('wp_ajax_osds3d_load_design', array($this, 'load_design'));
        add_action('wp_ajax_nopriv_osds3d_load_design', array($this, 'load_design'));
    }

    /**
     * Retourne le contexte courant de propriete du design.
     *
     * @return array
     */
    private function get_current_design_context()
    {
        $user_id = get_current_user_id();

        if ($user_id > 0) {
            return array(
                'type' => 'user',
                'id'   => (string) $user_id,
            );
        }

        if (function_exists('WC')) {
            $wc = WC();

            if ($wc && isset($wc->session) && $wc->session && method_exists($wc->session, 'get_customer_id')) {
                $customer_id = (string) $wc->session->get_customer_id();

                if ($customer_id !== '') {
                    return array(
                        'type' => 'guest',
                        'id'   => $customer_id,
                    );
                }
            }
        }

        return array(
            'type' => 'none',
            'id'   => '',
        );
    }

    /**
     * Decode les notes de validation et conserve un eventuel contenu legacy.
     *
     * @param mixed $raw_notes
     * @return array
     */
    private function parse_validation_notes($raw_notes)
    {
        if (!is_string($raw_notes) || $raw_notes === '') {
            return array();
        }

        $decoded = json_decode($raw_notes, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        return array(
            '_legacy_note' => sanitize_textarea_field($raw_notes),
        );
    }

    /**
     * Construit un token signe representant le contexte du design.
     *
     * @param int    $product_id
     * @param string $context_type
     * @param string $context_id
     * @return string
     */
    private function build_design_token($product_id, $context_type, $context_id)
    {
        $payload = implode('|', array(
            intval($product_id),
            (string) $context_type,
            (string) $context_id,
        ));

        return hash_hmac('sha256', $payload, wp_salt('auth'));
    }

    /**
     * Construit les metadonnees de securite a stocker sur le design.
     *
     * @param int   $product_id
     * @param array $context
     * @return array
     */
    private function build_design_security_meta($product_id, $context)
    {
        $context_type = isset($context['type']) ? (string) $context['type'] : 'none';
        $context_id   = isset($context['id']) ? (string) $context['id'] : '';

        return array(
            'owner_type'   => $context_type,
            'owner_id'     => $context_id,
            'product_id'   => intval($product_id),
            'security_ver' => 1,
            'design_token' => $this->build_design_token($product_id, $context_type, $context_id),
        );
    }

    /**
     * Verifie si le design appartient bien au contexte courant.
     *
     * @param object $design
     * @return bool
     */
    private function current_context_can_access_design($design)
    {
        if (!$design || !is_object($design)) {
            return false;
        }

        $current_context = $this->get_current_design_context();
        $stored_meta     = $this->parse_validation_notes(isset($design->validation_notes) ? $design->validation_notes : '');

        $stored_type  = isset($stored_meta['owner_type']) ? (string) $stored_meta['owner_type'] : '';
        $stored_id    = isset($stored_meta['owner_id']) ? (string) $stored_meta['owner_id'] : '';
        $stored_token = isset($stored_meta['design_token']) ? (string) $stored_meta['design_token'] : '';
        $expected     = $this->build_design_token(
            isset($design->product_id) ? (int) $design->product_id : 0,
            $current_context['type'],
            $current_context['id']
        );

        if (
            $stored_type !== '' &&
            $stored_id !== '' &&
            hash_equals($stored_type, $current_context['type']) &&
            hash_equals($stored_id, $current_context['id']) &&
            $stored_token !== '' &&
            hash_equals($stored_token, $expected)
        ) {
            return true;
        }

        $current_user_id = get_current_user_id();
        if ($current_user_id > 0 && isset($design->user_id) && (int) $design->user_id === $current_user_id) {
            return true;
        }

        return false;
    }

    /**
     * Sauvegarder un design.
     *
     * @return void
     */
    public function save_design()
    {
        check_ajax_referer('osds3d_customizer', 'nonce');

        $product_id         = isset($_POST['product_id']) ? intval(wp_unslash($_POST['product_id'])) : 0;
        $design_data        = isset($_POST['design_data']) ? wp_unslash($_POST['design_data']) : '';
        $preview_base64     = isset($_POST['preview_image']) ? trim(wp_unslash($_POST['preview_image'])) : '';
        $preview_3d_base64  = isset($_POST['preview_3d_image']) ? trim(wp_unslash($_POST['preview_3d_image'])) : '';
        $user_id            = get_current_user_id();
        $design_context     = $this->get_current_design_context();

        if ($product_id <= 0) {
            wp_send_json_error(array(
                'message' => __('Produit invalide.', 'osds3d-customizer-pro'),
            ));
        }

        if (empty($design_data)) {
            wp_send_json_error(array(
                'message' => __('Aucune donnée de design reçue.', 'osds3d-customizer-pro'),
            ));
        }

        $decoded_design = json_decode($design_data, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded_design)) {
            wp_send_json_error(array(
                'message' => __('Les données du design sont invalides.', 'osds3d-customizer-pro'),
            ));
        }

        global $wpdb;
        $table = $wpdb->prefix . 'osds3d_designs';

        $insert_data = array(
            'user_id'     => $user_id > 0 ? $user_id : null,
            'product_id'  => $product_id,
            'design_type' => '2d',
            'design_data' => $design_data,
            'status'      => 'pending',
            'validation_notes' => wp_json_encode($this->build_design_security_meta($product_id, $design_context)),
            'created_at'  => current_time('mysql'),
            'updated_at'  => current_time('mysql'),
        );

        $insert_format = array(
            $user_id > 0 ? '%d' : '%s',
            '%d',
            '%s',
            '%s',
            '%s',
            '%s',
            '%s',
            '%s',
        );

        $inserted = $wpdb->insert($table, $insert_data, $insert_format);

        if ($inserted === false) {
            wp_send_json_error(array(
                'message' => __('Impossible d’enregistrer le design en base de données.', 'osds3d-customizer-pro'),
            ));
        }

        $design_id       = (int) $wpdb->insert_id;
        $preview_url     = '';
        $preview_3d_url  = '';
        $preview_ok      = false;
        $preview_3d_ok   = false;

        if ($design_id > 0 && !empty($preview_base64)) {
            $preview_url = $this->save_preview_image($design_id, $preview_base64, '2d');

            if (!empty($preview_url)) {
                $preview_ok = true;
            }
        }

        if ($design_id > 0 && !empty($preview_3d_base64)) {
            $preview_3d_url = $this->save_preview_image($design_id, $preview_3d_base64, '3d');

            if (!empty($preview_3d_url)) {
                $preview_3d_ok = true;
            }
        }

        if ($preview_ok || $preview_3d_ok) {
            $update_data   = array(
                'updated_at' => current_time('mysql'),
            );
            $update_format = array('%s');

            if ($preview_ok) {
                $update_data['preview_url'] = $preview_url;
                $update_format[] = '%s';
            }

            if ($preview_3d_ok) {
                $update_data['preview_3d_url'] = $preview_3d_url;
                $update_format[] = '%s';
            }

            $wpdb->update(
                $table,
                $update_data,
                array('id' => $design_id),
                $update_format,
                array('%d')
            );
        }

        wp_send_json_success(array(
            'design_id'        => $design_id,
            'product_id'       => $product_id,
            'preview_url'      => $preview_url,
            'preview_saved'    => $preview_ok,
            'preview_3d_url'   => $preview_3d_url,
            'preview_3d_saved' => $preview_3d_ok,
            'design_token'     => $this->build_design_token($product_id, $design_context['type'], $design_context['id']),
        ));
    }

    /**
     * Charger un design existant.
     *
     * @return void
     */
    public function load_design()
    {
        check_ajax_referer('osds3d_customizer', 'nonce');

        $design_id = isset($_POST['design_id']) ? intval(wp_unslash($_POST['design_id'])) : 0;

        if ($design_id <= 0) {
            wp_send_json_error(array(
                'message' => __('ID de design invalide.', 'osds3d-customizer-pro'),
            ));
        }

        if (!class_exists('OSDS3D_Database') || !method_exists('OSDS3D_Database', 'get_design')) {
            wp_send_json_error(array(
                'message' => __('Gestionnaire de base de données indisponible.', 'osds3d-customizer-pro'),
            ));
        }

        $design = OSDS3D_Database::get_design($design_id);

        if (!$design) {
            wp_send_json_error(array(
                'message' => __('Design introuvable.', 'osds3d-customizer-pro'),
            ));
        }

        if (!$this->current_context_can_access_design($design)) {
            wp_send_json_error(array(
                'message' => __('Vous n’etes pas autorise a charger ce design.', 'osds3d-customizer-pro'),
            ), 403);
        }

        wp_send_json_success(array(
            'id'              => isset($design->id) ? (int) $design->id : 0,
            'user_id'         => isset($design->user_id) ? (int) $design->user_id : 0,
            'product_id'      => isset($design->product_id) ? (int) $design->product_id : 0,
            'design_type'     => isset($design->design_type) ? $design->design_type : '2d',
            'design_name'     => isset($design->design_name) ? $design->design_name : '',
            'design_data'     => isset($design->design_data) ? $design->design_data : '',
            'design_payload'  => isset($design->design_payload) ? $design->design_payload : null,
            'preview_url'     => isset($design->preview_url) ? $design->preview_url : '',
            'preview_3d_url'  => isset($design->preview_3d_url) ? $design->preview_3d_url : '',
            'order_id'        => isset($design->order_id) ? (int) $design->order_id : 0,
            'cart_item_key'   => isset($design->cart_item_key) ? $design->cart_item_key : '',
            'status'          => isset($design->status) ? $design->status : '',
            'validation_notes'=> isset($design->validation_notes) ? $design->validation_notes : '',
            'created_at'      => isset($design->created_at) ? $design->created_at : '',
            'updated_at'      => isset($design->updated_at) ? $design->updated_at : '',
        ));
    }

    /**
     * Sauvegarder l’image de prévisualisation dans uploads/osds3d-designs.
     *
     * @param int    $design_id
     * @param string $preview_base64
     * @param string $suffix
     * @return string URL du fichier ou chaîne vide
     */
    private function save_preview_image($design_id, $preview_base64, $suffix = '2d')
    {
        if (!preg_match('/^data:image\/(png|jpeg|jpg);base64,/', $preview_base64, $matches)) {
            return '';
        }

        $extension = strtolower($matches[1]);
        if ($extension === 'jpeg' || $extension === 'jpg') {
            $extension = 'jpg';
        } else {
            $extension = 'png';
        }

        $comma_pos = strpos($preview_base64, ',');
        if ($comma_pos === false) {
            return '';
        }

        $image_data = substr($preview_base64, $comma_pos + 1);
        $decoded    = base64_decode($image_data, true);

        if ($decoded === false || empty($decoded)) {
            return '';
        }

        $upload_dir = wp_upload_dir();

        if (!empty($upload_dir['error'])) {
            return '';
        }

        $designs_dir = trailingslashit($upload_dir['basedir']) . 'osds3d-designs';

        if (!file_exists($designs_dir)) {
            wp_mkdir_p($designs_dir);
        }

        if (!file_exists($designs_dir) || !is_dir($designs_dir)) {
            return '';
        }

        $safe_suffix = sanitize_key($suffix);
        $filename    = 'design-' . intval($design_id) . '-' . $safe_suffix . '.' . $extension;
        $file_path   = trailingslashit($designs_dir) . $filename;

        $written = file_put_contents($file_path, $decoded);
        if ($written === false) {
            return '';
        }

        return trailingslashit($upload_dir['baseurl']) . 'osds3d-designs/' . $filename;
    }
}
