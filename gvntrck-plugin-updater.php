<?php
/**
 * Plugin Name: Atualizador de Plugins GVNTRCK
 * Plugin URI: https://github.com/gvntrck/plugin-updater
 * Description: Atualizador automático para plugins personalizados com autor "gvntrck"
 * Version: 1.0.1
 * Author: gvntrck
 * Author URI: https://github.com/gvntrck
 * License: GPL-2.0+
 * Text Domain: gvntrck-updater
 * Domain Path: /languages
 */

// Se este arquivo for chamado diretamente, aborte.
if (!defined('WPINC')) {
    die;
}

// Define constantes do plugin
define('GVNTRCK_UPDATER_VERSION', '1.0.1');
define('GVNTRCK_UPDATER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('GVNTRCK_UPDATER_PLUGIN_URL', plugin_dir_url(__FILE__));

// Carrega as classes do plugin
require_once GVNTRCK_UPDATER_PLUGIN_DIR . 'includes/class-gvntrck-updater.php';
require_once GVNTRCK_UPDATER_PLUGIN_DIR . 'admin/class-gvntrck-updater-admin.php';

/**
 * Inicia o plugin.
 */
function gvntrck_updater_init() {
    // Inicializa a classe principal do atualizador
    $updater = new GVNTRCK_Updater();
    $updater->init();

    // Inicializa a interface de administração
    if (is_admin()) {
        $admin = new GVNTRCK_Updater_Admin();
        $admin->init();
    }
}

// Inicializa o plugin
add_action('plugins_loaded', 'gvntrck_updater_init');

// Registra função de ativação
register_activation_hook(__FILE__, 'gvntrck_updater_activate');
function gvntrck_updater_activate() {
    // Código para executar na ativação do plugin
    flush_rewrite_rules();
}

// Registra função de desativação
register_deactivation_hook(__FILE__, 'gvntrck_updater_deactivate');
function gvntrck_updater_deactivate() {
    // Código para executar na desativação do plugin
    flush_rewrite_rules();
}
