<?php
/**
 * Gestion des paramètres pour OSDS3D Customizer Pro
 *
 * Stocke les options dans wp_options avec un préfixe commun.
 *
 * @package OSDS3D_Customizer_Pro
 */

if (!defined('ABSPATH')) {
    exit;
}

class OSDS3D_Settings
{
    /**
     * Préfixe des options dans wp_options
     *
     * @var string
     */
    const OPTION_PREFIX = 'osds3d_';

    /**
     * Retourne la définition des options.
     *
     * @return array
     */
    protected static function get_option_schema()
    {
        return array(
            'customizer_page_id' => array(
                'default'  => 0,
                'type'     => 'integer',
            ),
            'admin_email' => array(
                'default'  => get_option('admin_email'),
                'type'     => 'email',
            ),
            'email_from_name' => array(
                'default'  => get_option('blogname'),
                'type'     => 'text',
            ),
            'email_from_email' => array(
                'default'  => get_option('admin_email'),
                'type'     => 'email',
            ),
            'log_actions' => array(
                'default'  => true,
                'type'     => 'boolean',
            ),
        );
    }

    /**
     * Obtenir les valeurs par défaut des options.
     *
     * @return array
     */
    public static function get_defaults()
    {
        $schema = self::get_option_schema();
        $defaults = array();

        foreach ($schema as $key => $config) {
            $defaults[$key] = isset($config['default']) ? $config['default'] : null;
        }

        return $defaults;
    }

    /**
     * Initialiser les paramètres en enregistrant les options via la Settings API.
     *
     * @return void
     */
    public static function init()
    {
        add_action('admin_init', array(__CLASS__, 'register_settings'));
    }

    /**
     * Enregistrer les options du plugin auprès de WordPress.
     *
     * @return void
     */
    public static function register_settings()
    {
        $schema = self::get_option_schema();

        foreach ($schema as $key => $config) {
            $option_name = self::OPTION_PREFIX . $key;

            register_setting(
                'osds3d_settings_group',
                $option_name,
                array(
                    'type'              => isset($config['type']) && $config['type'] === 'boolean' ? 'boolean' : 'string',
                    'sanitize_callback' => function ($value) use ($key) {
                        return self::sanitize_option_by_key($key, $value);
                    },
                    'default'           => isset($config['default']) ? $config['default'] : null,
                )
            );
        }
    }

    /**
     * Définir les valeurs par défaut lors de l'activation.
     *
     * @return void
     */
    public static function set_defaults()
    {
        $defaults = self::get_defaults();

        foreach ($defaults as $key => $value) {
            $option_name = self::OPTION_PREFIX . $key;

            if (get_option($option_name, null) === null) {
                add_option($option_name, $value);
            }
        }
    }

    /**
     * Récupérer une option individuelle.
     *
     * @param string $key
     * @param mixed  $default
     * @return mixed
     */
    public static function get($key, $default = null)
    {
        $option_name = self::OPTION_PREFIX . $key;
        $value = get_option($option_name, null);

        if ($value === null) {
            $defaults = self::get_defaults();
            return $default !== null ? $default : (isset($defaults[$key]) ? $defaults[$key] : null);
        }

        return $value;
    }

    /**
     * Mettre à jour une option.
     *
     * @param string $key
     * @param mixed  $value
     * @return bool
     */
    public static function set($key, $value)
    {
        $option_name = self::OPTION_PREFIX . $key;
        $sanitized   = self::sanitize_option_by_key($key, $value);

        return update_option($option_name, $sanitized);
    }

    /**
     * Obtenir toutes les options avec leurs valeurs actuelles.
     *
     * @return array
     */
    public static function get_all()
    {
        $defaults = self::get_defaults();
        $options = array();

        foreach ($defaults as $key => $value) {
            $options[$key] = self::get($key, $value);
        }

        return $options;
    }

    /**
     * Sanitize générique par clé.
     *
     * @param string $key
     * @param mixed  $value
     * @return mixed
     */
    public static function sanitize_option_by_key($key, $value)
    {
        $schema = self::get_option_schema();
        $type   = isset($schema[$key]['type']) ? $schema[$key]['type'] : 'text';

        switch ($type) {
            case 'integer':
                return absint($value);

            case 'boolean':
                return !empty($value) ? 1 : 0;

            case 'email':
                return sanitize_email($value);

            case 'array_text':
                if (!is_array($value)) {
                    return array();
                }
                return array_map('sanitize_text_field', $value);

            case 'text':
            default:
                if (is_array($value)) {
                    return array_map('sanitize_text_field', $value);
                }
                return sanitize_text_field($value);
        }
    }

    /**
     * Ancienne méthode conservée pour compatibilité.
     *
     * @param mixed $value
     * @return mixed
     */
    public static function sanitize_option($value)
    {
        if (is_array($value)) {
            return array_map('sanitize_text_field', $value);
        }

        return sanitize_text_field($value);
    }
}