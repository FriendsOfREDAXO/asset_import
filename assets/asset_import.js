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
        }
    };
    
    AssetImport.init();
});
