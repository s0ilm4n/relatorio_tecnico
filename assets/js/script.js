// Auto-cálculo para campos de tensão
document.addEventListener('DOMContentLoaded', function() {
    // Mostrar/esconder campos de valor mediante checkbox
    document.querySelectorAll('.checklist-item input[type="checkbox"]').forEach(function(cb) {
        cb.addEventListener('change', function() {
            const fields = this.closest('.checklist-item').querySelector('.item-fields');
            if (fields) {
                const inputs = fields.querySelectorAll('input, select, textarea');
                inputs.forEach(function(inp) {
                    inp.disabled = !cb.checked;
                    if (!cb.checked) inp.value = '';
                });
            }
        });
        // Trigger inicial para definir estado
        const event = new Event('change');
        cb.dispatchEvent(event);
    });

    // Preencher data atual automaticamente
    var dataField = document.getElementById('data');
    if (dataField && !dataField.value) {
        var today = new Date().toISOString().split('T')[0];
        dataField.value = today;
    }

    // Preencher hora de início
    var horaInicio = document.getElementById('hora_inicio');
    if (horaInicio && !horaInicio.value) {
        var now = new Date();
        horaInicio.value = now.getHours().toString().padStart(2, '0') + ':' + now.getMinutes().toString().padStart(2, '0');
    }
});

function confirmDelete(msg) {
    return confirm(msg || 'Tem a certeza que pretende eliminar?');
}

function printPage() {
    window.print();
}
