<?php
/**
 * Intégration WooCommerce pour OSDS3D Customizer Pro
 *
 * Gère :
 * - activation du customizer sur les produits
 * - bouton "Personnaliser"
 * - ajout du design au panier
 * - affichage du design dans le panier
 * - sauvegarde du design dans la commande
 * - affichage admin simple
 * - notification si commande avec design
 * - liaison produit -> modèle 3D de la bibliothèque
 *
 * @package OSDS3D_Customizer_Pro
 */

if (!defined('ABSPATH')) {
    exit;
}

class OSDS3D_WooCommerce
{
    /**
     * Types de produits personnalisables supportes.
     *
     * @var array
     */
    private static $product_types = array(
        'simple'   => 'Produit simple',
        'upload'   => 'Upload de fichier',
        'print_2d' => 'Impression 2D',
        'textile'  => 'Textile',
        'objet_3d' => 'Objet 3D',
        'print_3d' => 'Impression 3D',
    );

    /**
     * Modes de personnalisation supportes.
     *
     * @var array
     */
    private static $personalization_modes = array(
        'none'          => 'Aucune',
        'form'          => 'Formulaire simple',
        'upload'        => 'Upload de fichier',
        'customizer_2d' => 'Customizer 2D',
        'customizer_3d' => 'Customizer 3D',
        'print_3d_form' => 'Formulaire impression 3D',
    );

    /**
     * Familles metier supportees.
     *
     * @var array
     */
    private static $business_families = array(
        ''              => 'Aucune',
        'papeterie'     => 'Papeterie',
        'stickers'      => 'Stickers',
        'textile'       => 'Textile',
        'sublimation'   => 'Sublimation',
        'impression_3d' => 'Impression 3D',
        'epoxy'         => 'Epoxy',
        'laser'         => 'Laser',
    );

    /**
     * Instance unique
     *
     * @var OSDS3D_WooCommerce|null
     */
    private static $instance = null;

    /**
     * Initialiser l'intégration WooCommerce.
     *
     * @return OSDS3D_WooCommerce
     */
    public static function init()
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Retourne les types de produits supportes.
     *
     * @return array
     */
    public static function get_supported_product_types()
    {
        return self::$product_types;
    }

    /**
     * Retourne les modes de personnalisation supportes.
     *
     * @return array
     */
    public static function get_supported_personalization_modes()
    {
        return self::$personalization_modes;
    }

    /**
     * Retourne les familles metier supportees.
     *
     * @return array
     */
    public static function get_supported_business_families()
    {
        return self::$business_families;
    }

    /**
     * Normaliser la famille metier.
     *
     * @param string $business_family
     * @return string
     */
    private static function normalize_business_family($business_family)
    {
        $business_family = sanitize_key((string) $business_family);

        if (array_key_exists($business_family, self::$business_families)) {
            return $business_family;
        }

        return '';
    }

    /**
     * Normaliser le type produit.
     *
     * @param string $product_type
     * @return string
     */
    private static function normalize_product_type($product_type)
    {
        $product_type = sanitize_key((string) $product_type);

        if (isset(self::$product_types[$product_type])) {
            return $product_type;
        }

        return 'simple';
    }

    /**
     * Normaliser le mode de personnalisation.
     *
     * @param string $mode
     * @return string
     */
    private static function normalize_personalization_mode($mode)
    {
        $mode = sanitize_key((string) $mode);

        if ($mode === '2d') {
            $mode = 'customizer_2d';
        } elseif ($mode === '3d') {
            $mode = 'customizer_3d';
        }

        if (isset(self::$personalization_modes[$mode])) {
            return $mode;
        }

        return 'none';
    }

    /**
     * Retourner le mode legacy du plugin a partir du nouveau mode.
     *
     * @param string $mode
     * @return string
     */
    private static function get_legacy_mode_from_personalization_mode($mode)
    {
        $mode = self::normalize_personalization_mode($mode);

        if ($mode === 'customizer_3d') {
            return '3d';
        }

        if ($mode === 'customizer_2d') {
            return '2d';
        }

        return '2d';
    }

    /**
     * Retourner le type produit par defaut a partir d'un mode.
     *
     * @param string $mode
     * @return string
     */
    private static function infer_product_type_from_mode($mode)
    {
        $mode = self::normalize_personalization_mode($mode);

        if ($mode === 'customizer_3d') {
            return 'objet_3d';
        }

        if ($mode === 'customizer_2d') {
            return 'print_2d';
        }

        if ($mode === 'upload') {
            return 'upload';
        }

        if ($mode === 'print_3d_form') {
            return 'print_3d';
        }

        return 'simple';
    }

    /**
     * Construire la configuration structuree d'un produit.
     *
     * @param int        $product_id
     * @param array|null $request_data
     * @return array
     */
    private function build_product_personalization_config($product_id, $request_data = null)
    {
        $product_id = intval($product_id);
        $request_data = is_array($request_data) ? $request_data : null;

        $legacy_enabled = get_post_meta($product_id, '_osds3d_customizer_enabled', true);
        $legacy_mode    = get_post_meta($product_id, '_osds3d_customizer_mode', true);
        $stored_config  = get_post_meta($product_id, '_osds3d_product_config', true);
        $stored_config  = is_array($stored_config) ? $stored_config : array();

        $enabled = $request_data !== null
            ? (isset($request_data['osds3d_customizer_enabled']) ? '1' : '0')
            : (($legacy_enabled === '1' || !empty($stored_config['enabled'])) ? '1' : '0');

        $raw_type = $request_data !== null
            ? (isset($request_data['osds3d_product_type']) ? wp_unslash($request_data['osds3d_product_type']) : (isset($stored_config['product_type']) ? $stored_config['product_type'] : ''))
            : (isset($stored_config['product_type']) ? $stored_config['product_type'] : '');

        $raw_business_family = $request_data !== null
            ? (isset($request_data['osds3d_business_family']) ? wp_unslash($request_data['osds3d_business_family']) : (isset($stored_config['business_family']) ? $stored_config['business_family'] : ''))
            : (isset($stored_config['business_family']) ? $stored_config['business_family'] : get_post_meta($product_id, '_osds3d_business_family', true));

        $raw_mode = $request_data !== null
            ? (isset($request_data['osds3d_personalization_mode']) ? wp_unslash($request_data['osds3d_personalization_mode']) : (isset($request_data['osds3d_customizer_mode']) ? wp_unslash($request_data['osds3d_customizer_mode']) : ''))
            : (isset($stored_config['personalization_mode']) ? $stored_config['personalization_mode'] : $legacy_mode);

        $personalization_mode = self::normalize_personalization_mode($raw_mode);
        $product_type = self::normalize_product_type($raw_type);
        $business_family = self::normalize_business_family($raw_business_family);

        if ($raw_type === '' && $personalization_mode !== 'none') {
            $product_type = self::infer_product_type_from_mode($personalization_mode);
        }

        $model_id = $request_data !== null
            ? (isset($request_data['osds3d_model_3d_id']) ? intval(wp_unslash($request_data['osds3d_model_3d_id'])) : intval(get_post_meta($product_id, '_osds3d_model_3d_id', true)))
            : intval(get_post_meta($product_id, '_osds3d_model_3d_id', true));

        $config = array(
            'enabled' => ($enabled === '1'),
            'business_family' => $business_family,
            'product_type' => $product_type,
            'personalization_mode' => $personalization_mode,
            'legacy_mode' => self::get_legacy_mode_from_personalization_mode($personalization_mode),
            'version' => 1,
            'resources' => array(
                'model_3d_id' => $model_id,
            ),
            'options' => array(
                'background_url' => $request_data !== null
                    ? (isset($request_data['osds3d_model_bg']) ? esc_url_raw(wp_unslash($request_data['osds3d_model_bg'])) : '')
                    : (string) get_post_meta($product_id, '_osds3d_model_bg', true),
                'printable_width' => $request_data !== null && isset($request_data['osds3d_model_width'])
                    ? floatval(wp_unslash($request_data['osds3d_model_width']))
                    : floatval(get_post_meta($product_id, '_osds3d_model_width', true)),
                'printable_height' => $request_data !== null && isset($request_data['osds3d_model_height'])
                    ? floatval(wp_unslash($request_data['osds3d_model_height']))
                    : floatval(get_post_meta($product_id, '_osds3d_model_height', true)),
                'bleed' => $request_data !== null && isset($request_data['osds3d_model_bleed'])
                    ? floatval(wp_unslash($request_data['osds3d_model_bleed']))
                    : floatval(get_post_meta($product_id, '_osds3d_model_bleed', true)),
                'safe' => $request_data !== null && isset($request_data['osds3d_model_safe'])
                    ? floatval(wp_unslash($request_data['osds3d_model_safe']))
                    : floatval(get_post_meta($product_id, '_osds3d_model_safe', true)),
                'print_3d_allow_upload' => $request_data !== null
                    ? !empty($request_data['osds3d_print_3d_allow_upload'])
                    : (bool) get_post_meta($product_id, '_osds3d_print_3d_allow_upload', true),
                'print_3d_materials' => $request_data !== null
                    ? self::parse_print_3d_material_catalog(isset($request_data['osds3d_print_3d_materials']) ? (string) wp_unslash($request_data['osds3d_print_3d_materials']) : '')
                    : self::parse_print_3d_material_catalog((string) get_post_meta($product_id, '_osds3d_print_3d_materials', true)),
                'print_3d_filament_choices' => $request_data !== null
                    ? self::parse_choice_list(isset($request_data['osds3d_print_3d_filaments']) ? (string) wp_unslash($request_data['osds3d_print_3d_filaments']) : '')
                    : self::parse_choice_list((string) get_post_meta($product_id, '_osds3d_print_3d_filaments', true)),
                'print_3d_color_choices' => $request_data !== null
                    ? self::parse_choice_list(isset($request_data['osds3d_print_3d_colors']) ? (string) wp_unslash($request_data['osds3d_print_3d_colors']) : '')
                    : self::parse_choice_list((string) get_post_meta($product_id, '_osds3d_print_3d_colors', true)),
                'print_3d_defaults' => array(
                    'supports' => $request_data !== null
                        ? sanitize_text_field(isset($request_data['osds3d_print_3d_default_supports']) ? wp_unslash($request_data['osds3d_print_3d_default_supports']) : '')
                        : sanitize_text_field((string) get_post_meta($product_id, '_osds3d_print_3d_default_supports', true)),
                    'infill' => $request_data !== null
                        ? intval(isset($request_data['osds3d_print_3d_default_infill']) ? wp_unslash($request_data['osds3d_print_3d_default_infill']) : '')
                        : intval(get_post_meta($product_id, '_osds3d_print_3d_default_infill', true)),
                    'layer_height' => $request_data !== null
                        ? floatval(isset($request_data['osds3d_print_3d_default_layer_height']) ? wp_unslash($request_data['osds3d_print_3d_default_layer_height']) : '')
                        : floatval(get_post_meta($product_id, '_osds3d_print_3d_default_layer_height', true)),
                    'atelier_notes' => $request_data !== null
                        ? sanitize_textarea_field(isset($request_data['osds3d_print_3d_atelier_notes']) ? wp_unslash($request_data['osds3d_print_3d_atelier_notes']) : '')
                        : sanitize_textarea_field((string) get_post_meta($product_id, '_osds3d_print_3d_atelier_notes', true)),
                ),
            ),
        );

        return $config;
    }

    /**
     * Recuperer la configuration structuree d'un produit.
     *
     * @param int $product_id
     * @return array
     */
    public static function get_product_personalization_config($product_id)
    {
        $product_id = intval($product_id);
        $stored = get_post_meta($product_id, '_osds3d_product_config', true);

        if (is_array($stored) && !empty($stored)) {
            $stored['enabled'] = !empty($stored['enabled']);
            $stored['business_family'] = self::normalize_business_family(isset($stored['business_family']) ? $stored['business_family'] : get_post_meta($product_id, '_osds3d_business_family', true));
            $stored['product_type'] = self::normalize_product_type(isset($stored['product_type']) ? $stored['product_type'] : '');
            $stored['personalization_mode'] = self::normalize_personalization_mode(isset($stored['personalization_mode']) ? $stored['personalization_mode'] : '');
            $stored['legacy_mode'] = self::get_legacy_mode_from_personalization_mode($stored['personalization_mode']);
            $stored['version'] = isset($stored['version']) ? intval($stored['version']) : 1;
            $stored['resources'] = isset($stored['resources']) && is_array($stored['resources']) ? $stored['resources'] : array();
            $stored['options'] = isset($stored['options']) && is_array($stored['options']) ? $stored['options'] : array();
            return $stored;
        }

        $legacy_enabled = get_post_meta($product_id, '_osds3d_customizer_enabled', true);
        $legacy_mode = get_post_meta($product_id, '_osds3d_customizer_mode', true);
        $stored_type = get_post_meta($product_id, '_osds3d_product_type', true);
        $stored_mode = get_post_meta($product_id, '_osds3d_personalization_mode', true);
        $stored_business_family = get_post_meta($product_id, '_osds3d_business_family', true);
        $normalized_mode = self::normalize_personalization_mode($stored_mode ? $stored_mode : $legacy_mode);

        return array(
            'enabled' => ($legacy_enabled === '1'),
            'business_family' => self::normalize_business_family($stored_business_family),
            'product_type' => ($legacy_enabled === '1')
                ? self::normalize_product_type($stored_type ? $stored_type : self::infer_product_type_from_mode($normalized_mode))
                : 'simple',
            'personalization_mode' => ($legacy_enabled === '1') ? $normalized_mode : 'none',
            'legacy_mode' => self::get_legacy_mode_from_personalization_mode($normalized_mode),
            'version' => 1,
            'resources' => array(
                'model_3d_id' => intval(get_post_meta($product_id, '_osds3d_model_3d_id', true)),
            ),
            'options' => array(
                'background_url' => (string) get_post_meta($product_id, '_osds3d_model_bg', true),
                'printable_width' => floatval(get_post_meta($product_id, '_osds3d_model_width', true)),
                'printable_height' => floatval(get_post_meta($product_id, '_osds3d_model_height', true)),
                'bleed' => floatval(get_post_meta($product_id, '_osds3d_model_bleed', true)),
                'safe' => floatval(get_post_meta($product_id, '_osds3d_model_safe', true)),
                'print_3d_allow_upload' => (bool) get_post_meta($product_id, '_osds3d_print_3d_allow_upload', true),
                'print_3d_materials' => self::parse_print_3d_material_catalog((string) get_post_meta($product_id, '_osds3d_print_3d_materials', true)),
                'print_3d_filament_choices' => self::parse_choice_list((string) get_post_meta($product_id, '_osds3d_print_3d_filaments', true)),
                'print_3d_color_choices' => self::parse_choice_list((string) get_post_meta($product_id, '_osds3d_print_3d_colors', true)),
                'print_3d_defaults' => array(
                    'supports' => sanitize_text_field((string) get_post_meta($product_id, '_osds3d_print_3d_default_supports', true)),
                    'infill' => intval(get_post_meta($product_id, '_osds3d_print_3d_default_infill', true)),
                    'layer_height' => floatval(get_post_meta($product_id, '_osds3d_print_3d_default_layer_height', true)),
                    'atelier_notes' => sanitize_textarea_field((string) get_post_meta($product_id, '_osds3d_print_3d_atelier_notes', true)),
                ),
            ),
        );
    }

    /**
     * Construire la structure commune de personnalisation a stocker.
     *
     * @param int        $product_id
     * @param int        $design_id
     * @param object|nil $design
     * @return array
     */
    private function build_personalization_payload($product_id, $design_id = 0, $design = null)
    {
        $config = self::get_product_personalization_config($product_id);

        return array(
            'version' => 1,
            'enabled' => !empty($config['enabled']),
            'business_family' => isset($config['business_family']) ? $config['business_family'] : '',
            'product_type' => isset($config['product_type']) ? $config['product_type'] : 'simple',
            'personalization_mode' => isset($config['personalization_mode']) ? $config['personalization_mode'] : 'none',
            'design_id' => intval($design_id),
            'preview_url' => ($design && !empty($design->preview_url)) ? esc_url_raw($design->preview_url) : '',
            'design_data' => ($design && !empty($design->design_data)) ? $design->design_data : '',
            'customer_inputs' => array(),
            'uploaded_files' => array(),
            'production_specs' => array(),
            'summary_label' => intval($design_id) > 0
                ? sprintf(__('Design #%d', 'osds3d-customizer-pro'), intval($design_id))
                : __('Personnalisation active', 'osds3d-customizer-pro'),
        );
    }

    /**
     * Parser une liste de choix depuis un texte admin.
     *
     * @param string $raw_value
     * @return array
     */
    private static function parse_choice_list($raw_value)
    {
        if (!is_string($raw_value) || $raw_value === '') {
            return array();
        }

        $normalized = str_replace(array("\r\n", "\r"), "\n", $raw_value);
        $parts = preg_split('/[\n,]+/', $normalized);

        if (!is_array($parts)) {
            return array();
        }

        return array_values(array_filter(array_map('sanitize_text_field', array_map('trim', $parts))));
    }

    /**
     * Parser un catalogue simple de materiaux 3D.
     * Format attendu par ligne :
     * cle|Label|image_url|couleur1,couleur2,couleur3
     *
     * @param string $raw_value
     * @return array
     */
    private static function parse_print_3d_material_catalog($raw_value)
    {
        if (!is_string($raw_value) || trim($raw_value) === '') {
            return array();
        }

        $lines = preg_split('/\r\n|\r|\n/', $raw_value);
        if (!is_array($lines)) {
            return array();
        }

        $materials = array();

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $parts = array_map('trim', explode('|', $line));
            $key = isset($parts[0]) ? sanitize_key($parts[0]) : '';
            $label = isset($parts[1]) && $parts[1] !== '' ? sanitize_text_field($parts[1]) : strtoupper($key);
            $image_url = isset($parts[2]) ? esc_url_raw($parts[2]) : '';
            $colors = isset($parts[3]) ? self::parse_choice_list($parts[3]) : array();

            if ($key === '') {
                continue;
            }

            $materials[] = array(
                'key' => $key,
                'label' => $label,
                'image_url' => $image_url,
                'colors' => $colors,
            );
        }

        return $materials;
    }

    /**
     * Retourne les reglages utiles au mode print_3d_form.
     *
     * @param int $product_id
     * @return array
     */
    private function get_print_3d_form_settings($product_id)
    {
        $config = self::get_product_personalization_config($product_id);
        $options = isset($config['options']) && is_array($config['options']) ? $config['options'] : array();

        return array(
            'allow_upload' => !empty($options['print_3d_allow_upload']),
            'materials' => !empty($options['print_3d_materials']) && is_array($options['print_3d_materials'])
                ? $options['print_3d_materials']
                : array(),
            'filament_choices' => !empty($options['print_3d_filament_choices']) && is_array($options['print_3d_filament_choices'])
                ? array_values(array_map('sanitize_text_field', $options['print_3d_filament_choices']))
                : array(),
            'color_choices' => !empty($options['print_3d_color_choices']) && is_array($options['print_3d_color_choices'])
                ? array_values(array_map('sanitize_text_field', $options['print_3d_color_choices']))
                : array(),
            'defaults' => array(
                'supports' => !empty($options['print_3d_defaults']['supports']) ? sanitize_text_field($options['print_3d_defaults']['supports']) : '',
                'infill' => isset($options['print_3d_defaults']['infill']) ? intval($options['print_3d_defaults']['infill']) : '',
                'layer_height' => isset($options['print_3d_defaults']['layer_height']) ? floatval($options['print_3d_defaults']['layer_height']) : '',
                'atelier_notes' => !empty($options['print_3d_defaults']['atelier_notes']) ? sanitize_textarea_field($options['print_3d_defaults']['atelier_notes']) : '',
            ),
        );
    }

    /**
     * Verifier si le produit courant utilise le mode print_3d_form.
     *
     * @param int $product_id
     * @return bool
     */
    private function is_print_3d_form_product($product_id)
    {
        $config = self::get_product_personalization_config($product_id);

        return !empty($config['enabled']) && isset($config['personalization_mode']) && $config['personalization_mode'] === 'print_3d_form';
    }

    /**
     * Constructeur privé.
     *
     * @return void
     */
    private function __construct()
    {
        add_action('add_meta_boxes', array($this, 'add_product_meta_box'));
        add_action('save_post', array($this, 'save_product_meta'), 10, 3);

        add_action('woocommerce_before_add_to_cart_button', array($this, 'render_print_3d_form_fields'), 15);
        add_action('woocommerce_after_add_to_cart_button', array($this, 'display_customize_button'), 25);

        add_filter('woocommerce_add_to_cart_validation', array($this, 'validate_design_before_add_to_cart'), 10, 3);
        add_filter('woocommerce_add_to_cart_validation', array($this, 'validate_design_context_before_add_to_cart'), 20, 3);
        add_filter('woocommerce_add_to_cart_validation', array($this, 'validate_print_3d_form_before_add_to_cart'), 30, 3);
        add_filter('woocommerce_add_cart_item_data', array($this, 'add_design_to_cart'), 10, 2);
        add_filter('woocommerce_add_cart_item_data', array($this, 'add_print_3d_form_to_cart'), 20, 2);
        add_filter('woocommerce_get_cart_item_from_session', array($this, 'restore_cart_item_from_session'), 10, 2);
        add_filter('woocommerce_get_item_data', array($this, 'display_design_in_cart'), 10, 2);

        add_action('woocommerce_checkout_create_order_line_item', array($this, 'save_design_to_order'), 10, 4);
        add_action('woocommerce_after_order_itemmeta', array($this, 'display_design_in_admin_order'), 10, 3);

        add_action('woocommerce_new_order', array($this, 'maybe_notify_new_order'), 20, 1);
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
     * Decode les notes de validation associees au design.
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

        return array();
    }

    /**
     * Recalcule le token attendu pour un design.
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
     * Verifie que le design appartient bien au contexte courant.
     *
     * @param object $design
     * @return bool
     */
    private function current_context_can_use_design($design)
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
     * Ajouter une metabox pour activer la personnalisation sur un produit.
     *
     * @return void
     */
    public function add_product_meta_box()
    {
        add_meta_box(
            'osds3d_product_customizer',
            __('OSDS3D Personnalisation', 'osds3d-customizer-pro'),
            array($this, 'render_product_meta_box'),
            'product',
            'side',
            'default'
        );
    }

    /**
     * Récupérer les modèles 3D depuis la bibliothèque.
     *
     * @param bool $active_only
     * @return array
     */
    private function get_available_3d_models($active_only = false)
    {
        if (!class_exists('OSDS3D_Database')) {
            return array();
        }

        if (method_exists('OSDS3D_Database', 'get_models_3d')) {
            return OSDS3D_Database::get_models_3d($active_only);
        }

        return array();
    }

    /**
     * Récupérer un modèle 3D par son ID.
     *
     * @param int $model_id
     * @return object|null
     */
    private function get_model_3d($model_id)
    {
        $model_id = intval($model_id);

        if ($model_id <= 0 || !class_exists('OSDS3D_Database')) {
            return null;
        }

        if (method_exists('OSDS3D_Database', 'get_model_3d')) {
            return OSDS3D_Database::get_model_3d($model_id);
        }

        return null;
    }

    /**
     * Afficher le contenu de la metabox.
     *
     * @param WP_Post $post
     * @return void
     */
    public function render_product_meta_box($post)
    {
        wp_nonce_field('osds3d_product_meta', 'osds3d_product_meta_nonce');

        $config   = self::get_product_personalization_config($post->ID);
        $enabled  = !empty($config['enabled']) ? '1' : '0';
        $business_family = isset($config['business_family']) ? $config['business_family'] : '';
        $product_type = isset($config['product_type']) ? $config['product_type'] : 'simple';
        $personalization_mode = isset($config['personalization_mode']) ? $config['personalization_mode'] : 'none';
        $mode     = isset($config['legacy_mode']) ? $config['legacy_mode'] : '2d';
        $model_id = get_post_meta($post->ID, '_osds3d_model_3d_id', true);

        $bg_url = get_post_meta($post->ID, '_osds3d_model_bg', true);
        $width  = get_post_meta($post->ID, '_osds3d_model_width', true);
        $height = get_post_meta($post->ID, '_osds3d_model_height', true);
        $bleed  = get_post_meta($post->ID, '_osds3d_model_bleed', true);
        $safe   = get_post_meta($post->ID, '_osds3d_model_safe', true);
        $print_3d_allow_upload = !empty($config['options']['print_3d_allow_upload']);
        $print_3d_materials = !empty($config['options']['print_3d_materials']) && is_array($config['options']['print_3d_materials'])
            ? $config['options']['print_3d_materials']
            : array();
        $print_3d_filaments = !empty($config['options']['print_3d_filament_choices']) && is_array($config['options']['print_3d_filament_choices'])
            ? implode("\n", $config['options']['print_3d_filament_choices'])
            : '';
        $print_3d_colors = !empty($config['options']['print_3d_color_choices']) && is_array($config['options']['print_3d_color_choices'])
            ? implode("\n", $config['options']['print_3d_color_choices'])
            : '';
        $print_3d_materials_raw = '';

        if (!empty($print_3d_materials)) {
            $material_lines = array();

            foreach ($print_3d_materials as $material) {
                $material_lines[] = implode('|', array(
                    isset($material['key']) ? $material['key'] : '',
                    isset($material['label']) ? $material['label'] : '',
                    isset($material['image_url']) ? $material['image_url'] : '',
                    !empty($material['colors']) && is_array($material['colors']) ? implode(',', $material['colors']) : '',
                ));
            }

            $print_3d_materials_raw = implode("\n", $material_lines);
        }

        $print_3d_defaults = !empty($config['options']['print_3d_defaults']) && is_array($config['options']['print_3d_defaults'])
            ? $config['options']['print_3d_defaults']
            : array();

        $models = $this->get_available_3d_models(false);
        ?>
        <p>
            <label>
                <input type="checkbox" name="osds3d_customizer_enabled" value="1" <?php checked($enabled, '1'); ?> />
                <strong><?php echo esc_html__('Activer la personnalisation', 'osds3d-customizer-pro'); ?></strong>
            </label>
        </p>

        <p>
            <label for="osds3d_business_family">
                <strong><?php echo esc_html__('Famille metier', 'osds3d-customizer-pro'); ?>:</strong>
            </label>
            <select id="osds3d_business_family" name="osds3d_business_family" style="width:100%">
                <?php foreach (self::get_supported_business_families() as $family_key => $family_label) : ?>
                    <option value="<?php echo esc_attr($family_key); ?>" <?php selected($business_family, $family_key); ?>>
                        <?php echo esc_html__($family_label, 'osds3d-customizer-pro'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>

        <p>
            <label for="osds3d_product_type">
                <strong><?php echo esc_html__('Type de produit', 'osds3d-customizer-pro'); ?>:</strong>
            </label>
            <select id="osds3d_product_type" name="osds3d_product_type" style="width:100%">
                <?php foreach (self::get_supported_product_types() as $type_key => $type_label) : ?>
                    <option value="<?php echo esc_attr($type_key); ?>" <?php selected($product_type, $type_key); ?>>
                        <?php echo esc_html__($type_label, 'osds3d-customizer-pro'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>

        <p>
            <label for="osds3d_personalization_mode">
                <strong><?php echo esc_html__('Mode de personnalisation', 'osds3d-customizer-pro'); ?>:</strong>
            </label>
            <select id="osds3d_personalization_mode" name="osds3d_personalization_mode" style="width:100%">
                <?php foreach (self::get_supported_personalization_modes() as $mode_key => $mode_label) : ?>
                    <option value="<?php echo esc_attr($mode_key); ?>" <?php selected($personalization_mode, $mode_key); ?>>
                        <?php echo esc_html__($mode_label, 'osds3d-customizer-pro'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>

        <input type="hidden" name="osds3d_customizer_mode" value="<?php echo esc_attr($mode); ?>" />

        <p class="description">
            <?php echo esc_html__('Le produit peut maintenant declarer un type et un mode de personnalisation distincts, tout en restant compatible avec le customizer actuel.', 'osds3d-customizer-pro'); ?>
        </p>

        <div class="osds3d-3d-options" style="margin-top:10px;">
            <p>
                <strong><?php echo esc_html__('Options 3D', 'osds3d-customizer-pro'); ?></strong>
            </p>

            <p>
                <label for="osds3d_model_3d_id">
                    <?php echo esc_html__('Modèle GLB lié au produit', 'osds3d-customizer-pro'); ?>
                </label><br />
                <select id="osds3d_model_3d_id" name="osds3d_model_3d_id" style="width:100%;">
                    <option value="0"><?php echo esc_html__('— Aucun modèle sélectionné —', 'osds3d-customizer-pro'); ?></option>
                    <?php foreach ($models as $model) : ?>
                        <option value="<?php echo esc_attr($model->id); ?>" <?php selected((int) $model_id, (int) $model->id); ?>>
                            <?php
                            echo esc_html($model->model_name);
                            if (!empty($model->model_category)) {
                                echo ' — ' . esc_html($model->model_category);
                            }
                            if ((int) $model->is_active !== 1) {
                                echo ' (' . esc_html__('inactif', 'osds3d-customizer-pro') . ')';
                            }
                            ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </p>

            <p class="description" style="margin-top:-6px;">
                <?php echo esc_html__('Les modèles proviennent de la bibliothèque "Modèles 3D" dans l’administration du plugin.', 'osds3d-customizer-pro'); ?>
            </p>

            <p>
                <label for="osds3d_model_bg">
                    <?php echo esc_html__('Image de fond pour l’aperçu (PNG/JPG)', 'osds3d-customizer-pro'); ?>
                </label><br />
                <input
                    type="text"
                    id="osds3d_model_bg"
                    name="osds3d_model_bg"
                    value="<?php echo esc_attr($bg_url); ?>"
                    style="width:100%;"
                    placeholder="<?php echo esc_attr__('URL vers une image', 'osds3d-customizer-pro'); ?>"
                >
            </p>

            <p>
                <label><?php echo esc_html__('Dimensions imprimables (cm)', 'osds3d-customizer-pro'); ?></label><br />
                <input
                    type="number"
                    step="0.1"
                    min="0"
                    name="osds3d_model_width"
                    value="<?php echo esc_attr($width); ?>"
                    style="width:48%;"
                    placeholder="<?php echo esc_attr__('Largeur', 'osds3d-customizer-pro'); ?>"
                >&nbsp;
                <input
                    type="number"
                    step="0.1"
                    min="0"
                    name="osds3d_model_height"
                    value="<?php echo esc_attr($height); ?>"
                    style="width:48%;"
                    placeholder="<?php echo esc_attr__('Hauteur', 'osds3d-customizer-pro'); ?>"
                >
            </p>

            <p>
                <label><?php echo esc_html__('Fond perdu (cm)', 'osds3d-customizer-pro'); ?></label><br />
                <input
                    type="number"
                    step="0.1"
                    min="0"
                    name="osds3d_model_bleed"
                    value="<?php echo esc_attr($bleed); ?>"
                    style="width:48%;"
                    placeholder="<?php echo esc_attr__('Fond perdu', 'osds3d-customizer-pro'); ?>"
                >&nbsp;
                <input
                    type="number"
                    step="0.1"
                    min="0"
                    name="osds3d_model_safe"
                    value="<?php echo esc_attr($safe); ?>"
                    style="width:48%;"
                    placeholder="<?php echo esc_attr__('Zone sûre', 'osds3d-customizer-pro'); ?>"
                >
            </p>
        </div>

        <div class="osds3d-print3d-options" style="margin-top:10px;">
            <p>
                <strong><?php echo esc_html__('Options impression 3D', 'osds3d-customizer-pro'); ?></strong>
            </p>

            <p>
                <label>
                    <input type="checkbox" name="osds3d_print_3d_allow_upload" value="1" <?php checked($print_3d_allow_upload, true); ?> />
                    <?php echo esc_html__('Autoriser l’upload d’un fichier 3D client', 'osds3d-customizer-pro'); ?>
                </label>
            </p>

            <p>
                <label for="osds3d_print_3d_materials">
                    <?php echo esc_html__('Catalogue materiaux', 'osds3d-customizer-pro'); ?>
                </label><br />
                <textarea id="osds3d_print_3d_materials" name="osds3d_print_3d_materials" rows="6" style="width:100%;" placeholder="<?php echo esc_attr__("pla|PLA mat|https://.../pla.jpg|Blanc,Noir,Gris\npetg|PETG brillant|https://.../petg.jpg|Transparent,Noir,Bleu", 'osds3d-customizer-pro'); ?>"><?php echo esc_textarea($print_3d_materials_raw); ?></textarea>
                <span class="description"><?php echo esc_html__('Une ligne par materiau : cle|Label|image_url|couleurs separees par des virgules.', 'osds3d-customizer-pro'); ?></span>
            </p>

            <p>
                <label for="osds3d_print_3d_filaments">
                    <?php echo esc_html__('Ancien fallback filaments', 'osds3d-customizer-pro'); ?>
                </label><br />
                <textarea id="osds3d_print_3d_filaments" name="osds3d_print_3d_filaments" rows="3" style="width:100%;" placeholder="<?php echo esc_attr__("PLA\nPETG\nABS", 'osds3d-customizer-pro'); ?>"><?php echo esc_textarea($print_3d_filaments); ?></textarea>
                <span class="description"><?php echo esc_html__('Conserve pour compatibilite si aucun catalogue materiaux n’est defini.', 'osds3d-customizer-pro'); ?></span>
            </p>

            <p>
                <label for="osds3d_print_3d_colors">
                    <?php echo esc_html__('Ancien fallback couleurs', 'osds3d-customizer-pro'); ?>
                </label><br />
                <textarea id="osds3d_print_3d_colors" name="osds3d_print_3d_colors" rows="3" style="width:100%;" placeholder="<?php echo esc_attr__("Noir\nBlanc\nRouge", 'osds3d-customizer-pro'); ?>"><?php echo esc_textarea($print_3d_colors); ?></textarea>
                <span class="description"><?php echo esc_html__('Conserve pour compatibilite si les couleurs ne sont pas definies par materiau.', 'osds3d-customizer-pro'); ?></span>
            </p>

            <p>
                <strong><?php echo esc_html__('Reglages atelier par defaut', 'osds3d-customizer-pro'); ?></strong>
            </p>

            <p>
                <label for="osds3d_print_3d_default_supports"><?php echo esc_html__('Supports par defaut', 'osds3d-customizer-pro'); ?></label><br />
                <select id="osds3d_print_3d_default_supports" name="osds3d_print_3d_default_supports" style="width:100%;">
                    <option value="" <?php selected(isset($print_3d_defaults['supports']) ? $print_3d_defaults['supports'] : '', ''); ?>><?php echo esc_html__('A definir a l’atelier', 'osds3d-customizer-pro'); ?></option>
                    <option value="yes" <?php selected(isset($print_3d_defaults['supports']) ? $print_3d_defaults['supports'] : '', 'yes'); ?>><?php echo esc_html__('Oui', 'osds3d-customizer-pro'); ?></option>
                    <option value="no" <?php selected(isset($print_3d_defaults['supports']) ? $print_3d_defaults['supports'] : '', 'no'); ?>><?php echo esc_html__('Non', 'osds3d-customizer-pro'); ?></option>
                </select>
            </p>

            <p>
                <label><?php echo esc_html__('Reglages impression par defaut', 'osds3d-customizer-pro'); ?></label><br />
                <input
                    type="number"
                    step="1"
                    min="0"
                    max="100"
                    name="osds3d_print_3d_default_infill"
                    value="<?php echo esc_attr(isset($print_3d_defaults['infill']) ? $print_3d_defaults['infill'] : ''); ?>"
                    style="width:48%;"
                    placeholder="<?php echo esc_attr__('Remplissage %', 'osds3d-customizer-pro'); ?>"
                >&nbsp;
                <input
                    type="number"
                    step="0.01"
                    min="0.05"
                    max="1"
                    name="osds3d_print_3d_default_layer_height"
                    value="<?php echo esc_attr(isset($print_3d_defaults['layer_height']) ? $print_3d_defaults['layer_height'] : ''); ?>"
                    style="width:48%;"
                    placeholder="<?php echo esc_attr__('Hauteur couche mm', 'osds3d-customizer-pro'); ?>"
                >
            </p>

            <p>
                <label for="osds3d_print_3d_atelier_notes"><?php echo esc_html__('Notes atelier par defaut', 'osds3d-customizer-pro'); ?></label><br />
                <textarea id="osds3d_print_3d_atelier_notes" name="osds3d_print_3d_atelier_notes" rows="3" style="width:100%;"><?php echo esc_textarea(isset($print_3d_defaults['atelier_notes']) ? $print_3d_defaults['atelier_notes'] : ''); ?></textarea>
            </p>
        </div>
        <?php
    }

    /**
     * Enregistrer les métadonnées de personnalisation du produit.
     *
     * @param int     $post_id
     * @param WP_Post $post
     * @param bool    $update
     * @return void
     */
    public function save_product_meta($post_id, $post = null, $update = false)
    {
        if (!isset($_POST['osds3d_product_meta_nonce'])) {
            return;
        }

        $nonce = sanitize_text_field(wp_unslash($_POST['osds3d_product_meta_nonce']));
        if (!wp_verify_nonce($nonce, 'osds3d_product_meta')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!$post || $post->post_type !== 'product') {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $enabled = isset($_POST['osds3d_customizer_enabled']) ? '1' : '0';
        update_post_meta($post_id, '_osds3d_customizer_enabled', $enabled);

        $personalization_mode = isset($_POST['osds3d_personalization_mode'])
            ? self::normalize_personalization_mode(wp_unslash($_POST['osds3d_personalization_mode']))
            : self::normalize_personalization_mode(isset($_POST['osds3d_customizer_mode']) ? wp_unslash($_POST['osds3d_customizer_mode']) : '2d');

        $product_type = isset($_POST['osds3d_product_type'])
            ? self::normalize_product_type(wp_unslash($_POST['osds3d_product_type']))
            : self::infer_product_type_from_mode($personalization_mode);
        $business_family = isset($_POST['osds3d_business_family'])
            ? self::normalize_business_family(wp_unslash($_POST['osds3d_business_family']))
            : '';

        $mode = self::get_legacy_mode_from_personalization_mode($personalization_mode);
        update_post_meta($post_id, '_osds3d_customizer_mode', $mode);
        update_post_meta($post_id, '_osds3d_business_family', $business_family);
        update_post_meta($post_id, '_osds3d_product_type', $product_type);
        update_post_meta($post_id, '_osds3d_personalization_mode', $personalization_mode);
        update_post_meta($post_id, '_osds3d_print_3d_allow_upload', !empty($_POST['osds3d_print_3d_allow_upload']) ? '1' : '0');
        update_post_meta(
            $post_id,
            '_osds3d_print_3d_filaments',
            isset($_POST['osds3d_print_3d_filaments']) ? sanitize_textarea_field(wp_unslash($_POST['osds3d_print_3d_filaments'])) : ''
        );
        update_post_meta(
            $post_id,
            '_osds3d_print_3d_colors',
            isset($_POST['osds3d_print_3d_colors']) ? sanitize_textarea_field(wp_unslash($_POST['osds3d_print_3d_colors'])) : ''
        );
        update_post_meta(
            $post_id,
            '_osds3d_print_3d_materials',
            isset($_POST['osds3d_print_3d_materials']) ? sanitize_textarea_field(wp_unslash($_POST['osds3d_print_3d_materials'])) : ''
        );
        update_post_meta(
            $post_id,
            '_osds3d_print_3d_default_supports',
            isset($_POST['osds3d_print_3d_default_supports']) ? sanitize_text_field(wp_unslash($_POST['osds3d_print_3d_default_supports'])) : ''
        );
        update_post_meta(
            $post_id,
            '_osds3d_print_3d_default_infill',
            isset($_POST['osds3d_print_3d_default_infill']) ? intval(wp_unslash($_POST['osds3d_print_3d_default_infill'])) : ''
        );
        update_post_meta(
            $post_id,
            '_osds3d_print_3d_default_layer_height',
            isset($_POST['osds3d_print_3d_default_layer_height']) ? floatval(wp_unslash($_POST['osds3d_print_3d_default_layer_height'])) : ''
        );
        update_post_meta(
            $post_id,
            '_osds3d_print_3d_atelier_notes',
            isset($_POST['osds3d_print_3d_atelier_notes']) ? sanitize_textarea_field(wp_unslash($_POST['osds3d_print_3d_atelier_notes'])) : ''
        );

        $model_id = isset($_POST['osds3d_model_3d_id']) ? intval(wp_unslash($_POST['osds3d_model_3d_id'])) : 0;
        $bg       = isset($_POST['osds3d_model_bg']) ? esc_url_raw(wp_unslash($_POST['osds3d_model_bg'])) : '';
        $w        = isset($_POST['osds3d_model_width']) ? floatval(wp_unslash($_POST['osds3d_model_width'])) : '';
        $h        = isset($_POST['osds3d_model_height']) ? floatval(wp_unslash($_POST['osds3d_model_height'])) : '';
        $bleed    = isset($_POST['osds3d_model_bleed']) ? floatval(wp_unslash($_POST['osds3d_model_bleed'])) : '';
        $safe     = isset($_POST['osds3d_model_safe']) ? floatval(wp_unslash($_POST['osds3d_model_safe'])) : '';

        $glb_url = '';

        if ($model_id > 0) {
            $model = $this->get_model_3d($model_id);

            if ($model && !empty($model->model_url)) {
                $glb_url = esc_url_raw($model->model_url);
            } else {
                $model_id = 0;
            }
        }

        update_post_meta($post_id, '_osds3d_model_3d_id', $model_id);
        update_post_meta($post_id, '_osds3d_model_bg', $bg);
        update_post_meta($post_id, '_osds3d_model_width', $w);
        update_post_meta($post_id, '_osds3d_model_height', $h);
        update_post_meta($post_id, '_osds3d_model_bleed', $bleed);
        update_post_meta($post_id, '_osds3d_model_safe', $safe);

        $product_config = $this->build_product_personalization_config($post_id, $_POST);
        update_post_meta($post_id, '_osds3d_product_config', $product_config);

        // Compatibilité ancienne structure
        update_post_meta($post_id, '_osds3d_model_glb', $glb_url);
    }

    /**
     * Afficher le bouton "Personnaliser" sur la page produit.
     *
     * @return void
     */
    public function display_customize_button()
    {
        global $product;

        if (!$product || !is_a($product, 'WC_Product')) {
            return;
        }

        $product_id = $product->get_id();
        $config     = self::get_product_personalization_config($product_id);
        $enabled    = !empty($config['enabled']) ? '1' : '0';
        $mode       = isset($config['personalization_mode']) ? $config['personalization_mode'] : 'none';

        if ($enabled !== '1' || $mode === 'none' || $mode === 'print_3d_form') {
            return;
        }

        if (!class_exists('OSDS3D_Settings')) {
            return;
        }

        $page_id = OSDS3D_Settings::get('customizer_page_id', 0);

        if (!$page_id) {
            echo '<p style="color:red;">' . esc_html__('Page customizer non configurée.', 'osds3d-customizer-pro') . '</p>';
            return;
        }

        $url = add_query_arg('product_id', $product_id, get_permalink($page_id));
        ?>
        <div class="osds3d-customize-wrapper" style="margin-top:20px;">
            <a href="<?php echo esc_url($url); ?>" class="button alt osds3d-customize-btn" style="display:block;width:100%;text-align:center;background:#0073aa;color:#fff;padding:15px 30px;border-radius:8px;font-weight:700;font-size:16px;text-decoration:none;">
                🎨 <?php echo esc_html__('Personnaliser ce produit', 'osds3d-customizer-pro'); ?>
            </a>
        </div>
        <?php
    }

    /**
     * Afficher les champs frontend du mode print_3d_form.
     *
     * @return void
     */
    public function render_print_3d_form_fields()
    {
        global $product;

        if (!$product || !is_a($product, 'WC_Product')) {
            return;
        }

        $product_id = $product->get_id();

        if (!$this->is_print_3d_form_product($product_id)) {
            return;
        }

        $settings = $this->get_print_3d_form_settings($product_id);
        $materials = !empty($settings['materials']) ? $settings['materials'] : array();

        if (empty($materials) && !empty($settings['filament_choices'])) {
            foreach ($settings['filament_choices'] as $choice) {
                $materials[] = array(
                    'key' => sanitize_key($choice),
                    'label' => $choice,
                    'image_url' => '',
                    'colors' => !empty($settings['color_choices']) ? $settings['color_choices'] : array(),
                );
            }
        }
        ?>
        <div class="osds3d-flow-card osds3d-print3d-form">
            <div class="osds3d-flow-header">
                <div>
                    <h4 class="osds3d-flow-title"><?php echo esc_html__('Informations impression 3D', 'osds3d-customizer-pro'); ?></h4>
                    <p class="osds3d-flow-intro"><?php echo esc_html__('Renseignez les options de fabrication utiles pour lancer la production.', 'osds3d-customizer-pro'); ?></p>
                </div>
            </div>

            <div class="osds3d-flow-section">
                <div class="osds3d-flow-section-head">
                    <h5 class="osds3d-flow-section-title"><?php echo esc_html__('Choisissez votre materiau', 'osds3d-customizer-pro'); ?></h5>
                    <p class="osds3d-flow-section-text"><?php echo esc_html__('Selectionnez la finition qui correspond le mieux a votre projet.', 'osds3d-customizer-pro'); ?></p>
                </div>

                <?php if (!empty($materials)) : ?>
                    <div class="osds3d-material-grid" id="osds3d-print3d-material-grid">
                        <?php foreach ($materials as $index => $material) : ?>
                            <?php
                            $material_key = isset($material['key']) ? sanitize_key($material['key']) : '';
                            $material_label = isset($material['label']) ? sanitize_text_field($material['label']) : $material_key;
                            $material_image = isset($material['image_url']) ? esc_url($material['image_url']) : '';
                            $material_colors = !empty($material['colors']) && is_array($material['colors']) ? $material['colors'] : array();
                            ?>
                            <label class="osds3d-material-card">
                                <input
                                    type="radio"
                                    name="osds3d_print3d_filament"
                                    value="<?php echo esc_attr($material_key); ?>"
                                    class="osds3d-material-input"
                                    data-material-key="<?php echo esc_attr($material_key); ?>"
                                    data-material-label="<?php echo esc_attr($material_label); ?>"
                                    data-colors="<?php echo esc_attr(wp_json_encode(array_values($material_colors))); ?>"
                                    <?php checked($index, 0); ?>
                                />
                                <span class="osds3d-material-card-ui">
                                    <span class="osds3d-material-media">
                                        <?php if ($material_image) : ?>
                                            <img src="<?php echo esc_url($material_image); ?>" alt="" class="osds3d-material-image" />
                                        <?php else : ?>
                                            <span class="osds3d-material-placeholder"><?php echo esc_html(strtoupper(substr($material_label, 0, 1))); ?></span>
                                        <?php endif; ?>
                                    </span>
                                    <span class="osds3d-material-content">
                                        <span class="osds3d-material-title"><?php echo esc_html($material_label); ?></span>
                                        <span class="osds3d-material-meta"><?php echo esc_html(!empty($material_colors) ? sprintf(__('%d couleurs', 'osds3d-customizer-pro'), count($material_colors)) : __('Couleurs sur demande', 'osds3d-customizer-pro')); ?></span>
                                    </span>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php else : ?>
                    <div class="osds3d-flow-field">
                        <label class="osds3d-flow-label" for="osds3d_print3d_filament"><?php echo esc_html__('Materiau', 'osds3d-customizer-pro'); ?></label>
                        <input type="text" id="osds3d_print3d_filament" name="osds3d_print3d_filament" class="osds3d-flow-input" />
                    </div>
                <?php endif; ?>
            </div>

            <div class="osds3d-flow-section">
                <div class="osds3d-flow-section-head">
                    <h5 class="osds3d-flow-section-title"><?php echo esc_html__('Choisissez votre couleur', 'osds3d-customizer-pro'); ?></h5>
                    <p class="osds3d-flow-section-text"><?php echo esc_html__('Les couleurs proposees s’adaptent au materiau selectionne.', 'osds3d-customizer-pro'); ?></p>
                </div>

                <div class="osds3d-color-choice-grid" id="osds3d-print3d-color-grid"></div>
                <input type="hidden" id="osds3d_print3d_color" name="osds3d_print3d_color" value="" />
                <p class="osds3d-flow-help" id="osds3d-print3d-color-help"><?php echo esc_html__('Selectionnez d’abord un materiau pour voir les couleurs disponibles.', 'osds3d-customizer-pro'); ?></p>
            </div>

            <?php if (!empty($settings['allow_upload'])) : ?>
                <div class="osds3d-flow-section">
                    <div class="osds3d-flow-section-head">
                        <h5 class="osds3d-flow-section-title"><?php echo esc_html__('Fichier 3D', 'osds3d-customizer-pro'); ?></h5>
                        <p class="osds3d-flow-section-text"><?php echo esc_html__('Ajoutez votre modele si vous souhaitez faire imprimer un fichier existant.', 'osds3d-customizer-pro'); ?></p>
                    </div>

                    <div class="osds3d-flow-upload">
                        <label class="osds3d-flow-upload-label" for="osds3d_print3d_file"><?php echo esc_html__('Choisir un fichier 3D', 'osds3d-customizer-pro'); ?></label>
                        <input type="file" id="osds3d_print3d_file" name="osds3d_print3d_file" accept=".stl,.obj,.3mf" class="osds3d-flow-input osds3d-flow-file" />
                        <p class="osds3d-flow-help"><?php echo esc_html__('Formats acceptes : STL, OBJ, 3MF.', 'osds3d-customizer-pro'); ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <div class="osds3d-flow-section">
                <div class="osds3d-flow-section-head">
                    <h5 class="osds3d-flow-section-title"><?php echo esc_html__('Informations complementaires', 'osds3d-customizer-pro'); ?></h5>
                    <p class="osds3d-flow-section-text"><?php echo esc_html__('Ajoutez des precisions utiles pour l’atelier si necessaire.', 'osds3d-customizer-pro'); ?></p>
                </div>

                <div class="osds3d-flow-field">
                    <label class="osds3d-flow-label" for="osds3d_print3d_notes"><?php echo esc_html__('Notes', 'osds3d-customizer-pro'); ?></label>
                    <textarea id="osds3d_print3d_notes" name="osds3d_print3d_notes" rows="4" class="osds3d-flow-input osds3d-flow-textarea"></textarea>
                </div>
            </div>
        </div>

        <script>
            (function () {
                var field = document.getElementById('osds3d_print3d_notes');
                if (!field) {
                    return;
                }

                var form = field.closest('form.cart');
                if (form) {
                    form.setAttribute('enctype', 'multipart/form-data');
                    form.classList.add('osds3d-print3d-cart-form');
                }

                var materialInputs = document.querySelectorAll('.osds3d-material-input');
                var colorGrid = document.getElementById('osds3d-print3d-color-grid');
                var colorInput = document.getElementById('osds3d_print3d_color');
                var colorHelp = document.getElementById('osds3d-print3d-color-help');

                function escapeHtml(value) {
                    return String(value || '')
                        .replace(/&/g, '&amp;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;')
                        .replace(/"/g, '&quot;')
                        .replace(/'/g, '&#039;');
                }

                function resolveSwatchColor(colorName) {
                    var normalized = String(colorName || '').toLowerCase();
                    var map = {
                        noir: '#111827',
                        black: '#111827',
                        blanc: '#f8fafc',
                        white: '#f8fafc',
                        gris: '#9ca3af',
                        gray: '#9ca3af',
                        grey: '#9ca3af',
                        rouge: '#dc2626',
                        red: '#dc2626',
                        bleu: '#2563eb',
                        blue: '#2563eb',
                        vert: '#16a34a',
                        green: '#16a34a',
                        jaune: '#facc15',
                        yellow: '#facc15',
                        orange: '#f97316',
                        violet: '#8b5cf6',
                        purple: '#8b5cf6',
                        rose: '#ec4899',
                        pink: '#ec4899',
                        transparent: 'linear-gradient(135deg,#ffffff 0%,#e2e8f0 100%)'
                    };

                    return map[normalized] || 'linear-gradient(180deg,#cbd5e1 0%,#94a3b8 100%)';
                }

                function renderColorsForMaterial(input) {
                    if (!colorGrid || !colorInput || !input) {
                        return;
                    }

                    var colors = [];

                    try {
                        colors = JSON.parse(input.getAttribute('data-colors') || '[]');
                    } catch (e) {
                        colors = [];
                    }

                    colorGrid.innerHTML = '';
                    colorInput.value = '';

                    if (!colors.length) {
                        if (colorHelp) {
                            colorHelp.textContent = '<?php echo esc_js(__('Les couleurs seront confirmees avec l’atelier pour ce materiau.', 'osds3d-customizer-pro')); ?>';
                        }
                        return;
                    }

                    if (colorHelp) {
                        colorHelp.textContent = '<?php echo esc_js(__('Choisissez une couleur parmi les options proposees.', 'osds3d-customizer-pro')); ?>';
                    }

                    colors.forEach(function (color, index) {
                        var button = document.createElement('button');
                        button.type = 'button';
                        button.className = 'osds3d-color-choice';
                        button.setAttribute('data-color-value', color);
                        button.innerHTML =
                            '<span class="osds3d-color-choice-swatch" aria-hidden="true"></span>' +
                            '<span class="osds3d-color-choice-label">' + escapeHtml(color) + '</span>';

                        var swatch = button.querySelector('.osds3d-color-choice-swatch');
                        if (swatch) {
                            swatch.style.background = resolveSwatchColor(color);
                        }

                        button.addEventListener('click', function () {
                            colorGrid.querySelectorAll('.osds3d-color-choice').forEach(function (chip) {
                                chip.classList.remove('is-selected');
                            });

                            button.classList.add('is-selected');
                            colorInput.value = color;
                        });

                        colorGrid.appendChild(button);

                        if (index === 0) {
                            button.click();
                        }
                    });
                }

                materialInputs.forEach(function (input) {
                    input.addEventListener('change', function () {
                        renderColorsForMaterial(input);
                    });
                });

                var selectedMaterial = document.querySelector('.osds3d-material-input:checked');
                if (selectedMaterial) {
                    renderColorsForMaterial(selectedMaterial);
                }
            })();
        </script>
        <?php
    }

    /**
     * Vérifier la validité du design avant ajout au panier.
     *
     * @param bool $passed
     * @param int  $product_id
     * @param int  $quantity
     * @return bool
     */
    public function validate_design_before_add_to_cart($passed, $product_id, $quantity)
    {
        if (!isset($_POST['osds3d_design_id']) || empty($_POST['osds3d_design_id'])) {
            return $passed;
        }

        $design_id = intval(wp_unslash($_POST['osds3d_design_id']));
        $design = null;

        if ($design_id <= 0) {
            wc_add_notice(__('Design invalide.', 'osds3d-customizer-pro'), 'error');
            return false;
        }

        if (!class_exists('OSDS3D_Database') || !method_exists('OSDS3D_Database', 'get_design')) {
            wc_add_notice(__('Base de données design indisponible.', 'osds3d-customizer-pro'), 'error');
            return false;
        }

        $design = OSDS3D_Database::get_design($design_id);

        if (!$design) {
            wc_add_notice(__('Le design sélectionné est introuvable.', 'osds3d-customizer-pro'), 'error');
            return false;
        }

        if ((int) $design->product_id > 0 && (int) $design->product_id !== (int) $product_id) {
            wc_add_notice(__('Ce design ne correspond pas au produit sélectionné.', 'osds3d-customizer-pro'), 'error');
            return false;
        }

        return $passed;
    }

    /**
     * Ajouter l'ID du design aux données du panier.
     *
     * @param array $cart_item_data
     * @param int   $product_id
     * @return array
     */
    public function add_design_to_cart($cart_item_data, $product_id)
    {
        if (!isset($_POST['osds3d_design_id']) || empty($_POST['osds3d_design_id'])) {
            return $cart_item_data;
        }

        $design_id = intval(wp_unslash($_POST['osds3d_design_id']));
        $design = null;

        if ($design_id <= 0) {
            return $cart_item_data;
        }

        if (class_exists('OSDS3D_Database') && method_exists('OSDS3D_Database', 'get_design')) {
            $design = OSDS3D_Database::get_design($design_id);

            if (!$design || !$this->current_context_can_use_design($design)) {
                return $cart_item_data;
            }
        }

        $cart_item_data['osds3d_design_id'] = $design_id;

        if (class_exists('OSDS3D_Database') && method_exists('OSDS3D_Database', 'get_design')) {
            $design = OSDS3D_Database::get_design($design_id);

            if ($design) {
                if (!empty($design->preview_url)) {
                    $cart_item_data['osds3d_preview_url'] = esc_url_raw($design->preview_url);
                }

                if (!empty($design->design_data)) {
                    $cart_item_data['osds3d_design_data'] = $design->design_data;
                }
            }
        }

        $cart_item_data['osds3d_personalization'] = $this->build_personalization_payload($product_id, $design_id, $design);

        // Empêche WooCommerce de fusionner deux articles personnalisés
        $cart_item_data['osds3d_unique_key'] = md5($product_id . '|' . $design_id . '|' . microtime(true) . '|' . wp_rand());

        return $cart_item_data;
    }

    /**
     * Restaurer les données custom depuis la session panier.
     *
     * @param array $cart_item
     * @param array $values
     * @return array
     */
    public function restore_cart_item_from_session($cart_item, $values)
    {
        if (isset($values['osds3d_design_id'])) {
            $cart_item['osds3d_design_id'] = intval($values['osds3d_design_id']);
        }

        if (isset($values['osds3d_preview_url'])) {
            $cart_item['osds3d_preview_url'] = esc_url_raw($values['osds3d_preview_url']);
        }

        if (isset($values['osds3d_design_data'])) {
            $cart_item['osds3d_design_data'] = $values['osds3d_design_data'];
        }

        if (isset($values['osds3d_unique_key'])) {
            $cart_item['osds3d_unique_key'] = sanitize_text_field($values['osds3d_unique_key']);
        }

        if (isset($values['osds3d_personalization']) && is_array($values['osds3d_personalization'])) {
            $cart_item['osds3d_personalization'] = $values['osds3d_personalization'];
        }

        return $cart_item;
    }

    /**
     * Afficher les infos design dans le panier.
     *
     * @param array $item_data
     * @param array $cart_item
     * @return array
     */
    public function display_design_in_cart($item_data, $cart_item)
    {
        if (isset($cart_item['osds3d_design_id'])) {
            $item_data[] = array(
                'name'  => __('Design personnalisé', 'osds3d-customizer-pro'),
                'value' => '#' . intval($cart_item['osds3d_design_id']),
            );
        }

        if (!empty($cart_item['osds3d_personalization']) && is_array($cart_item['osds3d_personalization'])) {
            $personalization = $cart_item['osds3d_personalization'];

            if (!empty($personalization['business_family'])) {
                $item_data[] = array(
                    'name'  => __('Famille OSDS3D', 'osds3d-customizer-pro'),
                    'value' => sanitize_text_field($personalization['business_family']),
                );
            }

            $item_data[] = array(
                'name'  => __('Type OSDS3D', 'osds3d-customizer-pro'),
                'value' => isset($personalization['product_type']) ? sanitize_text_field($personalization['product_type']) : 'simple',
            );

            $item_data[] = array(
                'name'  => __('Mode OSDS3D', 'osds3d-customizer-pro'),
                'value' => isset($personalization['personalization_mode']) ? sanitize_text_field($personalization['personalization_mode']) : 'none',
            );

            if (!empty($personalization['customer_inputs']['filament'])) {
                $item_data[] = array(
                    'name'  => __('Materiau', 'osds3d-customizer-pro'),
                    'value' => sanitize_text_field($personalization['customer_inputs']['filament']),
                );
            }

            if (!empty($personalization['customer_inputs']['color'])) {
                $item_data[] = array(
                    'name'  => __('Couleur', 'osds3d-customizer-pro'),
                    'value' => sanitize_text_field($personalization['customer_inputs']['color']),
                );
            }

            if (isset($personalization['production_specs']['infill']) && $personalization['production_specs']['infill'] !== '') {
                $item_data[] = array(
                    'name'  => __('Remplissage atelier', 'osds3d-customizer-pro'),
                    'value' => intval($personalization['production_specs']['infill']) . '%',
                );
            }

            if (isset($personalization['production_specs']['layer_height']) && $personalization['production_specs']['layer_height'] !== '') {
                $item_data[] = array(
                    'name'  => __('Hauteur de couche atelier', 'osds3d-customizer-pro'),
                    'value' => floatval($personalization['production_specs']['layer_height']) . ' mm',
                );
            }

            if (!empty($personalization['production_specs']['supports'])) {
                $item_data[] = array(
                    'name'  => __('Supports atelier', 'osds3d-customizer-pro'),
                    'value' => $personalization['production_specs']['supports'] === 'yes'
                        ? __('Oui', 'osds3d-customizer-pro')
                        : __('Non', 'osds3d-customizer-pro'),
                );
            }

            if (!empty($personalization['production_specs']['atelier_notes'])) {
                $item_data[] = array(
                    'name'  => __('Notes atelier', 'osds3d-customizer-pro'),
                    'value' => nl2br(esc_html($personalization['production_specs']['atelier_notes'])),
                );
            }

            if (!empty($personalization['uploaded_files'][0]['url'])) {
                $file_url = esc_url($personalization['uploaded_files'][0]['url']);
                $file_name = !empty($personalization['uploaded_files'][0]['name'])
                    ? sanitize_text_field($personalization['uploaded_files'][0]['name'])
                    : __('Fichier 3D', 'osds3d-customizer-pro');

                $item_data[] = array(
                    'name'    => __('Fichier 3D', 'osds3d-customizer-pro'),
                    'value'   => wp_kses_post('<a href="' . $file_url . '" target="_blank" rel="noopener noreferrer">' . esc_html($file_name) . '</a>'),
                    'display' => wp_kses_post('<a href="' . $file_url . '" target="_blank" rel="noopener noreferrer">' . esc_html($file_name) . '</a>'),
                );
            }

            if (!empty($personalization['customer_inputs']['notes'])) {
                $item_data[] = array(
                    'name'  => __('Notes', 'osds3d-customizer-pro'),
                    'value' => nl2br(esc_html($personalization['customer_inputs']['notes'])),
                );
            }
        }

        if (!empty($cart_item['osds3d_preview_url'])) {
            $preview = '<img src="' . esc_url($cart_item['osds3d_preview_url']) . '" alt="" style="max-width:80px;height:auto;border:1px solid #ddd;border-radius:6px;" />';

            $item_data[] = array(
                'name'    => __('Aperçu', 'osds3d-customizer-pro'),
                'value'   => wp_kses_post($preview),
                'display' => wp_kses_post($preview),
            );
        }

        return $item_data;
    }

    /**
     * Enregistrer les infos design dans la ligne de commande.
     *
     * @param WC_Order_Item_Product $item
     * @param string                $cart_item_key
     * @param array                 $values
     * @param WC_Order              $order
     * @return void
     */
    public function save_design_to_order($item, $cart_item_key, $values, $order)
    {
        if (isset($values['osds3d_design_id'])) {
            $item->add_meta_data('_osds3d_design_id', intval($values['osds3d_design_id']), true);
        }

        if (!empty($values['osds3d_preview_url'])) {
            $item->add_meta_data('_osds3d_preview_url', esc_url_raw($values['osds3d_preview_url']), true);
        }

        if (!empty($values['osds3d_design_data'])) {
            $item->add_meta_data('_osds3d_design_data', $values['osds3d_design_data'], true);
        }

        if (!empty($values['osds3d_personalization']) && is_array($values['osds3d_personalization'])) {
            $item->add_meta_data('_osds3d_personalization', $values['osds3d_personalization'], true);
        }
    }

    /**
     * Affichage admin simple dans la commande.
     *
     * @param int           $item_id
     * @param WC_Order_Item $item
     * @param WC_Product    $product
     * @return void
     */
    public function display_design_in_admin_order($item_id, $item, $product)
    {
        if (!is_admin() || !is_object($item) || !method_exists($item, 'get_meta')) {
            return;
        }

        $design_id  = $item->get_meta('_osds3d_design_id', true);
        $preview_url = $item->get_meta('_osds3d_preview_url', true);
        $personalization = $item->get_meta('_osds3d_personalization', true);

        if (!$design_id && !$preview_url && empty($personalization)) {
            return;
        }

        echo '<div class="osds3d-admin-order-design" style="margin-top:8px;padding:8px;border:1px solid #ddd;border-radius:6px;background:#fafafa;">';

        if ($design_id) {
            echo '<p style="margin:0 0 6px;"><strong>' . esc_html__('Design ID', 'osds3d-customizer-pro') . ':</strong> #' . intval($design_id) . '</p>';
        }

        if ($preview_url) {
            echo '<p style="margin:0;"><img src="' . esc_url($preview_url) . '" alt="" style="max-width:120px;height:auto;border:1px solid #ddd;border-radius:6px;" /></p>';
        }

        if (is_array($personalization)) {
            $business_family = isset($personalization['business_family']) ? sanitize_text_field($personalization['business_family']) : '';
            $product_type = isset($personalization['product_type']) ? sanitize_text_field($personalization['product_type']) : 'simple';
            $mode = isset($personalization['personalization_mode']) ? sanitize_text_field($personalization['personalization_mode']) : 'none';

            if ($business_family !== '') {
                echo '<p style="margin:6px 0 0;"><strong>' . esc_html__('Famille', 'osds3d-customizer-pro') . ':</strong> ' . esc_html($business_family) . '</p>';
            }
            echo '<p style="margin:6px 0 0;"><strong>' . esc_html__('Type', 'osds3d-customizer-pro') . ':</strong> ' . esc_html($product_type) . '</p>';
            echo '<p style="margin:2px 0 0;"><strong>' . esc_html__('Mode', 'osds3d-customizer-pro') . ':</strong> ' . esc_html($mode) . '</p>';

            if (!empty($personalization['customer_inputs']['filament'])) {
                echo '<p style="margin:2px 0 0;"><strong>' . esc_html__('Materiau', 'osds3d-customizer-pro') . ':</strong> ' . esc_html($personalization['customer_inputs']['filament']) . '</p>';
            }

            if (!empty($personalization['customer_inputs']['color'])) {
                echo '<p style="margin:2px 0 0;"><strong>' . esc_html__('Couleur', 'osds3d-customizer-pro') . ':</strong> ' . esc_html($personalization['customer_inputs']['color']) . '</p>';
            }

            if (isset($personalization['production_specs']['infill']) && $personalization['production_specs']['infill'] !== '') {
                echo '<p style="margin:2px 0 0;"><strong>' . esc_html__('Remplissage atelier', 'osds3d-customizer-pro') . ':</strong> ' . intval($personalization['production_specs']['infill']) . '%</p>';
            }

            if (isset($personalization['production_specs']['layer_height']) && $personalization['production_specs']['layer_height'] !== '') {
                echo '<p style="margin:2px 0 0;"><strong>' . esc_html__('Hauteur de couche atelier', 'osds3d-customizer-pro') . ':</strong> ' . floatval($personalization['production_specs']['layer_height']) . ' mm</p>';
            }

            if (!empty($personalization['production_specs']['supports'])) {
                echo '<p style="margin:2px 0 0;"><strong>' . esc_html__('Supports atelier', 'osds3d-customizer-pro') . ':</strong> ' . esc_html($personalization['production_specs']['supports'] === 'yes' ? __('Oui', 'osds3d-customizer-pro') : __('Non', 'osds3d-customizer-pro')) . '</p>';
            }

            if (!empty($personalization['production_specs']['atelier_notes'])) {
                echo '<p style="margin:2px 0 0;"><strong>' . esc_html__('Notes atelier', 'osds3d-customizer-pro') . ':</strong> ' . nl2br(esc_html($personalization['production_specs']['atelier_notes'])) . '</p>';
            }

            if (!empty($personalization['uploaded_files'][0]['url'])) {
                $file_url = esc_url($personalization['uploaded_files'][0]['url']);
                $file_name = !empty($personalization['uploaded_files'][0]['name'])
                    ? sanitize_text_field($personalization['uploaded_files'][0]['name'])
                    : __('Fichier 3D', 'osds3d-customizer-pro');
                echo '<p style="margin:2px 0 0;"><strong>' . esc_html__('Fichier 3D', 'osds3d-customizer-pro') . ':</strong> <a href="' . $file_url . '" target="_blank" rel="noopener noreferrer">' . esc_html($file_name) . '</a></p>';
            }

            if (!empty($personalization['customer_inputs']['notes'])) {
                echo '<p style="margin:2px 0 0;"><strong>' . esc_html__('Notes', 'osds3d-customizer-pro') . ':</strong> ' . nl2br(esc_html($personalization['customer_inputs']['notes'])) . '</p>';
            }
        }

        echo '</div>';
    }

    /**
     * Envoyer une notification lorsqu'une commande contenant un design est créée.
     *
     * @param int $order_id
     * @return void
     */
    /**
     * Verification complementaire du contexte de propriete du design.
     *
     * @param bool $passed
     * @param int  $product_id
     * @param int  $quantity
     * @return bool
     */
    public function validate_design_context_before_add_to_cart($passed, $product_id, $quantity)
    {
        if (!$passed) {
            return false;
        }

        if (!isset($_POST['osds3d_design_id']) || empty($_POST['osds3d_design_id'])) {
            return $passed;
        }

        $design_id = intval(wp_unslash($_POST['osds3d_design_id']));

        if ($design_id <= 0) {
            return false;
        }

        if (!class_exists('OSDS3D_Database') || !method_exists('OSDS3D_Database', 'get_design')) {
            wc_add_notice(__('Base de donnees design indisponible.', 'osds3d-customizer-pro'), 'error');
            return false;
        }

        $design = OSDS3D_Database::get_design($design_id);

        if (!$design) {
            wc_add_notice(__('Le design selectionne est introuvable.', 'osds3d-customizer-pro'), 'error');
            return false;
        }

        if (!$this->current_context_can_use_design($design)) {
            wc_add_notice(__('Ce design n’appartient pas a votre session actuelle.', 'osds3d-customizer-pro'), 'error');
            return false;
        }

        if ((int) $design->product_id > 0 && (int) $design->product_id !== (int) $product_id) {
            wc_add_notice(__('Ce design ne correspond pas au produit selectionne.', 'osds3d-customizer-pro'), 'error');
            return false;
        }

        return $passed;
    }

    /**
     * Validation minimale du mode print_3d_form.
     *
     * @param bool $passed
     * @param int  $product_id
     * @param int  $quantity
     * @return bool
     */
    public function validate_print_3d_form_before_add_to_cart($passed, $product_id, $quantity)
    {
        if (!$passed || !$this->is_print_3d_form_product($product_id)) {
            return $passed;
        }

        $settings = $this->get_print_3d_form_settings($product_id);
        $material = isset($_POST['osds3d_print3d_filament']) ? sanitize_key(wp_unslash($_POST['osds3d_print3d_filament'])) : '';
        $color = isset($_POST['osds3d_print3d_color']) ? sanitize_text_field(wp_unslash($_POST['osds3d_print3d_color'])) : '';
        $materials = !empty($settings['materials']) ? $settings['materials'] : array();
        $selected_material = null;

        if (!empty($materials)) {
            foreach ($materials as $material_config) {
                if (!empty($material_config['key']) && sanitize_key($material_config['key']) === $material) {
                    $selected_material = $material_config;
                    break;
                }
            }

            if (!$selected_material) {
                wc_add_notice(__('Veuillez choisir un materiau valide.', 'osds3d-customizer-pro'), 'error');
                return false;
            }

            $available_colors = !empty($selected_material['colors']) && is_array($selected_material['colors'])
                ? array_values(array_map('sanitize_text_field', $selected_material['colors']))
                : array();

            if (!empty($available_colors) && ($color === '' || !in_array($color, $available_colors, true))) {
                wc_add_notice(__('Veuillez choisir une couleur valide pour ce materiau.', 'osds3d-customizer-pro'), 'error');
                return false;
            }
        } else {
            $filament = isset($_POST['osds3d_print3d_filament']) ? sanitize_text_field(wp_unslash($_POST['osds3d_print3d_filament'])) : '';

            if (!empty($settings['filament_choices']) && ($filament === '' || !in_array($filament, $settings['filament_choices'], true))) {
                wc_add_notice(__('Veuillez choisir un filament valide.', 'osds3d-customizer-pro'), 'error');
                return false;
            }

            if (!empty($settings['color_choices']) && ($color === '' || !in_array($color, $settings['color_choices'], true))) {
                wc_add_notice(__('Veuillez choisir une couleur valide.', 'osds3d-customizer-pro'), 'error');
                return false;
            }
        }

        if (!empty($settings['allow_upload']) && !empty($_FILES['osds3d_print3d_file']['name'])) {
            $filename = sanitize_file_name(wp_unslash($_FILES['osds3d_print3d_file']['name']));
            $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

            if (!in_array($extension, array('stl', 'obj', '3mf'), true)) {
                wc_add_notice(__('Le fichier 3D doit etre au format STL, OBJ ou 3MF.', 'osds3d-customizer-pro'), 'error');
                return false;
            }
        }

        return $passed;
    }

    /**
     * Ajouter les donnees du formulaire print_3d_form au panier.
     *
     * @param array $cart_item_data
     * @param int   $product_id
     * @return array
     */
    public function add_print_3d_form_to_cart($cart_item_data, $product_id)
    {
        if (!$this->is_print_3d_form_product($product_id)) {
            return $cart_item_data;
        }

        $settings = $this->get_print_3d_form_settings($product_id);
        $payload = isset($cart_item_data['osds3d_personalization']) && is_array($cart_item_data['osds3d_personalization'])
            ? $cart_item_data['osds3d_personalization']
            : $this->build_personalization_payload($product_id, 0, null);

        $material_key = isset($_POST['osds3d_print3d_filament']) ? sanitize_key(wp_unslash($_POST['osds3d_print3d_filament'])) : '';
        $selected_material = null;

        if (!empty($settings['materials'])) {
            foreach ($settings['materials'] as $material) {
                if (!empty($material['key']) && sanitize_key($material['key']) === $material_key) {
                    $selected_material = $material;
                    break;
                }
            }
        }

        $payload['customer_inputs'] = array(
            'material_key' => $material_key,
            'filament' => $selected_material && !empty($selected_material['label'])
                ? sanitize_text_field($selected_material['label'])
                : (isset($_POST['osds3d_print3d_filament']) ? sanitize_text_field(wp_unslash($_POST['osds3d_print3d_filament'])) : ''),
            'color' => isset($_POST['osds3d_print3d_color']) ? sanitize_text_field(wp_unslash($_POST['osds3d_print3d_color'])) : '',
            'notes' => isset($_POST['osds3d_print3d_notes']) ? sanitize_textarea_field(wp_unslash($_POST['osds3d_print3d_notes'])) : '',
        );

        $payload['production_specs'] = array(
            'infill' => isset($settings['defaults']['infill']) ? $settings['defaults']['infill'] : '',
            'layer_height' => isset($settings['defaults']['layer_height']) ? $settings['defaults']['layer_height'] : '',
            'supports' => isset($settings['defaults']['supports']) ? $settings['defaults']['supports'] : '',
            'atelier_notes' => isset($settings['defaults']['atelier_notes']) ? $settings['defaults']['atelier_notes'] : '',
        );

        $payload['uploaded_files'] = array();

        if (!empty($settings['allow_upload']) && !empty($_FILES['osds3d_print3d_file']['name'])) {
            require_once ABSPATH . 'wp-admin/includes/file.php';

            $uploaded = wp_handle_upload(
                $_FILES['osds3d_print3d_file'],
                array(
                    'test_form' => false,
                    'mimes' => array(
                        'stl' => 'model/stl',
                        'obj' => 'text/plain',
                        '3mf' => 'application/vnd.ms-package.3dmanufacturing-3dmodel+xml',
                    ),
                )
            );

            if (!isset($uploaded['error']) && !empty($uploaded['url'])) {
                $payload['uploaded_files'][] = array(
                    'type' => 'model_3d',
                    'name' => !empty($_FILES['osds3d_print3d_file']['name']) ? sanitize_file_name(wp_unslash($_FILES['osds3d_print3d_file']['name'])) : '',
                    'url' => esc_url_raw($uploaded['url']),
                );
            }
        }

        $payload['summary_label'] = __('Impression 3D personnalisee', 'osds3d-customizer-pro');

        $cart_item_data['osds3d_personalization'] = $payload;
        $cart_item_data['osds3d_unique_key'] = md5($product_id . '|print3d|' . microtime(true) . '|' . wp_rand());

        return $cart_item_data;
    }

    public function maybe_notify_new_order($order_id)
    {
        $order = wc_get_order($order_id);

        if (!$order) {
            return;
        }

        $has_design = false;

        foreach ($order->get_items() as $item) {
            if (!is_object($item) || !method_exists($item, 'get_meta')) {
                continue;
            }

            $design_id = $item->get_meta('_osds3d_design_id', true);

            if ($design_id) {
                $has_design = true;
                break;
            }
        }

        if ($has_design && class_exists('OSDS3D_Email') && method_exists('OSDS3D_Email', 'send_design_order_notification')) {
            OSDS3D_Email::send_design_order_notification($order_id);
        }
    }
}
