<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Journalisation des actions pour OSDS3D.
 *
 * Permet d'enregistrer des actions importantes, des erreurs et des événements
 * système dans la table `osds3d_logs`. L'activation des logs peut être
 * contrôlée via les paramètres du plugin.
 */
class OSDS3D_Logger
{
    /** Types de log */
    const TYPE_INFO    = 'info';
    const TYPE_WARNING = 'warning';
    const TYPE_ERROR   = 'error';
    const TYPE_DEBUG   = 'debug';
    const TYPE_SYSTEM  = 'system';

    /**
     * Initialiser le logger. Pour l'instant aucune action n'est requise.
     *
     * @return void
     */
    public static function init()
    {
        // Pas de hook nécessaire, mais la méthode existe pour cohérence
    }

    /**
     * Enregistrer un événement dans la table de logs.
     *
     * @param string $type    Type de log (info, warning, error, debug, system)
     * @param string $message Message principal
     * @param array  $data    Données additionnelles (sérialisées)
     * @return void
     */
    public static function log($type, $message, $data = array())
    {
        // Vérifier si les logs sont activés via les paramètres
        $logging_enabled = OSDS3D_Settings::get('log_actions', true);
        if (!$logging_enabled && $type !== self::TYPE_ERROR) {
            return;
        }
        global $wpdb;
        $table = $wpdb->prefix . 'osds3d_logs';
        $wpdb->insert(
            $table,
            array(
                'user_id'    => get_current_user_id(),
                'action_type'=> $type,
                'action_data'=> maybe_serialize($data),
                'ip_address' => self::get_user_ip(),
                'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 255) : '',
                'created_at' => current_time('mysql'),
            ),
            array('%d','%s','%s','%s','%s','%s')
        );
    }

    /**
     * Obtenir l'adresse IP de l'utilisateur actuel.
     *
     * @return string
     */
    private static function get_user_ip()
    {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return sanitize_text_field($_SERVER['HTTP_CLIENT_IP']);
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return sanitize_text_field($_SERVER['HTTP_X_FORWARDED_FOR']);
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            return sanitize_text_field($_SERVER['REMOTE_ADDR']);
        }
        return '';
    }

    /**
     * Méthodes utilitaires pour différents types de log.
     */
    public static function info($message, $data = array())   { self::log(self::TYPE_INFO,    $message, $data); }
    public static function warning($message, $data = array()){ self::log(self::TYPE_WARNING, $message, $data); }
    /**
     * Enregistrer une erreur.
     *
     * @param string $message
     * @param array  $data
     * @return void
     */
    public static function error($message, $data = array())
    {
        self::log(self::TYPE_ERROR, $message, $data);
    }
    public static function debug($message, $data = array())  { self::log(self::TYPE_DEBUG,   $message, $data); }
    public static function system($message, $data = array()) { self::log(self::TYPE_SYSTEM,  $message, $data); }
}
