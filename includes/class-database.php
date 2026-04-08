<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Gestion de la base de données pour OSDS3D.
 *
 * Cette classe contient les méthodes nécessaires à la création et à la
 * maintenance des tables utilisées par le plugin.
 */
class OSDS3D_Database
{
    /**
     * Initialisation générale.
     *
     * @return void
     */
    public static function init()
    {
        // Réservé pour futurs besoins.
    }

    /**
     * Créer toutes les tables nécessaires au plugin.
     *
     * @return void
     */
    public static function create_tables()
    {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // Table des designs
        $table_designs = $wpdb->prefix . 'osds3d_designs';
        $sql_designs = "CREATE TABLE $table_designs (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) DEFAULT NULL,
            product_id bigint(20) NOT NULL,
            design_type varchar(10) DEFAULT '2d',
            design_name varchar(255) DEFAULT NULL,
            design_data longtext NOT NULL,
            preview_url varchar(500) DEFAULT NULL,
            preview_3d_url varchar(500) DEFAULT NULL,
            order_id bigint(20) DEFAULT NULL,
            cart_item_key varchar(200) DEFAULT NULL,
            status varchar(20) DEFAULT 'pending',
            validation_notes text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY product_id (product_id),
            KEY user_id (user_id),
            KEY order_id (order_id),
            KEY status (status)
        ) $charset_collate;";

        // Table des polices
        $table_fonts = $wpdb->prefix . 'osds3d_fonts';
        $sql_fonts = "CREATE TABLE $table_fonts (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            font_name varchar(255) NOT NULL,
            font_url varchar(500) NOT NULL,
            font_family varchar(100) DEFAULT NULL,
            font_format varchar(10) DEFAULT NULL,
            is_active tinyint(1) DEFAULT 1,
            sort_order int(11) DEFAULT 0,
            uploaded_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY is_active (is_active),
            KEY sort_order (sort_order)
        ) $charset_collate;";

        // Table des cliparts
        $table_cliparts = $wpdb->prefix . 'osds3d_cliparts';
        $sql_cliparts = "CREATE TABLE $table_cliparts (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            clipart_name varchar(255) NOT NULL,
            clipart_url varchar(500) NOT NULL,
            clipart_category varchar(100) DEFAULT NULL,
            clipart_tags longtext DEFAULT NULL,
            width int(11) DEFAULT NULL,
            height int(11) DEFAULT NULL,
            file_size bigint(20) DEFAULT NULL,
            is_active tinyint(1) DEFAULT 1,
            sort_order int(11) DEFAULT 0,
            uploaded_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY is_active (is_active),
            KEY clipart_category (clipart_category),
            KEY sort_order (sort_order)
        ) $charset_collate;";

        // Table des templates
        $table_templates = $wpdb->prefix . 'osds3d_templates';
        $sql_templates = "CREATE TABLE $table_templates (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            product_id bigint(20) DEFAULT 0,
            template_name varchar(255) NOT NULL,
            template_category varchar(100) DEFAULT NULL,
            template_data longtext NOT NULL,
            preview_url varchar(500) DEFAULT NULL,
            is_premium tinyint(1) DEFAULT 0,
            usage_count int(11) DEFAULT 0,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY product_id (product_id),
            KEY is_active (is_active),
            KEY template_category (template_category)
        ) $charset_collate;";

        // Table des formes
        $table_shapes = $wpdb->prefix . 'osds3d_shapes';
        $sql_shapes = "CREATE TABLE $table_shapes (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            shape_name varchar(255) NOT NULL,
            shape_type varchar(50) NOT NULL,
            shape_svg_path longtext NOT NULL,
            shape_category varchar(100) DEFAULT NULL,
            is_active tinyint(1) DEFAULT 1,
            sort_order int(11) DEFAULT 0,
            PRIMARY KEY (id),
            KEY is_active (is_active),
            KEY shape_category (shape_category),
            KEY sort_order (sort_order)
        ) $charset_collate;";

        // Table des modèles 3D
        $table_models = $wpdb->prefix . 'osds3d_models_3d';
        $sql_models = "CREATE TABLE $table_models (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            model_name varchar(255) NOT NULL,
            model_url varchar(500) NOT NULL,
            model_format varchar(10) DEFAULT NULL,
            model_category varchar(100) DEFAULT NULL,
            file_size bigint(20) DEFAULT NULL,
            dimensions longtext DEFAULT NULL,
            is_active tinyint(1) DEFAULT 1,
            sort_order int(11) DEFAULT 0,
            uploaded_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY is_active (is_active),
            KEY model_category (model_category),
            KEY sort_order (sort_order)
        ) $charset_collate;";

        // Table des logs
        $table_logs = $wpdb->prefix . 'osds3d_logs';
        $sql_logs = "CREATE TABLE $table_logs (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) DEFAULT NULL,
            action_type varchar(100) NOT NULL,
            action_data longtext DEFAULT NULL,
            ip_address varchar(100) DEFAULT NULL,
            user_agent varchar(255) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY action_type (action_type),
            KEY user_id (user_id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        dbDelta($sql_designs);
        dbDelta($sql_fonts);
        dbDelta($sql_cliparts);
        dbDelta($sql_templates);
        dbDelta($sql_shapes);
        dbDelta($sql_models);
        dbDelta($sql_logs);
    }

    /**
     * Récupérer un design à partir de son identifiant.
     *
     * @param int $design_id
     * @return object|null
     */
    public static function get_design($design_id)
    {
        global $wpdb;

        $design_id = intval($design_id);
        if ($design_id <= 0) {
            return null;
        }

        $table = $wpdb->prefix . 'osds3d_designs';
        $design = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table WHERE id = %d LIMIT 1", $design_id)
        );

        if (!$design) {
            return null;
        }

        $design->design_payload = null;

        if (!empty($design->design_data)) {
            $decoded = json_decode($design->design_data, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $design->design_payload = $decoded;
            }
        }

        return $design;
    }

    /**
     * Mettre à jour un design existant.
     *
     * @param int   $design_id
     * @param array $data
     * @return bool
     */
    public static function update_design($design_id, $data)
    {
        global $wpdb;

        $design_id = intval($design_id);
        if ($design_id <= 0 || empty($data) || !is_array($data)) {
            return false;
        }

        $table = $wpdb->prefix . 'osds3d_designs';
        $fields = array();
        $formats = array();

        if (isset($data['design_type'])) {
            $fields['design_type'] = sanitize_text_field($data['design_type']);
            $formats[] = '%s';
        }

        if (isset($data['design_name'])) {
            $fields['design_name'] = sanitize_text_field($data['design_name']);
            $formats[] = '%s';
        }

        if (isset($data['design_data'])) {
            $fields['design_data'] = $data['design_data'];
            $formats[] = '%s';
        }

        if (isset($data['preview_url'])) {
            $fields['preview_url'] = esc_url_raw($data['preview_url']);
            $formats[] = '%s';
        }

        if (isset($data['preview_3d_url'])) {
            $fields['preview_3d_url'] = esc_url_raw($data['preview_3d_url']);
            $formats[] = '%s';
        }

        if (isset($data['order_id'])) {
            $fields['order_id'] = intval($data['order_id']);
            $formats[] = '%d';
        }

        if (isset($data['cart_item_key'])) {
            $fields['cart_item_key'] = sanitize_text_field($data['cart_item_key']);
            $formats[] = '%s';
        }

        if (isset($data['status'])) {
            $fields['status'] = sanitize_text_field($data['status']);
            $formats[] = '%s';
        }

        if (isset($data['validation_notes'])) {
            $fields['validation_notes'] = sanitize_textarea_field($data['validation_notes']);
            $formats[] = '%s';
        }

        if (empty($fields)) {
            return false;
        }

        $fields['updated_at'] = current_time('mysql');
        $formats[] = '%s';

        return (bool) $wpdb->update(
            $table,
            $fields,
            array('id' => $design_id),
            $formats,
            array('%d')
        );
    }

    /**
     * Supprimer un design.
     *
     * @param int $design_id
     * @return bool
     */
    public static function delete_design($design_id)
    {
        global $wpdb;

        $design_id = intval($design_id);
        if ($design_id <= 0) {
            return false;
        }

        $table = $wpdb->prefix . 'osds3d_designs';

        return (bool) $wpdb->delete(
            $table,
            array('id' => $design_id),
            array('%d')
        );
    }

    /**
     * Retourner uniquement l'URL de preview d'un design.
     *
     * @param int $design_id
     * @return string
     */
    public static function get_design_preview_url($design_id)
    {
        $design = self::get_design($design_id);

        if (!$design || empty($design->preview_url)) {
            return '';
        }

        return $design->preview_url;
    }

    /**
     * Ajouter un template.
     *
     * @param array $data
     * @return int|false
     */
    public static function add_template($data)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'osds3d_templates';

        $defaults = array(
            'product_id'        => 0,
            'template_name'     => '',
            'template_category' => '',
            'template_data'     => '',
            'preview_url'       => '',
            'is_premium'        => 0,
            'usage_count'       => 0,
            'is_active'         => 1,
        );

        $data = wp_parse_args($data, $defaults);

        $inserted = $wpdb->insert(
            $table,
            array(
                'product_id'        => intval($data['product_id']),
                'template_name'     => sanitize_text_field($data['template_name']),
                'template_category' => sanitize_text_field($data['template_category']),
                'template_data'     => $data['template_data'],
                'preview_url'       => esc_url_raw($data['preview_url']),
                'is_premium'        => intval($data['is_premium']),
                'usage_count'       => intval($data['usage_count']),
                'is_active'         => intval($data['is_active']),
            ),
            array('%d', '%s', '%s', '%s', '%s', '%d', '%d', '%d')
        );

        if ($inserted) {
            return intval($wpdb->insert_id);
        }

        return false;
    }

    /**
     * Obtenir les templates disponibles.
     *
     * @param int $product_id
     * @return array
     */
    public static function get_templates($product_id = 0)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'osds3d_templates';
        $product_id = intval($product_id);

        if ($product_id > 0) {
            $sql = $wpdb->prepare(
                "SELECT * FROM $table WHERE is_active = 1 AND (product_id = 0 OR product_id = %d) ORDER BY sort_order ASC, id DESC",
                $product_id
            );
        } else {
            $sql = "SELECT * FROM $table WHERE is_active = 1 ORDER BY sort_order ASC, id DESC";
        }

        return $wpdb->get_results($sql);
    }

    /**
     * Supprimer un template.
     *
     * @param int $template_id
     * @return bool
     */
    public static function delete_template($template_id)
    {
        global $wpdb;

        $template_id = intval($template_id);
        if (!$template_id) {
            return false;
        }

        $table = $wpdb->prefix . 'osds3d_templates';

        return (bool) $wpdb->delete($table, array('id' => $template_id), array('%d'));
    }

    /**
     * Ajouter une police personnalisée.
     *
     * @param array $data
     * @return int|false
     */
    public static function add_font($data)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'osds3d_fonts';

        $defaults = array(
            'font_name'   => '',
            'font_url'    => '',
            'font_family' => '',
            'font_format' => '',
            'is_active'   => 1,
            'sort_order'  => 0,
        );

        $data = wp_parse_args($data, $defaults);

        if (empty($data['font_name']) || empty($data['font_url'])) {
            return false;
        }

        $inserted = $wpdb->insert(
            $table,
            array(
                'font_name'   => sanitize_text_field($data['font_name']),
                'font_url'    => esc_url_raw($data['font_url']),
                'font_family' => sanitize_text_field($data['font_family']),
                'font_format' => sanitize_text_field($data['font_format']),
                'is_active'   => intval($data['is_active']),
                'sort_order'  => intval($data['sort_order']),
            ),
            array('%s', '%s', '%s', '%s', '%d', '%d')
        );

        if ($inserted) {
            return intval($wpdb->insert_id);
        }

        return false;
    }

    /**
     * Récupérer les polices.
     *
     * @param bool  $active_only
     * @param array $ids
     * @return array
     */
    public static function get_fonts($active_only = true, $ids = array())
    {
        global $wpdb;

        $table = $wpdb->prefix . 'osds3d_fonts';
        $where = '1=1';
        $params = array();

        if ($active_only) {
            $where .= ' AND is_active = 1';
        }

        if (!empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '%d'));
            $where .= ' AND id IN (' . $placeholders . ')';
            $params = array_merge($params, array_map('intval', $ids));
        }

        $sql = "SELECT * FROM $table WHERE $where ORDER BY sort_order ASC, font_name ASC";

        if (!empty($params)) {
            return $wpdb->get_results($wpdb->prepare($sql, $params));
        }

        return $wpdb->get_results($sql);
    }

    /**
     * Mettre à jour une police.
     *
     * @param int   $font_id
     * @param array $data
     * @return bool
     */
    public static function update_font($font_id, $data)
    {
        global $wpdb;

        $font_id = intval($font_id);
        if (!$font_id) {
            return false;
        }

        $table = $wpdb->prefix . 'osds3d_fonts';
        $fields = array();
        $format = array();

        if (isset($data['font_name'])) {
            $fields['font_name'] = sanitize_text_field($data['font_name']);
            $format[] = '%s';
        }

        if (isset($data['font_url'])) {
            $fields['font_url'] = esc_url_raw($data['font_url']);
            $format[] = '%s';
        }

        if (isset($data['font_family'])) {
            $fields['font_family'] = sanitize_text_field($data['font_family']);
            $format[] = '%s';
        }

        if (isset($data['font_format'])) {
            $fields['font_format'] = sanitize_text_field($data['font_format']);
            $format[] = '%s';
        }

        if (isset($data['is_active'])) {
            $fields['is_active'] = intval($data['is_active']);
            $format[] = '%d';
        }

        if (isset($data['sort_order'])) {
            $fields['sort_order'] = intval($data['sort_order']);
            $format[] = '%d';
        }

        if (empty($fields)) {
            return false;
        }

        return (bool) $wpdb->update(
            $table,
            $fields,
            array('id' => $font_id),
            $format,
            array('%d')
        );
    }

    /**
     * Supprimer une police.
     *
     * @param int $font_id
     * @return bool
     */
    public static function delete_font($font_id)
    {
        global $wpdb;

        $font_id = intval($font_id);
        if (!$font_id) {
            return false;
        }

        $table = $wpdb->prefix . 'osds3d_fonts';

        return (bool) $wpdb->delete($table, array('id' => $font_id), array('%d'));
    }

    /**
     * Ajouter un modèle 3D.
     *
     * @param array $data
     * @return int|false
     */
    public static function add_model_3d($data)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'osds3d_models_3d';

        $defaults = array(
            'model_name'     => '',
            'model_url'      => '',
            'model_format'   => 'glb',
            'model_category' => '',
            'file_size'      => 0,
            'dimensions'     => '',
            'is_active'      => 1,
            'sort_order'     => 0,
        );

        $data = wp_parse_args($data, $defaults);

        if (empty($data['model_name']) || empty($data['model_url'])) {
            return false;
        }

        $inserted = $wpdb->insert(
            $table,
            array(
                'model_name'     => sanitize_text_field($data['model_name']),
                'model_url'      => esc_url_raw($data['model_url']),
                'model_format'   => sanitize_text_field($data['model_format']),
                'model_category' => sanitize_text_field($data['model_category']),
                'file_size'      => intval($data['file_size']),
                'dimensions'     => is_array($data['dimensions']) ? wp_json_encode($data['dimensions']) : $data['dimensions'],
                'is_active'      => intval($data['is_active']),
                'sort_order'     => intval($data['sort_order']),
            ),
            array('%s', '%s', '%s', '%s', '%d', '%s', '%d', '%d')
        );

        if ($inserted) {
            return intval($wpdb->insert_id);
        }

        return false;
    }

    /**
     * Récupérer un modèle 3D.
     *
     * @param int $model_id
     * @return object|null
     */
    public static function get_model_3d($model_id)
    {
        global $wpdb;

        $model_id = intval($model_id);
        if ($model_id <= 0) {
            return null;
        }

        $table = $wpdb->prefix . 'osds3d_models_3d';
        $model = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table WHERE id = %d LIMIT 1", $model_id)
        );

        if (!$model) {
            return null;
        }

        $model->dimensions_payload = null;

        if (!empty($model->dimensions)) {
            $decoded = json_decode($model->dimensions, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $model->dimensions_payload = $decoded;
            }
        }

        $model->scene_config = self::extract_model_3d_scene_config($model->dimensions_payload);

        return $model;
    }

    /**
     * Récupérer les modèles 3D.
     *
     * @param bool   $active_only
     * @param string $category
     * @return array
     */
    public static function get_models_3d($active_only = true, $category = '')
    {
        global $wpdb;

        $table = $wpdb->prefix . 'osds3d_models_3d';
        $where = '1=1';
        $params = array();

        if ($active_only) {
            $where .= ' AND is_active = 1';
        }

        if ($category !== '') {
            $where .= ' AND model_category = %s';
            $params[] = sanitize_text_field($category);
        }

        $sql = "SELECT * FROM $table WHERE $where ORDER BY sort_order ASC, model_name ASC";

        if (!empty($params)) {
            return $wpdb->get_results($wpdb->prepare($sql, $params));
        }

        return $wpdb->get_results($sql);
    }

    /**
     * Normaliser la configuration de scene 3D stockee dans dimensions.
     *
     * @param mixed $dimensions_payload
     * @return array
     */
    public static function extract_model_3d_scene_config($dimensions_payload)
    {
        $config = array(
            'printable_mesh' => '',
            'excluded_meshes' => array(),
            'default_rotation' => array(
                'x' => 0,
                'y' => 0,
                'z' => 0,
            ),
            'camera_distance' => 0,
        );

        if (!is_array($dimensions_payload) || empty($dimensions_payload['scene_config']) || !is_array($dimensions_payload['scene_config'])) {
            return $config;
        }

        $raw_config = $dimensions_payload['scene_config'];
        $config['printable_mesh'] = isset($raw_config['printable_mesh']) ? sanitize_text_field($raw_config['printable_mesh']) : '';

        if (!empty($raw_config['excluded_meshes'])) {
            if (is_string($raw_config['excluded_meshes'])) {
                $raw_config['excluded_meshes'] = array_map('trim', explode(',', $raw_config['excluded_meshes']));
            }

            if (is_array($raw_config['excluded_meshes'])) {
                $config['excluded_meshes'] = array_values(array_filter(array_map('sanitize_text_field', $raw_config['excluded_meshes'])));
            }
        }

        if (!empty($raw_config['default_rotation']) && is_array($raw_config['default_rotation'])) {
            $config['default_rotation'] = array(
                'x' => isset($raw_config['default_rotation']['x']) ? floatval($raw_config['default_rotation']['x']) : 0,
                'y' => isset($raw_config['default_rotation']['y']) ? floatval($raw_config['default_rotation']['y']) : 0,
                'z' => isset($raw_config['default_rotation']['z']) ? floatval($raw_config['default_rotation']['z']) : 0,
            );
        }

        if (isset($raw_config['camera_distance'])) {
            $config['camera_distance'] = max(0, floatval($raw_config['camera_distance']));
        }

        return $config;
    }

    /**
     * Mettre à jour un modèle 3D.
     *
     * @param int   $model_id
     * @param array $data
     * @return bool
     */
    public static function update_model_3d($model_id, $data)
    {
        global $wpdb;

        $model_id = intval($model_id);
        if ($model_id <= 0 || empty($data) || !is_array($data)) {
            return false;
        }

        $table = $wpdb->prefix . 'osds3d_models_3d';
        $fields = array();
        $formats = array();

        if (isset($data['model_name'])) {
            $fields['model_name'] = sanitize_text_field($data['model_name']);
            $formats[] = '%s';
        }

        if (isset($data['model_url'])) {
            $fields['model_url'] = esc_url_raw($data['model_url']);
            $formats[] = '%s';
        }

        if (isset($data['model_format'])) {
            $fields['model_format'] = sanitize_text_field($data['model_format']);
            $formats[] = '%s';
        }

        if (isset($data['model_category'])) {
            $fields['model_category'] = sanitize_text_field($data['model_category']);
            $formats[] = '%s';
        }

        if (isset($data['file_size'])) {
            $fields['file_size'] = intval($data['file_size']);
            $formats[] = '%d';
        }

        if (isset($data['dimensions'])) {
            $fields['dimensions'] = is_array($data['dimensions']) ? wp_json_encode($data['dimensions']) : $data['dimensions'];
            $formats[] = '%s';
        }

        if (isset($data['is_active'])) {
            $fields['is_active'] = intval($data['is_active']);
            $formats[] = '%d';
        }

        if (isset($data['sort_order'])) {
            $fields['sort_order'] = intval($data['sort_order']);
            $formats[] = '%d';
        }

        if (empty($fields)) {
            return false;
        }

        return (bool) $wpdb->update(
            $table,
            $fields,
            array('id' => $model_id),
            $formats,
            array('%d')
        );
    }

    /**
     * Supprimer un modèle 3D.
     *
     * @param int $model_id
     * @return bool
     */
    public static function delete_model_3d($model_id)
    {
        global $wpdb;

        $model_id = intval($model_id);
        if ($model_id <= 0) {
            return false;
        }

        $table = $wpdb->prefix . 'osds3d_models_3d';

        return (bool) $wpdb->delete(
            $table,
            array('id' => $model_id),
            array('%d')
        );
    }

    /**
     * Retourner uniquement l'URL d'un modèle 3D.
     *
     * @param int $model_id
     * @return string
     */
    public static function get_model_3d_url($model_id)
    {
        $model = self::get_model_3d($model_id);

        if (!$model || empty($model->model_url)) {
            return '';
        }

        return $model->model_url;
    }
}
