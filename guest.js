let allEquipmentData  = [];
let selectedEquipment = [];
let hiromiSignatureUrl = '';
let hiromiSignatureVersion = Date.now();

function escapeHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function getHiromiSignatureSrc() {
    if (!hiromiSignatureUrl) return '';
    const joiner = hiromiSignatureUrl.includes('?') ? '&' : '?';
    return `${hiromiSignatureUrl}${joiner}v=${hiromiSignatureVersion}`;
}

function buildHiromiSignatureImage() {
    const src = getHiromiSignatureSrc();
    if (src) {
        return `<img src="${escapeHtml(src)}" alt="Mr. Hiromi Rivas e-signature" style="display:block;width:180px;height:60px;object-fit:contain;margin:8px 0;">`;
    }
    return `<div style="width:180px;height:60px;border:1.5px dashed #bbb;border-radius:4px;display:flex;align-items:center;justify-content:center;color:#777;font-size:12px;margin:8px 0;background:#fafafa;">e-signature here</div>`;
}

function refreshHiromiSignatureSlots() {
    document.querySelectorAll('.hiromi-esig-slot').forEach(slot => {
        slot.innerHTML = buildHiromiSignatureImage();
    });
}

function loadHiromiSignature() {
    return fetch('get_esignature.php')
        .then(r => r.json())
        .then(res => {
            if (res.success && res.url) {
                hiromiSignatureUrl = res.url;
                hiromiSignatureVersion = Date.now();
            }
            refreshHiromiSignatureSlots();
            return res;
        })
        .catch(() => refreshHiromiSignatureSlots());
}

// ════════════════════════════════════════════════════════════════
// REAL-TIME REQUIRED FIELD INDICATOR (asterisk visibility)
// ════════════════════════════════════════════════════════════════
function initializeRequiredIndicators() {
    const fieldConfigs = [
        { inputs: ['lastName', 'firstName', 'middleInitial'], indicator: 'borrowerRequired' },
        { inputs: ['studentID'], indicator: 'studentIdRequired' },
        { inputs: ['subjectCode'], indicator: 'subjectCodeRequired' },
        { inputs: ['usageDate'], indicator: 'usageDateRequired' },
        { inputs: ['departmentSelect'], indicator: 'departmentRequired' },
        { inputs: ['roomSelect', 'roomCustomInput'], indicator: 'roomRequired' },
        { inputs: ['instructorName', 'instructorCustomInput'], indicator: 'instructorRequired' }
    ];

    function updateIndicator(config) {
        const indicator = document.getElementById(config.indicator);
        if (!indicator) return;

        const allFilled = config.inputs.every(inputId => {
            const element = document.getElementById(inputId);
            if (!element) return true;
            const value = element.value ? element.value.trim() : '';
            const isHidden = element.classList && element.classList.contains('hidden-input');
            return (value.length > 0) || isHidden;
        });

        if (allFilled && config.inputs.some(id => document.getElementById(id)?.value?.trim())) {
            indicator.classList.add('hidden');
        } else {
            indicator.classList.remove('hidden');
        }
    }

    fieldConfigs.forEach(config => {
        config.inputs.forEach(inputId => {
            const element = document.getElementById(inputId);
            if (element) {
                element.addEventListener('input', () => updateIndicator(config));
                element.addEventListener('change', () => updateIndicator(config));
            }
        });
    });

    // Initial check
    fieldConfigs.forEach(updateIndicator);
}

// ════════════════════════════════════════════════
// RULES MODAL — 2-slide, scroll-to-reveal agree
// ════════════════════════════════════════════════
let rulesSlide = 0; // 0 or 1

function rulesGoToSlide(index) {
    rulesSlide = index;
    const track = document.getElementById('rulesTrack');
    if (track) track.style.transform = `translateX(-${index * 50}%)`;

    // Dots
    document.getElementById('rulesDot0').classList.toggle('active', index === 0);
    document.getElementById('rulesDot1').classList.toggle('active', index === 1);

    // Step label
    document.getElementById('rulesStepLabel').textContent = `Slide ${index + 1} of 2`;

    // Nav buttons
    document.getElementById('rulesPrevBtn').disabled = index === 0;
    const nextBtn = document.getElementById('rulesNextBtn');
    nextBtn.style.display = index === 1 ? 'none' : '';

    // Check scroll position for the active slide
    if (index === 0) checkSlideScroll(0);
    if (index === 1) checkSlideScroll(1);
}

function checkSlideScroll(slideIndex) {
    const slide = document.getElementById(`rulesSlide${slideIndex}`);
    const hint  = document.getElementById(`scrollHint${slideIndex}`);
    if (!slide) return;

    const atBottom = slide.scrollHeight - slide.scrollTop <= slide.clientHeight + 6;

    if (hint) hint.style.opacity = atBottom ? '0' : '1';

    // Only reveal agree section when user has scrolled to bottom of slide 2
    if (slideIndex === 1 && atBottom) {
        document.getElementById('rulesAgreeSection').classList.add('visible');
    }
}

function initRulesModal() {
    rulesGoToSlide(0);

    // Reset agree section
    const agreeSection = document.getElementById('rulesAgreeSection');
    const checkbox     = document.getElementById('rulesCheckbox');
    const agreeBtn     = document.getElementById('rulesAgreeBtn');
    agreeSection.classList.remove('visible');
    if (checkbox) checkbox.checked = false;
    if (agreeBtn) agreeBtn.disabled = true;

    // Reset scroll positions
    ['rulesSlide0', 'rulesSlide1'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.scrollTop = 0;
    });

    // Attach scroll listeners
    const slide0 = document.getElementById('rulesSlide0');
    const slide1 = document.getElementById('rulesSlide1');
    if (slide0) slide0.onscroll = () => checkSlideScroll(0);
    if (slide1) slide1.onscroll = () => checkSlideScroll(1);

    // Check initial state
    checkSlideScroll(0);

    // Nav
    document.getElementById('rulesPrevBtn').onclick = () => {
        if (rulesSlide > 0) rulesGoToSlide(rulesSlide - 1);
    };
    document.getElementById('rulesNextBtn').onclick = () => {
        if (rulesSlide < 1) rulesGoToSlide(rulesSlide + 1);
    };

    // Agree checkbox
    document.getElementById('rulesCheckbox').onchange = function () {
        document.getElementById('rulesAgreeBtn').disabled = !this.checked;
    };

    // Agree button
    document.getElementById('rulesAgreeBtn').onclick = function () {
        if (!this.disabled) {
            document.getElementById('rulesModal').classList.remove('is-open');
        }
    };
}

// ════════════════════════════════════════════════
// OPEN FORM BUTTON STATE
// ════════════════════════════════════════════════
function updateOpenFormButton() {
    const anyChecked = $('.equipment-checkbox:checked').length > 0;
    const $btn       = $('#openBorrowerFormBtn');

    if (anyChecked) {
        $btn.prop('disabled', false).attr('aria-disabled', 'false').removeAttr('title');
        $('#borrowerFormActions').addClass('has-selection');
    } else {
        $btn.prop('disabled', true).attr('aria-disabled', 'true')
            .attr('title', 'Select at least one item from the table');
        $('#borrowerFormActions').removeClass('has-selection');
        $('#borrowerFormModal').removeClass('is-open');
        $('body').css('overflow', '');
    }
}

// ════════════════════════════════════════════════
// REVIEW MODAL
// ════════════════════════════════════════════════
function openReviewModal() {
    // Validate name fields
    const last  = document.getElementById('lastName').value.trim();
    const first = document.getElementById('firstName').value.trim();
    const mi    = document.getElementById('middleInitial').value.trim();
    if (!last || !first || !mi) {
        alert("Please complete the borrower's full name: Last, First, and Middle Initial.");
        return;
    }

    const fullName = `${last.toUpperCase()}, ${first.toUpperCase()} ${mi.toUpperCase()}.`;
    document.getElementById('borrowerName').value = fullName;

    const studentID   = document.getElementById('studentID').value.trim();
    const subjectCode = document.getElementById('subjectCode').value.trim();
    const usageDate   = document.getElementById('usageDate').value;
    const department  = document.getElementById('departmentSelect').value;
    const roomCustom  = document.getElementById('roomCustomInput');
    const roomSelect  = document.getElementById('roomSelect');
    // Get room value from either custom input or select
    const room = (roomCustom && roomCustom.classList.contains('show'))
        ? roomCustom.value.trim()
        : roomSelect.value;
    const instructorCustom = document.getElementById('instructorCustomInput');
    const instructorSelect = document.getElementById('instructorName');
    // Get instructor value from either custom input or select
    const instructor = (instructorCustom && instructorCustom.classList.contains('show'))
        ? instructorCustom.value.trim()
        : instructorSelect.value;
    const hasEquip    = $('#equipmentListInForm tr').length > 0;

    if (!studentID || !subjectCode || !usageDate || !department || !room || !instructor) {
        alert('Please fill out all fields before continuing, including selecting a department, room and instructor.');
        return;
    }
    if (!hasEquip) {
        alert('Please select at least one equipment/material before continuing.');
        return;
    }

    // Populate review fields
    document.getElementById('rv-guest').textContent      = document.getElementById('borrowerGuestNumber').textContent.trim();
    document.getElementById('rv-date').textContent       = document.getElementById('borrowDate').textContent.trim();
    document.getElementById('rv-name').textContent       = fullName;
    document.getElementById('rv-instructor').textContent = instructor;
    document.getElementById('rv-student-id').textContent = studentID;
    document.getElementById('rv-subject').textContent    = subjectCode;
    document.getElementById('rv-department').textContent = department;
    document.getElementById('rv-room').textContent       = room;

    const uParts = usageDate.split('-');
    document.getElementById('rv-usage-date').textContent =
        uParts.length === 3 ? `${uParts[1]}/${uParts[2]}/${uParts[0]}` : usageDate;

    // Equipment rows
    const tbody = document.getElementById('rv-equipment-list');
    tbody.innerHTML = '';
    let idx = 1;
    $('#equipmentListInForm tr').each(function () {
        const name = $(this).find('td:eq(0)').text().trim();
        const qty  = $(this).find('td:eq(1) input').val() || $(this).find('td:eq(1)').text().trim();
        const tr   = document.createElement('tr');
        tr.innerHTML = `<td style="padding:9px 12px;color:var(--text-3);font-size:12px;">${idx++}</td>
                        <td style="padding:9px 12px;">${name}</td>
                        <td style="padding:9px 12px;">${qty}</td>`;
        tbody.appendChild(tr);
    });

    // Fade out borrower form → fade in review
    const $bForm  = $('#borrowerFormModal');
    const $review = $('#reviewModal');
    $bForm.css({ opacity: '0', 'pointer-events': 'none' });
    setTimeout(() => {
        $bForm.removeClass('is-open').css({ opacity: '', 'pointer-events': '' });
        $review.addClass('is-open');
    }, 210);
}

function closeReviewModal() {
    const $review = $('#reviewModal');
    const $bForm  = $('#borrowerFormModal');
    $review.removeClass('is-open');
    setTimeout(() => {
        $bForm.addClass('is-open');
        $('body').css('overflow', 'hidden');
    }, 180);
}

// ════════════════════════════════════════════════
// INIT
// ════════════════════════════════════════════════
$(document).ready(function () {
    loadHiromiSignature();
    getBorrowerNumber();
    setCurrentDate();
    fetchEquipment();
    loadInstructorList();
    getRooms();
    setUsageDate();

    // Show rules modal
    initRulesModal();
    setTimeout(() => { $('#rulesModal').addClass('is-open'); }, 80);

    // Equipment checkbox — also toggles card selected state
    $('#equipmentList').on('change', '.equipment-checkbox', function () {
        const eq      = allEquipmentData.find(e => e.equipment_id === $(this).data('id'));
        const checked = $(this).is(':checked');
        // Toggle card visual
        $(this).closest('.eq-card').toggleClass('selected', checked);
        if (eq) toggleEquipmentSelection(eq, checked);
        updateOpenFormButton();
    });

    // Open borrower form
    $('#openBorrowerFormBtn').on('click', function () {
        updateBorrowerFormList();
        $('#borrowerFormModal').addClass('is-open');
        $('body').css('overflow', 'hidden');
    });

    // Close borrower form
    $('#closeBorrowerFormBtn').on('click', function () {
        $('#borrowerFormModal').removeClass('is-open');
        $('body').css('overflow', '');
    });
    $('#borrowerFormModal').on('click', function (e) {
        if (e.target === this) {
            $('#borrowerFormModal').removeClass('is-open');
            $('body').css('overflow', '');
        }
    });

    // Review modal edit → back
    $('#reviewEditBtn').on('click', closeReviewModal);
    $('#reviewModal').on('click', function (e) {
        if (e.target === this) closeReviewModal();
    });

    // Review confirm → guest-number confirm modal
    $('#reviewConfirmBtn').on('click', function () {
        $('#reviewModal').removeClass('is-open');
        setTimeout(() => { $('#confirmationModal').addClass('is-open'); }, 200);
    });

    updateOpenFormButton();
});

// ════════════════════════════════════════════════
// GUEST NUMBER & DATE
// ════════════════════════════════════════════════
function getBorrowerNumber() {
    $.ajax({
        url: 'get_guest_number.php', method: 'GET',
        success: function (res) {
            const data = JSON.parse(res);
            $('#guestNumber').val(data.guest_number);
            $('#borrowerLoginNumber').text('Login Number: ' + data.guest_number);
            $('#borrowerGuestNumber').text(data.guest_number);
        }
    });
}

function setCurrentDate() {
    const today = new Date();
    const d  = String(today.getDate()).padStart(2, '0');
    const m  = String(today.getMonth() + 1).padStart(2, '0');
    const y  = today.getFullYear();
    $('#borrowDate').text(`${m}/${d}/${y}`);
    $('#borrowDateForDB').val(`${y}-${m}-${d}`);
}

function setUsageDate() {
    const today = new Date();
    const y = today.getFullYear();
    const m = String(today.getMonth() + 1).padStart(2, '0');
    const d = String(today.getDate()).padStart(2, '0');
    $('#usageDate').val(`${y}-${m}-${d}`);
}

// ════════════════════════════════════════════════
// DROPDOWNS
// ════════════════════════════════════════════════
function loadInstructorList() {
    $.ajax({
        url: 'get_instructors.php', method: 'GET',
        success: function (res) {
            const instructors = JSON.parse(res);
            const select      = $('#instructorName');
            instructors.forEach(name => select.append(`<option value="${name}">${name}</option>`));
        }
    });
}

function getRooms() {
    $.ajax({
        url: 'get_rooms.php', method: 'GET',
        success: function (res) {
            const rooms = JSON.parse(res);
            rooms.forEach(r => $('#roomSelect').append($('<option>', { value: r.room_number, text: r.room_number })));
        }
    });
}

// Also load instructors/rooms via fetch_instructors / fetch_rooms (fallback for old endpoints)
$(document).ready(function () {
    $.ajax({ url: 'fetch_instructors.php', method: 'GET', success: function (data) {
        try {
            const list = JSON.parse(data);
            list.forEach(i => {
                if (!$('#instructorName option[value="' + (i.name || i.instructor_name) + '"]').length)
                    $('#instructorName').append(new Option(i.instructor_name || i.name, i.name || i.instructor_name));
            });
        } catch(e) {}
    }});
    $.ajax({ url: 'fetch_rooms.php', method: 'GET', success: function (data) {
        try {
            const list = JSON.parse(data);
            list.forEach(r => {
                if (!$('#roomSelect option[value="' + r.room_number + '"]').length)
                    $('#roomSelect').append(new Option(r.room_number, r.room_number));
            });
        } catch(e) {}
    }});
});

// ════════════════════════════════════════════════
// EQUIPMENT TABLE
// ════════════════════════════════════════════════
function fetchEquipment() {
    $.ajax({
        url: 'fetch_equipment.php', method: 'GET', dataType: 'json',
        success: function (data) { allEquipmentData = data; filterAndDisplayEquipment(); }
    });
}

$(document).ready(function () {
    fetchEquipment();
    $('#categorySelect').on('change', filterAndDisplayEquipment);
    $('#searchInput').on('input', filterAndDisplayEquipment);
});

function filterAndDisplayEquipment() {
    const cat = $('#categorySelect').val();
    const kw  = $('#searchInput').val().toLowerCase();
    const filtered = allEquipmentData.filter(item => {
        const matchSearch = item.equipment_id.toLowerCase().includes(kw) || item.equipment_name.toLowerCase().includes(kw);
        const prefix = item.equipment_id.charAt(0).toUpperCase();
        let matchCat = true;
        if (cat === 'equipment') matchCat = prefix === 'E';
        else if (cat === 'books')     matchCat = prefix === 'B';
        else if (cat === 'chemicals') matchCat = prefix === 'C';
        else if (cat === 'measuring') matchCat = prefix === 'M';
        return matchSearch && matchCat;
    });
    populateEquipmentTable(filtered);
}

function populateEquipmentTable(data) {
    const grid = $('#equipmentList').empty();
    data.forEach(item => {
        const isChecked  = selectedEquipment.some(e => e.equipment_id === item.equipment_id);
        const isDisabled = Number(item.available) === 0;

        // Resolve image — same convention as admin: equipment_images/{id}.jpg
        const photoSrc = item.photo_url
            ? item.photo_url
            : `equipment_images/${item.equipment_id}.jpg`;

        const card = $('<div>')
            .addClass('eq-card' + (isChecked ? ' selected' : '') + (isDisabled ? ' disabled' : ''))
            .attr('data-id', item.equipment_id);

        // Image
        const imgWrap = $('<div>');
        const img = $('<img>')
            .addClass('eq-card-img')
            .attr('src', photoSrc)
            .attr('alt', item.equipment_name)
            .on('error', function() {
                $(this).replaceWith(`
                    <div class="eq-card-img-placeholder">
                        <svg width="32" height="32" fill="none" stroke="currentColor" stroke-width="1.2" viewBox="0 0 24 24" opacity=".35">
                            <rect x="3" y="3" width="18" height="18" rx="3"/>
                            <circle cx="8.5" cy="8.5" r="1.5"/>
                            <path d="M21 15l-5-5L5 21"/>
                        </svg>
                    </div>`);
            });
        imgWrap.append(img);
        card.append(imgWrap);

        // Selected checkmark badge
        card.append(`
            <div class="eq-card-selected-badge">
                <svg fill="none" stroke="#fff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 14 14">
                    <path d="M2 7l4 4 6-6"/>
                </svg>
            </div>`);

        // Body
        const availCount = Number(item.available);
        card.append(`
            <div class="eq-card-body">
                <div class="eq-card-name">${item.equipment_name}</div>
                <div class="eq-card-id">${item.equipment_id}</div>
                <div class="eq-card-footer">
                    <span class="eq-card-avail ${availCount > 0 ? 'ok' : 'none'}">
                        ${availCount > 0 ? availCount + ' available' : 'Unavailable'}
                    </span>
                    <input type="checkbox" class="eq-card-check equipment-checkbox"
                        data-id="${item.equipment_id}"
                        ${isChecked ? 'checked' : ''}
                        ${isDisabled ? 'disabled' : ''}>
                </div>
            </div>`);

        // Clicking the whole card toggles the checkbox (unless disabled)
        card.on('click', function(e) {
            if (isDisabled) return;
            if ($(e.target).is('input[type=checkbox]')) return; // let checkbox handle itself
            const cb = card.find('.equipment-checkbox');
            cb.prop('checked', !cb.prop('checked')).trigger('change');
        });

        grid.append(card);
    });
}

function toggleEquipmentSelection(equipment, checked) {
    if (checked) {
        if (!selectedEquipment.find(i => i.equipment_id === equipment.equipment_id))
            selectedEquipment.push({ ...equipment, selected_quantity: 1 });
    } else {
        selectedEquipment = selectedEquipment.filter(i => i.equipment_id !== equipment.equipment_id);
    }
    updateBorrowerFormList();
    updateOpenFormButton();
}

function updateBorrowerFormList() {
    const list = $('#equipmentListInForm').empty();
    selectedEquipment.forEach((item, index) => {
        const maxAvail = Number(item.available) || 1;
        const qty      = Math.max(1, Math.min(Number(item.selected_quantity) || 1, maxAvail));
        selectedEquipment[index].selected_quantity = qty;

        const row   = $('<tr>');
        const input = $('<input>', { type: 'number', min: 1, max: maxAvail, step: 1, value: qty, 'data-index': index, css: { width: '60px' } });
        row.append($('<td>').text(item.equipment_name || item.equipment_id || '—'));
        row.append($('<td>').append(input));
        row.append($('<td>').text('YES'));
        row.append($('<td>').text('___________'));
        row.append($('<td>').text('______________'));

        let timer;
        input.on('input', function () {
            clearTimeout(timer);
            timer = setTimeout(() => {
                const i = $(this).data('index');
                let val = Math.floor(parseFloat(this.value));
                if (isNaN(val) || val < 1) val = 1;
                if (val > Number(selectedEquipment[i].available)) val = Number(selectedEquipment[i].available);
                this.value = selectedEquipment[i].selected_quantity = val;
            }, 400);
        });

        list.append(row);
    });
}

// ════════════════════════════════════════════════
// CONFIRMATION / SUBMIT
// ════════════════════════════════════════════════
function closeConfirmationModal() {
    $('#confirmationModal').removeClass('is-open');
}

function submitBorrowRequest() {
    const confirmNum  = $('#confirmGuestNumber').val().trim();
    const guestNum    = $('#borrowerGuestNumber').text().trim();

    if (confirmNum !== guestNum) { alert('Borrower number does not match.'); return; }

    const roomCustom = document.getElementById('roomCustomInput');
    const roomSelect = document.getElementById('roomSelect');
    const instructorCustom = document.getElementById('instructorCustomInput');
    const instructorSelect = document.getElementById('instructorName');
    const room = (roomCustom && roomCustom.classList.contains('show'))
        ? roomCustom.value.trim()
        : roomSelect.value;
    const instructor = (instructorCustom && instructorCustom.classList.contains('show'))
        ? instructorCustom.value.trim()
        : instructorSelect.value;

    const data = {
        guestNumber:    guestNum,
        date:           $('#borrowDateForDB').val(),
        borrowerName:   $('#borrowerName').val(),
        instructorName: instructor,
        studentID:      $('#studentID').val(),
        subjectCode:    $('#subjectCode').val(),
        usageDate:      $('#usageDate').val(),
        department:     $('#departmentSelect').val(),
        room:           room,
        equipmentList:  []
    };

    $('#equipmentListInForm tr').each(function () {
        data.equipmentList.push({
            equipmentName: $(this).find('td:eq(0)').text(),
            quantity:      $(this).find('td:eq(1) input').val(),
            available:     'YES'
        });
    });

    $.ajax({
        url: 'submit_borrow_request.php', method: 'POST', data: { data: JSON.stringify(data) },
        success: function (res) {
            if (res.success) {
                showFinalConfirmationModal(data);
                closeConfirmationModal();
                $('#borrowerFormModal, #reviewModal').removeClass('is-open');
                $('body').css('overflow', '');
                fetchEquipment();
                selectedEquipment = [];
                updateBorrowerFormList();
                updateOpenFormButton();
            } else {
                alert('Server Error: ' + res.message);
            }
        },
        error: function (xhr, status, err) { console.error(err); alert('An error occurred.'); }
    });
}

function showFinalConfirmationModal(data) {
    const orig = document.getElementById('borrowerForm');
    if (!orig) return;
    const clone = orig.cloneNode(true);
    try { clone.querySelector('#departmentSelect').value = data.department || ''; } catch(e) {}
    try { clone.querySelector('#instructorName').value = data.instructorName || ''; } catch(e) {}
    try { clone.querySelector('#roomSelect').value     = data.room || ''; } catch(e) {}
    try { clone.querySelector('#instructorCustomInput').value = data.instructorName || ''; } catch(e) {}
    try { clone.querySelector('#roomCustomInput').value       = data.room || ''; } catch(e) {}
    clone.querySelectorAll('input, select, textarea, button').forEach(el => {
        el.disabled = true; el.readOnly = true;
        if (el.type === 'submit' || el.type === 'button') el.style.display = 'none';
    });
    const container = document.getElementById('finalFormData');
    container.innerHTML = '';
    container.appendChild(clone);
    $('#finalConfirmationModal').addClass('is-open');
}

$(document).ready(function () {
    $('#closeFinalModalBtn').on('click', function () {
        $('#finalConfirmationModal').removeClass('is-open');
        alert('Form submitted successfully!');
        window.location.href = 'logout.php';
    });
});

function combineBorrowerName() {
    const last  = document.getElementById('lastName').value.trim().toUpperCase();
    const first = document.getElementById('firstName').value.trim().toUpperCase();
    const mi    = document.getElementById('middleInitial').value.trim().toUpperCase();
    document.getElementById('borrowerName').value = `${last}, ${first} ${mi}.`;
}

function showEquipment() {
    const eq = document.getElementById('equipmentSection');
    const tb = document.getElementById('topBar');
    if (eq) eq.style.display = 'block';
    if (tb) tb.style.display = 'block';
}

window.logout = function () { window.location.href = 'logout.php'; };


// ════════════════════════════════════════════════════════════════
// DEPARTMENT-LINKED SELECTS (Room & Instructor)
// ════════════════════════════════════════════════════════════════

// Department to Room Mapping
const DEPARTMENT_ROOM_MAP = {
    'Applied Physics': ['407','414'],
    'Mathematics': ['401'],
    'General Education': ['401', '407', '412','414'],
    'Psychology': ['412'],
    'Others': null  // Triggers text input instead of select
};

// Department to Instructor Mapping
const DEPARTMENT_INSTRUCTOR_MAP = {
    'Applied Physics': ['Mr. Lester D. Bernardino', 'Dr. Raymund B. Bolalin', 'Mr. Hiromi Rivas', 'Mr. Reenier R. Ledesma'],
    'Mathematics': ['Prof. Larex B. Tagalog', 'Dr. Roel P. Balayan', 'Dr. Jayson D. Tolentino', 'Mr. Raynard C. Redondo', 'Mr. Mark Ronoele R. Gonzalvo' ,'Mr. Joneil G. Pontejos', 'Mr. Eleazar B. Bernales'],
    'General Education': ['Dr. Joseph T. Moraca', 'Prof. Romeo B. Capucao Jr.', 'Prof. Nerissa B. Capili', 'Prof. Dennis D. Mangubat', 'Dr. Carlito C. Biares', 'Mrs. Rosario U. Tanuecoz', 'Dr. Eugune S. Abdon', 'Mr. Ricky G. Tebang'],
    'Psychology': ['Dr. Jinamarlyn B. Doctor', 'Dr. Lourdes P. Jusay', 'Prof. Ruth Lareza A. Morales', 'Dr. Rosei O. Cipriano', 'Prof. Myrtle P. Macam', 'Ms. Jazmine B Lasam'],
    'Others': null  // Triggers text input instead of select
};

function initDepartmentLinkedSelects() {
    const deptSelect = document.getElementById('departmentSelect');
    const roomSelect = document.getElementById('roomSelect');
    const roomCustom = document.getElementById('roomCustomInput');
    const instSelect = document.getElementById('instructorName');
    const instCustom = document.getElementById('instructorCustomInput');

    if (!deptSelect || !roomSelect || !instSelect) return;

    // Populate department select with options
    Object.keys(DEPARTMENT_ROOM_MAP).forEach(dept => {
        const option = document.createElement('option');
        option.value = dept;
        option.textContent = dept;
        deptSelect.appendChild(option);
    });

    // Handle department change - affects both room and instructor
    deptSelect.addEventListener('change', function () {
        const selectedDept = this.value;

        if (!selectedDept) {
            // Department cleared - disable and clear both
            disableAndClearSelect(roomSelect, roomCustom, 'Select a department first');
            disableAndClearSelect(instSelect, instCustom, 'Select a department first');
            return;
        }

        // Update room select
        updateDependentSelect(selectedDept, roomSelect, roomCustom, DEPARTMENT_ROOM_MAP, 'a room');

        // Update instructor select
        updateDependentSelect(selectedDept, instSelect, instCustom, DEPARTMENT_INSTRUCTOR_MAP, 'an instructor');
    });

    // Handle room select change
    roomSelect.addEventListener('change', function () {
        roomCustom.value = this.value;
        updateRequiredIndicator('roomRequired', this.value);
    });

    // Handle custom room input
    roomCustom.addEventListener('input', function () {
        roomSelect.value = this.value;
        updateRequiredIndicator('roomRequired', this.value);
    });

    // Allow switching back from custom input to room select by pressing Escape
    roomCustom.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            deptSelect.value = '';
            disableAndClearSelect(roomSelect, roomCustom, 'Select a department first');
            disableAndClearSelect(instSelect, instCustom, 'Select a department first');
            deptSelect.focus();
        }
    });

    // Handle instructor select change
    instSelect.addEventListener('change', function () {
        instCustom.value = this.value;
        updateRequiredIndicator('instructorRequired', this.value);
    });

    // Handle custom instructor input
    instCustom.addEventListener('input', function () {
        instSelect.value = this.value;
        updateRequiredIndicator('instructorRequired', this.value);
    });

    // Allow switching back from custom input to instructor select by pressing Escape
    instCustom.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            deptSelect.value = '';
            disableAndClearSelect(roomSelect, roomCustom, 'Select a department first');
            disableAndClearSelect(instSelect, instCustom, 'Select a department first');
            deptSelect.focus();
        }
    });
}

// Helper: Disable and clear a dependent select
function disableAndClearSelect(selectEl, customEl, placeholder) {
    selectEl.disabled = true;
    selectEl.value = '';
    selectEl.style.display = 'block';
    selectEl.innerHTML = `<option value="" disabled selected>${placeholder}</option>`;
    if (customEl) {
        customEl.classList.remove('show');
        customEl.value = '';
        customEl.disabled = true;
    }
}

// Helper: Update dependent select based on department
function updateDependentSelect(dept, selectEl, customEl, dataMap, fieldLabel) {
    const options = dataMap[dept];

    if (dept === 'Others') {
        // Show custom text input
        selectEl.style.display = 'none';
        selectEl.disabled = true;
        if (customEl) {
            customEl.classList.add('show');
            customEl.disabled = false;
            customEl.value = '';
            customEl.focus();
        }
    } else {
        // Show select with options
        selectEl.style.display = 'block';
        selectEl.disabled = false;
        if (customEl) {
            customEl.classList.remove('show');
            customEl.disabled = true;
            customEl.value = '';
        }

        // Populate options
        selectEl.innerHTML = `<option value="" disabled selected>Select ${fieldLabel}</option>`;
        if (options && Array.isArray(options)) {
            options.forEach(option => {
                const optElement = document.createElement('option');
                optElement.value = option;
                optElement.textContent = option;
                selectEl.appendChild(optElement);
            });
        }
    }
}

// Helper: Update required indicator
function updateRequiredIndicator(indicatorId, value) {
    const indicator = document.getElementById(indicatorId);
    if (indicator) {
        if (value) {
            indicator.classList.add('hidden');
        } else {
            indicator.classList.remove('hidden');
        }
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    initializeRequiredIndicators();
    initDepartmentLinkedSelects();
});

// Also initialize if DOM is already ready
if (document.readyState !== 'loading') {
    initializeRequiredIndicators();
    initDepartmentLinkedSelects();
}
