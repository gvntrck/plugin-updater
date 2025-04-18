<?php
/**
 * Classe principal do atualizador de plugins
 *
 * @package GVNTRCK_Updater
 */

if (!class_exists('GVNTRCK_Updater')) {

    class GVNTRCK_Updater {
        /**
         * Armazena plugins que correspondem ao autor "gvntrck"
         *
         * @var array
         */
        private $gvntrck_plugins = array();
        
        /**
         * Nome do transiente usado para armazenar informações de atualização
         *
         * @var string
         */
        private $transient_name = 'gvntrck_updater_cache';

        /**
         * Inicializa o atualizador
         */
        public function init() {
            // Filtros para modificar os dados de atualização dos plugins
            add_filter('pre_set_site_transient_update_plugins', array($this, 'check_for_updates'));
            add_filter('plugins_api', array($this, 'plugin_info'), 10, 3);
            add_filter('upgrader_post_install', array($this, 'after_update'), 10, 3);
            
            // Carrega os plugins na inicialização
            add_action('admin_init', array($this, 'load_gvntrck_plugins'));
            
            // Adiciona ação para limpar o cache de atualizações
            add_action('admin_init', array($this, 'maybe_clear_update_cache'));
            
            // Adiciona ação para o AJAX de verificação manual de atualizações
            add_action('wp_ajax_gvntrck_check_updates', array($this, 'ajax_check_updates'));
        }
        
        /**
         * Carrega todos os plugins de autoria "gvntrck"
         */
        public function load_gvntrck_plugins() {
            if (!function_exists('get_plugins')) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }
            
            $all_plugins = get_plugins();
            $this->gvntrck_plugins = array();
            
            // Depuração: Registrar todos os plugins e seus autores
            error_log('GVNTRCK Updater - Plugins encontrados: ' . count($all_plugins));
            foreach ($all_plugins as $plugin_file => $plugin_data) {
                error_log('Plugin: ' . $plugin_data['Name'] . ' | Autor: ' . (isset($plugin_data['Author']) ? $plugin_data['Author'] : 'Não definido'));
                
                // Verifica se o autor contém "gvntrck"
                if (isset($plugin_data['Author']) && (stripos($plugin_data['Author'], 'gvntrck') !== false)) {
                    // Guarda informações importantes para atualização
                    $this->gvntrck_plugins[$plugin_file] = array(
                        'name' => $plugin_data['Name'],
                        'version' => $plugin_data['Version'],
                        'plugin_uri' => isset($plugin_data['PluginURI']) ? $plugin_data['PluginURI'] : '',
                        'description' => $plugin_data['Description'],
                        'author' => $plugin_data['Author'],
                        'author_uri' => $plugin_data['AuthorURI']
                    );
                }
            }
            
            return $this->gvntrck_plugins;
        }
        
        /**
         * Intercepta o transiente de atualização de plugins
         * 
         * @param object $transient Objeto transient de atualização
         * @return object Objeto transient modificado
         */
        public function check_for_updates($transient) {
            if (empty($transient->checked)) {
                return $transient;
            }
            
            // Verifica se já temos um cache válido
            $cache = get_site_transient($this->transient_name);
            if ($cache !== false && is_object($cache) && isset($cache->last_checked) && (time() - $cache->last_checked) < GVNTRCK_UPDATER_CHECK_INTERVAL) {
                // Se o cache for válido, aplica as atualizações do cache
                if (isset($cache->updates) && is_array($cache->updates)) {
                    foreach ($cache->updates as $plugin_file => $update_data) {
                        $transient->response[$plugin_file] = $update_data;
                    }
                }
                return $transient;
            }
            
            // Inicializa o objeto de cache
            $cache = new stdClass();
            $cache->last_checked = time();
            $cache->updates = array();
            
            foreach ($this->gvntrck_plugins as $plugin_file => $plugin_data) {
                // Pula se não houver URI do plugin
                if (empty($plugin_data['plugin_uri']) || !$this->is_github_url($plugin_data['plugin_uri'])) {
                    continue;
                }
                
                // Obtém informações de versão do GitHub
                $github_info = $this->get_github_plugin_info($plugin_data['plugin_uri']);
                
                if (is_wp_error($github_info) || empty($github_info)) {
                    continue;
                }
                
                // Se houver uma versão mais recente disponível
                if (isset($github_info->tag_name) && $this->is_newer_version($github_info->tag_name, $plugin_data['version'])) {
                    // Cria objeto de resposta
                    $response = new stdClass();
                    $response->slug = dirname($plugin_file);
                    $response->plugin = $plugin_file;
                    $response->new_version = $this->clean_version($github_info->tag_name);
                    $response->url = $plugin_data['plugin_uri'];
                    $response->package = $github_info->zipball_url;
                    
                    // Adiciona ao transient
                    $transient->response[$plugin_file] = $response;
                    
                    // Armazena no cache
                    $cache->updates[$plugin_file] = $response;
                }
            }
            
            // Atualiza o cache
            set_site_transient($this->transient_name, $cache, GVNTRCK_UPDATER_CHECK_INTERVAL);
            
            return $transient;
        }
        
        /**
         * Fornece informações detalhadas sobre o plugin para a tela de atualização
         * 
         * @param false|object|array $result Resultado padrão
         * @param string $action Ação da API
         * @param object $args Argumentos da API
         * @return false|object
         */
        public function plugin_info($result, $action, $args) {
            // Retorna se não for uma solicitação de informações de plugin ou se o slug não estiver definido
            if ($action !== 'plugin_information' || !isset($args->slug)) {
                return $result;
            }
            
            // Procura o plugin pelo slug
            foreach ($this->gvntrck_plugins as $plugin_file => $plugin_data) {
                if (dirname($plugin_file) === $args->slug) {
                    // Obtém informações do GitHub
                    if (!empty($plugin_data['plugin_uri']) && $this->is_github_url($plugin_data['plugin_uri'])) {
                        $github_info = $this->get_github_plugin_info($plugin_data['plugin_uri']);
                        
                        if (!is_wp_error($github_info) && !empty($github_info)) {
                            $response = new stdClass();
                            $response->name = $plugin_data['name'];
                            $response->slug = $args->slug;
                            $response->version = $this->clean_version($github_info->tag_name);
                            $response->author = $plugin_data['author'];
                            $response->author_profile = $plugin_data['author_uri'];
                            $response->homepage = $plugin_data['plugin_uri'];
                            $response->requires = '5.0'; // Versão mínima do WordPress
                            $response->tested = '6.5'; // Versão testada do WordPress
                            $response->downloaded = 0;
                            $response->last_updated = isset($github_info->published_at) ? date('Y-m-d', strtotime($github_info->published_at)) : '';
                            $response->sections = array(
                                'description' => $plugin_data['description'],
                                'changelog' => isset($github_info->body) ? $this->format_github_markdown($github_info->body) : 'Não há informações de changelog disponíveis.'
                            );
                            $response->download_link = isset($github_info->zipball_url) ? $github_info->zipball_url : '';
                            
                            return $response;
                        }
                    }
                }
            }
            
            return $result;
        }
        
        /**
         * Ações após a atualização do plugin
         * 
         * @param bool $response Resposta da instalação
         * @param array $hook_extra Dados extras do hook
         * @param array $result Resultado da instalação
         * @return array Resultado da instalação
         */
        public function after_update($response, $hook_extra, $result) {
            // Verifica se é uma atualização de plugin
            if (isset($hook_extra['plugin']) && array_key_exists($hook_extra['plugin'], $this->gvntrck_plugins)) {
                // Garante que o diretório tem o nome correto após a atualização
                $plugin_slug = dirname($hook_extra['plugin']);
                $proper_destination = WP_PLUGIN_DIR . '/' . $plugin_slug;
                
                // Se o diretório destino não existir, rename pode falhar, então verificamos
                if (isset($result['destination_name']) && $result['destination_name'] !== $plugin_slug) {
                    $current_destination = $result['destination'];
                    $new_destination = str_replace($result['destination_name'], $plugin_slug, $result['destination']);
                    
                    // Renomeia o diretório para o slug correto
                    if ($current_destination !== $new_destination) {
                        rename($current_destination, $new_destination);
                        $result['destination'] = $new_destination;
                    }
                }
            }
            
            return $result;
        }
        
        /**
         * Obtém informações do plugin a partir do GitHub
         * 
         * @param string $github_url URL do repositório GitHub
         * @return object|WP_Error Informações do GitHub ou erro
         */
        private function get_github_plugin_info($github_url) {
            // Extrai informações do usuário e repo da URL do GitHub
            $github_data = $this->parse_github_url($github_url);
            
            if (empty($github_data)) {
                return new WP_Error('parse_error', 'Não foi possível analisar a URL do GitHub');
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
                return $response;
            }
            
            $code = wp_remote_retrieve_response_code($response);
            if ($code !== 200) {
                return new WP_Error('github_error', 'Erro ao acessar a API do GitHub: ' . $code);
            }
            
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body);
            
            if (empty($data)) {
                return new WP_Error('github_error', 'Resposta inválida da API do GitHub');
            }
            
            return $data;
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
         * Verifica se uma versão é mais recente que outra
         * 
         * @param string $remote_version Versão remota (ex: v1.2.3 ou 1.2.3)
         * @param string $local_version Versão local
         * @return bool True se a versão remota for mais recente
         */
        private function is_newer_version($remote_version, $local_version) {
            $remote_clean = $this->clean_version($remote_version);
            $local_clean = $this->clean_version($local_version);
            
            return version_compare($remote_clean, $local_clean, '>');
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
        
        /**
         * Formata o markdown do GitHub para HTML
         * 
         * @param string $markdown Texto em markdown
         * @return string HTML formatado
         */
        private function format_github_markdown($markdown) {
            // Implementação simples, pode ser melhorada com uma biblioteca de processamento de markdown
            $formatted = nl2br(esc_html($markdown));
            
            // Formatação básica
            $formatted = preg_replace('/\*\*(.*?)\*\*/s', '<strong>$1</strong>', $formatted);
            $formatted = preg_replace('/\*(.*?)\*/s', '<em>$1</em>', $formatted);
            
            return $formatted;
        }
        
        /**
         * Retorna a lista de plugins GVNTRCK
         * 
         * @return array Lista de plugins
         */
        public function get_gvntrck_plugins() {
            return $this->gvntrck_plugins;
        }
        
        /**
         * Limpa o cache de atualizações se solicitado via parâmetro GET
         */
        public function maybe_clear_update_cache() {
            if (isset($_GET['gvntrck_clear_cache']) && current_user_can('manage_options')) {
                $this->clear_update_cache();
                
                // Redireciona de volta para a página de administração
                if (wp_safe_redirect(admin_url('plugins.php?page=gvntrck-updater&cache_cleared=1'))) {
                    exit;
                }
            }
        }
        
        /**
         * Limpa o cache de atualizações
         */
        public function clear_update_cache() {
            delete_site_transient('update_plugins');
            delete_site_transient($this->transient_name);
            return true;
        }
        
        /**
         * Manipula solicitações AJAX para verificação manual de atualizações
         */
        public function ajax_check_updates() {
            // Verifica nonce e permissões
            if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'gvntrck_updater_nonce') || !current_user_can('manage_options')) {
                wp_send_json_error(array('message' => __('Erro de segurança. Recarregue a página e tente novamente.', 'gvntrck-updater')));
            }
            
            // Limpa o cache de atualizações
            $this->clear_update_cache();
            
            // Força a verificação de atualizações
            $this->load_gvntrck_plugins();
            $plugin_file = isset($_POST['plugin_file']) ? sanitize_text_field($_POST['plugin_file']) : '';
            
            if (empty($plugin_file) || !isset($this->gvntrck_plugins[$plugin_file])) {
                wp_send_json_error(array('message' => __('Plugin não encontrado.', 'gvntrck-updater')));
            }
            
            $plugin_data = $this->gvntrck_plugins[$plugin_file];
            
            // Verifica se há atualizações disponíveis
            if (empty($plugin_data['plugin_uri']) || !$this->is_github_url($plugin_data['plugin_uri'])) {
                wp_send_json_error(array('message' => __('Este plugin não tem um repositório GitHub válido.', 'gvntrck-updater')));
            }
            
            // Obtém informações do GitHub
            $github_info = $this->get_github_plugin_info($plugin_data['plugin_uri']);
            
            if (is_wp_error($github_info)) {
                wp_send_json_error(array('message' => $github_info->get_error_message()));
            }
            
            if (empty($github_info)) {
                wp_send_json_error(array('message' => __('Não foi possível obter informações do GitHub.', 'gvntrck-updater')));
            }
            
            // Verifica se há uma versão mais recente
            $has_update = false;
            $remote_version = '';
            $published_at = '';
            
            if (isset($github_info->tag_name)) {
                $remote_version = $github_info->tag_name;
                $has_update = $this->is_newer_version($remote_version, $plugin_data['version']);
                $published_at = isset($github_info->published_at) ? date_i18n(get_option('date_format'), strtotime($github_info->published_at)) : '';
            }
            
            // Retorna os resultados
            wp_send_json_success(array(
                'has_update' => $has_update,
                'remote_version' => $remote_version,
                'published_at' => $published_at,
                'message' => $has_update ? __('Atualização disponível!', 'gvntrck-updater') : __('Nenhuma atualização disponível.', 'gvntrck-updater')
            ));
        }
    }
}
