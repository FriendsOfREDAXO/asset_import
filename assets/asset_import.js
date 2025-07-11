$(document).on('rex:ready', function() {
    const AssetImport = {
        currentPage: 1,
        loading: false,
        hasMore: true,
        currentQuery: '',
        
        init: function() {
            this.bindEvents();
            this.resetResults();
        },
        
        bindEvents: function() {
            $('#asset-import-search-form').on('submit', (e) => {
                e.preventDefault();
                this.currentQuery = $('#asset-import-query').val();
                this.currentPage = 1;
                this.hasMore = true;
                this.resetResults();
                this.search();
            });
            
            $('#asset-import-load-more button').on('click', (e) => {
                e.preventDefault();
                if (!this.loading && this.hasMore) {
                    this.currentPage++;
                    this.search();
                }
            });
            
            $(document).on('click', '.asset-import-import-btn', (e) => {
                e.preventDefault();
                const btn = $(e.currentTarget);
                const item = btn.closest('.asset-import-item');
                const selectSize = item.find('.asset-import-size-select');
                const selectedOption = selectSize.find('option:selected');
                const url = selectedOption.data('url');
                const filename = item.find('.asset-import-title').text();
                const copyright = item.data('copyright') || '';
                
                this.import(url, filename, copyright, btn);
            });
            
            // Lightbox Events
            $(document).on('click', '.asset-import-preview', (e) => {
                e.preventDefault();
                const item = $(e.currentTarget).closest('.asset-import-item');
                this.openLightbox(item);
            });
            
            // Lightbox schließen
            $(document).on('click', '.asset-import-lightbox', (e) => {
                if (e.target === e.currentTarget) {
                    this.closeLightbox();
                }
            });
            
            $(document).on('click', '.asset-import-lightbox-close', (e) => {
                e.preventDefault();
                this.closeLightbox();
            });
            
            // ESC-Taste zum Schließen der Lightbox
            $(document).on('keyup', (e) => {
                if (e.keyCode === 27) { // ESC
                    this.closeLightbox();
                }
            });
        },
        
        resetResults: function() {
            $('#asset-import-results').empty();
            $('#asset-import-load-more').hide();
            $('#asset-import-status').hide();
        },
        
        search: function() {
            if (this.loading) return;
            
            this.loading = true;
            this.showStatus('loading');
            
            const data = {
                asset_import_api: 1,
                action: 'search',
                provider: $('#asset-import-provider').val(),
                query: this.currentQuery,
                page: this.currentPage,
                options: {
                    type: $('#asset-import-type').val()
                }
            };

            $.ajax({
                url: window.location.href,
                data: data,
                success: (response) => {
                    if (response.success) {
                        this.renderResults(response.data);
                    } else {
                        this.showError(response.error || rex.asset_import.error_unknown);
                    }
                },
                error: (xhr, status, error) => {
                    this.showError(rex.asset_import.error_loading + ': ' + error);
                },
                complete: () => {
                    this.loading = false;
                }
            });
        },
        
        renderResults: function(data) {
            const container = $('#asset-import-results');
            let html = '';
            
            if (!data.items || !data.items.length) {
                this.showStatus('no-results');
                return;
            }
            
            data.items.forEach(item => {
                const copyright = item.copyright || '';
                html += `
                    <div class="asset-import-item" data-copyright="${copyright}">
                        <div class="asset-import-preview">
                            ${item.type === 'video' ? `
                                <video controls>
                                    <source src="${item.size.tiny.url}" type="video/mp4">
                                </video>
                            ` : `
                                <img src="${item.preview_url}" alt="${this.escapeHtml(item.title)}">
                            `}
                        </div>
                        <div class="asset-import-info">
                            <div class="asset-import-title">${this.escapeHtml(item.title)}</div>
                            <select class="form-control selectpicker asset-import-size-select">
                                ${Object.entries(item.size).map(([key, value]) => `
                                    <option value="${key}" data-url="${value.url}">
                                        ${key.charAt(0).toUpperCase() + key.slice(1)}
                                    </option>
                                `).join('')}
                            </select>
                            <div class="asset-import-actions">
                                <button class="btn btn-primary asset-import-import-btn">
                                    <i class="rex-icon fa-download"></i> ${rex.asset_import.import}
                                </button>
                            </div>
                            <div class="progress" style="display: none;">
                                <div class="progress-bar progress-bar-striped active" role="progressbar" style="width: 100%">
                                    <i class="rex-icon fa-download"></i> ${rex.asset_import.importing}
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            if (this.currentPage === 1) {
                container.html(html);
            } else {
                container.append(html);
            }
            
            this.hasMore = data.page < data.total_pages;
            $('#asset-import-load-more').toggle(this.hasMore);
            
            this.showStatus('results', data.total);

            // Initialize bootstrap-select for newly added selects
            $('.selectpicker').selectpicker('refresh');
        },
        
        import: function(url, filename, copyright, btn) {
            const item = btn.closest('.asset-import-item');
            const progress = item.find('.progress');
            const categoryId = $('#rex-mediapool-category').val();
            
            btn.hide();
            progress.show();
            
            $.ajax({
                url: window.location.href,
                method: 'POST',
                data: {
                    asset_import_api: 1,
                    action: 'import',
                    provider: $('#asset-import-provider').val(),
                    url: url,
                    filename: filename,
                    category_id: categoryId,
                    copyright: copyright
                },
                success: (response) => {
                    if (response.success) {
                        this.showSuccess(rex.asset_import.success);
                        setTimeout(() => {
                            progress.hide();
                            btn.show();
                        }, 1000);
                    } else {
                        this.showError(response.error || rex.asset_import.error_import);
                        progress.hide();
                        btn.show();
                    }
                },
                error: (xhr, status, error) => {
                    this.showError(rex.asset_import.error_loading + ': ' + error);
                    progress.hide();
                    btn.show();
                }
            });
        },
        
        showStatus: function(type, total = 0) {
            const status = $('#asset-import-status');
            
            switch(type) {
                case 'loading':
                    status.removeClass('alert-danger alert-info')
                        .addClass('alert-info')
                        .html(`<i class="rex-icon fa-spinner fa-spin"></i> ${rex.asset_import.loading}`)
                        .show();
                    break;
                    
                case 'results':
                    status.removeClass('alert-danger alert-info')
                        .addClass('alert-info')
                        .text(total + ' ' + rex.asset_import.results_found)
                        .show();
                    break;
                    
                case 'no-results':
                    status.removeClass('alert-danger alert-info')
                        .addClass('alert-info')
                        .text(rex.asset_import.no_results)
                        .show();
                    break;
                    
                default:
                    status.hide();
            }
        },
        
        showError: function(message) {
            $('#asset-import-status')
                .removeClass('alert-info')
                .addClass('alert-danger')
                .text(message)
                .show();
                
            setTimeout(() => {
                $('#asset-import-status').fadeOut();
            }, 5000);
        },
        
        showSuccess: function(message) {
            $('#asset-import-status')
                .removeClass('alert-danger')
                .addClass('alert-info')
                .text(message)
                .show();
                
            setTimeout(() => {
                $('#asset-import-status').fadeOut();
            }, 3000);
        },
        
        escapeHtml: function(unsafe) {
            return unsafe
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        },
        
        openLightbox: function(item) {
            const title = item.find('.asset-import-title').text();
            const selectSize = item.find('.asset-import-size-select');
            const selectedOption = selectSize.find('option:selected');
            const copyright = item.data('copyright') || '';
            
            // Versuche die beste verfügbare Auflösung zu finden
            let bestSize = this.getBestAvailableSize(item);
            let mediaUrl = bestSize.url;
            let mediaType = bestSize.type;
            
            // Bestimme den Media-Typ basierend auf der Dateiendung falls nicht verfügbar
            if (!mediaType) {
                const extension = mediaUrl.split('.').pop().toLowerCase();
                mediaType = ['mp4', 'webm', 'ogg', 'mov', 'avi'].includes(extension) ? 'video' : 'image';
            }
            
            // Erstelle Lightbox HTML
            const lightboxHtml = `
                <div class="asset-import-lightbox">
                    <div class="asset-import-lightbox-content">
                        <button class="asset-import-lightbox-close" title="Schließen">×</button>
                        ${mediaType === 'video' ? `
                            <video class="asset-import-lightbox-media" controls>
                                <source src="${mediaUrl}" type="video/mp4">
                                Ihr Browser unterstützt kein HTML5 Video.
                            </video>
                        ` : `
                            <img class="asset-import-lightbox-media" src="${mediaUrl}" alt="${this.escapeHtml(title)}">
                        `}
                        <div class="asset-import-lightbox-info">
                            <div class="asset-import-lightbox-title">${this.escapeHtml(title)}</div>
                            <div class="asset-import-lightbox-meta">
                                Größe: ${bestSize.label}
                                ${copyright ? ' | © ' + this.escapeHtml(copyright) : ''}
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Entferne vorhandene Lightbox falls vorhanden
            $('.asset-import-lightbox').remove();
            
            // Füge neue Lightbox hinzu
            $('body').append(lightboxHtml).addClass('lightbox-active');
            
            // Trigger reflow um sicherzustellen, dass CSS angewendet wird
            const lightbox = $('.asset-import-lightbox')[0];
            lightbox.offsetHeight; // Force reflow
            
            // Zeige Lightbox mit Animation mit kleiner Verzögerung
            requestAnimationFrame(() => {
                $('.asset-import-lightbox').addClass('active');
            });
        },
        
        closeLightbox: function() {
            $('.asset-import-lightbox').removeClass('active');
            $('body').removeClass('lightbox-active');
            
            setTimeout(() => {
                $('.asset-import-lightbox').remove();
            }, 400); // Angepasst an die längere Animationsdauer
        },
        
        getBestAvailableSize: function(item) {
            const sizeSelect = item.find('.asset-import-size-select');
            const options = sizeSelect.find('option');
            
            // Prioritätsliste für die beste Qualität
            const sizePriority = ['large', 'original', 'medium', 'small', 'tiny'];
            
            let bestOption = null;
            let bestPriority = -1;
            
            options.each(function() {
                const option = $(this);
                const sizeKey = option.val();
                const priority = sizePriority.indexOf(sizeKey);
                
                if (priority !== -1 && (bestPriority === -1 || priority < bestPriority)) {
                    bestOption = option;
                    bestPriority = priority;
                }
            });
            
            // Falls keine priorisierte Größe gefunden wurde, nimm die erste verfügbare
            if (!bestOption) {
                bestOption = options.first();
            }
            
            // Bestimme den Typ basierend auf dem ursprünglichen Item
            const preview = item.find('.asset-import-preview');
            const isVideo = preview.find('video').length > 0;
            
            return {
                url: bestOption.data('url'),
                label: bestOption.text(),
                type: isVideo ? 'video' : 'image'
            };
        }
    };
    
    AssetImport.init();
});
