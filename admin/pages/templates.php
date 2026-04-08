<?php
/**
 * Page d'administration pour les templates 2D
 *
 * Permet d'ajouter de nouveaux templates et de lister ceux existants.
 * Un template correspond à un design de base (fonds, modèles) préchargé
 * pouvant être appliqué dans le customizer. Chaque template peut être
 * global (pour tous les produits) ou associé à un produit spécifique.
 *
 * @package OSDS3D_Customizer_Pro
 */

if (!defined('ABSPATH')) {
    exit;
}

// Traitement de la suppression de template via GET
if (isset($_GET['action'], $_GET['template_id']) && $_GET['action'] === 'delete' && current_user_can('manage_options')) {
    $template_id = intval($_GET['template_id']);
    check_admin_referer('osds3d_delete_template_' . $template_id);
    if ($template_id) {
        OSDS3D_Database::delete_template($template_id);
        OSDS3D_Logger::info('admin', 'Template supprimé', array('template_id' => $template_id));
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Template supprimé.', 'osds3d-customizer-pro') . '</p></div>';
    }
}

// Traitement de l'ajout de template
if (isset($_POST['osds3d_add_template']) && current_user_can('manage_options')) {
    check_admin_referer('osds3d_add_template');
    $template_name     = isset($_POST['template_name']) ? sanitize_text_field($_POST['template_name']) : '';
    $template_category = isset($_POST['template_category']) ? sanitize_text_field($_POST['template_category']) : '';
    $template_product  = isset($_POST['template_product']) ? intval($_POST['template_product']) : 0;
    $canvas_width      = isset($_POST['template_width']) ? intval($_POST['template_width']) : 800;
    $canvas_height     = isset($_POST['template_height']) ? intval($_POST['template_height']) : 600;
    // Nous autorisons uniquement les images pour la prévisualisation
    if (!empty($_FILES['template_file']['name'])) {
        $allowed_types = array('jpg', 'jpeg', 'png', 'svg');
        $file_type     = strtolower(pathinfo($_FILES['template_file']['name'], PATHINFO_EXTENSION));
        if (in_array($file_type, $allowed_types)) {
            // Utiliser la fonction WordPress pour gérer l'upload
            require_once ABSPATH . 'wp-admin/includes/file.php';
            $upload_overrides = array('test_form' => false);
            $uploaded = wp_handle_upload($_FILES['template_file'], $upload_overrides);
            if (!isset($uploaded['error'])) {
                $preview_url = $uploaded['url'];
                // Construire le contenu JSON minimal pour le canvas (image de fond)
                $template_data = json_encode(array(
                    'version' => '5.3.0',
                    'objects' => array(),
                    'backgroundImage' => array(
                        'type' => 'image',
                        'src'  => $preview_url,
                        'left' => 0,
                        'top'  => 0,
                        'width'=> $canvas_width,
                        'height'=> $canvas_height,
                        'scaleX' => 1,
                        'scaleY' => 1
                    ),
                    'canvasWidth'  => $canvas_width,
                    'canvasHeight' => $canvas_height
                ));
                // Insérer dans la base
                $template_id = OSDS3D_Database::add_template(array(
                    'product_id'        => $template_product,
                    'template_name'     => $template_name,
                    'template_category' => $template_category,
                    'template_data'     => $template_data,
                    'preview_url'       => $preview_url,
                    'is_premium'        => 0,
                    'usage_count'       => 0,
                    'is_active'         => 1,
                ));
                if ($template_id) {
                    OSDS3D_Logger::info('admin', 'Nouveau template ajouté', array('template_id' => $template_id));
                    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Template ajouté avec succès.', 'osds3d-customizer-pro') . '</p></div>';
                } else {
                    echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Erreur lors de l’enregistrement du template.', 'osds3d-customizer-pro') . '</p></div>';
                }
            } else {
                echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($uploaded['error']) . '</p></div>';
            }
        } else {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Type de fichier non autorisé.', 'osds3d-customizer-pro') . '</p></div>';
        }
    } else {
        echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Veuillez sélectionner une image pour le template.', 'osds3d-customizer-pro') . '</p></div>';
    }
}

// Récupérer les templates existants
$all_templates = OSDS3D_Database::get_templates(0);
// Récupérer la liste des produits personnalisables pour l'assignation
$customizable_products = new WP_Query(array(
    'post_type'  => 'product',
    'meta_query' => array(
        array(
            'key'   => '_osds3d_customizable',
            'value' => '1'
        )
    ),
    'posts_per_page' => -1
));

?>
<div class="wrap osds3d-templates-page">
    <h1 class="wp-heading-inline"><?php echo esc_html__('Templates 2D', 'osds3d-customizer-pro'); ?></h1>
    <p class="description">
        <?php echo esc_html__('Gérez ici les fonds de personnalisation disponibles pour vos clients. Ces modèles peuvent être assignés à un produit spécifique ou restés globaux.', 'osds3d-customizer-pro'); ?>
    </p>
    <hr class="wp-header-end">

    <div class="osds3d-two-columns">
        <!-- Formulaire d'ajout -->
        <div class="osds3d-panel">
            <div class="osds3d-panel-header">
                <h2><?php echo esc_html__('Ajouter un template', 'osds3d-customizer-pro'); ?></h2>
            </div>
            <div class="osds3d-panel-body">
                <form method="post" enctype="multipart/form-data">
                    <?php wp_nonce_field('osds3d_add_template'); ?>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="template_name"><?php echo esc_html__('Nom du template', 'osds3d-customizer-pro'); ?> *</label></th>
                            <td><input type="text" name="template_name" id="template_name" class="regular-text" required></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="template_category"><?php echo esc_html__('Catégorie', 'osds3d-customizer-pro'); ?></label></th>
                            <td><input type="text" name="template_category" id="template_category" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="template_product"><?php echo esc_html__('Produit', 'osds3d-customizer-pro'); ?></label></th>
                            <td>
                                <select name="template_product" id="template_product">
                                    <option value="0"><?php echo esc_html__('Global (tous produits)', 'osds3d-customizer-pro'); ?></option>
                                    <?php if ($customizable_products->have_posts()) : ?>
                                        <?php while ($customizable_products->have_posts()) : $customizable_products->the_post(); global $product; ?>
                                            <option value="<?php echo esc_attr(get_the_ID()); ?>"><?php echo esc_html($product->get_name()); ?></option>
                                        <?php endwhile; wp_reset_postdata(); ?>
                                    <?php endif; ?>
                                </select>
                                <p class="description"><?php echo esc_html__('Choisissez un produit pour rendre ce template spécifique à ce produit.', 'osds3d-customizer-pro'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="template_file"><?php echo esc_html__('Image du template', 'osds3d-customizer-pro'); ?> *</label></th>
                            <td><input type="file" name="template_file" id="template_file" accept=".png,.jpg,.jpeg,.svg" required></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="template_width"><?php echo esc_html__('Largeur du canvas (px)', 'osds3d-customizer-pro'); ?></label></th>
                            <td><input type="number" name="template_width" id="template_width" value="800" min="100" max="4000" required></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="template_height"><?php echo esc_html__('Hauteur du canvas (px)', 'osds3d-customizer-pro'); ?></label></th>
                            <td><input type="number" name="template_height" id="template_height" value="600" min="100" max="4000" required></td>
                        </tr>
                    </table>
                    <p class="submit"><button type="submit" name="osds3d_add_template" class="button button-primary"><?php echo esc_html__('Ajouter le template', 'osds3d-customizer-pro'); ?></button></p>
                </form>
            </div>
        </div>
        <!-- Liste des templates -->
        <div class="osds3d-panel">
            <div class="osds3d-panel-header">
                <h2><?php echo esc_html__('Templates existants', 'osds3d-customizer-pro'); ?></h2>
            </div>
            <div class="osds3d-panel-body">
                <?php if ($all_templates) : ?>
                    <table class="widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php echo esc_html__('ID', 'osds3d-customizer-pro'); ?></th>
                                <th><?php echo esc_html__('Nom', 'osds3d-customizer-pro'); ?></th>
                                <th><?php echo esc_html__('Produit', 'osds3d-customizer-pro'); ?></th>
                                <th><?php echo esc_html__('Catégorie', 'osds3d-customizer-pro'); ?></th>
                                <th><?php echo esc_html__('Prévisualisation', 'osds3d-customizer-pro'); ?></th>
                                <th><?php echo esc_html__('Actions', 'osds3d-customizer-pro'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($all_templates as $tpl) : ?>
                            <tr>
                                <td><?php echo esc_html($tpl->id); ?></td>
                                <td><?php echo esc_html($tpl->template_name); ?></td>
                                <td>
                                    <?php
                                    if ($tpl->product_id) {
                                        $p = wc_get_product($tpl->product_id);
                                        echo $p ? esc_html($p->get_name()) : esc_html__('Inconnu', 'osds3d-customizer-pro');
                                    } else {
                                        echo esc_html__('Global', 'osds3d-customizer-pro');
                                    }
                                    ?>
                                </td>
                                <td><?php echo esc_html($tpl->template_category); ?></td>
                                <td>
                                    <?php if ($tpl->preview_url) : ?>
                                        <img src="<?php echo esc_url($tpl->preview_url); ?>" alt="<?php echo esc_attr($tpl->template_name); ?>" style="max-width:80px;height:auto;">
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $delete_url = wp_nonce_url(admin_url('admin.php?page=osds3d-templates&action=delete&template_id=' . $tpl->id), 'osds3d_delete_template_' . $tpl->id);
                                    ?>
                                    <a href="<?php echo esc_url($delete_url); ?>" class="button button-small delete-template" onclick="return confirm('<?php echo esc_js(__('Supprimer ce template ?', 'osds3d-customizer-pro')); ?>');">
                                        <?php echo esc_html__('Supprimer', 'osds3d-customizer-pro'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else : ?>
                    <p><?php echo esc_html__('Aucun template pour le moment.', 'osds3d-customizer-pro'); ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>