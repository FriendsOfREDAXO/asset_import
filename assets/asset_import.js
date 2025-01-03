$(document).on('rex:ready', function() {
    const AssetImport = {
        currentPage: 1,
        loading: false,
        hasMore: true,
        currentQuery: '',
        touchStartY: 0,
        touchMoveThreshold: 100,
        
        init: function() {
            this.bindEvents();
            this.resetResults();
            this.initTouchEvents();
            
            // Lazy loading für Bilder aktivieren
            if ('IntersectionObserver' in window) {
                this.initLazyLoading();
            }
        },
        
        initTouchEvents: function() {
            document.addEventListener('touchstart', (e) => {
                this.touchStartY = e.touches[0].clientY;
            }, { passive: true });

            document.addEventListener('touchmove', (e) => {
                if (!this.loading && this.hasMore) {
                    const touchEndY = e.touches[0].clientY;
                    const diff = this.touchStartY - touchEndY;

                    if (diff > this.touchMoveThreshold) {
                        const scrollPos = window.scrollY + window.innerHeight;
                        const docHeight = document.documentElement.scrollHeight;

                        if (docHeight - scrollPos < 100) {
                            this.currentPage++;
                            this.search();
                            this.touchStartY = touchEndY;
                        }
                    }
                }
            }, { passive: true });
        },
        
        initLazyLoading: function() {
            const options = {
                root: null,
                rootMargin: '50px',
                threshold: 0.1
            };

            const observer = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const media = entry.target;
                        if (media.dataset.src) {
                            media.src = media.dataset.src;
                            media.removeAttribute('data-src');
                            observer.unobserve(media);
                        }
                    }
                });
            }, options);

            this.lazyLoadObserver = observer;
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

            // Verbesserte Touch-Events für Vorschaubilder
            $(document).on('click touchend', '.asset-import-preview', function(e) {
                const preview = $(this);
                const img = preview.find('img, video');
                
                if (img.length) {
                    preview.toggleClass('expanded');
                    img.css('transform', preview.hasClass('expanded') ? 'scale(1.1)' : 'none');
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
                const copyright = this.formatCopyright(item);
                html += `
                    <div class="asset-import-item" data-copyright="${copyright}">
                        <div class="asset-import-preview">
                            ${item.type === 'video' ? `
                                <video controls preload="none" data-src="${item.size.tiny.url}">
                                    <source src="${item.size.tiny.url}" type="video/mp4">
                                </video>
                            ` : `
                                <img data-src="${item.preview_url}" alt="${this.escapeHtml(item.title)}">
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
                                    <i class="rex-icon fa-download"></i> ${rex.asset_import_import}
                                </button>
                            </div>
                            <div class="progress" style="display: none;">
                                <div class="progress-bar progress-bar-striped active" role="progressbar" style="width: 100%">
                                    <i class="rex-icon fa-download"></i> ${rex.asset_import_importing}
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
            
            // Lazy Loading für neue Bilder aktivieren
            if (this.lazyLoadObserver) {
                container.find('img[data-src], video[data-src]').each((i, el) => {
                    this.lazyLoadObserver.observe(el);
                });
            }
            
            this.hasMore = data.page < data.total_pages;
            $('#asset-import-load-more').toggle(this.hasMore);
            
            this.showStatus('results', data.total);

            // Bootstrap Select initialisieren
            $('.selectpicker').selectpicker('refresh');
        },
        
        formatCopyright: function(item) {
            const parts = [];
            
            if (item.author) {
                parts.push(item.author);
            }
            
            // Provider-spezifische Copyright-Informationen
            switch($('#asset-import-provider').val()) {
                case 'pixabay':
                    parts.push('Pixabay.com');
                    break;
                case 'pexels':
                    parts.push('Pexels.com');
                    break;
            }
            
            return this.escapeHtml(parts.join(' / '));
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
                        this.showSuccess(rex.asset_import_success);
                        setTimeout(() => {
                            progress.hide();
                            btn.show();
                        }, 1000);
                    } else {
                        this.showError(response.error || rex.asset_import_error);
                        progress.hide();
                        btn.show();
                    }
                },
                error: (xhr, status, error) => {
                    this.showError(rex.asset_import_error_import + ': ' + error);
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
                        .html(`<i class="rex-icon fa-spinner fa-spin"></i> ${rex.asset_import_loading}`)
                        .show();
                    break;
                    
                case 'results':
                    status.removeClass('alert-danger alert-info')
                        .addClass('alert-info')
                        .text(total + ' ' + rex.asset_import_results_found)
                        .show();
                    break;
                    
                case 'no-results':
                    status.removeClass('alert-danger alert-info')
                        .addClass('alert-info')
                        .text(rex.asset_import_no_results)
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
        }
    };
    
    AssetImport.init();
});
