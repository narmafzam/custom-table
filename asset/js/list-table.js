// Helper to retrieve an URL parameter
function ajaxListTableGetParameter( sURL, sParam ) {

    var sPageURL = decodeURIComponent(sURL.split('?')[1]),
        sURLVariables = sPageURL.split('&'),
        sParameterName,
        i;

    for (i = 0; i < sURLVariables.length; i++) {
        sParameterName = sURLVariables[i].split('=');

        if (sParameterName[0] === sParam) {
            return sParameterName[1] === undefined ? true : sParameterName[1];
        }
    }

}

function ajaxListTableAddLoader( table ) {
    table.find('#the-list').append('<div class="ajax-list-table-loader"><span class="spinner is-active ajax-list-table-spinner"></span></div>')
}

function ajaxListTableRemoveLoader( table ) {
    table.find('#the-list .ajax-list-table-loader').remove();
}

// Initialize table listeners
function ajaxListTableAddListeners( table ) {

    var $ = $ || jQuery;

    table.find('.pagination-links a').click(function(e) {
        e.preventDefault();

        var url = $(this).attr('href');
        var paged = ajaxListTableGetParameter( url, 'paged' );

        ajaxListTablePaginateTable( $(this).closest('.ajax-list-table'), paged );
    });

    table.find('.paging-input .current-page').change(function(e) {
        var paged = $(this).val();

        var total_pages = parseInt( $(this).closest('.ajax-list-table').find('.tablenav.top .paging-input .total-pages').text() );

        if( paged > total_pages ) {
            paged = total_pages;
            $(this).val(total_pages);
        }

        ajaxListTablePaginateTable( $(this).closest('.ajax-list-table'), paged );
    });

}

// Ajax pagination
function ajaxListTablePaginateTable( table, paged ) {

    var $ = $ || jQuery;

    // Setup vars
    var object = table.data('object');
    var query_args = table.data('query-args');

    // Turn query args into an object
    query_args = $.parseJSON( query_args.split("'").join('"') );

    // Add the table loader
    ajaxListTableAddLoader( table );

    $.ajax({
        url: ajaxurl,
        data: {
            action: 'ct_ajax_list_table_request',
            object: object,
            query_args: query_args,
            paged: paged
        },
        success: function( response ) {

            if( response.data.length ) {
                var parsed_response = $(response.data);

                // Update top and bottom pagination
                table.find('.tablenav.top').html(parsed_response.filter('.tablenav.top').html());
                table.find('.tablenav.bottom').html(parsed_response.filter('.tablenav.bottom').html());

                // Update table content
                table.find('.wp-list-table').html(parsed_response.filter('.wp-list-table').html());

                // Remove the table loader, note: table content has been replaced, so not needle here
                //ajaxListTableRemoveLoader( table );

                // Update again pagination links
                ajaxListTableAddListeners( table );
            }

        }
    });

}

(function( $ ) {

    // Initialize all tables
    $('.ajax-list-table').each(function() {
        ajaxListTableAddListeners( $(this) );
    });

})( jQuery );