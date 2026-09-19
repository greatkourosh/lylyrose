jQuery(document).ready(function ($) {
    function getDashboardContent(){
        $.ajax({
            url: gateland_ajax.url,
            type: 'POST',
            data: {
                action: 'gateland_dashboard', // The AJAX action hook
            },
            success: function(response) {
                // Handle the response
                $("#dashboard_content").replaceWith(response.result)
            },
            error: function(jqXHR, textStatus, errorThrown) {
                // Handle the error
                window.location.reload();
            }
        });
    }

    setInterval(getDashboardContent, 14000)
});