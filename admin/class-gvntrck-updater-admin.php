<?php
/**
 * Classe de Administração do Atualizador de Plugins
 *
 * @package GVNTRCK_Updater
 */

if (!class_exists('GVNTRCK_Updater_Admin')) {

    class GVNTRCK_Updater_Admin {
        /**
         * Instância do atualizador principal
         *
         * @var GVNTRCK_Updater
         */
        private $updater;
        
        /**
         * Inicializa a interface de administração
         */
        public function init() {
            // Adiciona menu no painel administrativo
            add_action('admin_menu', array($this, 'add_admin_menu'));
            
            // Registra os assets do admin
            add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
            
            // Adiciona link para as configurações na listagem de plugins
            add_filter('plugin_action_links_gvn-updater/gvntrck-plugin-updater.php', array($this, 'add_settings_link'));
            
            // Inicializa a instância do atualizador
            $this->updater = new GVNTRCK_Updater();
        }
        
        /**
         * Adiciona o menu de administração
         */
        public function add_admin_menu() {
            add_submenu_page(
                'plugins.php',
                'Plugins GVNTRCK',
                'Plugins GVNTRCK',
                'manage_options',
                'gvntrck-updater',
                array($this, 'render_admin_page')
            );
        }
        
        /**
         * Renderiza a página de administração
         */
        public function render_admin_page() {
            // Verificar permissões
            if (!current_user_can('manage_options')) {
                wp_die(__('Você não tem permissões suficientes para acessar esta página.', 'gvntrck-updater'));
            }
            
            // Forçar o carregamento de plugins
            $this->updater->load_gvntrck_plugins();
            
            // Carrega os plugins GVNTRCK
            $gvntrck_plugins = $this->updater->get_gvntrck_plugins();
            
            // Depuração: Verificar os plugins carregados
            error_log('GVNTRCK Admin - Plugins carregados: ' . count($gvntrck_plugins));
            foreach ($gvntrck_plugins as $plugin_file => $plugin_data) {
                error_log('Admin Plugin: ' . $plugin_data['name'] . ' | Arquivo: ' . $plugin_file);
            }
            
            // Verifica se existem novidades para cada plugin
            $plugins_with_updates = array();
            foreach ($gvntrck_plugins as $plugin_file => $plugin_data) {
                if (!empty($plugin_data['plugin_uri']) && $this->is_github_url($plugin_data['plugin_uri'])) {
                    $plugins_with_updates[$plugin_file] = $this->check_plugin_update($plugin_data);
                }
            }
            
            // Incluir o template da página de administração
            include_once GVNTRCK_UPDATER_PLUGIN_DIR . 'admin/partials/admin-display.php';
        }
        
        /**
         * Verifica se há atualizações disponíveis para um plugin
         * 
         * @param array $plugin_data Dados do plugin
         * @return array Dados do plugin com informação de atualização
         */
        private function check_plugin_update($plugin_data) {
            $update_data = $plugin_data;
            $update_data['has_update'] = false;
            $update_data['remote_version'] = '';
            
            if (empty($plugin_data['plugin_uri'])) {
                return $update_data;
            }
            
            // Obtém as informações do GitHub
            $github_url = $plugin_data['plugin_uri'];
            $github_data = $this->parse_github_url($github_url);
            
            if (empty($github_data)) {
                return $update_data;
            }
            
            // URL da API do GitHub para a release mais recente
            $api_url = "https://api.github.com/repos/{$github_data['owner']}/{$github_data['repo']}/releases/latest";
            
            // Faz a requisição à API do GitHub
            $response = wp_remote_get($api_url, array(
                'headers' => array(
                    'Accept' => 'application/vnd.github.v3+json',
                    'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . get_bloginfo('url')
                ),
                'timeout' => 10
            ));
            
            if (is_wp_error($response)) {
                $update_data['error'] = $response->get_error_message();
                return $update_data;
            }
            
            $code = wp_remote_retrieve_response_code($response);
            if ($code !== 200) {
                $update_data['error'] = 'Erro ao acessar a API do GitHub: ' . $code;
                return $update_data;
            }
            
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body);
            
            if (empty($data)) {
                $update_data['error'] = 'Resposta inválida da API do GitHub';
                return $update_data;
            }
            
            if (isset($data->tag_name)) {
                $remote_version = $this->clean_version($data->tag_name);
                $local_version = $this->clean_version($plugin_data['version']);
                
                $update_data['remote_version'] = $remote_version;
                $update_data['has_update'] = version_compare($remote_version, $local_version, '>');
                $update_data['download_url'] = isset($data->zipball_url) ? $data->zipball_url : '';
                $update_data['published_at'] = isset($data->published_at) ? date('d/m/Y', strtotime($data->published_at)) : '';
                $update_data['release_notes'] = isset($data->body) ? $data->body : '';
            }
            
            return $update_data;
        }
        
        /**
         * Registra e carrega os assets do admin
         * 
         * @param string $hook Sufixo da página atual
         */
        public function enqueue_admin_assets($hook) {
            if ($hook !== 'plugins_page_gvntrck-updater') {
                return;
            }
            
            // Registra e enfileira o CSS
            wp_register_style(
                'gvntrck-updater-admin',
                GVNTRCK_UPDATER_PLUGIN_URL . 'admin/css/gvntrck-updater-admin.css',
                array(),
                GVNTRCK_UPDATER_VERSION
            );
            wp_enqueue_style('gvntrck-updater-admin');
            
            // Registra e enfileira o JavaScript
            wp_register_script(
                'gvntrck-updater-admin',
                GVNTRCK_UPDATER_PLUGIN_URL . 'admin/js/gvntrck-updater-admin.js',
                array('jquery'),
                GVNTRCK_UPDATER_VERSION,
                true
            );
            wp_enqueue_script('gvntrck-updater-admin');
            
            // Localização de script
            wp_localize_script('gvntrck-updater-admin', 'gvntrckUpdater', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('gvntrck_updater_nonce'),
                'checkingText' => __('Verificando atualizações...', 'gvntrck-updater'),
                'updateAvailableText' => __('Atualização disponível!', 'gvntrck-updater'),
                'noUpdateText' => __('Nenhuma atualização disponível', 'gvntrck-updater')
            ));
        }
        
        /**
         * Adiciona link para as configurações na listagem de plugins
         * 
         * @param array $links Links de ações existentes
         * @return array Links modificados
         */
        public function add_settings_link($links) {
            $settings_link = '<a href="' . admin_url('plugins.php?page=gvntrck-updater') . '">' . __('Configurações', 'gvntrck-updater') . '</a>';
            array_unshift($links, $settings_link);
            return $links;
        }
        
        /**
         * Verifica se a URL é uma URL válida do GitHub
         * 
         * @param string $url URL para verificar
         * @return bool True se for URL do GitHub, caso contrário False
         */
        private function is_github_url($url) {
            return (strpos($url, 'github.com') !== false);
        }
        
        /**
         * Extrai informações do usuário e repositório da URL do GitHub
         * 
         * @param string $github_url URL do GitHub
         * @return array|null Dados do GitHub ou null
         */
        private function parse_github_url($github_url) {
            $pattern = '#https?://github\.com/([^/]+)/([^/]+)/?.*#i';
            if (preg_match($pattern, $github_url, $matches)) {
                return array(
                    'owner' => $matches[1],
                    'repo' => $matches[2]
                );
            }
            return null;
        }
        
        /**
         * Limpa uma string de versão removendo 'v' ou outros prefixos
         * 
         * @param string $version String da versão
         * @return string Versão limpa
         */
        private function clean_version($version) {
            return preg_replace('/^[vV]/', '', trim($version));
        }
    }
}
