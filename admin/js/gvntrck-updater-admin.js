/**
 * JavaScript para a página de administração do Atualizador de Plugins GVNTRCK
 */
(function($) {
    'use strict';

    $(document).ready(function() {
        // Manipula o clique nos botões de verificação de atualização
        $('.check-update-button').on('click', function(e) {
            e.preventDefault();
            
            const button = $(this);
            const pluginFile = button.data('plugin-file');
            
            // Evita cliques múltiplos
            if (button.hasClass('loading')) {
                return;
            }
            
            // Altera o estado do botão
            button.addClass('loading').text(gvntrckUpdater.checkingText);
            
            // Faz a chamada AJAX para verificar atualizações
            $.ajax({
                url: gvntrckUpdater.ajaxUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'gvntrck_check_update',
                    plugin_file: pluginFile,
                    nonce: gvntrckUpdater.nonce
                },
                success: function(response) {
                    // Remove a classe de carregamento
                    button.removeClass('loading');
                    
                    if (response.success) {
                        // Atualiza a UI com as informações de versão
                        const row = button.closest('tr');
                        const remoteVersionCell = row.find('.column-remote-version');
                        const statusCell = row.find('.column-status');
                        const actionsCell = row.find('.column-actions');
                        
                        if (response.data.has_update) {
                            // Atualiza a versão remota
                            let versionText = response.data.remote_version;
                            if (response.data.published_at) {
                                versionText += ' <span class="release-date">(' + response.data.published_at + ')</span>';
                            }
                            remoteVersionCell.html(versionText);
                            
                            // Atualiza o status
                            statusCell.html('<span class="status-pill update-available">' + gvntrckUpdater.updateAvailableText + '</span>');
                            
                            // Atualiza o botão de ação
                            const updateUrl = response.data.update_url || '#';
                            actionsCell.html('<a href="' + updateUrl + '" class="button button-primary">Atualizar Agora</a>');
                            
                            // Adiciona classe à linha
                            row.addClass('has-update');
                        } else {
                            // Atualiza a versão remota
                            let versionText = response.data.remote_version;
                            if (response.data.published_at) {
                                versionText += ' <span class="release-date">(' + response.data.published_at + ')</span>';
                            }
                            remoteVersionCell.html(versionText);
                            
                            // Atualiza o status
                            statusCell.html('<span class="status-pill up-to-date">' + gvntrckUpdater.noUpdateText + '</span>');
                            
                            // Retorna o botão ao estado normal
                            button.text('Verificar Atualizações');
                        }
                    } else {
                        // Exibe mensagem de erro
                        button.text('Erro ao verificar');
                        
                        // Adiciona tooltip de erro
                        const errorMsg = response.data.message || 'Erro desconhecido';
                        const row = button.closest('tr');
                        const statusCell = row.find('.column-status');
                        
                        statusCell.html(
                            '<span class="status-pill error">Erro</span>' +
                            '<span class="error-details" title="' + errorMsg + '">' +
                            '<span class="dashicons dashicons-info-outline"></span>' +
                            '</span>'
                        );
                        
                        // Volta o botão ao normal após 3 segundos
                        setTimeout(function() {
                            button.text('Verificar Atualizações');
                        }, 3000);
                    }
                },
                error: function() {
                    // Remove a classe de carregamento
                    button.removeClass('loading');
                    button.text('Erro na requisição');
                    
                    // Volta o botão ao normal após 3 segundos
                    setTimeout(function() {
                        button.text('Verificar Atualizações');
                    }, 3000);
                }
            });
        });
        
        // Inicializa tooltips (opcional, pode usar uma biblioteca de tooltip se necessário)
        $(document).on('mouseenter', '.error-details', function() {
            // Código para exibir tooltip, se implementado
        });
    });

})(jQuery);
