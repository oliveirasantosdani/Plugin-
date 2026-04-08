<?php
/**
 * Gestion des notifications par e‑mail pour OSDS3D Customizer Pro
 *
 * Cette classe fournit des méthodes pour envoyer des e‑mails simples lors
 * de la création de commandes contenant des designs. Les e‑mails sont
 * générés en HTML et envoyés via la fonction WordPress wp_mail().
 *
 * @package OSDS3D_Customizer_Pro
 */

if (!defined('ABSPATH')) {
    exit;
}

class OSDS3D_Email
{
    /**
     * Initialiser la classe. Actuellement, aucun hook n'est nécessaire.
     *
     * @return void
     */
    public static function init()
    {
        // Aucun hook global à enregistrer pour l'instant
    }

    /**
     * Envoyer un e‑mail au format HTML.
     *
     * @param string $to      Adresse e‑mail destinataire
     * @param string $subject Sujet de l'e‑mail
     * @param string $content Contenu HTML du message (sans template)
     * @param array  $headers En-têtes supplémentaires
     * @param array  $attachments Pièces jointes
     * @return bool
     */
    private static function send($to, $subject, $content, $headers = array(), $attachments = array())
    {
        // Récupérer les paramètres (adresse d'envoi, nom...)
        $from_name  = OSDS3D_Settings::get('email_from_name', get_option('blogname'));
        $from_email = OSDS3D_Settings::get('email_from_email', get_option('admin_email'));
        $default_headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $from_name . ' <' . $from_email . '>'
        );
        $headers = array_merge($default_headers, $headers);

        $html = self::wrap_template($content);
        return wp_mail($to, wp_specialchars_decode($subject), $html, $headers, $attachments);
    }

    /**
     * Envelopper le contenu dans un template HTML basique.
     *
     * @param string $content Contenu HTML
     * @return string
     */
    private static function wrap_template($content)
    {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>OSDS3D</title>
        </head>
        <body style="background:#f7f7f7;margin:0;padding:20px;font-family:Arial,sans-serif;">
            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;margin:0 auto;background:#ffffff;border-radius:6px;overflow:hidden;box-shadow:0 2px 5px rgba(0,0,0,0.1);">
                <tr>
                    <td style="background:#0073aa;padding:20px;text-align:center;color:#fff;">
                        <h1 style="margin:0;font-size:24px;">OSDS3D</h1>
                    </td>
                </tr>
                <tr>
                    <td style="padding:30px;">
                        <?php echo wp_kses_post($content); ?>
                    </td>
                </tr>
                <tr>
                    <td style="background:#f3f3f3;padding:20px;text-align:center;color:#888;font-size:12px;">
                        <?php echo esc_html__('Cet e‑mail a été envoyé depuis votre site OSDS3D.', 'osds3d-customizer-pro'); ?>
                    </td>
                </tr>
            </table>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }

    /**
     * Envoyer une notification d'ordre avec design au/à la responsable.
     *
     * @param int $order_id
     * @return void
     */
    public static function send_design_order_notification($order_id)
    {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
        // Déterminer s'il y a au moins un design dans la commande
        $has_design = false;
        foreach ($order->get_items() as $item) {
            if ($item->get_meta('_osds3d_design_id')) {
                $has_design = true;
                break;
            }
        }
        if (!$has_design) {
            return;
        }
        // Destinataire : adresse de notification ou admin
        $to = OSDS3D_Settings::get('admin_email', get_option('admin_email'));
        if (empty($to)) {
            $to = get_option('admin_email');
        }
        // Sujet
        $subject = sprintf(__('Nouvelle commande #%s avec design personnalisé', 'osds3d-customizer-pro'), $order->get_order_number());
        // Construire le contenu
        ob_start();
        ?>
        <h2><?php echo esc_html(sprintf(__('Commande #%s', 'osds3d-customizer-pro'), $order->get_order_number())); ?></h2>
        <p><?php echo esc_html__('Une nouvelle commande contient un ou plusieurs designs personnalisés. Voici un récapitulatif :', 'osds3d-customizer-pro'); ?></p>
        <table style="width:100%;border-collapse:collapse;margin:20px 0;">
            <tr>
                <th align="left" style="padding:8px;border-bottom:1px solid #ddd;"><?php echo esc_html__('Produit', 'osds3d-customizer-pro'); ?></th>
                <th align="left" style="padding:8px;border-bottom:1px solid #ddd;"><?php echo esc_html__('Design ID', 'osds3d-customizer-pro'); ?></th>
            </tr>
            <?php foreach ($order->get_items() as $item) :
                $design_id = $item->get_meta('_osds3d_design_id');
                if (!$design_id) { continue; }
                ?>
                <tr>
                    <td style="padding:8px;border-bottom:1px solid #eee;"><?php echo esc_html($item->get_name()); ?></td>
                    <td style="padding:8px;border-bottom:1px solid #eee;">#<?php echo esc_html($design_id); ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
        <p><a href="<?php echo esc_url($order->get_edit_order_url()); ?>" style="display:inline-block;padding:10px 20px;background:#0073aa;color:#fff;text-decoration:none;border-radius:4px;"><?php echo esc_html__('Voir la commande', 'osds3d-customizer-pro'); ?></a></p>
        <?php
        $content = ob_get_clean();
        self::send($to, $subject, $content);
    }
}