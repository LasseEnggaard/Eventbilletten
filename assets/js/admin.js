jQuery(function($){
    // Billedvælger
    var frame;
    $('#eb_img_select').on('click', function(e){
        e.preventDefault();
        if(!frame){
            frame = wp.media({ title: 'Vælg billede til eventet', button: { text: 'Brug billedet' }, library: { type: 'image' }, multiple: false });
            frame.on('select', function(){
                var a   = frame.state().get('selection').first().toJSON();
                var url = (a.sizes && a.sizes.medium) ? a.sizes.medium.url : a.url;
                $('#eb_img_id').val(a.id);
                $('#eb_img_preview').addClass('has-img').css('background-image', 'url(' + url + ')');
                $('#eb_img_select').text('Skift billede');
                $('#eb_img_remove').show();
            });
        }
        frame.open();
    });
    $('#eb_img_remove').on('click', function(e){
        e.preventDefault();
        $('#eb_img_id').val('');
        $('#eb_img_preview').removeClass('has-img').css('background-image', '');
        $('#eb_img_select').text('Vælg billede');
        $(this).hide();
    });

    // Bekræft sletning
    $('form[data-eb-confirm]').on('submit', function(){
        return window.confirm($(this).data('ebConfirm'));
    });

    // Træk og slip
    var $list = $('#eb_sortable');
    if($list.length && $.fn.sortable){
        $list.sortable({
            handle: '.eb-handle',
            axis: 'y',
            placeholder: 'eb-sort-placeholder',
            helper: function(e, tr){
                var $orig = tr.children(), $help = tr.clone();
                $help.children().each(function(i){ $(this).width($orig.eq(i).outerWidth()); });
                return $help;
            },
            start: function(e, ui){ ui.placeholder.html('<td colspan="7"></td>'); },
            update: function(){
                $('#eb_order').val($list.children('tr').map(function(){ return $(this).data('id'); }).get().join(','));
                $('#eb_order_bar').addClass('show');
            }
        });
    }
});
