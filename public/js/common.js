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

        if (cells.length === 0) {
            addMessageToDataTableInfo('sumAmount', "");
            return;
        }

        for (const amount of cells) {
            let amountInt = convertToInt(amount);

            if (amountInt === null) {
                addMessageToDataTableInfo('sumAmount', "Tổng cộng: Không thể tính tổng các ô đã chọn !");
                return;
            }

            sumAmount += amountInt;
        }

        addMessageToDataTableInfo('sumAmount', "Tổng cộng: " + formatNumber(sumAmount));
    } catch (e) {
        console.log(e);

        addMessageToDataTableInfo('sumAmount', "Tổng cộng: Không thể tính tổng các ô đã chọn !");
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