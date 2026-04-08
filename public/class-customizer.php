<?php
/**
 * Classe d'interface publique pour OSDS3D Customizer Pro
 *
 * Gère :
 * - le shortcode [osds3d_customizer]
 * - le chargement conditionnel des assets
 * - l'inclusion du template public
 * - la transmission des données produit / 3D au front
 *
 * @package OSDS3D_Customizer_Pro
 */

if (!defined('ABSPATH')) {
    exit;
}

class OSDS3D_Customizer
{
    /**
     * Produit courant détecté pour le shortcode.
     *
     * @var int
     */
    protected static $detected_product_id = 0;

    /**
     * Indique si la requete courante doit charger les assets du customizer.
     *
     * @var bool|null
     */
    protected static $should_enqueue_assets = null;

    /**
     * Initialiser les hooks publics.
     *
     * @return void
     */
    public static function init()
    {
        add_shortcode('osds3d_customizer', array(__CLASS__, 'render_shortcode'));
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_assets'));
        add_filter('script_loader_tag', array(__CLASS__, 'add_module_to_three_script'), 10, 3);
    }

    /**
     * Ajouter type="module" au script 3D.
     *
     * @param string $tag
     * @param string $handle
     * @param string $src
     * @return string
     */
    public static function add_module_to_three_script($tag, $handle, $src)
    {
        if ($handle === 'osds3d-three-customizer') {
            return '<script type="module" id="' . esc_attr($handle) . '-js" src="' . esc_url($src) . '"></script>';
        }

        return $tag;
    }

    /**
     * Vérifie si la page courante contient le shortcode.
     *
     * @return bool
     */
    protected static function has_customizer_shortcode()
    {
        if (!is_singular()) {
            return false;
        }

        global $post;

        if (!$post || empty($post->post_content)) {
            return false;
        }

        return has_shortcode($post->post_content, 'osds3d_customizer');
    }

    /**
     * Verifie si la requete courante correspond probablement a une page customizer.
     *
     * Cette verification est volontairement un peu plus large que la simple
     * detection du shortcode dans post_content afin de mieux couvrir les
     * rendus dynamiques courants (notamment Divi).
     *
     * @return bool
     */
    protected static function should_enqueue_assets()
    {
        if (self::$should_enqueue_assets !== null) {
            return self::$should_enqueue_assets;
        }

        if (!is_singular()) {
            self::$should_enqueue_assets = false;
            return self::$should_enqueue_assets;
        }

        if (self::has_customizer_shortcode()) {
            self::$should_enqueue_assets = true;
            return self::$should_enqueue_assets;
        }

        global $post;

        $current_post_id = ($post && isset($post->ID)) ? intval($post->ID) : 0;
        $requested_product_id = isset($_GET['product_id']) ? intval(wp_unslash($_GET['product_id'])) : 0;
        $configured_page_id = 0;

        if (class_exists('OSDS3D_Settings')) {
            $configured_page_id = intval(OSDS3D_Settings::get('customizer_page_id', 0));
        }

        if ($configured_page_id > 0 && $current_post_id === $configured_page_id) {
            self::$should_enqueue_assets = true;
            return self::$should_enqueue_assets;
        }

        if ($requested_product_id > 0 && $configured_page_id > 0 && $current_post_id === $configured_page_id) {
            self::$should_enqueue_assets = true;
            return self::$should_enqueue_assets;
        }

        self::$should_enqueue_assets = false;
        return self::$should_enqueue_assets;
    }

    /**
     * Tente de détecter l'ID produit depuis le contenu du shortcode.
     *
     * @return int
     */
    protected static function detect_product_id_from_post_content()
    {
        if (!is_singular()) {
            return 0;
        }

        global $post;

        if (!$post || empty($post->post_content)) {
            return 0;
        }

        if (!has_shortcode($post->post_content, 'osds3d_customizer')) {
            return 0;
        }

        if (preg_match('/\[osds3d_customizer([^\]]*)\]/', $post->post_content, $matches)) {
            $shortcode_raw_atts = isset($matches[1]) ? $matches[1] : '';

            if (!empty($shortcode_raw_atts)) {
                $atts = shortcode_parse_atts($shortcode_raw_atts);

                if (!empty($atts['product_id'])) {
                    return intval($atts['product_id']);
                }
            }
        }

        return 0;
    }

    /**
     * Retourne l'ID produit depuis l'URL, les attributs shortcode ou la détection.
     *
     * @param array $atts
     * @return int
     */
    protected static function get_product_id($atts = array())
    {
        if (!empty($atts['product_id'])) {
            return intval($atts['product_id']);
        }

        if (self::$detected_product_id > 0) {
            return intval(self::$detected_product_id);
        }

        if (isset($_GET['product_id'])) {
            return intval(wp_unslash($_GET['product_id']));
        }

        $detected = self::detect_product_id_from_post_content();
        if ($detected > 0) {
            return $detected;
        }

        return 0;
    }

    /**
     * Retourne l'ID produit courant pour les templates publics.
     *
     * @return int
     */
    public static function get_current_product_id()
    {
        return self::get_product_id();
    }

    /**
     * Retourne une version de fichier pour limiter les soucis de cache.
     *
     * @param string $relative_path
     * @return string
     */
    protected static function get_asset_version($relative_path)
    {
        if (!defined('OSDS3D_PRO_PLUGIN_DIR')) {
            return defined('OSDS3D_PRO_VERSION') ? OSDS3D_PRO_VERSION : '1.0.0';
        }

        $full_path = OSDS3D_PRO_PLUGIN_DIR . ltrim($relative_path, '/');

        if (file_exists($full_path)) {
            return (string) filemtime($full_path);
        }

        return defined('OSDS3D_PRO_VERSION') ? OSDS3D_PRO_VERSION : '1.0.0';
    }

    /**
     * Récupère les données 3D liées à un produit.
     *
     * @param int $product_id
     * @return array
     */
    protected static function get_product_3d_data($product_id)
    {
        $product_id = intval($product_id);

        $product_config = class_exists('OSDS3D_WooCommerce') && method_exists('OSDS3D_WooCommerce', 'get_product_personalization_config')
            ? OSDS3D_WooCommerce::get_product_personalization_config($product_id)
            : array();

        $data = array(
            'enabled'          => false,
            'mode'             => 'customizer_2d',
            'model_id'         => 0,
            'model_url'        => '',
            'model_name'       => '',
            'model_format'     => '',
            'background_url'   => '',
            'printable_width'  => 0,
            'printable_height' => 0,
            'bleed'            => 0,
            'safe'             => 0,
            'printable_mesh'   => '',
            'excluded_meshes'  => array(),
            'default_rotation' => array(
                'x' => 0,
                'y' => 0,
                'z' => 0,
            ),
            'camera_distance'  => 0,
        );

        if ($product_id <= 0) {
            return $data;
        }

        $enabled  = get_post_meta($product_id, '_osds3d_customizer_enabled', true);
        $mode     = get_post_meta($product_id, '_osds3d_customizer_mode', true);
        $model_id = intval(get_post_meta($product_id, '_osds3d_model_3d_id', true));

        $data['enabled']          = !empty($product_config) ? !empty($product_config['enabled']) : ($enabled === '1');
        $data['mode']             = !empty($product_config['personalization_mode']) ? (string) $product_config['personalization_mode'] : ($mode ? $mode : 'customizer_2d');
        $data['model_id']         = !empty($product_config['resources']['model_3d_id']) ? intval($product_config['resources']['model_3d_id']) : $model_id;
        $data['background_url']   = !empty($product_config['options']['background_url']) ? (string) $product_config['options']['background_url'] : (string) get_post_meta($product_id, '_osds3d_model_bg', true);
        $data['printable_width']  = !empty($product_config['options']['printable_width']) ? floatval($product_config['options']['printable_width']) : floatval(get_post_meta($product_id, '_osds3d_model_width', true));
        $data['printable_height'] = !empty($product_config['options']['printable_height']) ? floatval($product_config['options']['printable_height']) : floatval(get_post_meta($product_id, '_osds3d_model_height', true));
        $data['bleed']            = isset($product_config['options']['bleed']) ? floatval($product_config['options']['bleed']) : floatval(get_post_meta($product_id, '_osds3d_model_bleed', true));
        $data['safe']             = isset($product_config['options']['safe']) ? floatval($product_config['options']['safe']) : floatval(get_post_meta($product_id, '_osds3d_model_safe', true));

        $legacy_glb = (string) get_post_meta($product_id, '_osds3d_model_glb', true);

        if ($model_id > 0 && class_exists('OSDS3D_Database') && method_exists('OSDS3D_Database', 'get_model_3d')) {
            $model = OSDS3D_Database::get_model_3d($model_id);

            if ($model) {
                $data['model_url']    = !empty($model->model_url) ? (string) $model->model_url : '';
                $data['model_name']   = !empty($model->model_name) ? (string) $model->model_name : '';
                $data['model_format'] = !empty($model->model_format) ? strtolower((string) $model->model_format) : '';

                if (!empty($model->scene_config) && is_array($model->scene_config)) {
                    $data['printable_mesh'] = !empty($model->scene_config['printable_mesh']) ? (string) $model->scene_config['printable_mesh'] : '';
                    $data['excluded_meshes'] = !empty($model->scene_config['excluded_meshes']) && is_array($model->scene_config['excluded_meshes'])
                        ? array_values(array_map('sanitize_text_field', $model->scene_config['excluded_meshes']))
                        : array();
                    $data['default_rotation'] = !empty($model->scene_config['default_rotation']) && is_array($model->scene_config['default_rotation'])
                        ? array(
                            'x' => isset($model->scene_config['default_rotation']['x']) ? floatval($model->scene_config['default_rotation']['x']) : 0,
                            'y' => isset($model->scene_config['default_rotation']['y']) ? floatval($model->scene_config['default_rotation']['y']) : 0,
                            'z' => isset($model->scene_config['default_rotation']['z']) ? floatval($model->scene_config['default_rotation']['z']) : 0,
                        )
                        : $data['default_rotation'];
                    $data['camera_distance'] = isset($model->scene_config['camera_distance']) ? floatval($model->scene_config['camera_distance']) : 0;
                }
            }
        }

        if (empty($data['model_url']) && !empty($legacy_glb)) {
            $data['model_url']    = $legacy_glb;
            $data['model_format'] = 'glb';
        }

        return $data;
    }

    /**
     * Enfiler les scripts et styles nécessaires.
     *
     * @return void
     */
    public static function enqueue_assets()
    {
        if (!self::should_enqueue_assets()) {
            return;
        }

        if (!defined('OSDS3D_PRO_PLUGIN_URL') || !defined('OSDS3D_PRO_PLUGIN_DIR')) {
            return;
        }

        $plugin_url = OSDS3D_PRO_PLUGIN_URL;
        $product_id = self::get_product_id();
        $product_3d = self::get_product_3d_data($product_id);

        wp_enqueue_style(
            'osds3d-customizer-style',
            $plugin_url . 'public/css/customizer-style.css',
            array(),
            self::get_asset_version('public/css/customizer-style.css')
        );

        if (class_exists('OSDS3D_Database') && method_exists('OSDS3D_Database', 'get_fonts')) {
            $fonts = OSDS3D_Database::get_fonts(true);

            if (!empty($fonts)) {
                $font_css = '';

                foreach ($fonts as $font) {
                    $format = isset($font->font_format) ? strtolower($font->font_format) : '';
                    $src    = isset($font->font_url) ? esc_url($font->font_url) : '';
                    $family = !empty($font->font_family) ? $font->font_family : (!empty($font->font_name) ? $font->font_name : '');

                    if (!$src || !$family) {
                        continue;
                    }

                    $css_format = '';
                    switch ($format) {
                        case 'ttf':
                            $css_format = 'truetype';
                            break;
                        case 'otf':
                            $css_format = 'opentype';
                            break;
                        case 'woff':
                            $css_format = 'woff';
                            break;
                        case 'woff2':
                            $css_format = 'woff2';
                            break;
                    }

                    if ($css_format) {
                        $font_css .= "@font-face{font-family:'" . esc_attr($family) . "';src:url('" . $src . "') format('" . esc_attr($css_format) . "');font-weight:normal;font-style:normal;font-display:swap;}\n";
                    }
                }

                if (!empty($font_css)) {
                    wp_add_inline_style('osds3d-customizer-style', $font_css);
                }
            }
        }

        wp_enqueue_script('jquery');

        wp_enqueue_script(
            'fabric-js',
            'https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.0/fabric.min.js',
            array(),
            '5.3.0',
            true
        );

        wp_enqueue_script(
            'qrcode-js',
            'https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js',
            array(),
            '1.0.0',
            true
        );

        wp_enqueue_script(
            'osds3d-three-customizer',
            $plugin_url . 'public/js/three-customizer.js',
            array(),
            self::get_asset_version('public/js/three-customizer.js'),
            true
        );

        wp_enqueue_script(
            'osds3d-fabric-customizer',
            $plugin_url . 'public/js/fabric-customizer.js',
            array('jquery', 'fabric-js', 'qrcode-js'),
            self::get_asset_version('public/js/fabric-customizer.js'),
            true
        );

        $templates = array();

        if (
            $product_id > 0 &&
            class_exists('OSDS3D_Database') &&
            method_exists('OSDS3D_Database', 'get_templates')
        ) {
            $template_objects = OSDS3D_Database::get_templates($product_id);

            if (!empty($template_objects)) {
                foreach ($template_objects as $tpl) {
                    $templates[] = array(
                        'id'          => isset($tpl->id) ? intval($tpl->id) : 0,
                        'name'        => isset($tpl->template_name) ? $tpl->template_name : '',
                        'category'    => isset($tpl->template_category) ? $tpl->template_category : '',
                        'data'        => isset($tpl->template_data) ? $tpl->template_data : '',
                        'preview_url' => isset($tpl->preview_url) ? $tpl->preview_url : '',
                        'product_id'  => isset($tpl->product_id) ? intval($tpl->product_id) : 0,
                    );
                }
            }
        }

        wp_localize_script(
            'osds3d-fabric-customizer',
            'osds3d_customizer_params',
            array(
                'ajax_url'      => admin_url('admin-ajax.php'),
                'nonce'         => wp_create_nonce('osds3d_customizer'),
                'product_id'    => $product_id,
                'templates'     => $templates,
                'preview_3d'    => true,
                'canvas_width'  => 800,
                'canvas_height' => 600,
                'autosave'      => true,
                'multi_faces'   => true,
                'product'       => array(
                    'id'   => $product_id,
                    'mode' => $product_3d['mode'],
                ),
                'model3d'       => array(
                    'enabled'          => (bool) $product_3d['enabled'],
                    'mode'             => $product_3d['mode'],
                    'id'               => intval($product_3d['model_id']),
                    'url'              => !empty($product_3d['model_url']) ? esc_url($product_3d['model_url']) : '',
                    'name'             => sanitize_text_field($product_3d['model_name']),
                    'format'           => sanitize_text_field($product_3d['model_format']),
                    'background_url'   => !empty($product_3d['background_url']) ? esc_url($product_3d['background_url']) : '',
                    'printable_width'  => floatval($product_3d['printable_width']),
                    'printable_height' => floatval($product_3d['printable_height']),
                    'bleed'            => floatval($product_3d['bleed']),
                    'safe'             => floatval($product_3d['safe']),
                    'printable_mesh'   => sanitize_text_field($product_3d['printable_mesh']),
                    'excluded_meshes'  => !empty($product_3d['excluded_meshes']) && is_array($product_3d['excluded_meshes'])
                        ? array_values(array_map('sanitize_text_field', $product_3d['excluded_meshes']))
                        : array(),
                    'default_rotation' => array(
                        'x' => isset($product_3d['default_rotation']['x']) ? floatval($product_3d['default_rotation']['x']) : 0,
                        'y' => isset($product_3d['default_rotation']['y']) ? floatval($product_3d['default_rotation']['y']) : 0,
                        'z' => isset($product_3d['default_rotation']['z']) ? floatval($product_3d['default_rotation']['z']) : 0,
                    ),
                    'camera_distance'  => floatval($product_3d['camera_distance']),
                ),
                'safe_zone'     => array(
                    'top_percent'    => 10,
                    'right_percent'  => 12,
                    'bottom_percent' => 10,
                    'left_percent'   => 12,
                ),
                'i18n'          => array(
                    'save'            => __('Sauvegarder', 'osds3d-customizer-pro'),
                    'add_text'        => __('Ajouter du texte', 'osds3d-customizer-pro'),
                    'add_image'       => __('Ajouter une image', 'osds3d-customizer-pro'),
                    'add_rect'        => __('Ajouter un rectangle', 'osds3d-customizer-pro'),
                    'add_circle'      => __('Ajouter un cercle', 'osds3d-customizer-pro'),
                    'add_triangle'    => __('Ajouter un triangle', 'osds3d-customizer-pro'),
                    'add_star'        => __('Ajouter une étoile', 'osds3d-customizer-pro'),
                    'select_template' => __('Sélectionner un modèle', 'osds3d-customizer-pro'),
                    'saving'          => __('Enregistrement en cours', 'osds3d-customizer-pro'),
                    'confirm_clear'   => __('Êtes-vous sûr de vouloir tout effacer ?', 'osds3d-customizer-pro'),
                    'error_upload'    => __('Erreur lors du chargement du fichier.', 'osds3d-customizer-pro'),
                    'bold'            => __('Gras', 'osds3d-customizer-pro'),
                    'italic'          => __('Italique', 'osds3d-customizer-pro'),
                    'underline'       => __('Souligné', 'osds3d-customizer-pro'),
                    'stroke'          => __('Contour', 'osds3d-customizer-pro'),
                    'stroke_width'    => __('Épaisseur contour', 'osds3d-customizer-pro'),
                    'shadow'          => __('Ombre', 'osds3d-customizer-pro'),
                    'undo'            => __('Annuler', 'osds3d-customizer-pro'),
                    'redo'            => __('Rétablir', 'osds3d-customizer-pro'),
                    'grid'            => __('Grille', 'osds3d-customizer-pro'),
                    'zoom_in'         => __('Zoom +', 'osds3d-customizer-pro'),
                    'zoom_out'        => __('Zoom -', 'osds3d-customizer-pro'),
                    'duplicate'       => __('Dupliquer', 'osds3d-customizer-pro'),
                    'lock'            => __('Verrouiller', 'osds3d-customizer-pro'),
                    'unlock'          => __('Déverrouiller', 'osds3d-customizer-pro'),
                    'bring_forward'   => __('Avancer', 'osds3d-customizer-pro'),
                    'send_backward'   => __('Reculer', 'osds3d-customizer-pro'),
                    'download_png'    => __('Télécharger PNG', 'osds3d-customizer-pro'),
                    'download_svg'    => __('Télécharger SVG', 'osds3d-customizer-pro'),
                    'download_pdf'    => __('Télécharger PDF', 'osds3d-customizer-pro'),
                    'show_preview3d'  => __('Voir l’aperçu 3D', 'osds3d-customizer-pro'),
                    'hide_preview3d'  => __('Masquer l’aperçu 3D', 'osds3d-customizer-pro'),
                ),
            )
        );
    }

    /**
     * Rendu du shortcode [osds3d_customizer]
     *
     * @param array  $atts
     * @param string $content
     * @return string
     */
    public static function render_shortcode($atts, $content = '')
    {
        $atts = shortcode_atts(
            array(
                'product_id' => 0,
            ),
            $atts,
            'osds3d_customizer'
        );

        $product_id = self::get_product_id($atts);
        self::$detected_product_id = $product_id;

        if (!$product_id) {
            return '<p>' . esc_html__('Aucun produit sélectionné.', 'osds3d-customizer-pro') . '</p>';
        }

        if (!defined('OSDS3D_PRO_PLUGIN_DIR')) {
            return '<p>' . esc_html__('Constante du plugin introuvable.', 'osds3d-customizer-pro') . '</p>';
        }

        $template_file = OSDS3D_PRO_PLUGIN_DIR . 'public/templates/customizer-2d.php';

        if (!file_exists($template_file)) {
            return '<p>' . esc_html__('Template du customizer introuvable.', 'osds3d-customizer-pro') . '</p>';
        }

        ob_start();
        include $template_file;
        return ob_get_clean();
    }
}
