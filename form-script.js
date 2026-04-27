/**
 * Form Script - Client-side validation and helpers only.
 * No AJAX, no step navigation. PHP controls step via ?step=X.
 */

document.addEventListener('DOMContentLoaded', function() {
    setupEventListeners();
    applyReadOnlyModeIfSubmitted();
    toggleApplicantTypeFields();
});

function applyReadOnlyModeIfSubmitted() {
    const isSubmitted    = Boolean(window.APP_CONTEXT?.isSubmitted);
    const isReuploadMode = Boolean(window.APP_CONTEXT?.isReuploadMode);

    // KEY FIX: skip read-only mode entirely when re-uploading docs
    if (!isSubmitted || isReuploadMode) return;

    document.querySelectorAll('form.form-step-wrapper input, form.form-step-wrapper select, form.form-step-wrapper textarea, form.form-step-wrapper button')
        .forEach(el => {
            if (el.closest('#successMessage')) return;
            if (el.tagName === 'BUTTON' && (el.classList.contains('btn-save') || el.type === 'submit')) {
                el.style.display = 'none';
                return;
            }
            if (el.type === 'checkbox' || el.type === 'radio') el.disabled = true;
            else if (el.type === 'file') el.disabled = true;
            else el.readOnly = true;
        });

    document.querySelectorAll('a.btn-edit').forEach(a => a.style.display = 'none');
}

function setupEventListeners() {
    document.getElementById('dateOfBirth')?.addEventListener('change', calculateAge);
    document.getElementById('sameAsPermanent')?.addEventListener('change', togglePermanentAddress);
    togglePermanentAddress();

    document.querySelectorAll('input[name="applicantType"]').forEach(radio => {
        radio.addEventListener('change', toggleApplicantTypeFields);
    });

    document.getElementById('scholarship')?.addEventListener('change', function() {
        document.getElementById('scholarshipDetails').style.display = this.checked ? 'block' : 'none';
    });
    document.getElementById('pwd')?.addEventListener('change', function() {
        document.getElementById('pwdDetails').style.display = this.checked ? 'block' : 'none';
    });

    setupFileUploads();

    // Client-side validation on form submit
    document.querySelectorAll('form.form-step-wrapper').forEach(form => {
        form.addEventListener('submit', function(e) {
            const isReuploadMode = Boolean(window.APP_CONTEXT?.isReuploadMode);

            // In reupload mode: skip validation entirely, let PHP handle it
            if (isReuploadMode) return true;

            if (!validateForm(form)) {
                e.preventDefault();
                alert('Please fill in all required fields before proceeding.');
                return false;
            }
        });
    });
}

function calculateAge() {
    const birthDateInput = document.getElementById('dateOfBirth')?.value;
    if (!birthDateInput) {
        const ageEl = document.getElementById('age');
        if (ageEl) ageEl.value = '';
        return;
    }
    const birthDate = new Date(birthDateInput);
    const today = new Date();
    if (birthDate > today) {
        alert('Birth date cannot be in the future!');
        document.getElementById('dateOfBirth').value = '';
        document.getElementById('age').value = '';
        return;
    }
    let age = today.getFullYear() - birthDate.getFullYear();
    const monthDiff = today.getMonth() - birthDate.getMonth();
    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) age--;
    if (age < 0) age = 0;
    const ageEl = document.getElementById('age');
    if (ageEl) ageEl.value = age;
}

function togglePermanentAddress() {
    const checkbox = document.getElementById('sameAsPermanent');
    const permanentFields = document.getElementById('permanentAddressFields');
    if (!checkbox || !permanentFields) return;
    const permInputs = permanentFields.querySelectorAll('[required]');
    if (checkbox.checked) {
        permanentFields.style.display = 'none';
        const curr = v => document.getElementById(v)?.value || '';
        document.getElementById('permanentHouseStreet').value = curr('currentHouseStreet');
        document.getElementById('permanentBarangay').value = curr('currentBarangay');
        document.getElementById('permanentCity').value = curr('currentCity');
        document.getElementById('permanentProvince').value = curr('currentProvince');
        document.getElementById('permanentZipCode').value = curr('currentZipCode');
        permInputs.forEach(el => el.removeAttribute('required'));
    } else {
        permanentFields.style.display = 'block';
        permInputs.forEach(el => el.setAttribute('required', 'required'));
    }
}

function toggleApplicantTypeFields() {
    const applicantType = document.querySelector('input[name="applicantType"]:checked')?.value;
    const freshmenFields = document.getElementById('freshmenFields');
    const transfereeFields = document.getElementById('transfereeFields');
    const transfereeDocuments = document.getElementById('transfereeDocuments');
    const gradesLabel = document.getElementById('gradesLabel');
    const gradesInput = document.getElementById('grades');

    if (!freshmenFields || !transfereeFields) return;

    const freshmenInputs = freshmenFields?.querySelectorAll('[required]') || [];
    const transfereeInputs = transfereeFields?.querySelectorAll('[required]') || [];
    const transferCred = document.getElementById('transferCred');
    const tor = document.getElementById('tor');

    if (applicantType === 'Freshmen') {
        freshmenFields.style.display = 'block';
        transfereeFields.style.display = 'none';
        if (transfereeDocuments) transfereeDocuments.style.display = 'none';
        if (gradesLabel) gradesLabel.innerHTML = 'Form 138 / Report Card (Grade 12) <span class="required">*</span>';
        if (gradesInput) gradesInput.setAttribute('required', 'required');
        freshmenInputs.forEach(el => el.setAttribute('required', 'required'));
        transfereeInputs.forEach(el => el.removeAttribute('required'));
        if (transferCred) transferCred.removeAttribute('required');
        if (tor) tor.removeAttribute('required');
    } else {
        freshmenFields.style.display = 'none';
        transfereeFields.style.display = 'block';
        if (transfereeDocuments) transfereeDocuments.style.display = 'block';
        if (gradesLabel) gradesLabel.innerHTML = 'Latest Grade Report (optional, if available)';
        if (gradesInput) gradesInput.removeAttribute('required');
        freshmenInputs.forEach(el => el.removeAttribute('required'));
        transfereeInputs.forEach(el => el.setAttribute('required', 'required'));
        if (transferCred) transferCred.setAttribute('required', 'required');
        if (tor) tor.setAttribute('required', 'required');
    }
}

function validateForm(form) {
    const stepEl = form.querySelector('.form-step');
    if (!stepEl) return true;

    const required = stepEl.querySelectorAll('[required]');
    const processedRadio = new Set();
    let valid = true;

    required.forEach(field => {
        if (field.type === 'radio') {
            if (processedRadio.has(field.name)) return;
            processedRadio.add(field.name);
            const radios = stepEl.querySelectorAll(`input[type="radio"][name="${field.name}"]`);
            const visible = radios.filter(r => r.offsetParent !== null);
            if (visible.length === 0) return;
            const checked = visible.some(r => r.checked);
            if (!checked) { valid = false; visible.forEach(r => r.classList.add('error')); }
            else visible.forEach(r => r.classList.remove('error'));
            return;
        }

        const anchor = field.type === 'file' ? (field.closest('.file-upload-wrapper') || field) : field;
        if (anchor.offsetParent === null) return;

        let hasValue = false;
        if (field.type === 'checkbox') hasValue = field.checked;
        else if (field.type === 'radio') hasValue = field.checked;
        else hasValue = String(field.value || '').trim() !== '';

        if (!hasValue) {
            valid = false;
            field.classList.add('error');
            field.closest('.form-group')?.classList.add('has-error');
        } else {
            field.classList.remove('error');
            field.closest('.form-group')?.classList.remove('has-error');
        }
    });

    return valid;
}

function setupFileUploads() {
    ['idPhoto', 'grades', 'birthCert', 'transferCred', 'tor'].forEach(id => {
        const input = document.getElementById(id);
        if (input) input.addEventListener('change', function() { handleFileUpload(this); });
    });
}

function handleFileUpload(input) {
    const file = input.files[0];
    if (!file) return;
    const maxSize = 10 * 1024 * 1024;
    if (file.size > maxSize) {
        alert('File size exceeds 10MB. Please upload a smaller file.');
        input.value = '';
        return;
    }
    const preview = document.getElementById(`${input.id}Preview`);
    const fileName = document.getElementById(`${input.id}Name`);
    const fileSize = document.getElementById(`${input.id}Size`);
    if (preview && fileName && fileSize) {
        fileName.textContent = file.name;
        fileSize.textContent = formatFileSize(file.size);
        preview.classList.add('active');
    }
}

function removeFile(inputId) {
    const input = document.getElementById(inputId);
    const preview = document.getElementById(`${inputId}Preview`);
    if (input) input.value = '';
    if (preview) preview.classList.remove('active');
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024, sizes = ['Bytes', 'KB', 'MB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
}