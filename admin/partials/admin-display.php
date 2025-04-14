<?php
/**
 * Template para exibição da página de administração do plugin
 *
 * @package GVNTRCK_Updater
 */
?>

<div class="wrap gvntrck-updater-admin">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <?php if (isset($cache_cleared) && $cache_cleared) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php _e('Cache de atualizações limpo com sucesso!', 'gvntrck-updater'); ?></p>
        </div>
    <?php endif; ?>
    
    <div class="gvntrck-updater-intro">
        <p><?php _e('Este painel lista todos os plugins de autoria "gvntrck" instalados neste site, e mostra informações sobre atualizações disponíveis no GitHub.', 'gvntrck-updater'); ?></p>
        <p>
            <a href="<?php echo esc_url(admin_url('plugins.php?page=gvntrck-updater&gvntrck_clear_cache=1')); ?>" class="button">
                <span class="dashicons dashicons-update"></span> <?php _e('Limpar Cache de Atualizações', 'gvntrck-updater'); ?>
            </a>
        </p>
    </div>
    
    <?php if (empty($gvntrck_plugins)) : ?>
        <div class="gvntrck-updater-notice notice notice-info">
            <p><?php _e('Nenhum plugin com autor "gvntrck" foi encontrado neste site.', 'gvntrck-updater'); ?></p>
        </div>
    <?php else : ?>
        <div class="gvntrck-updater-table-container">
            <table class="wp-list-table widefat fixed striped gvntrck-plugins-table">
                <thead>
                    <tr>
                        <th class="column-name"><?php _e('Plugin', 'gvntrck-updater'); ?></th>
                        <th class="column-version"><?php _e('Versão Instalada', 'gvntrck-updater'); ?></th>
                        <th class="column-remote-version"><?php _e('Versão no GitHub', 'gvntrck-updater'); ?></th>
                        <th class="column-repo"><?php _e('Repositório', 'gvntrck-updater'); ?></th>
                        <th class="column-status"><?php _e('Status', 'gvntrck-updater'); ?></th>
                        <th class="column-actions"><?php _e('Ações', 'gvntrck-updater'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($gvntrck_plugins as $plugin_file => $plugin_data) : 
                        $has_update = isset($plugins_with_updates[$plugin_file]['has_update']) ? $plugins_with_updates[$plugin_file]['has_update'] : false;
                        $remote_version = isset($plugins_with_updates[$plugin_file]['remote_version']) ? $plugins_with_updates[$plugin_file]['remote_version'] : '';
                        $published_at = isset($plugins_with_updates[$plugin_file]['published_at']) ? $plugins_with_updates[$plugin_file]['published_at'] : '';
                        $error = isset($plugins_with_updates[$plugin_file]['error']) ? $plugins_with_updates[$plugin_file]['error'] : '';
                        
                        // Definir classes CSS com base no status de atualização
                        $row_class = $has_update ? 'has-update' : '';
                        $status_class = $has_update ? 'update-available' : 'up-to-date';
                        $status_text = $has_update ? __('Atualização disponível!', 'gvntrck-updater') : __('Atualizado', 'gvntrck-updater');
                        
                        if (!empty($error)) {
                            $status_class = 'error';
                            $status_text = __('Erro', 'gvntrck-updater');
                        } elseif (empty($plugin_data['plugin_uri'])) {
                            $status_class = 'warning';
                            $status_text = __('Sem URI do GitHub', 'gvntrck-updater');
                        }
                    ?>
                        <tr class="<?php echo esc_attr($row_class); ?>">
                            <td class="column-name">
                                <strong><?php echo esc_html($plugin_data['name']); ?></strong>
                            </td>
                            <td class="column-version">
                                <?php echo esc_html($plugin_data['version']); ?>
                            </td>
                            <td class="column-remote-version">
                                <?php if (!empty($remote_version)) : ?>
                                    <?php echo esc_html($remote_version); ?>
                                    <?php if (!empty($published_at)) : ?>
                                        <span class="release-date">(<?php echo esc_html($published_at); ?>)</span>
                                    <?php endif; ?>
                                <?php else : ?>
                                    <span class="na">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="column-repo">
                                <?php if (!empty($plugin_data['plugin_uri'])) : ?>
                                    <a href="<?php echo esc_url($plugin_data['plugin_uri']); ?>" target="_blank">
                                        <?php echo esc_html($this->get_repo_display_name($plugin_data['plugin_uri'])); ?>
                                        <span class="dashicons dashicons-external"></span>
                                    </a>
                                <?php else : ?>
                                    <span class="na">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="column-status">
                                <span class="status-pill <?php echo esc_attr($status_class); ?>">
                                    <?php echo esc_html($status_text); ?>
                                </span>
                                <?php if (!empty($error)) : ?>
                                    <span class="error-details" title="<?php echo esc_attr($error); ?>">
                                        <span class="dashicons dashicons-info-outline"></span>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="column-actions">
                                <?php if ($has_update) : ?>
                                    <a href="<?php echo esc_url(wp_nonce_url(self_admin_url('update.php?action=upgrade-plugin&plugin=' . urlencode($plugin_file)), 'upgrade-plugin_' . $plugin_file)); ?>" class="button button-primary">
                                        <?php _e('Atualizar Agora', 'gvntrck-updater'); ?>
                                    </a>
                                <?php elseif (!empty($plugin_data['plugin_uri'])) : ?>
                                    <button class="button check-update-button" data-plugin-file="<?php echo esc_attr($plugin_file); ?>">
                                        <?php _e('Verificar Atualizações', 'gvntrck-updater'); ?>
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="gvntrck-updater-footer">
            <p>
                <?php _e('Para que seus plugins personalizados apareçam nesta lista, eles devem ter o autor definido como "gvntrck" no cabeçalho do arquivo principal.', 'gvntrck-updater'); ?>
            </p>
            <p>
                <?php _e('Para habilitar a atualização automática, seu plugin deve ter o URI do plugin definido como a URL do repositório GitHub.', 'gvntrck-updater'); ?>
            </p>
        </div>
    <?php endif; ?>
</div>

<?php
/**
 * Helper para obter nome de exibição do repositório a partir da URL
 */
function get_repo_display_name($url) {
    // Obtém apenas o nome do repositório da URL do GitHub
    $pattern = '#https?://github\.com/([^/]+)/([^/]+)/?.*#i';
    if (preg_match($pattern, $url, $matches)) {
        return $matches[1] . '/' . $matches[2];
    }
    return $url;
}
?>
