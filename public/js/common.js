function convertToInt(value) {
    if (typeof value === 'number') {
        return value;
    }

    value = value.replaceAll("VNĐ", '').replaceAll(",", '').replaceAll(" ", '');
    if (isFinite(value)) {
        return parseInt(value);
    }
    return null;
}

function formatNumber(value) {
    return value.toLocaleString();
}

function addMessageToDataTableInfo(id, message) {
    const messageElmSelector = $(`#dataTable_wrapper .dt-info #${id}`);
    if (messageElmSelector.length > 0) {
        messageElmSelector.remove();
    }
    $("#dataTable_wrapper .dt-info").append(`<span id="${id}" class="ms-4">${message}</span>`);
}

function calculateSumAmountCells(table) {
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