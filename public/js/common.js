const FLASH_MESSAGE_TYPE_ERROR = 0;
const FLASH_MESSAGE_TYPE_SUCCESS = 1;
const FLASH_MESSAGE_TYPE_INFO = 2;

$(document).ready(function () {
    $('.date-picker').datepicker({
        format: 'dd-mm-yyyy',
        autoclose:true
    });
});

function convertToInt(value)
{
    if (typeof value === 'number') {
        return value;
    }

    value = value.replaceAll("VNĐ", '').replaceAll(",", '').replaceAll(" ", '');
    if (isFinite(value)) {
        return parseInt(value);
    }
    return null;
}

function formatNumber(value)
{
    return value.toLocaleString();
}

function addMessageToDataTableInfo(id, message)
{
    const messageElmSelector = $(`#dataTable_wrapper .dt-info #${id}`);
    if (messageElmSelector.length > 0) {
        messageElmSelector.remove();
    }
    $("#dataTable_wrapper .dt-info").append(`<span id="${id}" class="ms-4">${message}</span>`);
}

function calculateSumAmountCells(table)
{
    try {
        const cells = table.cells({selected: true}).data().toArray();
        let sumAmount = 0;
        let avgAmount = 0;

        if (cells.length === 0) {
            addMessageToDataTableInfo('sumAmount', "");
            addMessageToDataTableInfo('avgAmount', "");
            return;
        }

        for (const amount of cells) {
            let amountInt = convertToInt(amount);

            if (amountInt === null) {
                addMessageToDataTableInfo('sumAmount', "Tổng cộng: Lỗi: Chứa giá trị không phải số.");
                addMessageToDataTableInfo('avgAmount', "Trung bình: Lỗi: Chứa giá trị không phải số.");
                return;
            }

            sumAmount += amountInt;
        }
        addMessageToDataTableInfo('sumAmount', "Tổng cộng: " + formatNumber(sumAmount));

        avgAmount = Math.round(sumAmount / cells.length);
        addMessageToDataTableInfo('avgAmount', "Trung bình: " + formatNumber(avgAmount));
    } catch (e) {
        console.log(e);
        addMessageToDataTableInfo('sumAmount', "Tổng cộng: Có lỗi xảy ra. Hãy xem log trong console.");
        addMessageToDataTableInfo('avgAmount', "Trung bình: Có lỗi xảy ra. Hãy xem log trong console.");
    }
}

function calculateExpression(expression)
{
    try {
        return eval(expression);
    } catch (error) {
        return "Phép tính không hợp lệ !";
    }
}

function loadResultCalculate()
{
    const expression = $('#calculateExpression').val();
    let result = calculateExpression(expression);
    if (! isNaN(result)) {
        result = formatNumber(result);
    }

    $('#calculateResult').html(result);
}

function validateModalForm(modalId)
{
    const form = $(modalId).find('form')[0];
    if (form.checkValidity()) {
        return true;
    }

    form.classList.add('was-validated')
    return false;
}

function clearModalForm(modalId)
{
    const form = $(modalId).find('form')[0];
    form.classList.remove('was-validated');
    form.reset();
}

function closeAlertMessage(elm)
{
    $(elm).closest('.alert').remove();
}

function addFlashMessage(message, type = FLASH_MESSAGE_TYPE_SUCCESS)
{
    let alertClass = '';
    switch (type) {
        case FLASH_MESSAGE_TYPE_ERROR:
            alertClass = 'alert-danger';
            break;
        case FLASH_MESSAGE_TYPE_SUCCESS:
            alertClass = 'alert-success';
            break;
        case FLASH_MESSAGE_TYPE_INFO:
            alertClass = 'alert-info';
            break;
    }

    $('.flash-messages').append(`
        <div class="alert ${alertClass} m-0 p-1">
            ${message}
            <button type="button" class="btn-close float-end" onclick="closeAlertMessage(this)"></button>
        </div>
    `);
}