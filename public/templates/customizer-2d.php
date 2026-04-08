<?php
if (!defined('ABSPATH')) {
    exit;
}

$product_id = 0;

if (class_exists('OSDS3D_Customizer') && method_exists('OSDS3D_Customizer', 'get_current_product_id')) {
    $product_id = absint(OSDS3D_Customizer::get_current_product_id());
}

if (!$product_id && isset($_GET['product_id'])) {
    $product_id = absint(wp_unslash($_GET['product_id']));
}

$custom_fonts = array();

if (class_exists('OSDS3D_Database') && method_exists('OSDS3D_Database', 'get_fonts')) {
    $custom_fonts = OSDS3D_Database::get_fonts(true);
}

$cart_url = function_exists('wc_get_cart_url') ? wc_get_cart_url() : '';
?>

<div class="osds3d-app-shell">

    <header class="osds3d-app-header">
        <div class="osds3d-app-header-left">
            <h1 class="osds3d-app-title"><?php echo esc_html__('Personnaliser votre produit', 'osds3d-customizer-pro'); ?></h1>
            <div id="osds3d-message" class="osds3d-inline-message"></div>
        </div>

        <div class="osds3d-app-header-right">
            <button id="osds3d-help-btn" class="osds3d-ui-btn osds3d-ui-btn-ghost" type="button">
                ❓ <?php echo esc_html__('Aide', 'osds3d-customizer-pro'); ?>
            </button>

            <button id="osds3d-save-design" class="osds3d-ui-btn osds3d-ui-btn-primary" type="button">
                💾 <?php echo esc_html__('Sauvegarder', 'osds3d-customizer-pro'); ?>
            </button>

            <?php if (!empty($cart_url) && $product_id > 0) : ?>
                <form
                    id="osds3d-add-to-cart-form"
                    method="post"
                    action="<?php echo esc_url($cart_url); ?>"
                    style="display:none;"
                >
                    <input type="hidden" name="add-to-cart" value="<?php echo esc_attr($product_id); ?>">
                    <input type="hidden" name="osds3d_design_id" id="osds3d_design_id" value="">
                    <button type="submit" class="osds3d-ui-btn osds3d-ui-btn-primary">
                        <?php echo esc_html__('Ajouter au panier', 'osds3d-customizer-pro'); ?>
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </header>

    <div class="osds3d-app-layout">

        <aside class="osds3d-app-sidebar">
            <div class="osds3d-app-sidebar-search">
                <input
                    type="search"
                    id="osds3d-search"
                    placeholder="<?php echo esc_attr__('Rechercher...', 'osds3d-customizer-pro'); ?>"
                >
            </div>

            <ul class="osds3d-app-tabs">
                <li class="osds3d-tab active is-active" data-target="panel-text">
                    <span class="osds3d-tab-icon">T</span>
                    <span><?php echo esc_html__('Texte', 'osds3d-customizer-pro'); ?></span>
                </li>
                <li class="osds3d-tab" data-target="panel-images">
                    <span class="osds3d-tab-icon">🖼</span>
                    <span><?php echo esc_html__('Images', 'osds3d-customizer-pro'); ?></span>
                </li>
                <li class="osds3d-tab" data-target="panel-cliparts">
                    <span class="osds3d-tab-icon">🎨</span>
                    <span><?php echo esc_html__('Cliparts', 'osds3d-customizer-pro'); ?></span>
                </li>
                <li class="osds3d-tab" data-target="panel-colors">
                    <span class="osds3d-tab-icon">🎯</span>
                    <span><?php echo esc_html__('Couleurs', 'osds3d-customizer-pro'); ?></span>
                </li>
                <li class="osds3d-tab" data-target="panel-background">
                    <span class="osds3d-tab-icon">◫</span>
                    <span><?php echo esc_html__('Fond', 'osds3d-customizer-pro'); ?></span>
                </li>
                <li class="osds3d-tab" data-target="panel-shapes">
                    <span class="osds3d-tab-icon">⬢</span>
                    <span><?php echo esc_html__('Formes', 'osds3d-customizer-pro'); ?></span>
                </li>
                <li class="osds3d-tab" data-target="panel-align">
                    <span class="osds3d-tab-icon">⇔</span>
                    <span><?php echo esc_html__('Alignement', 'osds3d-customizer-pro'); ?></span>
                </li>
                <li class="osds3d-tab" data-target="panel-qr">
                    <span class="osds3d-tab-icon">▣</span>
                    <span><?php echo esc_html__('QR Code', 'osds3d-customizer-pro'); ?></span>
                </li>
                <li class="osds3d-tab" data-target="panel-templates">
                    <span class="osds3d-tab-icon">☷</span>
                    <span><?php echo esc_html__('Modèles', 'osds3d-customizer-pro'); ?></span>
                </li>
                <li class="osds3d-tab" data-target="panel-guided">
                    <span class="osds3d-tab-icon">✍</span>
                    <span><?php echo esc_html__('Champs guidés', 'osds3d-customizer-pro'); ?></span>
                </li>
                <li class="osds3d-tab" data-target="panel-models3d">
                    <span class="osds3d-tab-icon">3D</span>
                    <span><?php echo esc_html__('Modèle 3D', 'osds3d-customizer-pro'); ?></span>
                </li>
                <li class="osds3d-tab" data-target="panel-faces">
                    <span class="osds3d-tab-icon">⇄</span>
                    <span><?php echo esc_html__('Faces', 'osds3d-customizer-pro'); ?></span>
                </li>
            </ul>
        </aside>

        <section class="osds3d-app-panel">

            <div id="panel-text" class="osds3d-panel is-active">
                <h3 class="osds3d-panel-title"><?php echo esc_html__('Texte', 'osds3d-customizer-pro'); ?></h3>

                <div class="osds3d-field">
                    <button id="osds3d-add-text" class="osds3d-ui-btn osds3d-ui-btn-primary osds3d-full" type="button">
                        <?php echo esc_html__('Ajouter du texte', 'osds3d-customizer-pro'); ?>
                    </button>
                </div>

                <div class="osds3d-field">
                    <label class="osds3d-label" for="osds3d-text-content"><?php echo esc_html__('Contenu du texte', 'osds3d-customizer-pro'); ?></label>
                    <textarea id="osds3d-text-content" rows="4"></textarea>
                </div>

                <div class="osds3d-field">
                    <button id="osds3d-update-text" class="osds3d-ui-btn osds3d-ui-btn-ghost osds3d-full" type="button">
                        <?php echo esc_html__('Mettre à jour', 'osds3d-customizer-pro'); ?>
                    </button>
                </div>

                <div class="osds3d-field">
                    <label class="osds3d-label" for="osds3d-font-family"><?php echo esc_html__('Police', 'osds3d-customizer-pro'); ?></label>
                    <select id="osds3d-font-family">
                        <option value="Arial">Arial</option>
                        <option value="Helvetica">Helvetica</option>
                        <option value="Courier New">Courier New</option>
                        <option value="Georgia">Georgia</option>
                        <option value="Times New Roman">Times New Roman</option>
                        <option value="Verdana">Verdana</option>
                        <?php if (!empty($custom_fonts)) : ?>
                            <optgroup label="<?php echo esc_attr__('Polices personnalisées', 'osds3d-customizer-pro'); ?>">
                                <?php foreach ($custom_fonts as $font) : ?>
                                    <option value="<?php echo esc_attr(!empty($font->font_family) ? $font->font_family : $font->font_name); ?>">
                                        <?php echo esc_html($font->font_name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="osds3d-grid-2">
                    <div class="osds3d-field">
                        <label class="osds3d-label" for="osds3d-text-color"><?php echo esc_html__('Couleur', 'osds3d-customizer-pro'); ?></label>
                        <input type="color" id="osds3d-text-color" value="#000000">
                    </div>
                    <div class="osds3d-field">
                        <label class="osds3d-label" for="osds3d-font-size"><?php echo esc_html__('Taille', 'osds3d-customizer-pro'); ?></label>
                        <input type="number" id="osds3d-font-size" min="8" max="200" value="32">
                    </div>
                </div>

                <div class="osds3d-field">
                    <label class="osds3d-label"><?php echo esc_html__('Style', 'osds3d-customizer-pro'); ?></label>
                    <div class="osds3d-style-buttons">
                        <button id="osds3d-bold" class="osds3d-ui-btn osds3d-style-btn" type="button">B</button>
                        <button id="osds3d-italic" class="osds3d-ui-btn osds3d-style-btn" type="button">I</button>
                        <button id="osds3d-underline" class="osds3d-ui-btn osds3d-style-btn" type="button">U</button>
                    </div>
                </div>

                <div class="osds3d-grid-2">
                    <div class="osds3d-field">
                        <label class="osds3d-label" for="osds3d-stroke-color"><?php echo esc_html__('Contour', 'osds3d-customizer-pro'); ?></label>
                        <input type="color" id="osds3d-stroke-color" value="#000000">
                    </div>
                    <div class="osds3d-field">
                        <label class="osds3d-label" for="osds3d-stroke-width"><?php echo esc_html__('Épaisseur', 'osds3d-customizer-pro'); ?></label>
                        <input type="number" id="osds3d-stroke-width" min="0" max="10" value="0">
                    </div>
                </div>

                <div class="osds3d-field">
                    <label class="osds3d-label" for="osds3d-shadow-color"><?php echo esc_html__('Ombre', 'osds3d-customizer-pro'); ?></label>
                    <input type="color" id="osds3d-shadow-color" value="#000000">
                </div>

                <div class="osds3d-grid-3">
                    <div class="osds3d-field">
                        <input type="number" id="osds3d-shadow-offset-x" value="5" placeholder="X">
                    </div>
                    <div class="osds3d-field">
                        <input type="number" id="osds3d-shadow-offset-y" value="5" placeholder="Y">
                    </div>
                    <div class="osds3d-field">
                        <input type="number" id="osds3d-shadow-blur" value="10" placeholder="Blur">
                    </div>
                </div>

                <div class="osds3d-field">
                    <button id="osds3d-apply-shadow" class="osds3d-ui-btn osds3d-ui-btn-ghost osds3d-full" type="button">
                        <?php echo esc_html__('Appliquer ombre', 'osds3d-customizer-pro'); ?>
                    </button>
                </div>
            </div>

            <div id="panel-images" class="osds3d-panel" hidden>
                <h3 class="osds3d-panel-title"><?php echo esc_html__('Images', 'osds3d-customizer-pro'); ?></h3>

                <div class="osds3d-field">
                    <button id="osds3d-add-image" class="osds3d-ui-btn osds3d-ui-btn-primary osds3d-full" type="button">
                        <?php echo esc_html__('Ajouter une image', 'osds3d-customizer-pro'); ?>
                    </button>
                </div>

                <div class="osds3d-dropzone" id="osds3d-image-dropzone">
                    <?php echo esc_html__('Glissez-déposez une image ici', 'osds3d-customizer-pro'); ?>
                </div>

                <div class="osds3d-field">
                    <label class="osds3d-label" for="osds3d-image-filter"><?php echo esc_html__('Filtre', 'osds3d-customizer-pro'); ?></label>
                    <select id="osds3d-image-filter">
                        <option value="none"><?php echo esc_html__('Aucun', 'osds3d-customizer-pro'); ?></option>
                        <option value="grayscale"><?php echo esc_html__('Gris', 'osds3d-customizer-pro'); ?></option>
                        <option value="sepia"><?php echo esc_html__('Sépia', 'osds3d-customizer-pro'); ?></option>
                        <option value="invert"><?php echo esc_html__('Inverser', 'osds3d-customizer-pro'); ?></option>
                        <option value="brightness"><?php echo esc_html__('Luminosité', 'osds3d-customizer-pro'); ?></option>
                        <option value="contrast"><?php echo esc_html__('Contraste', 'osds3d-customizer-pro'); ?></option>
                        <option value="saturation"><?php echo esc_html__('Saturation', 'osds3d-customizer-pro'); ?></option>
                        <option value="blur"><?php echo esc_html__('Flou', 'osds3d-customizer-pro'); ?></option>
                    </select>
                </div>

                <div class="osds3d-grid-2">
                    <button id="osds3d-apply-filter" class="osds3d-ui-btn osds3d-ui-btn-ghost" type="button">
                        <?php echo esc_html__('Appliquer filtre', 'osds3d-customizer-pro'); ?>
                    </button>
                    <button id="osds3d-start-crop" class="osds3d-ui-btn osds3d-ui-btn-ghost" type="button">
                        <?php echo esc_html__('Recadrer', 'osds3d-customizer-pro'); ?>
                    </button>
                </div>
            </div>

            <div id="panel-cliparts" class="osds3d-panel" hidden>
                <h3 class="osds3d-panel-title"><?php echo esc_html__('Cliparts', 'osds3d-customizer-pro'); ?></h3>

                <div class="osds3d-field">
                    <input type="search" id="osds3d-clipart-search" placeholder="<?php echo esc_attr__('Rechercher un clipart...', 'osds3d-customizer-pro'); ?>">
                </div>

                <div class="osds3d-clipart-categories">
                    <button class="osds3d-ui-btn osds3d-ui-btn-ghost" type="button" data-clipart-cat="all"><?php echo esc_html__('Tous', 'osds3d-customizer-pro'); ?></button>
                    <button class="osds3d-ui-btn osds3d-ui-btn-ghost" type="button" data-clipart-cat="love"><?php echo esc_html__('Amour', 'osds3d-customizer-pro'); ?></button>
                    <button class="osds3d-ui-btn osds3d-ui-btn-ghost" type="button" data-clipart-cat="birthday"><?php echo esc_html__('Anniversaire', 'osds3d-customizer-pro'); ?></button>
                    <button class="osds3d-ui-btn osds3d-ui-btn-ghost" type="button" data-clipart-cat="kids"><?php echo esc_html__('Enfants', 'osds3d-customizer-pro'); ?></button>
                </div>

                <div id="osds3d-clipart-grid" class="osds3d-clipart-grid">
                    <div class="osds3d-empty-note"><?php echo esc_html__('Bibliothèque à connecter.', 'osds3d-customizer-pro'); ?></div>
                </div>
            </div>

            <div id="panel-colors" class="osds3d-panel" hidden>
                <h3 class="osds3d-panel-title"><?php echo esc_html__('Palette couleurs', 'osds3d-customizer-pro'); ?></h3>

                <div class="osds3d-field">
                    <label class="osds3d-label" for="osds3d-global-color"><?php echo esc_html__('Couleur active', 'osds3d-customizer-pro'); ?></label>
                    <input type="color" id="osds3d-global-color" value="#000000">
                </div>

                <div class="osds3d-field">
                    <label class="osds3d-label" for="osds3d-global-hex"><?php echo esc_html__('Code HEX', 'osds3d-customizer-pro'); ?></label>
                    <input type="text" id="osds3d-global-hex" value="#000000">
                </div>

                <div class="osds3d-field">
                    <label class="osds3d-label" for="osds3d-opacity"><?php echo esc_html__('Opacité', 'osds3d-customizer-pro'); ?></label>
                    <input type="range" id="osds3d-opacity" min="0" max="100" value="100">
                </div>

                <div class="osds3d-color-palette" id="osds3d-color-palette">
                    <button type="button" class="osds3d-color-chip" data-color="#000000" style="background:#000000;"></button>
                    <button type="button" class="osds3d-color-chip" data-color="#ffffff" style="background:#ffffff;"></button>
                    <button type="button" class="osds3d-color-chip" data-color="#ef4444" style="background:#ef4444;"></button>
                    <button type="button" class="osds3d-color-chip" data-color="#f59e0b" style="background:#f59e0b;"></button>
                    <button type="button" class="osds3d-color-chip" data-color="#10b981" style="background:#10b981;"></button>
                    <button type="button" class="osds3d-color-chip" data-color="#2563eb" style="background:#2563eb;"></button>
                    <button type="button" class="osds3d-color-chip" data-color="#7c3aed" style="background:#7c3aed;"></button>
                    <button type="button" class="osds3d-color-chip" data-color="#ec4899" style="background:#ec4899;"></button>
                </div>

                <div class="osds3d-field">
                    <button id="osds3d-apply-global-color" class="osds3d-ui-btn osds3d-ui-btn-primary osds3d-full" type="button">
                        <?php echo esc_html__('Appliquer à l’objet sélectionné', 'osds3d-customizer-pro'); ?>
                    </button>
                </div>
            </div>

            <div id="panel-background" class="osds3d-panel" hidden>
                <h3 class="osds3d-panel-title"><?php echo esc_html__('Arrière-plan', 'osds3d-customizer-pro'); ?></h3>

                <div class="osds3d-field">
                    <label class="osds3d-label" for="osds3d-background-color"><?php echo esc_html__('Couleur de fond', 'osds3d-customizer-pro'); ?></label>
                    <input type="color" id="osds3d-background-color" value="#ffffff">
                </div>

                <div class="osds3d-field">
                    <button id="osds3d-apply-background" class="osds3d-ui-btn osds3d-ui-btn-ghost osds3d-full" type="button">
                        <?php echo esc_html__('Appliquer', 'osds3d-customizer-pro'); ?>
                    </button>
                </div>
            </div>

            <div id="panel-shapes" class="osds3d-panel" hidden>
                <h3 class="osds3d-panel-title"><?php echo esc_html__('Formes', 'osds3d-customizer-pro'); ?></h3>

                <div class="osds3d-grid-2">
                    <button id="osds3d-add-rect" class="osds3d-ui-btn" type="button"><?php echo esc_html__('Rectangle', 'osds3d-customizer-pro'); ?></button>
                    <button id="osds3d-add-circle" class="osds3d-ui-btn" type="button"><?php echo esc_html__('Cercle', 'osds3d-customizer-pro'); ?></button>
                    <button id="osds3d-add-triangle" class="osds3d-ui-btn" type="button"><?php echo esc_html__('Triangle', 'osds3d-customizer-pro'); ?></button>
                    <button id="osds3d-add-star" class="osds3d-ui-btn" type="button"><?php echo esc_html__('Étoile', 'osds3d-customizer-pro'); ?></button>
                    <button id="osds3d-add-star6" class="osds3d-ui-btn" type="button"><?php echo esc_html__('Étoile 6', 'osds3d-customizer-pro'); ?></button>
                    <button id="osds3d-add-star8" class="osds3d-ui-btn" type="button"><?php echo esc_html__('Étoile 8', 'osds3d-customizer-pro'); ?></button>
                    <button id="osds3d-add-polygon" class="osds3d-ui-btn osds3d-span-2" type="button"><?php echo esc_html__('Polygone', 'osds3d-customizer-pro'); ?></button>
                </div>

                <div class="osds3d-field">
                    <label class="osds3d-label" for="osds3d-gradient-start"><?php echo esc_html__('Couleur de départ', 'osds3d-customizer-pro'); ?></label>
                    <input type="color" id="osds3d-gradient-start" value="#ff0000">
                </div>

                <div class="osds3d-field">
                    <label class="osds3d-label" for="osds3d-gradient-end"><?php echo esc_html__('Couleur de fin', 'osds3d-customizer-pro'); ?></label>
                    <input type="color" id="osds3d-gradient-end" value="#0000ff">
                </div>

                <div class="osds3d-field">
                    <label class="osds3d-label" for="osds3d-gradient-type"><?php echo esc_html__('Type de dégradé', 'osds3d-customizer-pro'); ?></label>
                    <select id="osds3d-gradient-type">
                        <option value="linear"><?php echo esc_html__('Linéaire', 'osds3d-customizer-pro'); ?></option>
                        <option value="radial"><?php echo esc_html__('Radial', 'osds3d-customizer-pro'); ?></option>
                    </select>
                </div>

                <div class="osds3d-field">
                    <button id="osds3d-apply-gradient" class="osds3d-ui-btn osds3d-ui-btn-ghost osds3d-full" type="button">
                        <?php echo esc_html__('Appliquer dégradé', 'osds3d-customizer-pro'); ?>
                    </button>
                </div>

                <div class="osds3d-field">
                    <label class="osds3d-label" for="osds3d-free-color"><?php echo esc_html__('Couleur du pinceau', 'osds3d-customizer-pro'); ?></label>
                    <input type="color" id="osds3d-free-color" value="#000000">
                </div>

                <div class="osds3d-field">
                    <label class="osds3d-label" for="osds3d-free-size"><?php echo esc_html__('Taille du pinceau', 'osds3d-customizer-pro'); ?></label>
                    <input type="range" id="osds3d-free-size" min="1" max="100" value="5">
                </div>

                <div class="osds3d-grid-2">
                    <button id="osds3d-start-draw" class="osds3d-ui-btn" type="button"><?php echo esc_html__('Démarrer le dessin', 'osds3d-customizer-pro'); ?></button>
                    <button id="osds3d-stop-draw" class="osds3d-ui-btn" type="button"><?php echo esc_html__('Arrêter le dessin', 'osds3d-customizer-pro'); ?></button>
                </div>
            </div>

            <div id="panel-align" class="osds3d-panel" hidden>
                <h3 class="osds3d-panel-title"><?php echo esc_html__('Alignement', 'osds3d-customizer-pro'); ?></h3>

                <div class="osds3d-grid-3">
                    <button id="osds3d-align-left" class="osds3d-ui-btn" type="button"><?php echo esc_html__('Gauche', 'osds3d-customizer-pro'); ?></button>
                    <button id="osds3d-align-center-x" class="osds3d-ui-btn" type="button"><?php echo esc_html__('Centre H', 'osds3d-customizer-pro'); ?></button>
                    <button id="osds3d-align-right" class="osds3d-ui-btn" type="button"><?php echo esc_html__('Droite', 'osds3d-customizer-pro'); ?></button>
                    <button id="osds3d-align-top" class="osds3d-ui-btn" type="button"><?php echo esc_html__('Haut', 'osds3d-customizer-pro'); ?></button>
                    <button id="osds3d-align-center-y" class="osds3d-ui-btn" type="button"><?php echo esc_html__('Centre V', 'osds3d-customizer-pro'); ?></button>
                    <button id="osds3d-align-bottom" class="osds3d-ui-btn" type="button"><?php echo esc_html__('Bas', 'osds3d-customizer-pro'); ?></button>
                </div>

                <div class="osds3d-grid-2">
                    <button id="osds3d-distribute-x" class="osds3d-ui-btn osds3d-ui-btn-ghost" type="button"><?php echo esc_html__('Distribuer H', 'osds3d-customizer-pro'); ?></button>
                    <button id="osds3d-distribute-y" class="osds3d-ui-btn osds3d-ui-btn-ghost" type="button"><?php echo esc_html__('Distribuer V', 'osds3d-customizer-pro'); ?></button>
                </div>
            </div>

            <div id="panel-qr" class="osds3d-panel" hidden>
                <h3 class="osds3d-panel-title"><?php echo esc_html__('QR Code', 'osds3d-customizer-pro'); ?></h3>

                <div class="osds3d-field">
                    <label class="osds3d-label" for="osds3d-qr-content"><?php echo esc_html__('Contenu', 'osds3d-customizer-pro'); ?></label>
                    <input type="text" id="osds3d-qr-content">
                </div>

                <div class="osds3d-grid-2">
                    <div class="osds3d-field">
                        <label class="osds3d-label" for="osds3d-qr-color"><?php echo esc_html__('Avant-plan', 'osds3d-customizer-pro'); ?></label>
                        <input type="color" id="osds3d-qr-color" value="#000000">
                    </div>
                    <div class="osds3d-field">
                        <label class="osds3d-label" for="osds3d-qr-bg-color"><?php echo esc_html__('Arrière-plan', 'osds3d-customizer-pro'); ?></label>
                        <input type="color" id="osds3d-qr-bg-color" value="#ffffff">
                    </div>
                </div>

                <div class="osds3d-field">
                    <label class="osds3d-label" for="osds3d-qr-logo"><?php echo esc_html__('Logo', 'osds3d-customizer-pro'); ?></label>
                    <input type="file" id="osds3d-qr-logo" accept="image/png,image/jpeg">
                </div>

                <div class="osds3d-grid-2">
                    <div class="osds3d-field">
                        <label class="osds3d-label" for="osds3d-qr-level"><?php echo esc_html__('Correction', 'osds3d-customizer-pro'); ?></label>
                        <select id="osds3d-qr-level">
                            <option value="L">L</option>
                            <option value="M" selected>M</option>
                            <option value="Q">Q</option>
                            <option value="H">H</option>
                        </select>
                    </div>
                    <div class="osds3d-field">
                        <label class="osds3d-label" for="osds3d-qr-size"><?php echo esc_html__('Taille', 'osds3d-customizer-pro'); ?></label>
                        <input type="number" id="osds3d-qr-size" value="256" min="64" max="1024">
                    </div>
                </div>

                <div class="osds3d-field">
                    <button id="osds3d-generate-qr" class="osds3d-ui-btn osds3d-ui-btn-primary osds3d-full" type="button">
                        <?php echo esc_html__('Générer QR', 'osds3d-customizer-pro'); ?>
                    </button>
                </div>
            </div>

            <div id="panel-templates" class="osds3d-panel" hidden>
                <h3 class="osds3d-panel-title"><?php echo esc_html__('Modèles', 'osds3d-customizer-pro'); ?></h3>

                <div class="osds3d-field">
                    <select id="osds3d-template-select">
                        <option value="">-- <?php echo esc_html__('Sélectionner', 'osds3d-customizer-pro'); ?> --</option>
                    </select>
                </div>

                <div class="osds3d-field">
                    <button id="osds3d-apply-template" class="osds3d-ui-btn osds3d-ui-btn-ghost osds3d-full" type="button">
                        <?php echo esc_html__('Charger modèle', 'osds3d-customizer-pro'); ?>
                    </button>
                </div>
            </div>

            <div id="panel-guided" class="osds3d-panel" hidden>
                <h3 class="osds3d-panel-title"><?php echo esc_html__('Champs guidés', 'osds3d-customizer-pro'); ?></h3>

                <div class="osds3d-field">
                    <label class="osds3d-label" for="osds3d-guided-name"><?php echo esc_html__('Prénom', 'osds3d-customizer-pro'); ?></label>
                    <input type="text" id="osds3d-guided-name">
                </div>

                <div class="osds3d-field">
                    <label class="osds3d-label" for="osds3d-guided-date"><?php echo esc_html__('Date', 'osds3d-customizer-pro'); ?></label>
                    <input type="text" id="osds3d-guided-date">
                </div>

                <div class="osds3d-field">
                    <label class="osds3d-label" for="osds3d-guided-message"><?php echo esc_html__('Message', 'osds3d-customizer-pro'); ?></label>
                    <textarea id="osds3d-guided-message" rows="3"></textarea>
                </div>

                <div class="osds3d-field">
                    <button id="osds3d-apply-guided-fields" class="osds3d-ui-btn osds3d-ui-btn-primary osds3d-full" type="button">
                        <?php echo esc_html__('Appliquer les champs', 'osds3d-customizer-pro'); ?>
                    </button>
                </div>
            </div>

            <div id="panel-models3d" class="osds3d-panel" hidden>
                <h3 class="osds3d-panel-title"><?php echo esc_html__('Modèle 3D', 'osds3d-customizer-pro'); ?></h3>

                <div class="osds3d-field">
                    <label class="osds3d-label" for="osds3d-model-file"><?php echo esc_html__('Fichier STL/OBJ/GLB', 'osds3d-customizer-pro'); ?></label>
                    <input type="file" id="osds3d-model-file" accept=".stl,.obj,.glb">
                </div>

                <div class="osds3d-grid-2">
                    <div class="osds3d-field">
                        <label class="osds3d-label" for="osds3d-model-color"><?php echo esc_html__('Couleur', 'osds3d-customizer-pro'); ?></label>
                        <input type="color" id="osds3d-model-color" value="#cccccc">
                    </div>
                    <div class="osds3d-field">
                        <label class="osds3d-label" for="osds3d-model-scale"><?php echo esc_html__('Échelle', 'osds3d-customizer-pro'); ?></label>
                        <input type="range" id="osds3d-model-scale" min="0.1" max="2" step="0.1" value="1">
                    </div>
                </div>

                <div class="osds3d-grid-2">
                    <button id="osds3d-add-model" class="osds3d-ui-btn" type="button"><?php echo esc_html__('Ajouter / Mettre à jour', 'osds3d-customizer-pro'); ?></button>
                    <button id="osds3d-remove-model" class="osds3d-ui-btn osds3d-ui-btn-danger" type="button"><?php echo esc_html__('Supprimer', 'osds3d-customizer-pro'); ?></button>
                </div>
            </div>

            <div id="panel-faces" class="osds3d-panel" hidden>
                <h3 class="osds3d-panel-title"><?php echo esc_html__('Faces / Zones', 'osds3d-customizer-pro'); ?></h3>

                <div class="osds3d-face-switcher">
                    <button id="osds3d-face-front" class="osds3d-ui-btn osds3d-ui-btn-primary" type="button"><?php echo esc_html__('Face avant', 'osds3d-customizer-pro'); ?></button>
                    <button id="osds3d-face-back" class="osds3d-ui-btn osds3d-ui-btn-ghost" type="button"><?php echo esc_html__('Face arrière', 'osds3d-customizer-pro'); ?></button>
                </div>

                <div class="osds3d-field">
                    <label class="osds3d-label"><?php echo esc_html__('Zone imprimable', 'osds3d-customizer-pro'); ?></label>
                    <button id="osds3d-toggle-safe-zone" class="osds3d-ui-btn osds3d-ui-btn-ghost osds3d-full" type="button">
                        <?php echo esc_html__('Afficher / masquer la zone de sécurité', 'osds3d-customizer-pro'); ?>
                    </button>
                </div>
            </div>
        </section>

        <main class="osds3d-app-stage">

            <div class="osds3d-stage-toolbar">
                <button id="osds3d-undo" class="osds3d-ui-btn" type="button">⤺</button>
                <button id="osds3d-redo" class="osds3d-ui-btn" type="button">⤻</button>
                <button id="osds3d-toggle-grid" class="osds3d-ui-btn" type="button">#</button>
                <button id="osds3d-zoom-in" class="osds3d-ui-btn" type="button">+</button>
                <button id="osds3d-zoom-out" class="osds3d-ui-btn" type="button">-</button>
                <button id="osds3d-duplicate" class="osds3d-ui-btn" type="button">⧉</button>
                <button id="osds3d-bring-forward" class="osds3d-ui-btn" type="button">↑</button>
                <button id="osds3d-send-backward" class="osds3d-ui-btn" type="button">↓</button>
                <button id="osds3d-lock" class="osds3d-ui-btn" type="button">🔒</button>
                <button id="osds3d-unlock" class="osds3d-ui-btn" type="button">🔓</button>
                <button id="osds3d-delete-object" class="osds3d-ui-btn osds3d-ui-btn-danger" type="button">🗑</button>
                <button id="osds3d-clear-canvas" class="osds3d-ui-btn osds3d-ui-btn-danger" type="button">♻</button>
                <button id="osds3d-download-png" class="osds3d-ui-btn" type="button">PNG</button>
                <button id="osds3d-download-png-hd" class="osds3d-ui-btn" type="button">PNG HD</button>
                <button id="osds3d-download-svg" class="osds3d-ui-btn" type="button">SVG</button>
                <button id="osds3d-download-pdf" class="osds3d-ui-btn" type="button">PDF</button>
                <button id="osds3d-show-preview3d" class="osds3d-ui-btn" type="button">3D</button>
                <button id="osds3d-group" class="osds3d-ui-btn" type="button">👥</button>
                <button id="osds3d-ungroup" class="osds3d-ui-btn" type="button">🚫</button>
            </div>

            <div class="osds3d-stage-main">

                <div class="osds3d-stage-canvas-wrap">
                    <div class="osds3d-canvas-card">
                        <div id="osds3d-safe-zone" class="osds3d-safe-zone" hidden></div>
                        <canvas id="osds3d-canvas" width="800" height="600"></canvas>
                    </div>

                    <div id="osds3d-preview3d" class="osds3d-preview3d" hidden>
                        <div class="osds3d-canvas-card osds3d-canvas-card-3d">
                            <button id="osds3d-hide-preview3d" class="osds3d-ui-btn osds3d-ui-btn-ghost osds3d-preview3d-close" type="button">
                                ✕
                            </button>
                            <canvas id="osds3d-three-canvas" width="800" height="600"></canvas>
                        </div>
                    </div>
                </div>

                <aside class="osds3d-stage-rightbar">
                    <div class="osds3d-side-card">
                        <h3 class="osds3d-side-title"><?php echo esc_html__('Calques', 'osds3d-customizer-pro'); ?></h3>
                        <div id="osds3d-layers-panel" class="osds3d-layers-panel">
                            <div class="osds3d-empty-note"><?php echo esc_html__('Aucun calque pour le moment.', 'osds3d-customizer-pro'); ?></div>
                        </div>
                    </div>

                    <div class="osds3d-side-card">
                        <h3 class="osds3d-side-title"><?php echo esc_html__('Historique', 'osds3d-customizer-pro'); ?></h3>
                        <div id="osds3d-history-panel" class="osds3d-history-panel">
                            <div class="osds3d-empty-note"><?php echo esc_html__('Historique visuel à venir.', 'osds3d-customizer-pro'); ?></div>
                        </div>
                    </div>

                    <div class="osds3d-side-card">
                        <h3 class="osds3d-side-title"><?php echo esc_html__('Autosave', 'osds3d-customizer-pro'); ?></h3>
                        <div class="osds3d-autosave-box">
                            <button id="osds3d-restore-autosave" class="osds3d-ui-btn osds3d-ui-btn-ghost osds3d-full" type="button">
                                <?php echo esc_html__('Restaurer le brouillon', 'osds3d-customizer-pro'); ?>
                            </button>
                            <button id="osds3d-clear-autosave" class="osds3d-ui-btn osds3d-ui-btn-danger osds3d-full" type="button">
                                <?php echo esc_html__('Supprimer le brouillon', 'osds3d-customizer-pro'); ?>
                            </button>
                        </div>
                    </div>
                </aside>

            </div>
        </main>
    </div>
</div>

<div id="osds3d-help-modal" class="osds3d-help-modal" hidden>
    <div class="osds3d-help-dialog">
        <button id="osds3d-help-close" class="osds3d-ui-btn osds3d-ui-btn-ghost osds3d-help-close" type="button">✕</button>
        <h2><?php echo esc_html__('Besoin d’aide ?', 'osds3d-customizer-pro'); ?></h2>
        <p><?php echo esc_html__('Voici les bases pour utiliser l’éditeur.', 'osds3d-customizer-pro'); ?></p>
        <ul>
            <li><?php echo esc_html__('Choisissez un onglet à gauche pour ajouter vos éléments.', 'osds3d-customizer-pro'); ?></li>
            <li><?php echo esc_html__('Utilisez la barre au-dessus du canvas pour les actions rapides.', 'osds3d-customizer-pro'); ?></li>
            <li><?php echo esc_html__('Utilisez le glisser-déposer pour vos images.', 'osds3d-customizer-pro'); ?></li>
            <li><?php echo esc_html__('Sauvegardez votre design avant l’ajout au panier.', 'osds3d-customizer-pro'); ?></li>
        </ul>
    </div>
</div>