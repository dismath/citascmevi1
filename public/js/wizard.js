/**
 * Portal Cmevi - Wizard Interactions
 */

document.addEventListener('DOMContentLoaded', function () {
    // Handling Date Selection and loading Time Slots via AJAX
    const dateSelector = document.getElementById('date_selector');
    if (dateSelector) {
        // Pre-select if there's only one date
        if (dateSelector.options.length === 2) {
            dateSelector.selectedIndex = 1;
            dateSelector.dispatchEvent(new Event('change'));
        }
    }
});

function loadTimeSlots(date, doctorId) {
    if (!date) return;

    const slotsContainer = document.getElementById('slots_container');
    const slotsGrid = document.getElementById('slots_grid');
    const btnContainer = document.getElementById('btn_continue_container');
    const inputSlotId = document.getElementById('selected_slot_id');
    const inputDate = document.getElementById('selected_date');

    // Reset
    slotsGrid.innerHTML = '<div class="loading text-muted"><i class="fa-solid fa-spinner fa-spin"></i> Cargando horarios...</div>';
    slotsContainer.classList.remove('hidden');
    btnContainer.classList.add('hidden');
    inputSlotId.value = '';
    inputDate.value = date;

    // Fetch slots
    fetch(`${BASE_URL}/api/availability/${doctorId}/${date}`)
        .then(response => response.json())
        .then(result => {
            slotsGrid.innerHTML = '';

            if (result.success && result.data.length > 0) {
                result.data.forEach(slot => {
                    const timeStr = slot.start_time.substring(0, 5); // HH:mm
                    const slotEl = document.createElement('div');
                    slotEl.className = 'time-slot';
                    slotEl.dataset.id = slot.id;
                    slotEl.textContent = timeStr;

                    slotEl.addEventListener('click', function () {
                        // Remove selected class from all
                        document.querySelectorAll('.time-slot').forEach(el => el.classList.remove('selected'));

                        // Add to current
                        this.classList.add('selected');

                        // Set hidden input
                        inputSlotId.value = this.dataset.id;

                        // Show continue button
                        btnContainer.classList.remove('hidden');
                    });

                    slotsGrid.appendChild(slotEl);
                });
            } else {
                slotsGrid.innerHTML = '<div class="text-danger">No hay horarios disponibles para esta fecha.</div>';
            }
        })
        .catch(error => {
            console.error('Error fetching slots:', error);
            slotsGrid.innerHTML = '<div class="text-danger">Error al cargar los horarios. Intente nuevamente.</div>';
        });
}
