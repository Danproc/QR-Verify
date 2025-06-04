jQuery(document).ready(function($) {
    // Media upload functionality
    $('.vqr-upload-image').on('click', function(e) {
        e.preventDefault();
        
        var button = $(this);
        var target = button.data('target');
        var mediaUploader;
        
        if (mediaUploader) {
            mediaUploader.open();
            return;
        }
        
        mediaUploader = wp.media.frames.file_frame = wp.media({
            title: 'Choose Image',
            button: {
                text: 'Choose Image'
            },
            multiple: false
        });
        
        mediaUploader.on('select', function() {
            var attachment = mediaUploader.state().get('selection').first().toJSON();
            $('#' + target).val(attachment.id);
            button.siblings('.vqr-image-preview').html('<img src="' + attachment.url + '" style="max-width: 150px;" />');
        });
        
        mediaUploader.open();
    });
    
    // Remove image functionality
    $('.vqr-remove-image').on('click', function(e) {
        e.preventDefault();
        
        var button = $(this);
        var target = button.data('target');
        
        $('#' + target).val('');
        button.siblings('.vqr-image-preview').html('');
    });
});