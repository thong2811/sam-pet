/// <reference types="datatables.net" />
/// <reference types="jquery.dataTables" />

const DATA_TABLES_CONFIG = {
    layout: {
        topStart: {
            className: 'active-sticky',
            features: 'info'
        },
        topEnd: null,
        top1Start: 'search',
        top1End: 'pageLength',
    },
    select: {
        style: 'os',
        items: 'cell'
    },

    aLengthMenu: [
        [30, 50, 100, 200, -1],
        [30, 50, 100, 200, "Tất cả"]
    ],
    iDisplayLength: 30,

    order: [['1', 'desc']],
    columnDefs: [
        {
            searchable: false,
            orderable: false,
            targets: ['no:name', 'action:name']
        },
        {
            type: "date-eu",
            targets: ['date:name']
        }
    ]
}

function initDataTable(tableId = '#dataTable', config = DATA_TABLES_CONFIG) {
    const table = new DataTable(tableId, config);

    // sum value on select, deselect
    table.on('select', function () {
        calculateSumAmountCells(table);
    })
    table.on('deselect', function () {
        calculateSumAmountCells(table);
    });

    return table;
}