var $ = jQuery

$(window).click(function () {
    $(".custom-dropdown").fadeOut().addClass('d-none')
});

$(".custom-dropdown").click(function (event) {
    event.stopPropagation();
})

$("[data-dropdown]").click(function (event) {
    event.stopPropagation();

    const dropdownId = $(this).attr('data-dropdown');
    if($(`#${dropdownId}`).hasClass("d-none")){
        $(".custom-dropdown").fadeOut().addClass('d-none')
    }
    $(`#${dropdownId}`).fadeToggle().toggleClass("d-none")
})
