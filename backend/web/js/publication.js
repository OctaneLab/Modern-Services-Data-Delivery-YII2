/**
 * Created by start on 06.04.17.
 */

var spub_host = 'http://shocking-polar-1110.vagrantshare.com/';

$('#modal').on('shown.bs.modal', function () {

    getPortals();
});

$(document).on('click', '#portals > li:not(.disabled, .published)', function (e) {

    $(this).addClass('chosen');
    $(this).attr('data-picked', true);

    $(this).siblings().css('cursor', 'default').css('opacity', 0.5);
    $(this).siblings().addClass('disabled');

    getForm($(this).data('id'));

    $(this).addClass('disabled');
});


$(document).on('click published', '#reset-button', function (e) {


    swal({
            title: "Czy na pewno chcesz anulować?",
            text: "Nie będzie można cofnąć tej akcji!",
            type: "warning",
            cancelButtonText: "Nie",
            confirmButtonText: "Tak, anuluj",
            showCancelButton: true,
            closeOnConfirm: true,
            showLoaderOnConfirm: true
        },
        function (isConfirm) {

            if (isConfirm) {
                $('#posting-list').find(".nav-tabs-custom").fadeOut(300, function () {
                    $(this).remove();
                });
                $('#form-buttons').fadeOut(300, function () {
                    $(this).remove();
                });
                $('#posting-list').find('form').fadeOut(300, function () {
                    $(this).remove();
                });
                var li = $(document).find('#portals').children();

                li.css('opacity', 1).css('cursor', 'pointer');
                li.removeClass('disabled').removeClass('chosen');

                $('#load-content-btn').addClass('hidden');
            }
        }
    );
});


$(document).on('submit', 'form', function (e) {
    e.preventDefault();
    var form = $(this);
    validateForm(form);


    $('#load-content-btn').addClass('hidden');
});


//gets form attributes from spublio REST service and generates form using those attributes
function getForm(id) {

    var loading = $('.tabbox').find('.loading-state');

    $.ajax({
        type: "GET",
        url: spub_host + 'backend/web/publication/form-attributes',
        data: {
            id: id
        },
        beforeSend: function () {
            loading.fadeIn();
        },
        success: function (response) {

            $.ajax({
                type: "POST",
                url: '/backend/web/recruitment/generate-form',  //TODO fix url
                data: {response: response, portal_id: id},
                beforeSend: function () {
                },
                success: function (response) {

                    $('#posting-list').append(response);
                    var form = $('#posting-list').find('form');
                    form.trigger('formShow');

                    // if there is invalid input in any of the tabs, this method will move the user to this tab
                    document.querySelector('#posting-list').addEventListener('invalid', function (event) {
                        var target = $(event.target);
                        var href = target.attr('name');
                        var tabNumber = href.match(/\d/g);
                        tabNumber = tabNumber.join('');

                        var goTab = $('#posting-list').find('li').find('a[href="#tab-step-' + tabNumber + '"]');
                        goTab.tab('show');
                    }, true);

                    loading.fadeOut();

                }

            });

            // $('#posting-list').append(response);
            // var form = $('#posting-list').find('form');
            // form.trigger('formShow');

            //refresh load content button
            var aid = $('#posting-list').attr('data-announcement-id');

            $('#load-content-btn').attr('data-announcement-id',aid);
            $('#load-content-btn').attr('data-portal-id',id);
            $('#load-content-btn').removeClass('hidden');
        }

    });

}

//gets portals and their logos from spublio REST service
function getPortals() {

    var loading = $('#posting-list').find('.loading-state:first');
    $.ajax({
        type: "GET",
        url: spub_host + 'backend/web/publication/get-portals',

        beforeSend: function () {
            loading.fadeIn();
        },
        success: function (response) {

            var parsedResponse = response;
            var ul = $('#posting-list').find('ul');
            $.each(parsedResponse, function (index, value) {
                ul.append('<li data-id="' + value.id + '" id="' + index + '"><h1 class="text-center" style="height: 50px">'
                    + '<img class="posting-site-img img-circle" src="' + value.logo + '" width="50px" title="' + index + '">'
                    + '</h1></li>');
            });
            ul.find('li').addClass('list-group-item visible-md-inline-block visible-lg-inline-block');

            loading.fadeOut();

        }
    })
    .done(function(response) {
        //console.log("done");
    })
    .fail(function() {
        console.warn("Fail at getPortals()");
        var ul = $('#posting-list').find('ul');
        loading.fadeOut();
        ul.html("<p>Nie mogę załadować pulikatora z podanego hosta: <br/><br/> " + spub_host + " <br/><br/>Skontaktuj się z serwisantem.</p>");
    })
}

//validates submitted form data, gets status from spublio REST service and informs user whether the publication is in queue
function validateForm(form) {

    // $.each(formArray, function (index, value) {
    //     dataForAjaxPost['step' + index] = $(value).serialize();
    //     dataForAjaxPost['portal_id'] = $(value).attr('data-portal');
    // });

    $.ajax({
        type: "POST",
        url: spub_host + 'backend/web/publication/create',
        data: {form: form.serialize(), portal_id: form.data('portal')},
        beforeSend: function () {

        },
        success: function (response) {

            if (response.status === true) {

                $('#posting-list').find('.form-control').css('background-color', '#d4f2e3').css('border', '2px solid #7fffbf ');

                swal({
                    title: "Pomyślnie zwalidowano formularz!",
                    text: "Publikacja ogłoszenia jest już w kolejce",
                    type: "success",
                    timer: 3000
                });

                $('#posting-list').find('form').fadeOut('slow');

                var li = $(document).find('#portals').children();
                $('#portal-form').fadeOut(1000, function () {
                    $(this).remove();
                });

                li.css('opacity', 1).css('cursor', 'pointer');
                li.removeClass('disabled').removeClass('chosen');

                var publishedLi = $('#posting-list').find('[data-id="' + response.portal_id + '"]');
                publishedLi.addClass('disabled published');
                //$('#' + $(value).attr('data-portal')).addClass('disabled published');


                $('#posting-list').find(".nav-tabs-custom").fadeOut(300, function () {
                    $(this).remove();
                });
                $('#form-buttons').fadeOut(300, function () {
                    $(this).remove();
                });


            } else {

                var form = $('#posting-list').find('form');

                var message = '';
                var wrongStepsNumbers = [];

                if(Array.isArray(response.message)){
                    $.each(response.message, function (index, value) {

                        for (var first in value) {
                            var field = first;
                        }

                        var invalidInputs = form.find('[name="Form[' + index + ']' + '[' + field + ']' + '"]');
                        invalidInputs.css('background-color', '#ffcaca').css('border', '2px solid #ff7784 ');

                        message += 'Krok ' + ((parseInt(index)) + 1) + ': ' + value[field][0] + ' ';

                        wrongStepsNumbers.push(index);
                    });

                    swal(
                        'Błędnie wypełniony formularz!',
                        message,
                        'error');
                }else{
                    console.warn(response.message);
                    swal(
                        'Uzytkowniku',
                        'Coś mogło nie wykonać się do końca. Sprawdź.',
                        'info');
                }


                $.each(wrongStepsNumbers, function (index, value) {
                    var tab = $('#posting-list').find('.nav-tabs').find('a[href="#tab-step-' + value + '"]');
                    tab.parent().addClass('tab-has-errors');
                });

                var firstWrongNavTab = $('#posting-list').find('.nav-tabs').find('a[href="#tab-step-' + wrongStepsNumbers[0] + '"]');
                firstWrongNavTab.tab('show');
            }
        },

        error: function (response) {
            swal("Coś poszło nie tak... Prosimy o zgłoszenie.", 'error');
            console.warn("coś nie tak z odpowiedzią");
            console.warn(response);
            $('#portal-form').fadeOut();
        }
    })
}

/** JK **/
var postingListSelectorName = 'posting-list';
var postingListSelector = '#' + postingListSelectorName;

/** Used on click **/
var OLoadFormContent = function(item){

    var id = $(item).attr('data-announcement-id');
    var portal_id = $(item).attr('data-portal-id');
    var form = $(postingListSelector).find('.form-control');
    var field_names = [];

    $.each(form, function(index, item){
        if(typeof $(item).attr('name') !== 'undefined'){
            var match = $(item).attr('name').match(/^Form\[(.*?)\]\[(.*?)\]$/);
            if(typeof match[2] !== 'undefined' && match[2].length > 0){
                field_names.push(match[2]);
            }
        }
    });

    try {

        swal({
                title: "Załadowanie danych nadpisze uzupełnione pola.",
                text: "Czy jesteś pewien, że chcesz załadować wartości do pól?",
                type: "warning",
                cancelButtonText: "Nie, nic nie zmieniaj",
                confirmButtonText: "Tak, załaduj",
                showCancelButton: true,
                closeOnConfirm: true,
                showLoaderOnConfirm: true
            },
            function (isConfirm) {
                if (typeof id !== 'undefined' && id !== 0) {
                    $.ajax({
                        type: "POST",
                        url: 'http://' + location.host + '/backend/web/announcement/get-announcement-ajax',
                        data: {
                            id         : id,
                            portal_id  : portal_id,
                            field_names: field_names
                        },
                        beforeSend: function () {
                            var spinner = '<i id="load-spinner" class="fa fa-spinner" aria-hidden="true" style="margin-left: 15px; font-size: 22px;"></i>';
                            $('#load-content-btn').parent().append(spinner);
                        },
                        success: function (response) {
                            var data = JSON.parse(response);

                            if(data.status === 'success'){
                                OInjectValues(data.fields);
                            }else{
                                throw new Error('Nie udało się załadować danych do formularza.');
                            }
                        },
                        error: function (response) {
                        },
                        complete: function(){
                            console.log('Completed');
                            $('#load-spinner').remove();
                        }
                    });
                } else {
                    throw new Error('Nie udało się załadować danych do formularza.');
                }
        });
    }catch(err){

        swal(
            'Uzytkowniku',
            err.message,
            'info');
    }

};

var OInjectValues = function(fields){
    var form = postingListSelector + ' form';
    var formChildren = form + ' input[name^=Form], ' + form + ' select[name^=Form], ' + form + ' textarea[name^=Form]';

    $.each(fields, function(name, value) {
        //var $field = $(postingListSelector + ' form').find('input[name^="Form[0][' + name + ']"]');
        $(formChildren).each(function(child_key,child){
            var isRightField = $(child).attr('name').indexOf(name) > 0;
            if(isRightField){
                $(child).val(value);
            }
        });
    });
};

