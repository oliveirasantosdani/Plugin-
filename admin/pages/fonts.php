<?php
/**
 * Page Polices (placeholder)
 *
 * Permettra à terme de gérer les polices uploadées et disponibles dans
 * le customizer. Actuellement, cette page affiche simplement un message
 * d'information.
 */

if (!defined('ABSPATH')) {
    exit;
}

?>
<?php
// Sécurité : empêcher l'accès direct
if (!defined('ABSPATH')) {
    exit;
}

// Gérer les actions GET (activer/désactiver/supprimer)
if (isset($_GET['toggle'])) {
    $font_id = intval($_GET['toggle']);
    $font    = null;
    $fonts   = OSDS3D_Database::get_fonts(false, array($font_id));
    if (!empty($fonts)) {
        $font = $fonts[0];
    }
    if ($font) {
        $new_status = $font->is_active ? 0 : 1;
        OSDS3D_Database::update_font($font_id, array('is_active' => $new_status));
        wp_redirect(admin_url('admin.php?page=osds3d-fonts'));
        exit;
    }
}
if (isset($_GET['delete'])) {
    $font_id = intval($_GET['delete']);
    OSDS3D_Database::delete_font($font_id);
    wp_redirect(admin_url('admin.php?page=osds3d-fonts'));
    exit;
}

// Gérer l'ajout de police via POST
if (isset($_POST['osds3d_add_font_nonce']) && wp_verify_nonce($_POST['osds3d_add_font_nonce'], 'osds3d_add_font')) {
    $font_name   = isset($_POST['font_name']) ? sanitize_text_field($_POST['font_name']) : '';
    $font_family = isset($_POST['font_family']) ? sanitize_text_field($_POST['font_family']) : '';
    $file        = isset($_FILES['font_file']) ? $_FILES['font_file'] : null;
    $error       = '';
    if (!$file || empty($file['name'])) {
        $error = __('Aucun fichier sélectionné.', 'osds3d-customizer-pro');
    } else {
        $allowed_types = array('ttf' => 'font/ttf', 'otf' => 'font/otf', 'woff' => 'font/woff', 'woff2' => 'font/woff2');
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!array_key_exists($ext, $allowed_types)) {
            $error = __('Type de fichier non pris en charge. Seuls les fichiers TTF, OTF et WOFF sont autorisés.', 'osds3d-customizer-pro');
        } else {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            $overrides = array('test_form' => false);
            $upload = wp_handle_upload($file, $overrides);
            if (isset($upload['error'])) {
                $error = $upload['error'];
            } else {
                // Déterminer la famille si non fournie
                if (!$font_family) {
                    $font_family = preg_replace('/\.[^.]+$/', '', basename($file['name']));
                }
                $font_data = array(
                    'font_name'   => $font_name ? $font_name : $font_family,
                    'font_url'    => $upload['url'],
                    'font_family' => $font_family,
                    'font_format' => $ext,
                    'is_active'   => 1,
                    'sort_order'  => 0,
                );
                OSDS3D_Database::add_font($font_data);
                wp_redirect(admin_url('admin.php?page=osds3d-fonts'));
                exit;
            }
        }
    }
}

// Récupérer les polices existantes
$fonts = OSDS3D_Database::get_fonts(false);
?>
<div class="wrap">
    <h1 class="wp-heading-inline"><?php echo esc_html__('Polices', 'osds3d-customizer-pro'); ?></h1>
    <p class="description"><?php echo esc_html__('Gérez vos polices personnalisées pour le customizer. Téléversez de nouvelles polices et activez-les pour qu’elles soient disponibles dans l’éditeur.', 'osds3d-customizer-pro'); ?></p>

    <?php if (!empty($error)) : ?>
        <div class="notice notice-error"><p><?php echo esc_html($error); ?></p></div>
    <?php endif; ?>

    <h2><?php echo esc_html__('Ajouter une nouvelle police', 'osds3d-customizer-pro'); ?></h2>
    <form method="post" enctype="multipart/form-data" action="">
        <?php wp_nonce_field('osds3d_add_font', 'osds3d_add_font_nonce'); ?>
        <table class="form-table">
            <tr>
                <th scope="row"><label for="font_file"><?php echo esc_html__('Fichier de police', 'osds3d-customizer-pro'); ?></label></th>
                <td><input type="file" name="font_file" id="font_file" accept=".ttf,.otf,.woff,.woff2" required></td>
            </tr>
            <tr>
                <th scope="row"><label for="font_name"><?php echo esc_html__('Nom de la police', 'osds3d-customizer-pro'); ?></label></th>
                <td><input type="text" name="font_name" id="font_name" class="regular-text" placeholder="Ex. Montserrat"></td>
            </tr>
            <tr>
                <th scope="row"><label for="font_family"><?php echo esc_html__('Famille CSS', 'osds3d-customizer-pro'); ?></label></th>
                <td><input type="text" name="font_family" id="font_family" class="regular-text" placeholder="Ex. montserrat"></td>
            </tr>
        </table>
        <p>
            <input type="submit" class="button button-primary" value="<?php echo esc_attr__('Ajouter', 'osds3d-customizer-pro'); ?>">
        </p>
    </form>

    <h2 style="margin-top:40px;"><?php echo esc_html__('Polices existantes', 'osds3d-customizer-pro'); ?></h2>
    <table class="widefat fixed">
        <thead>
            <tr>
                <th><?php echo esc_html__('Nom', 'osds3d-customizer-pro'); ?></th>
                <th><?php echo esc_html__('Famille', 'osds3d-customizer-pro'); ?></th>
                <th><?php echo esc_html__('Format', 'osds3d-customizer-pro'); ?></th>
                <th><?php echo esc_html__('Statut', 'osds3d-customizer-pro'); ?></th>
                <th><?php echo esc_html__('Actions', 'osds3d-customizer-pro'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($fonts)) : ?>
                <tr><td colspan="5"><?php echo esc_html__('Aucune police ajoutée pour le moment.', 'osds3d-customizer-pro'); ?></td></tr>
            <?php else : ?>
                <?php foreach ($fonts as $font) : ?>
                    <tr>
                        <td><?php echo esc_html($font->font_name); ?></td>
                        <td><code><?php echo esc_html($font->font_family); ?></code></td>
                        <td><?php echo esc_html(strtoupper($font->font_format)); ?></td>
                        <td><?php echo $font->is_active ? esc_html__('Active', 'osds3d-customizer-pro') : esc_html__('Inactive', 'osds3d-customizer-pro'); ?></td>
                        <td>
                            <?php
                            $toggle_url = admin_url('admin.php?page=osds3d-fonts&toggle=' . intval($font->id));
                            $delete_url = admin_url('admin.php?page=osds3d-fonts&delete=' . intval($font->id));
                            ?>
                            <a href="<?php echo esc_url($toggle_url); ?>" class="button button-small">
                                <?php echo $font->is_active ? esc_html__('Désactiver', 'osds3d-customizer-pro') : esc_html__('Activer', 'osds3d-customizer-pro'); ?>
                            </a>
                            <a href="<?php echo esc_url($delete_url); ?>" class="button button-small" onclick="return confirm('<?php echo esc_js(__('Supprimer cette police ?', 'osds3d-customizer-pro')); ?>');">
                                <?php echo esc_html__('Supprimer', 'osds3d-customizer-pro'); ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>