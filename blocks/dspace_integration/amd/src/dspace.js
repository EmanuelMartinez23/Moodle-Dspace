define(['jquery', 'datatables.net-bs5'], function($) {
    return {
        init: function() {
            $('.dspace-table').DataTable({
                pageLength: 5,
                lengthMenu: [ [5, 10, 15, 25, 50], [5, 10, 15, 25, 50] ],
                lengthChange: true,
                searching: true,
                ordering: true,
                autoWidth: false,
                scrollX: true,
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.1/i18n/es-ES.json'
                },
                columnDefs: [
                    { width: '220px', targets: 0 },
                    { width: '220px', targets: 1 }
                ]
            });
        }
    };
});
