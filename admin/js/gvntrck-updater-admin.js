/**
 * JavaScript para a página de administração do Atualizador de Plugins GVNTRCK
 * Versão 1.0.4
 */
(function($) {
    'use strict';

    $(document).ready(function() {
        // Inicializa notificações dismissíveis
        $('.notice-success.is-dismissible').fadeIn('slow').delay(3000).fadeOut('slow');
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
                    action: 'gvntrck_check_updates',
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
                error: function(xhr, status, error) {
                    // Remove a classe de carregamento
                    button.removeClass('loading');
                    button.text(gvntrckUpdater.errorText);
                    
                    // Adiciona tooltip de erro
                    const errorMsg = 'Erro na comunicação com o servidor: ' + status;
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
                    
                    console.error('Erro AJAX:', status, error);
                }
            });
        });
        
        // Implementa tooltips simples
        $(document).on('mouseenter', '.error-details', function() {
            const tooltip = $(this);
            const title = tooltip.attr('title');
            
            if (title) {
                // Cria um elemento de tooltip
                $('<div class="gvntrck-tooltip"></div>')
                    .text(title)
                    .appendTo('body')
                    .css({
                        top: tooltip.offset().top + tooltip.height() + 5,
                        left: tooltip.offset().left
                    })
                    .fadeIn('fast');
                
                // Remove o atributo title para evitar o tooltip nativo
                tooltip.attr('data-title', title).removeAttr('title');
            }
        });
        
        $(document).on('mouseleave', '.error-details', function() {
            // Restaura o atributo title
            const tooltip = $(this);
            const title = tooltip.attr('data-title');
            
            if (title) {
                tooltip.attr('title', title);
            }
            
            // Remove o tooltip
            $('.gvntrck-tooltip').remove();
        });
        
        // Confirma a limpeza do cache
        $('.gvntrck-clear-cache').on('click', function(e) {
            if (!confirm('Tem certeza que deseja limpar o cache de atualizações? Isso forçará uma nova verificação de todos os plugins.')) {
                e.preventDefault();
            }
        });
    });

})(jQuery);
