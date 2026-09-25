const dialogTriggers = new WeakMap();
let pdfJsPromise;

function loadPdfJs() {
    if (!pdfJsPromise) {
        pdfJsPromise = Promise.all([
            import('pdfjs-dist'),
            import('pdfjs-dist/build/pdf.worker.min.mjs?url'),
        ]).then(([pdfJs, worker]) => {
            pdfJs.GlobalWorkerOptions.workerSrc = worker.default;
            return pdfJs;
        });
    }

    return pdfJsPromise;
}

async function renderPdfPreview(dialog) {
    const viewer = dialog.querySelector('[data-pdf-preview]');
    if (!viewer || ['loading', 'ready'].includes(viewer.dataset.pdfState)) return;

    viewer.dataset.pdfState = 'loading';
    viewer.setAttribute('aria-busy', 'true');

    try {
        const [pdfJs, response] = await Promise.all([
            loadPdfJs(),
            fetch(viewer.dataset.pdfUrl, { credentials: 'same-origin' }),
        ]);
        if (!response.ok) throw new Error(`PDF preview failed with status ${response.status}`);

        const pdf = await pdfJs.getDocument({ data: await response.arrayBuffer() }).promise;
        const pages = document.createDocumentFragment();

        for (let pageNumber = 1; pageNumber <= pdf.numPages; pageNumber += 1) {
            const page = await pdf.getPage(pageNumber);
            const viewport = page.getViewport({ scale: 2 });
            const canvas = document.createElement('canvas');
            const context = canvas.getContext('2d');
            canvas.className = 'pdf-preview-page';
            canvas.width = Math.ceil(viewport.width);
            canvas.height = Math.ceil(viewport.height);
            canvas.setAttribute('aria-label', `หน้า ${pageNumber} จาก ${pdf.numPages}`);
            await page.render({ canvasContext: context, viewport }).promise;
            pages.append(canvas);
        }

        viewer.replaceChildren(pages);
        viewer.dataset.pdfState = 'ready';
    } catch {
        const message = document.createElement('p');
        message.className = 'pdf-preview-status';
        message.textContent = 'ไม่สามารถแสดงตัวอย่างเอกสารได้ กรุณาลองใหม่อีกครั้ง';
        viewer.replaceChildren(message);
        viewer.dataset.pdfState = 'error';
    } finally {
        viewer.removeAttribute('aria-busy');
    }
}

function openDialog(id, trigger) {
    const dialog = document.getElementById(id);
    if (!(dialog instanceof HTMLDialogElement) || dialog.open) return;
    dialogTriggers.set(dialog, trigger ?? document.activeElement);
    dialog.showModal();
    document.documentElement.classList.add('has-open-dialog');
    renderPdfPreview(dialog);
}

function resetUploadDialog(dialog) {
    const form = dialog.querySelector('form[id^="upload-form-"]');
    if (!form) return;

    form.reset();
    const input = form.querySelector('input[type="file"]');
    input?.setCustomValidity('');
    input?.dispatchEvent(new Event('change'));

    const step1 = form.querySelector('[id^="upload-step1-"]');
    const step2 = form.querySelector('[id^="upload-step2-"]');
    const cancelStep = form.querySelector('[id^="upload-cancel-step-"]');
    if (step1) step1.hidden = false;
    if (step2) step2.hidden = true;
    if (cancelStep) cancelStep.hidden = true;
}

function resetCourseIdDialog(dialog) {
    const form = dialog.querySelector('form[id^="course-id-form-"]');
    if (!form) return;

    form.reset();
    const step1 = form.querySelector('[id^="course-id-step1-"]');
    const step2 = form.querySelector('[id^="course-id-step2-"]');
    if (step1) step1.hidden = false;
    if (step2) step2.hidden = true;
}

document.addEventListener('click', (event) => {
    const opener = event.target.closest('[data-open-dialog]');
    if (opener) {
        event.preventDefault();
        openDialog(opener.dataset.openDialog, opener);
    }
    const closer = event.target.closest('[data-close-dialog]');
    if (closer) closer.closest('dialog')?.close();
});

document.addEventListener('click', async (event) => {
    const downloadButton = event.target.closest('[data-request-pdf-download], [data-approved-pdf-download]');
    if (!downloadButton) return;

    event.preventDefault();
    if (downloadButton.getAttribute('aria-busy') === 'true') return;

    downloadButton.setAttribute('aria-busy', 'true');
    try {
        const response = await fetch(downloadButton.href, { credentials: 'same-origin' });
        if (!response.ok) throw new Error(`PDF download failed with status ${response.status}`);

        const blobUrl = URL.createObjectURL(await response.blob());
        const disposition = response.headers.get('content-disposition') ?? '';
        const encodedName = disposition.match(/filename\*=UTF-8''([^;]+)/i)?.[1];
        const plainName = disposition.match(/filename="?([^";]+)"?/i)?.[1];
        const temporaryLink = document.createElement('a');
        temporaryLink.href = blobUrl;
        temporaryLink.download = encodedName ? decodeURIComponent(encodedName) : (plainName ?? 'request.pdf');
        document.body.append(temporaryLink);
        temporaryLink.click();
        temporaryLink.remove();
        window.setTimeout(() => URL.revokeObjectURL(blobUrl), 0);

        downloadButton.hidden = true;
        const nextAction = downloadButton.hasAttribute('data-approved-pdf-download')
            ? downloadButton.parentElement?.querySelector('[data-course-id-action]')
            : downloadButton.parentElement?.querySelector('[data-request-upload]');
        if (nextAction) {
            nextAction.hidden = false;
            nextAction.focus();
        }
    } catch {
        window.location.assign(downloadButton.href);
    } finally {
        downloadButton.removeAttribute('aria-busy');
    }
});

document.querySelector('[data-finish-request]')?.addEventListener('click', (event) => {
    const fileInput = document.querySelector('input[name="signed_document"]');
    if (fileInput?.files.length && !fileInput.checkValidity()) {
        fileInput.reportValidity();
        return;
    }

    const dialog = document.getElementById('finish-dialog');
    const title = document.getElementById('finish-dialog-title');
    const message = dialog?.querySelector('[data-finish-dialog-text]');
    const confirmLabel = dialog?.querySelector('[data-finish-confirm-label]');
    const hasSignedDocument = Boolean(fileInput?.files.length);

    if (title) title.textContent = hasSignedDocument ? 'ยืนยันการส่งคำร้อง' : 'ยืนยันการบันทึกคำร้อง';
    if (message) {
        message.innerHTML = hasSignedDocument
            ? 'ระบบจะส่งคำร้องให้เจ้าหน้าที่ตรวจสอบ<br>คุณต้องการยืนยันการส่งใช่หรือไม่?'
            : 'คุณต้องการบันทึกคำร้องนี้ใช่หรือไม่?<br>หลังจากบันทึกแล้ว <span class="finish-dialog__mobile-line">ระบบจะนำคุณไปยังหน้ารายการคำร้อง</span><br class="finish-dialog__desktop-break">เพื่อรอการอัปโหลดเอกสารที่ลงนามแล้วในภายหลัง';
    }
    if (confirmLabel) confirmLabel.textContent = hasSignedDocument ? 'ยืนยันการส่ง' : 'ยืนยันการบันทึก';

    openDialog('finish-dialog', event.currentTarget);
});

document.querySelectorAll('dialog').forEach((dialog) => {
    dialog.addEventListener('close', () => {
        resetUploadDialog(dialog);
        resetCourseIdDialog(dialog);
        if (!document.querySelector('dialog[open]')) {
            document.documentElement.classList.remove('has-open-dialog');
        }
        dialogTriggers.get(dialog)?.focus();
    });
    if (dialog.hasAttribute('data-auto-open')) openDialog(dialog.id);
});

document.addEventListener('click', (event) => {
    const rosterConfirmButton = event.target.closest('[data-roster-confirm]');
    if (rosterConfirmButton) {
        const requestId = rosterConfirmButton.dataset.rosterConfirm;
        const input = document.getElementById(`student-roster-file-${requestId}`);
        if (!input?.files.length) {
            input?.setCustomValidity('กรุณาเลือกไฟล์รายชื่อผู้เรียน');
            input?.reportValidity();
            return;
        }
        if (!input.checkValidity()) {
            input.reportValidity();
            return;
        }

        input.setCustomValidity('');
        openDialog(rosterConfirmButton.dataset.confirmDialog, rosterConfirmButton);
        return;
    }

    const cancelButton = event.target.closest('[data-upload-cancel]');
    if (cancelButton) {
        const requestId = cancelButton.dataset.uploadCancel;
        document.getElementById(`upload-step1-${requestId}`).hidden = true;
        document.getElementById(`upload-cancel-step-${requestId}`).hidden = false;
        return;
    }

    const cancelBackButton = event.target.closest('[data-upload-cancel-back]');
    if (cancelBackButton) {
        const requestId = cancelBackButton.dataset.uploadCancelBack;
        document.getElementById(`upload-step1-${requestId}`).hidden = false;
        document.getElementById(`upload-cancel-step-${requestId}`).hidden = true;
        return;
    }

    const cancelConfirmButton = event.target.closest('[data-upload-cancel-confirm]');
    if (cancelConfirmButton) {
        cancelConfirmButton.closest('dialog')?.close();
        return;
    }

    const nextButton = event.target.closest('[data-upload-next]');
    if (nextButton) {
        const requestId = nextButton.dataset.uploadNext;
        const input = document.getElementById(`signed-${requestId}`);
        if (!input?.files.length) {
            input?.setCustomValidity('กรุณาเลือกไฟล์ PDF');
            input?.reportValidity();
            return;
        }
        if (!input.checkValidity()) {
            input.reportValidity();
            return;
        }

        input.setCustomValidity('');
        if (nextButton.dataset.confirmDialog) {
            openDialog(nextButton.dataset.confirmDialog, nextButton);
            return;
        }
        document.getElementById(`upload-step1-${requestId}`).hidden = true;
        document.getElementById(`upload-step2-${requestId}`).hidden = false;
        return;
    }

    const backButton = event.target.closest('[data-upload-back]');
    if (backButton) {
        const requestId = backButton.dataset.uploadBack;
        document.getElementById(`upload-step1-${requestId}`).hidden = false;
        document.getElementById(`upload-step2-${requestId}`).hidden = true;
        return;
    }

    const courseIdNext = event.target.closest('[data-course-id-next]');
    if (courseIdNext) {
        const requestId = courseIdNext.dataset.courseIdNext;
        const input = document.getElementById(`course-id-${requestId}`);
        if (!input?.checkValidity()) {
            input?.reportValidity();
            return;
        }
        const step1 = document.getElementById(`course-id-step1-${requestId}`);
        const step2 = document.getElementById(`course-id-step2-${requestId}`);
        step2.querySelector('[data-course-id-preview]').textContent = input.value;
        step1.hidden = true;
        step2.hidden = false;
        return;
    }

    const courseIdBack = event.target.closest('[data-course-id-back]');
    if (courseIdBack) {
        const requestId = courseIdBack.dataset.courseIdBack;
        document.getElementById(`course-id-step1-${requestId}`).hidden = false;
        document.getElementById(`course-id-step2-${requestId}`).hidden = true;
        document.getElementById(`course-id-${requestId}`)?.focus();
        return;
    }

    const courseIdCancel = event.target.closest('[data-course-id-cancel]');
    if (courseIdCancel) {
        const requestId = courseIdCancel.dataset.courseIdCancel;
        document.getElementById(`course-id-step1-${requestId}`).hidden = true;
        document.getElementById(`course-id-step2-${requestId}`).hidden = true;
        document.getElementById(`course-id-step3-${requestId}`).hidden = false;
        return;
    }

    const courseIdCancelBack = event.target.closest('[data-course-id-cancel-back]');
    if (courseIdCancelBack) {
        const requestId = courseIdCancelBack.dataset.courseIdCancelBack;
        document.getElementById(`course-id-step1-${requestId}`).hidden = false;
        document.getElementById(`course-id-step3-${requestId}`).hidden = true;
        document.getElementById(`course-id-${requestId}`)?.focus();
        return;
    }

    const courseIdCancelConfirm = event.target.closest('[data-course-id-cancel-confirm]');
    if (courseIdCancelConfirm) {
        const requestId = courseIdCancelConfirm.dataset.courseIdCancelConfirm;
        const input = document.getElementById(`course-id-${requestId}`);
        if (input) input.value = '';
        document.getElementById(`course-id-step1-${requestId}`).hidden = false;
        document.getElementById(`course-id-step3-${requestId}`).hidden = true;
        document.getElementById(`course-id-${requestId}`)?.focus();
        document.getElementById(`course-${requestId}`)?.close();
    }
});

document.querySelector('[data-review-submit]')?.addEventListener('click', (event) => {
    const choice = document.querySelector('input[name="review_choice"]:checked')?.value;
    openDialog(choice === 'return' ? 'return-dialog' : 'review-dialog', event.currentTarget);
});

document.querySelectorAll('input[name="review_choice"]').forEach((input) => {
    input.addEventListener('change', () => {
        const btn = document.querySelector('[data-review-submit]');
        if (btn) {
            btn.disabled = false;
            btn.textContent = input.value === 'return'
                ? 'ส่งกลับให้ผู้ยื่นคำร้องแก้ไข' : 'ส่งต่อผู้มีอำนาจพิจารณา';
        }
    });
});

document.querySelectorAll('[data-conditional]').forEach((conditional) => {
    const controls = conditional.matches('input, select, textarea')
        ? [conditional]
        : [...conditional.querySelectorAll('input, select, textarea')];
    const radios = document.querySelectorAll(`input[name="${conditional.dataset.conditional}"]`);
    function update() {
        const selected = [...radios].find((radio) => radio.checked)?.value;
        conditional.hidden = selected !== conditional.dataset.when;
        controls.forEach((control) => {
            control.required = !conditional.hidden;
            control.disabled = conditional.hidden;
        });
    }
    radios.forEach((radio) => radio.addEventListener('change', update));
    update();
});

const activityDetails = document.querySelector('[data-activity-details]');
if (activityDetails) {
    const learningOptions = document.querySelectorAll('input[name="learning"]');
    const activityInputs = activityDetails.querySelectorAll('input');
    function updateActivityDetails() {
        const selected = [...learningOptions].find((radio) => radio.checked)?.value;
        const isPhaseBased = selected === 'เปิดแบบตามวงรอบ (Phase/Batch-based)';
        const isEventBased = selected === 'แบบเปิดตามกรอบระยะเวลาของโครงการ (Event / Project-based)';
        activityInputs.forEach((input) => {
            if (isEventBased) input.value = '';
            input.required = isPhaseBased;
            input.disabled = isEventBased;
            const requiredIndicator = input.closest('.form-label')?.querySelector('[data-required-indicator]');
            if (requiredIndicator) requiredIndicator.hidden = !isPhaseBased;
        });
    }
    learningOptions.forEach((radio) => radio.addEventListener('change', updateActivityDetails));
    updateActivityDetails();
}

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

const instructorList = document.querySelector('[data-instructor-list]');
function renumberInstructors() {
    instructorList.querySelectorAll('[data-instructor]').forEach((row, index) => {
        row.querySelector('[data-instructor-title]').textContent = `อาจารย์ผู้สอนคนที่ ${index + 1}`;
        row.querySelectorAll('input').forEach((input) => {
            const label = input.closest('label');
            input.name = input.name.replace(/instructors\[\d+\]/, `instructors[${index}]`);
            input.id = `field-${input.name.replaceAll('[', '-').replaceAll(']', '')}`;
            label.htmlFor = input.id;
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
    function update(event) {
        const file = input.files[0];
        input.setCustomValidity('');
        if (!file || event?.detail?.source === 'signature-pad') {
            filename.innerHTML = '';
            zone.classList.remove('has-file');
            return;
        }
        const allowed = input.accept.split(',').map((extension) => extension.trim());
        if (!allowed.some((extension) => file.name.toLowerCase().endsWith(extension))) {
            input.setCustomValidity('ประเภทไฟล์ไม่ถูกต้อง กรุณาเลือกไฟล์ที่รองรับ');
        } else if (file.size > 10 * 1024 * 1024) {
            input.setCustomValidity('ไฟล์ต้องมีขนาดไม่เกิน 10 MB');
        }
        const text = input.validationMessage || `${file.name} (${(file.size / 1024 / 1024).toFixed(2)} MB)`;
        const iconUrl = document.querySelector('.portal-logo')?.src.replace('logo.png', 'icons/file-text.svg') || '/img/icons/file-text.svg';
        filename.innerHTML = `
            <img src="${iconUrl}" alt="" class="portal-icon file-upload__file-icon">
            <span class="file-upload__file-row">
                <span class="file-upload__filename-text">${text}</span>
                <button type="button" class="file-upload__remove" aria-label="ยกเลิกการเลือกไฟล์">×</button>
            </span>
        `;
        zone.classList.add('has-file');
    }
    input.addEventListener('change', update);
    filename.addEventListener('click', (event) => {
        if (event.target.closest('.file-upload__remove')) {
            input.value = '';
            update();
        }
    });
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

document.querySelectorAll('[data-signature-pad]').forEach((signaturePad) => {
    const canvas = signaturePad.querySelector('[data-signature-canvas]');
    const clearButton = signaturePad.querySelector('[data-signature-clear]');
    const status = signaturePad.querySelector('[data-signature-status]');
    const dialog = signaturePad.closest('dialog');
    const dialogTitle = dialog?.querySelector('.modal-heading h2');
    const form = signaturePad.closest('form');
    const signatureContext = form?.dataset.signatureContext;
    const isApprovalSignature = signatureContext === 'approve';
    const isRejectionSignature = signatureContext === 'reject';
    const entry = form?.querySelector('[data-signature-entry]');
    const confirmStep = form?.querySelector('[data-signature-confirm]');
    const confirmMessage = form?.querySelector('[data-signature-confirm-message]');
    const confirmAction = form?.querySelector('[data-signature-confirm-action]');
    const confirmBackButton = form?.querySelector('[data-signature-confirm-back]');
    const sendButton = form?.querySelector('[data-signature-send]');
    const cancelButton = form?.querySelector('[data-signature-cancel]');
    const reason = form?.querySelector('textarea[name="reason"]');
    const input = dialog?.querySelector('input[name="signature_file"]');
    const context = canvas.getContext('2d');
    const drawnFilename = 'signature-drawn.png';
    let drawing = false;
    let hasSignature = false;
    let syncingDrawnFile = false;
    let exportVersion = 0;
    let confirmationMode = null;

    context.strokeStyle = '#111827';
    context.lineWidth = 5;
    context.lineCap = 'round';
    context.lineJoin = 'round';

    function pointFromEvent(event) {
        const bounds = canvas.getBoundingClientRect();
        return {
            x: (event.clientX - bounds.left) * (canvas.width / bounds.width),
            y: (event.clientY - bounds.top) * (canvas.height / bounds.height),
        };
    }

    function updatePadState(signed) {
        hasSignature = signed;
        clearButton.disabled = !signed;
        status.textContent = signed ? 'ลายเซ็นพร้อมส่งแล้ว' : 'ใช้เมาส์หรือปลายนิ้ววาดภายในกรอบ';
        // If drawing exists, disable file upload zone
        const dropzone = input?.closest('[data-dropzone]');
        if (dropzone) {
            dropzone.style.opacity = signed ? '0.4' : '';
            dropzone.style.pointerEvents = signed ? 'none' : '';
        }
    }

    function updateUploadState(hasFile) {
        // If file uploaded (not drawn), disable canvas
        const isDrawnFile = input?.files[0]?.name === drawnFilename;
        const uploadedExternal = hasFile && !isDrawnFile;
        canvas.style.opacity = uploadedExternal ? '0.4' : '';
        canvas.style.pointerEvents = uploadedExternal ? 'none' : '';
        canvas.style.cursor = uploadedExternal ? 'not-allowed' : '';
        clearButton.disabled = uploadedExternal ? true : !hasSignature;
        if (uploadedExternal) {
            status.textContent = 'ปิดใช้งาน: กำลังใช้ไฟล์ที่อัปโหลด';
        } else if (!hasSignature) {
            status.textContent = 'ใช้เมาส์หรือปลายนิ้ววาดภายในกรอบ';
        }
    }

    function clearCanvas(clearDrawnFile = true) {
        exportVersion += 1;
        drawing = false;
        context.clearRect(0, 0, canvas.width, canvas.height);
        updatePadState(false);

        if (clearDrawnFile && input?.files[0]?.name === drawnFilename) {
            input.value = '';
            input.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }

    function syncCanvasToFile() {
        const version = ++exportVersion;
        canvas.toBlob((blob) => {
            if (!blob || !hasSignature || version !== exportVersion || !input) return;
            const transfer = new DataTransfer();
            transfer.items.add(new File([blob], drawnFilename, { type: 'image/png', lastModified: Date.now() }));
            syncingDrawnFile = true;
            input.files = transfer.files;
            input.dispatchEvent(new CustomEvent('change', { bubbles: true, detail: { source: 'signature-pad' } }));
            syncingDrawnFile = false;
        }, 'image/png');
    }

    canvas.addEventListener('pointerdown', (event) => {
        if (event.pointerType === 'mouse' && event.button !== 0) return;
        event.preventDefault();
        exportVersion += 1;
        canvas.setPointerCapture(event.pointerId);
        drawing = true;
        const point = pointFromEvent(event);
        context.beginPath();
        context.moveTo(point.x, point.y);
        context.lineTo(point.x + 0.01, point.y + 0.01);
        context.stroke();
        updatePadState(true);
    });

    canvas.addEventListener('pointermove', (event) => {
        if (!drawing) return;
        event.preventDefault();
        const point = pointFromEvent(event);
        context.lineTo(point.x, point.y);
        context.stroke();
    });

    function finishDrawing(event) {
        if (!drawing) return;
        event.preventDefault();
        drawing = false;
        syncCanvasToFile();
    }

    canvas.addEventListener('pointerup', finishDrawing);
    canvas.addEventListener('pointercancel', finishDrawing);
    canvas.addEventListener('contextmenu', (event) => event.preventDefault());
    clearButton.addEventListener('click', () => clearCanvas());

    input?.addEventListener('change', () => {
        if (syncingDrawnFile) return;
        if (input.files[0]?.name !== drawnFilename) clearCanvas(false);
        updateUploadState(input.files.length > 0);
    });

    input?.closest('[data-dropzone]')?.addEventListener('click', (event) => {
        if (event.target.closest('.file-upload__remove')) {
            if (input.files[0]?.name === drawnFilename) {
                clearCanvas(false);
            }
            // Use setTimeout so the input files are cleared before we check
            setTimeout(() => updateUploadState(input.files.length > 0), 0);
        }
    }, true);

    function showEntry() {
        confirmationMode = null;
        if (dialogTitle) dialogTitle.textContent = isApprovalSignature
            ? 'ยืนยันการอนุมัติ'
            : (isRejectionSignature ? 'ยืนยันไม่อนุมัติการสร้างรายวิชา' : 'ยืนยันการส่งต่อเอกสาร');
        if (entry) entry.hidden = false;
        if (confirmStep) confirmStep.hidden = true;
    }

    function showConfirmation(mode) {
        confirmationMode = mode;
        if (dialogTitle) {
            dialogTitle.textContent = mode === 'submit'
                ? (isApprovalSignature
                    ? 'ยืนยันการอนุมัติ'
                    : (isRejectionSignature ? 'ยืนยันไม่อนุมัติการสร้างรายวิชา' : 'ยืนยันการส่งต่อเอกสาร'))
                : 'ยืนยันการยกเลิก';
        }
        if (entry) entry.hidden = true;
        if (confirmStep) confirmStep.hidden = false;
        if (confirmMessage) {
            confirmMessage.textContent = mode === 'submit'
                ? (isApprovalSignature
                    ? 'ระบบจะอนุมัติเอกสารพร้อมลายเซ็นของคุณ คุณต้องการยืนยันการอนุมัติใช่หรือไม่?'
                    : (isRejectionSignature
                        ? 'ระบบจะบันทึกผลไม่อนุมัติพร้อมเหตุผลและลายเซ็นของคุณ คุณต้องการยืนยันไม่อนุมัติใช่หรือไม่?'
                        : 'ระบบจะส่งเอกสารพร้อมลายเซ็นให้ผู้มีอำนาจพิจารณา คุณต้องการยืนยันการส่งต่อใช่หรือไม่?'))
                : `คุณต้องการยกเลิกใช่หรือไม่? ${isRejectionSignature ? 'เหตุผลและ' : ''}ลายเซ็นที่วาดหรือไฟล์ที่เลือกจะถูกล้าง`;
        }
        if (confirmAction) {
            confirmAction.textContent = mode === 'submit'
                ? (isApprovalSignature ? 'ยืนยันอนุมัติ' : (isRejectionSignature ? 'ยืนยันไม่อนุมัติ' : 'ยืนยันส่งต่อ'))
                : 'ยืนยันยกเลิก';
        }
        confirmAction?.focus();
    }

    sendButton?.addEventListener('click', () => {
        if (reason && !reason.checkValidity()) {
            reason.reportValidity();
            return;
        }
        if (!input?.checkValidity()) {
            input?.reportValidity();
            return;
        }
        showConfirmation('submit');
    });

    cancelButton?.addEventListener('click', () => showConfirmation('cancel'));
    confirmBackButton?.addEventListener('click', showEntry);
    confirmAction?.addEventListener('click', () => {
        if (confirmationMode === 'submit') {
            form?.requestSubmit();
            return;
        }
        if (confirmationMode !== 'cancel') return;
        form.reset();
        input?.setCustomValidity('');
        input?.dispatchEvent(new Event('change', { bubbles: true }));
        clearCanvas();
        updateUploadState(false);
        showEntry();
        dialog?.close();
    });

    dialog?.addEventListener('close', () => {
        showEntry();
        clearCanvas();
        updateUploadState(false);
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
                error.textContent = 'รองรับเฉพาะไฟล์ PDF, DOC, DOCX, XLS, XLSX และ CSV';
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
    const pagination = requestList.querySelector('[data-pagination]');
    const pageNumbers = pagination.querySelector('[data-page-numbers]');
    const previousPage = pagination.querySelector('[data-page-prev]');
    const nextPage = pagination.querySelector('[data-page-next]');
    const pageInput = pagination.querySelector('[data-page-input]');
    const goToPage = pagination.querySelector('[data-page-go]');
    const mobilePagination = window.matchMedia('(max-width: 600px)');
    let currentPage = 1;
    let rosterOnly = false;
    let rosterUpdatesOnly = false;

    function pageSize() {
        return mobilePagination.matches ? 5 : 10;
    }

    function updatePageInputWidth() {
        const minimumWidth = mobilePagination.matches ? 34 : 36;
        const digits = Math.max(pageInput.value.length, 1);
        pageInput.style.setProperty('--page-input-width', `${minimumWidth + ((digits - 1) * 8)}px`);
    }

    function matchesFilters(row) {
        const term = search.value.trim().toLocaleLowerCase('th');
        const now = new Date();
        const submitted = new Date(`${row.dataset.date}T00:00:00`);
        const sameYear = submitted.getFullYear() === now.getFullYear();
        const matchesDate = !date.value || (sameYear && (date.value === 'year' || submitted.getMonth() === now.getMonth()));
        const contentToSearch = row.dataset.searchContent || row.textContent;

        return contentToSearch.toLocaleLowerCase('th').includes(term)
            && (!status.value || status.value.split(',').includes(row.dataset.status))
            && (!rosterOnly || row.dataset.missingRoster === 'true')
            && (!rosterUpdatesOnly || row.dataset.rosterUpdate === 'true')
            && matchesDate;
    }

    function paginationItems(totalPages) {
        if (mobilePagination.matches && totalPages > 5) {
            if (currentPage <= 3) return [1, 2, 3, 'ellipsis', totalPages];
            if (currentPage >= totalPages - 2) return [1, 'ellipsis', totalPages - 2, totalPages - 1, totalPages];

            return [1, 'ellipsis', currentPage, 'ellipsis', totalPages];
        }
        if (totalPages <= 7) return Array.from({ length: totalPages }, (_, index) => index + 1);
        if (currentPage <= 4) return [1, 2, 3, 4, 5, 'ellipsis', totalPages];
        if (currentPage >= totalPages - 3) return [1, 'ellipsis', totalPages - 4, totalPages - 3, totalPages - 2, totalPages - 1, totalPages];

        return [1, 'ellipsis', currentPage - 1, currentPage, currentPage + 1, 'ellipsis', totalPages];
    }

    function renderPagination(totalPages) {
        pageNumbers.replaceChildren();
        paginationItems(totalPages).forEach((item) => {
            if (item === 'ellipsis') {
                const ellipsis = document.createElement('span');
                ellipsis.className = 'request-pagination__ellipsis';
                ellipsis.textContent = '…';
                ellipsis.setAttribute('aria-hidden', 'true');
                pageNumbers.append(ellipsis);
                return;
            }

            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'request-pagination__button';
            button.dataset.page = String(item);
            button.textContent = String(item);
            button.setAttribute('aria-label', `หน้าที่ ${item}`);
            if (item === currentPage) {
                button.classList.add('is-active');
                button.setAttribute('aria-current', 'page');
            }
            pageNumbers.append(button);
        });

        previousPage.disabled = currentPage === 1;
        nextPage.disabled = currentPage === totalPages;
        pageInput.max = String(totalPages);
        pageInput.value = String(currentPage);
        updatePageInputWidth();
    }

    function renderRows() {
        const filteredRows = rows.filter(matchesFilters);
        const size = pageSize();
        const totalPages = Math.max(1, Math.ceil(filteredRows.length / size));
        currentPage = Math.min(Math.max(currentPage, 1), totalPages);
        const firstRow = (currentPage - 1) * size;
        const visibleRows = new Set(filteredRows.slice(firstRow, firstRow + size));

        rows.forEach((row) => {
            row.hidden = !visibleRows.has(row);
            const defaultActions = row.querySelector('[data-default-actions]');
            const rosterAction = row.querySelector('[data-roster-action]');
            if (defaultActions) defaultActions.hidden = rosterOnly && Boolean(rosterAction);
            if (rosterAction) rosterAction.hidden = !rosterOnly;
        });
        const count = filteredRows.length;
        requestList.querySelector('[data-empty-row]').hidden = count !== 0;
        pagination.hidden = count <= size;
        renderPagination(totalPages);
        requestList.querySelector('[data-filter-count]').textContent = `พบ ${count} คำร้อง หน้า ${currentPage} จาก ${totalPages}`;
    }

    function filterRows() {
        currentPage = 1;
        renderRows();
    }

    function selectPage(page) {
        const totalPages = Math.max(1, Math.ceil(rows.filter(matchesFilters).length / pageSize()));
        currentPage = Math.min(Math.max(Number(page) || 1, 1), totalPages);
        renderRows();
    }

    function runStandardFilter() {
        rosterOnly = false;
        rosterUpdatesOnly = false;
        filterRows();
    }

    search.addEventListener('input', runStandardFilter);
    status.addEventListener('change', runStandardFilter);
    date.addEventListener('change', runStandardFilter);
    previousPage.addEventListener('click', () => selectPage(currentPage - 1));
    nextPage.addEventListener('click', () => selectPage(currentPage + 1));
    pageNumbers.addEventListener('click', (event) => {
        const button = event.target.closest('[data-page]');
        if (button) selectPage(button.dataset.page);
    });
    goToPage.addEventListener('click', () => selectPage(pageInput.value));
    pageInput.addEventListener('input', () => {
        pageInput.value = pageInput.value.replace(/\D/g, '');
        updatePageInputWidth();
    });
    pageInput.addEventListener('blur', () => {
        if (!pageInput.value) pageInput.value = String(currentPage);
        updatePageInputWidth();
    });
    pageInput.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            selectPage(pageInput.value);
        }
    });
    mobilePagination.addEventListener('change', filterRows);
    requestList.querySelector('[data-filter-pending]')?.addEventListener('click', () => {
        rosterOnly = false;
        rosterUpdatesOnly = false;
        status.value = 'PENDING_SIGNED_DOCUMENT';
        date.value = '';
        search.value = '';
        filterRows();
    });
    requestList.querySelector('[data-filter-roster]')?.addEventListener('click', () => {
        rosterOnly = true;
        rosterUpdatesOnly = false;
        status.value = '';
        date.value = '';
        search.value = '';
        filterRows();
    });
    requestList.querySelector('[data-filter-roster-updates]')?.addEventListener('click', () => {
        rosterOnly = false;
        rosterUpdatesOnly = true;
        status.value = '';
        date.value = '';
        search.value = '';
        filterRows();
    });
    requestList.querySelector('[data-clear-filter]')?.addEventListener('click', () => {
        rosterOnly = false;
        rosterUpdatesOnly = false;
        status.value = '';
        date.value = '';
        search.value = '';
        filterRows();
    });
    renderRows();
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

const returnDialog = document.getElementById('return-dialog');
if (returnDialog) {
    const entry = returnDialog.querySelector('[data-return-entry]');
    const confirmStep = returnDialog.querySelector('[data-return-confirm]');
    const confirmMessage = returnDialog.querySelector('[data-return-confirm-message]');
    const confirmAction = returnDialog.querySelector('[data-return-confirm-action]');
    const confirmBack = returnDialog.querySelector('[data-return-confirm-back]');
    const sendButton = returnDialog.querySelector('[data-return-send]');
    const cancelButton = returnDialog.querySelector('[data-return-cancel]');
    const form = returnDialog.querySelector('form');
    const textarea = form?.querySelector('textarea[name="reason"]');
    let confirmationMode = null;

    function showEntry() {
        confirmationMode = null;
        const h2 = returnDialog.querySelector('.modal-heading h2');
        if (h2) h2.textContent = 'ยืนยันส่งกลับแก้ไข';
        if (entry) entry.hidden = false;
        if (confirmStep) confirmStep.hidden = true;
    }

    function showConfirmation(mode) {
        confirmationMode = mode;
        const h2 = returnDialog.querySelector('.modal-heading h2');
        if (h2) {
            h2.textContent = mode === 'submit' ? 'ยืนยันส่งกลับแก้ไข' : 'ยืนยันการยกเลิก';
        }
        if (entry) entry.hidden = true;
        if (confirmStep) confirmStep.hidden = false;
        if (confirmMessage) {
            confirmMessage.innerHTML = mode === 'submit'
                ? 'ระบบจะส่งคำร้องกลับไปยังผู้ยื่นคำร้องพร้อมเหตุผลที่ระบุไว้<br>คุณต้องการยืนยันการส่งกลับแก้ไขใช่หรือไม่?'
                : 'คุณต้องการยกเลิกการส่งกลับแก้ไขใช่หรือไม่?<br>เหตุผลที่ระบุไว้จะถูกลบออกทั้งหมด';
        }
        if (confirmAction) {
            confirmAction.textContent = mode === 'submit' ? 'ยืนยันส่งกลับแก้ไข' : 'ยืนยันยกเลิก';
            confirmAction.className = mode === 'submit' ? 'portal-button portal-button--danger' : 'portal-button';
        }
        confirmAction?.focus();
    }

    sendButton?.addEventListener('click', () => {
        if (!textarea?.checkValidity()) {
            textarea?.reportValidity();
            return;
        }
        showConfirmation('submit');
    });

    cancelButton?.addEventListener('click', () => showConfirmation('cancel'));
    confirmBack?.addEventListener('click', showEntry);

    confirmAction?.addEventListener('click', () => {
        if (confirmationMode === 'submit') {
            form?.requestSubmit();
            return;
        }
        if (confirmationMode === 'cancel') {
            form?.reset();
            showEntry();
            returnDialog.close();
        }
    });

    returnDialog.addEventListener('close', showEntry);
}
