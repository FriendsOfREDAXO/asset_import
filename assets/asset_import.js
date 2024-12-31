$(document).on('rex:ready', function() {
    const AssetImport = {
        currentPage: 1,
        loading: false,
        hasMore: true,
        currentQuery: '',
        
        init: function() {
            this.bindEvents();
            this.resetResults();
            this.initModal();
        },
        
        initModal: function() {
            $('body').append(`
                <div class="modal fade" id="asset-preview-modal" tabindex="-1" role="dialog">
                    <div class="modal-dialog modal-xl" role="document">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h4 class="modal-title"></h4>
                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <div class="modal-body text-center">
                                <div class="preview-container" style="max-height: 80vh; overflow: hidden;">
                                    <div class="preview-content"></div>
                                </div>
                                <div class="asset-details mt-3">
                                    <table class="table table-sm">
                                        <tbody>
                                            <tr><th>Title:</th><td class="detail-title"></td></tr>
                                            <tr><th>Author:</th><td class="detail-author"></td></tr>
                                            <tr><th>Type:</th><td class="detail-type"></td></tr>
                                            <tr><th>Source:</th><td class="detail-source"></td></tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal fade" id="success-modal" tabindex="-1" role="dialog">
                    <div class="modal-dialog modal-sm" role="document">
                        <div class="modal-content">
                            <div class="modal-body text-center">
                                <i class="rex-icon fa-check-circle text-success" style="font-size: 48px;"></i>
                                <h4 class="mt-2"></h4>
                            </div>
                        </div>
                    </div>
                </div>
            `);
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
            
            $('#asset-import-load-more button').on('click', () => {
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
                const url = selectSize.find('option:selected').data('url');
                const filename = item.find('.asset-import-title').text();
                
                this.import(url, filename, btn);
            });

            $(document).on('click', '.asset-import-preview', (e) => {
                e.preventDefault();
                const item = $(e.currentTarget).closest('.asset-import-item');
                this.showPreviewModal(item);
            });
        },
        
        showPreviewModal: function(item) {
            const modal = $('#asset-preview-modal');
            const type = item.data('type');
            const previewUrl = item.data('largest-preview');
            const title = item.find('.asset-import-title').text();
            const author = item.data('author');
            const source = item.data('source');
            
            modal.find('.modal-title').text(title);
            modal.find('.detail-title').text(title);
            modal.find('.detail-author').text(author);
            modal.find('.detail-type').text(type.charAt(0).toUpperCase() + type.slice(1));
            modal.find('.detail-source').text(source);
            
            const content = type === 'video' 
                ? `<video controls style="max-width: 100%; max-height: 70vh;">
                     <source src="${previewUrl}" type="video/mp4">
                   </video>`
                : `<img src="${previewUrl}" style="max-width: 100%; max-height: 70vh; object-fit: contain;">`;
            
            modal.find('.preview-content').html(content);
            modal.modal('show');
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
                        this.showError(response.error || 'Unknown error');
                    }
                },
                error: (xhr, status, error) => {
                    this.showError('Error loading results: ' + error);
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
                // Find largest preview URL for modal
                const largestPreview = item.type === 'video' 
                    ? item.size.large?.url || item.size.medium?.url || item.preview_url
                    : item.size.large?.url || item.size.original?.url || item.preview_url;
                
                html += `
                    <div class="asset-import-item" 
                         data-type="${item.type}"
                         data-author="${item.author}"
                         data-source="${this.getProviderTitle()}"
                         data-largest-preview="${largestPreview}">
                        <div class="asset-import-preview">
                            ${item.type === 'video' ? `
                                <video>
                                    <source src="${item.size.small.url}" type="video/mp4">
                                </video>
                            ` : `
                                <img src="${item.preview_url}" alt="${item.title}">
                            `}
                        </div>
                        <div class="asset-import-info">
                            <div class="asset-import-title">${item.title}</div>
                            <select class="form-control selectpicker asset-import-size-select">
                                ${Object.entries(item.size).map(([key, value]) => `
                                    <option value="${key}" data-url="${value.url}">
                                        ${key.charAt(0).toUpperCase() + key.slice(1)}
                                    </option>
                                `).join('')}
                            </select>
                            <div class="asset-import-actions">
                                <button class="btn btn-primary btn-block asset-import-import-btn">
                                    <i class="rex-icon fa-download"></i> Import
                                </button>
                            </div>
                            <div class="progress" style="display: none;">
                                <div class="progress-bar progress-bar-striped active" role="progressbar" style="width: 100%">
                                    <i class="rex-icon fa-download"></i> Importing...
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

            $('.selectpicker').selectpicker('refresh');
        },
        
        getProviderTitle: function() {
            const provider = $('#asset-import-provider option:selected').text();
            return provider || '';
        },
        
        import: function(url, filename, btn) {
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
                    category_id: categoryId
                },
                success: (response) => {
                    if (response.success) {
                        this.showSuccessModal('Asset successfully imported');
                        setTimeout(() => {
                            progress.hide();
                            btn.show();
                        }, 1000);
                    } else {
                        this.showError(response.error || 'Import failed');
                        progress.hide();
                        btn.show();
                    }
                },
                error: (xhr, status, error) => {
                    this.showError('Error importing file: ' + error);
                    progress.hide();
                    btn.show();
                }
            });
        },
        
        showSuccessModal: function(message) {
            const modal = $('#success-modal');
            modal.find('.modal-body h4').text(message);
            modal.modal('show');
            setTimeout(() => modal.modal('hide'), 2000);
        },
        
        showStatus: function(type, total = 0) {
            const status = $('#asset-import-status');
            
            switch(type) {
                case 'loading':
                    status.removeClass('alert-danger alert-info').addClass('alert-info')
                        .html('<i class="rex-icon fa-spinner fa-spin"></i> Loading results...')
                        .show();
                    break;
                    
                case 'results':
                    status.removeClass('alert-danger alert-info').addClass('alert-info')
                        .text(`${total} results found`)
                        .show();
                    break;
                    
                case 'no-results':
                    status.removeClass('alert-danger alert-info').addClass('alert-info')
                        .text('No results found')
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
        }
    };
    
    AssetImport.init();
});
