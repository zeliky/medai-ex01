$(document).ready(function () {
    // Initialize the autocomplete widget
    $('#reset').click((e) => {
        window.location.href = '/';
    });
    $('#addBtn').click(function () {

        $('#clientId').val('')
        $('#firstName').val('').attr('readonly', false)
        $('#lastName').val('').attr('readonly', false)
        $('#loincCode').val('')
        $('#value').val('')
        $('#unit').val('')
        $('#validStartTime').val('')
        $('#validStopTime').val('')
        $('#transactionTime').val('')
        $('#recordModal').modal('show');

    });

    $('.editBtn').click(function () {
        var id = $(this).data('id')
        $.ajax({
            url: "/medai/" + id,
            success: function (data) {
                $('#clientId').val(data['client_id'])
                $('#firstName').val(data['first_name']).attr('readonly', true)
                $('#lastName').val(data['last_name']).attr('readonly', true)
                $('#loincCode').val(data['loinc_code']).attr('readonly', true)
                $('#value').val(data['value'])
                $('#unit').val(data['unit'])
                $('#validStartTime').val(data['valid_start_time']).attr('readonly', true)
                $('#validStopTime').val(data['valid_end_time']).attr('readonly', true)
                $('#transactionTime').val(nowInputDateString())


                $('#recordModal').modal('show');

            }
        });

    });

    $('.save-btn').click(function () {
        var raw_data = rawPostData('recordForm')
        if (!validate(raw_data))
            return
        var type = (raw_data['client_id'] == '') ? 'post' : 'put';
        $.ajax({
            type: type,
            url: "/medai/",
            data: JSON.stringify(raw_data),
            success: function (data) {
                $('#recordModal').modal('hide');
                window.location.reload()
            },
            error: function (jqXHR, textStatus, errorThrown) {
                alert(jqXHR.responseJSON['error'])
            }
        })
    })

    $('.deleteBtn').click(function () {
        var id = $(this).data('id')
        $('#delete-id').val(id)
        $.ajax({
            url: "/medai/" + id,
            success: function (data) {
                var localDate = new Date(data['valid_start_time']).toLocaleString()
                $('#recordInfo').html(`${data['first_name']} ${data['last_name']}'s ${data['loinc_code']}  on ${localDate}`)

                $('#delFirstName').val(data['first_name'])
                $('#delLastName').val(data['last_name'])
                $('#delLoincCode').val(data['loinc_code'])
                $('#delValidStartTime').val(data['valid_start_time'])
                $('#delDeletedAt').val(nowInputDateString())
                $('#confirmModal').modal('show');
            },
            error: function (jqXHR, textStatus, errorThrown) {
                alert(jqXHR.responseJSON['error'])
            }
        })
    });


    $('#deleteSubmit').click(function () {
        var raw_data = rawPostData('deleteForm')
        $.ajax({
            url: "/medai/",
            type: 'delete',
            data: JSON.stringify(raw_data),
            success: function (data) {
                $('#confirmModal').modal('hide');
                window.location.reload()
            },
            error: function (jqXHR, textStatus, errorThrown) {
                alert(jqXHR.responseJSON['error'])
            }
        })
    });



    $('.close').click(function () {
        $('#recordModal').modal('hide');
        $('#confirmModal').modal('hide');
    });

    ['client', 'loinc'].forEach((input) => {
        $("#" + input).autocomplete({
            source: function (request, response) {
                $.ajax({
                    url: "/autocomplete/" + input + "s",
                    data: {q: request.term},
                    success: function (data) {
                        response(data);
                    }
                });
            },
            select: function (event, ui) {
                // Set the input value to the selected label
                $(this).val(ui.item.label);
                $("#" + input + "_id").val(ui.item.value);
                // Prevent the default behavior of the autocomplete widget
                event.preventDefault();
            }
        });
    });


    queryTypeModifiers()
    $('#query_type').change(function () {
        return queryTypeModifiers()
    })


});

function queryTypeModifiers() {
    var selectedType = $('#query_type').val()
    $('.section-modifier').hide()
    $('.' + selectedType + '-section').show()
}

function nowInputDateString() {
    d = new Date().toISOString().split('T')
    return `${d[0]} ${d[1].split('.')[0]}`
}

function rawPostData(formId) {
    var data = $('#' + formId).serializeArray()
    var rawData = Object.fromEntries(data.map(x => [x.name, x.value]));
    rawData.valid_start_time = rawData.valid_start_time.replace('T', ' ');
    if (typeof rawData.valid_stop_time !== 'undefined')
        rawData.valid_stop_time = rawData.valid_stop_time.replace('T', ' ');
    if (typeof rawData.transaction_time !== 'undefined')
        rawData.transaction_time = rawData.transaction_time.replace('T', ' ');
    if (typeof rawData.deleted_at !== 'undefined')
        rawData.deleted_at = rawData.deleted_at.replace('T', ' ');

    return rawData
}

function validate(rawData) {
    var errors = ''
    if (rawData['first_name'].length === 0) {
        errors += ' first name'
    }
    if (rawData['last_name'].length === 0) {
        errors += ' last name'
    }
    if (rawData['loinc_code'].length === 0) {
        errors += ' loinc_code'
    }
    if (rawData['value'].length === 0) {
        errors += ' value'
    }
    if (errors.length > 0) {
        alert('invalid values in: ' + errors)
        return false
    }
    return true

}