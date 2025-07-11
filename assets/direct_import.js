$(document).on('rex:ready', function() {
    const DirectImport = {
        loading: false,
        
        init: function() {
            this.bindEvents();
        },
        
        bindEvents: function() {
            $('#direct-import-preview').on('click', (e) => {
                e.preventDefault();
                this.preview();
            });
            
            $('#direct-import-form').on('submit', (e) => {
                e.preventDefault();
                this.import();
            });
            
            $('#direct-import-reset').on('click', (e) => {
                e.preventDefault();
                this.reset();
            });
            
            // URL-Eingabe bei Enter
            $('#direct-import-url').on('keypress', (e) => {
                if (e.which === 13) {
                    e.preventDefault();
                    this.preview();
                }
            });
        },
        
        preview: function() {
            if (this.loading) return;
            
            const url = $('#direct-import-url').val().trim();
            if (!url) {
                this.showError(rex.asset_import.direct_url_required);
                return;
            }
            
            this.loading = true;
            this.showStatus('loading');
            
            $.ajax({
                url: window.location.href,
                data: {
                    direct_import_api: 1,
                    action: 'preview',
                    url: url
                },
                success: (response) => {
                    if (response.success) {
                        this.showPreview(response.data);
                    } else {
                        this.showError(response.error || rex.asset_import.error_unknown);
                    }
                },
                error: (xhr, status, error) => {
                    this.showError(rex.asset_import.error_loading + ': ' + error);
                },
                complete: () => {
                    this.loading = false;
                    this.hideStatus();
                }
            });
        },
        
        showPreview: function(data) {
            // Dateiname vorausfüllen
            $('#direct-import-filename').val(data.suggested_filename);
            
            // Preview HTML erstellen
            let previewHtml = '<div class="direct-import-preview">';
            
            if (data.is_image) {
                previewHtml += `
                    <div class="preview-image">
                        <img src="${data.preview_url}" alt="Preview" style="max-width: 100%; max-height: 200px; border-radius: 4px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                    </div>
                `;
            } else if (data.is_video) {
                previewHtml += `
                    <div class="preview-video">
                        <video controls style="max-width: 100%; max-height: 200px; border-radius: 4px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                            <source src="${data.preview_url}" type="${data.content_type}">
                        </video>
                    </div>
                `;
            }
            
            previewHtml += `
                <div class="preview-info" style="margin-top: 15px;">
                    <div class="row">
                        <div class="col-sm-6">
                            <strong>${rex.asset_import.direct_file_type}:</strong> ${data.content_type}
                        </div>
                        <div class="col-sm-6">
                            <strong>${rex.asset_import.direct_file_size}:</strong> ${data.file_size_formatted}
                        </div>
                    </div>
                </div>
            `;
            
            previewHtml += '</div>';
            
            $('#direct-import-preview-area').html(previewHtml);
            $('#direct-import-preview-container').slideDown();
            
            this.showSuccess(rex.asset_import.direct_preview_success);
        },
        
        import: function() {
            if (this.loading) return;
            
            const url = $('#direct-import-url').val().trim();
            const filename = $('#direct-import-filename').val().trim();
            const copyright = $('#direct-import-copyright').val().trim();
            const categoryId = $('#rex-mediapool-category-direct').val();
            
            if (!url || !filename) {
                this.showError(rex.asset_import.direct_fields_required);
                return;
            }
            
            this.loading = true;
            $('#direct-import-submit').hide();
            $('#direct-import-progress').show();
            
            $.ajax({
                url: window.location.href,
                method: 'POST',
                data: {
                    direct_import_api: 1,
                    action: 'import',
                    url: url,
                    filename: filename,
                    copyright: copyright,
                    category_id: categoryId
                },
                success: (response) => {
                    if (response.success) {
                        this.showSuccess(rex.asset_import.direct_import_success);
                        setTimeout(() => {
                            this.reset();
                        }, 2000);
                    } else {
                        this.showError(response.error || rex.asset_import.error_import);
                        $('#direct-import-progress').hide();
                        $('#direct-import-submit').show();
                    }
                },
                error: (xhr, status, error) => {
                    this.showError(rex.asset_import.error_loading + ': ' + error);
                    $('#direct-import-progress').hide();
                    $('#direct-import-submit').show();
                },
                complete: () => {
                    this.loading = false;
                }
            });
        },
        
        reset: function() {
            $('#direct-import-form')[0].reset();
            $('#direct-import-preview-container').slideUp();
            $('#direct-import-preview-area').empty();
            $('#direct-import-progress').hide();
            $('#direct-import-submit').show();
            this.hideStatus();
        },
        
        showStatus: function(type) {
            const status = $('#direct-import-status');
            
            switch(type) {
                case 'loading':
                    status.removeClass('alert-danger alert-success')
                        .addClass('alert-info')
                        .html(`<i class="rex-icon fa-spinner fa-spin"></i> ${rex.asset_import.loading}`)
                        .show();
                    break;
            }
        },
        
        hideStatus: function() {
            $('#direct-import-status').hide();
        },
        
        showError: function(message) {
            $('#direct-import-status')
                .removeClass('alert-info alert-success')
                .addClass('alert-danger')
                .text(message)
                .show();
                
            setTimeout(() => {
                $('#direct-import-status').fadeOut();
            }, 5000);
        },
        
        showSuccess: function(message) {
            $('#direct-import-status')
                .removeClass('alert-danger alert-info')
                .addClass('alert-success')
                .text(message)
                .show();
                
            setTimeout(() => {
                $('#direct-import-status').fadeOut();
            }, 3000);
        }
    };
    
    // Nur initialisieren wenn auf der Direct Import Seite
    if ($('.direct-import-container').length) {
        DirectImport.init();
    }
});
