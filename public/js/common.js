const FLASH_MESSAGE_TYPE_ERROR = 0;
const FLASH_MESSAGE_TYPE_SUCCESS = 1;
const FLASH_MESSAGE_TYPE_INFO = 2;

/**
 * Lấy CSRF token từ meta tag trong layout.
 * Token được inject bởi CsrfService::getToken() phía server.
 */
function getCsrfToken()
{
    return $('meta[name="csrf-token"]').attr('content') || '';
}

$(document).ready(function () {
    $('.date-picker').datepicker({
        format: 'dd-mm-yyyy',
        autoclose:true
    });

    // Tự động gửi CSRF token trong mọi AJAX POST request
    $.ajaxSetup({
        beforeSend: function (xhr, settings) {
            if (settings.type && settings.type.toUpperCase() === 'POST') {
                xhr.setRequestHeader('X-CSRF-Token', getCsrfToken());
            }
        }
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

/**
 * Hàm xóa record dùng chung cho tất cả trang.
 * @param {string} controllerName  - tên controller, ví dụ 'product'
 * @param {string} id              - id của record cần xóa
 * @param {object} table           - DataTables instance để reload sau khi xóa
 * @param {string} deleteUrl       - URL endpoint xóa (mặc định /{controllerName}/do-delete)
 */
function removeRow(controllerName, id, table, deleteUrl = null)
{
    if (!confirm('Bạn có chắc chắn muốn xóa?')) return;

    const url = deleteUrl || `/${controllerName}/do-delete`;

    $.ajax({
        url: url,
        type: 'DELETE',
        contentType: 'application/json',
        data: JSON.stringify({ id: id }),
        success: function (response) {
            if (response.success) {
                if (table) table.draw(false);
                addFlashMessage('Xoá thành công');
            } else {
                addFlashMessage('Đã xảy ra lỗi khi xóa: ' + (response.message || 'unknown'), FLASH_MESSAGE_TYPE_ERROR);
            }
        },
        error: function (xhr, status, error) {
            addFlashMessage('Đã xảy ra lỗi khi xóa: ' + error, FLASH_MESSAGE_TYPE_ERROR);
        }
    });
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