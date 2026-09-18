const dialogTriggers = new WeakMap();

function openDialog(id, trigger) {
    const dialog = document.getElementById(id);
    if (!(dialog instanceof HTMLDialogElement) || dialog.open) return;
    dialogTriggers.set(dialog, trigger ?? document.activeElement);
    dialog.showModal();
}

document.addEventListener('click', (event) => {
    const opener = event.target.closest('[data-open-dialog]');
    if (opener) openDialog(opener.dataset.openDialog, opener);
    const closer = event.target.closest('[data-close-dialog]');
    if (closer) closer.closest('dialog')?.close();
});

document.querySelectorAll('dialog').forEach((dialog) => {
    dialog.addEventListener('close', () => dialogTriggers.get(dialog)?.focus());
    if (dialog.hasAttribute('data-auto-open')) openDialog(dialog.id);
});

document.querySelector('[data-review-submit]')?.addEventListener('click', (event) => {
    const choice = document.querySelector('input[name="review_choice"]:checked')?.value;
    openDialog(choice === 'return' ? 'return-dialog' : 'review-dialog', event.currentTarget);
});

document.querySelectorAll('input[name="review_choice"]').forEach((input) => {
    input.addEventListener('change', () => {
        document.querySelector('[data-review-submit]').textContent = input.value === 'return'
            ? 'ส่งกลับให้ผู้ยื่นคำร้องแก้ไข' : 'ส่งต่อผู้มีอำนาจพิจารณา';
    });
});

document.querySelectorAll('[data-conditional]').forEach((input) => {
    const radios = document.querySelectorAll(`input[name="${input.dataset.conditional}"]`);
    function update() {
        const selected = [...radios].find((radio) => radio.checked)?.value;
        input.hidden = selected !== input.dataset.when;
        input.required = !input.hidden;
        input.disabled = input.hidden;
    }
    radios.forEach((radio) => radio.addEventListener('change', update));
    update();
});

document.querySelectorAll('[data-searchable-select]').forEach((field) => {
    const input = field.querySelector('[data-searchable-select-input]');
    const value = field.querySelector('[data-searchable-select-value]');
    const menu = field.querySelector('[data-searchable-select-menu]');
    const empty = field.querySelector('[data-searchable-select-empty]');
    const arrow = field.querySelector('[data-searchable-select-arrow]');
    const clear = field.querySelector('[data-searchable-select-clear]');
    const options = [...field.querySelectorAll('[data-searchable-select-option]')];
    let activeIndex = -1;

    function visibleOptions() {
        return options.filter((option) => !option.hidden);
    }

    function updateControls() {
        const hasText = input.value.trim() !== '';
        arrow.hidden = hasText;
        clear.hidden = !hasText;
    }

    function openMenu() {
        menu.hidden = false;
        input.setAttribute('aria-expanded', 'true');
    }

    function closeMenu() {
        menu.hidden = true;
        input.setAttribute('aria-expanded', 'false');
        setActive(-1);
    }

    function setActive(index) {
        const visible = visibleOptions();
        activeIndex = index;
        options.forEach((option) => option.classList.remove('is-active'));
        if (visible[index]) {
            visible[index].classList.add('is-active');
            visible[index].scrollIntoView({ block: 'nearest' });
            input.setAttribute('aria-activedescendant', `${input.id}-option-${visible[index].dataset.value}`);
        } else {
            input.removeAttribute('aria-activedescendant');
        }
    }

    function selectOption(option) {
        input.value = option.textContent.trim();
        value.value = option.dataset.value;
        options.forEach((item) => item.setAttribute('aria-selected', item === option ? 'true' : 'false'));
        input.setCustomValidity('');
        updateControls();
        input.focus();
        closeMenu();
    }

    function filterOptions() {
        const search = input.value.trim().toLocaleLowerCase('th');
        let resultCount = 0;
        let selected = null;
        options.forEach((option) => {
            const label = option.textContent.trim().toLocaleLowerCase('th');
            option.hidden = search !== '' && !label.includes(search);
            if (!option.hidden) resultCount += 1;
            if (label === search) selected = option;
        });
        value.value = selected?.dataset.value ?? '';
        options.forEach((option) => option.setAttribute('aria-selected', option === selected ? 'true' : 'false'));
        input.setCustomValidity(input.value && !selected ? 'กรุณาเลือกหน่วยงานจากรายการ' : '');
        empty.hidden = resultCount !== 0;
        updateControls();
        setActive(-1);
    }

    input.addEventListener('focus', () => {
        filterOptions();
        openMenu();
    });
    input.addEventListener('input', () => {
        filterOptions();
        openMenu();
    });
    input.addEventListener('keydown', (event) => {
        const visible = visibleOptions();
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            openMenu();
            setActive(Math.min(activeIndex + 1, visible.length - 1));
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            openMenu();
            setActive(Math.max(activeIndex - 1, 0));
        } else if (event.key === 'Enter' && activeIndex >= 0) {
            event.preventDefault();
            selectOption(visible[activeIndex]);
        } else if (event.key === 'Escape') {
            closeMenu();
        }
    });
    clear.addEventListener('click', () => {
        input.value = '';
        value.value = '';
        input.setCustomValidity('');
        filterOptions();
        input.focus();
        openMenu();
    });
    options.forEach((option) => option.addEventListener('click', () => selectOption(option)));
    document.addEventListener('click', (event) => {
        if (!field.contains(event.target)) closeMenu();
    });
    filterOptions();
});

const learningPeriod = document.querySelector('[data-learning-period]');
if (learningPeriod) {
    const learningOptions = document.querySelectorAll('input[name="learning"]');
    const periodInputs = learningPeriod.querySelectorAll('input');
    function updateLearningPeriod() {
        const selected = [...learningOptions].find((radio) => radio.checked)?.value;
        const scheduled = selected === 'แบบกำหนดช่วงเวลาเรียน';
        learningPeriod.hidden = !scheduled;
        periodInputs.forEach((input) => {
            input.required = scheduled;
            input.disabled = !scheduled;
            if (!scheduled) input.value = '';
        });
    }
    learningOptions.forEach((radio) => radio.addEventListener('change', updateLearningPeriod));
    updateLearningPeriod();
}

const instructorList = document.querySelector('[data-instructor-list]');
function renumberInstructors() {
    instructorList.querySelectorAll('[data-instructor]').forEach((row, index) => {
        row.querySelectorAll('input').forEach((input) => {
            const label = input.closest('label');
            input.name = input.name.replace(/instructors\[\d+\]/, `instructors[${index}]`);
            input.id = `field-${input.name.replaceAll('[', '-').replaceAll(']', '')}`;
            label.htmlFor = input.id;
            if (input.name.endsWith('[first]')) label.firstChild.textContent = `ชื่ออาจารย์ผู้สอนหลัก คนที่ ${index + 1} :`;
        });
        row.querySelector('[data-remove-instructor]').hidden = index === 0;
    });
    const addButton = document.querySelector('[data-add-instructor]');
    if (addButton) addButton.disabled = instructorList.children.length >= 20;
}
document.querySelector('[data-add-instructor]')?.addEventListener('click', () => {
    if (instructorList.children.length >= 20) return;
    const row = instructorList.firstElementChild.cloneNode(true);
    row.querySelectorAll('input').forEach((input) => { input.value = ''; });
    instructorList.append(row);
    renumberInstructors();
    row.querySelector('input').focus();
});
instructorList?.addEventListener('click', (event) => {
    if (!event.target.closest('[data-remove-instructor]')) return;
    if (instructorList.children.length <= 1) return;
    event.target.closest('[data-instructor]').remove();
    renumberInstructors();
    document.querySelector('[data-add-instructor]').focus();
});

document.querySelectorAll('[data-dropzone]').forEach((zone) => {
    const input = zone.querySelector('input[type="file"]');
    const filename = zone.querySelector('[data-file-name]');
    function update() {
        const file = input.files[0];
        input.setCustomValidity('');
        if (!file) { filename.textContent = ''; return; }
        const allowed = input.accept.split(',').map((extension) => extension.trim());
        if (!allowed.some((extension) => file.name.toLowerCase().endsWith(extension))) {
            input.setCustomValidity('ประเภทไฟล์ไม่ถูกต้อง กรุณาเลือกไฟล์ที่รองรับ');
        } else if (file.size > 10 * 1024 * 1024) {
            input.setCustomValidity('ไฟล์ต้องมีขนาดไม่เกิน 10 MB');
        }
        filename.textContent = input.validationMessage || `${file.name} (${(file.size / 1024 / 1024).toFixed(2)} MB)`;
    }
    input.addEventListener('change', update);
    ['dragenter', 'dragover'].forEach((name) => zone.addEventListener(name, (event) => {
        event.preventDefault();
        zone.classList.add('is-dragging');
    }));
    ['dragleave', 'drop'].forEach((name) => zone.addEventListener(name, () => zone.classList.remove('is-dragging')));
    zone.addEventListener('drop', (event) => {
        event.preventDefault();
        if (!event.dataTransfer?.files.length) return;
        const transfer = new DataTransfer();
        transfer.items.add(event.dataTransfer.files[0]);
        input.files = transfer.files;
        update();
    });
});

document.querySelectorAll('[data-multi-upload]').forEach((upload) => {
    const input = upload.querySelector('[data-multi-upload-input]');
    const list = upload.querySelector('[data-multi-upload-list]');
    const trigger = upload.querySelector('[data-multi-upload-trigger]');
    const error = upload.querySelector('[data-multi-upload-error]');
    const form = document.getElementById(input.getAttribute('form')) || input.closest('form');
    const maxFiles = Number(upload.dataset.maxFiles || 5);
    const allowedExtensions = input.accept.split(',').map((extension) => extension.trim().toLowerCase());
    let selectedFiles = [];

    function existingCount() {
        return list.querySelectorAll('[data-existing-file]').length;
    }

    function formatSize(bytes) {
        return `${(bytes / 1024 / 1024).toFixed(2)} MB`;
    }

    function syncInputFiles() {
        const transfer = new DataTransfer();
        selectedFiles.forEach((file) => transfer.items.add(file));
        input.files = transfer.files;
    }

    function updateTrigger() {
        const full = existingCount() + selectedFiles.length >= maxFiles;
        trigger.classList.toggle('is-disabled', full);
        trigger.setAttribute('aria-disabled', full ? 'true' : 'false');
    }

    function renderNewFiles() {
        list.querySelectorAll('[data-new-file]').forEach((item) => item.remove());
        selectedFiles.forEach((file, index) => {
            const item = document.createElement('li');
            item.className = 'multi-upload__item';
            item.dataset.newFile = String(index);

            const filename = document.createElement('span');
            filename.className = 'multi-upload__filename';
            filename.textContent = `${file.name} (${formatSize(file.size)})`;

            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'multi-upload__remove';
            remove.dataset.removeNew = String(index);
            remove.setAttribute('aria-label', `ลบไฟล์ ${file.name}`);
            remove.textContent = '×';

            item.append(filename, remove);
            list.append(item);
        });
        syncInputFiles();
        updateTrigger();
    }

    input.addEventListener('change', () => {
        error.textContent = '';
        const incoming = [...input.files];
        for (const file of incoming) {
            const extension = `.${file.name.split('.').pop()?.toLowerCase()}`;
            if (!allowedExtensions.includes(extension)) {
                error.textContent = 'รองรับเฉพาะไฟล์ PDF, DOC และ DOCX';
                continue;
            }
            if (file.size > 10 * 1024 * 1024) {
                error.textContent = `ไฟล์ ${file.name} มีขนาดเกิน 10 MB`;
                continue;
            }
            const duplicate = selectedFiles.some((selected) => selected.name === file.name && selected.size === file.size);
            if (duplicate) continue;
            if (existingCount() + selectedFiles.length >= maxFiles) {
                error.textContent = `อัปโหลดได้ไม่เกิน ${maxFiles} ไฟล์`;
                break;
            }
            selectedFiles.push(file);
        }
        renderNewFiles();
    });

    list.addEventListener('click', (event) => {
        const removeNew = event.target.closest('[data-remove-new]');
        if (removeNew) {
            selectedFiles.splice(Number(removeNew.dataset.removeNew), 1);
            error.textContent = '';
            renderNewFiles();
            return;
        }

        const removeExisting = event.target.closest('[data-remove-existing]');
        if (!removeExisting) return;
        const hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = 'remove_additional[]';
        hidden.value = removeExisting.dataset.removeExisting;
        form.append(hidden);
        removeExisting.closest('[data-existing-file]').remove();
        error.textContent = '';
        updateTrigger();
    });

    updateTrigger();
});

const requestList = document.querySelector('[data-request-list]');
if (requestList) {
    const search = requestList.querySelector('[data-search]');
    const status = requestList.querySelector('[data-status-filter]');
    const date = requestList.querySelector('[data-date-filter]');
    const rows = [...requestList.querySelectorAll('[data-request-row]')];
    function filterRows() {
        const term = search.value.trim().toLocaleLowerCase('th');
        const now = new Date();
        let count = 0;
        rows.forEach((row) => {
            const submitted = new Date(`${row.dataset.date}T00:00:00`);
            const sameYear = submitted.getFullYear() === now.getFullYear();
            const matchesDate = !date.value || (sameYear && (date.value === 'year' || submitted.getMonth() === now.getMonth()));
            row.hidden = !(row.textContent.toLocaleLowerCase('th').includes(term)
                && (!status.value || row.dataset.status === status.value) && matchesDate);
            if (!row.hidden) count += 1;
        });
        requestList.querySelector('[data-empty-row]').hidden = count !== 0;
        requestList.querySelector('[data-filter-count]').textContent = `พบ ${count} คำร้อง`;
    }
    search.addEventListener('input', filterRows);
    status.addEventListener('change', filterRows);
    date.addEventListener('change', filterRows);
    requestList.querySelector('[data-filter-pending]')?.addEventListener('click', () => {
        status.value = 'PENDING_SIGNED_DOCUMENT';
        date.value = '';
        search.value = '';
        filterRows();
    });
}

document.querySelectorAll('form').forEach((form) => {
    form.addEventListener('submit', (event) => {
        if (form.dataset.submitting) { event.preventDefault(); return; }
        form.dataset.submitting = 'true';
        event.submitter?.setAttribute('aria-busy', 'true');
    });
});
window.addEventListener('pageshow', () => {
    document.querySelectorAll('form[data-submitting]').forEach((form) => {
        delete form.dataset.submitting;
        form.querySelectorAll('[aria-busy]').forEach((button) => button.removeAttribute('aria-busy'));
    });
});
