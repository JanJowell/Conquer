import { validateCourse } from './course-map-data.js';

(() => {
    if (window.__eventReviewSubmitInit) return;
    window.__eventReviewSubmitInit = true;

    const lifecycle = new WeakMap();
    // All form-level guards (including venue validation) run before this.
    document.addEventListener('submit', (event) => lifecycle.get(event.target)?.finalize(event));
    window.addEventListener('pageshow', (event) => {
        if (event.persisted) document.querySelectorAll('[data-event-setup-form]').forEach((form) => lifecycle.get(form)?.restore());
    });
    document.addEventListener('livewire:navigating', () => {
        document.querySelectorAll('[data-event-setup-form]').forEach((form) => lifecycle.get(form)?.close());
    });

    const initialize = () => document.querySelectorAll('[data-event-setup-form]').forEach((form) => {
        if (form.dataset.eventReviewSubmitBound) return;
        form.dataset.eventReviewSubmitBound = 'true';
        const payment = form.querySelector('[data-payment-requirement]');
        const summary = form.querySelector('[data-event-client-errors]');
        const serverSummary = form.querySelector('[data-event-errors]');
        const dialog = form.querySelector('[data-event-review-confirmation]');
        const primary = form.querySelector('[name="intent"][value="submit"]');
        const confirmButton = dialog?.querySelector('[data-event-review-confirm]');
        const cancelButton = dialog?.querySelector('[data-event-review-cancel]');
        const primaryText = primary?.textContent;
        const previousBusy = form.getAttribute('aria-busy');
        const busyButtons = new Map();
        const painted = new Map();
        let attempted = false;
        let nextError = 0;
        let pendingSubmitter = null;
        let confirmedSubmitter = null;
        let acceptedEvent = null;
        let submitting = false;

        const available = (field) => field && field.isConnected && !field.matches(':disabled')
            && field.type !== 'hidden' && !field.readOnly && field.getClientRects().length > 0;
        const rows = () => [...form.querySelectorAll('[data-category-list] > [data-category-row]')];
        const mapControls = (row) => [...row.querySelectorAll('[name$="[checkpoint_map_image_upload]"], [data-course-map-value]')];
        const mapGroup = (row) => row?.querySelector('[data-category-map-requirement]');
        const mapTarget = (row) => mapControls(row).find(available) || mapGroup(row);
        const mapSatisfied = (row) => {
            if (mapGroup(row)?.dataset.savedMapImage === 'true') return true;
            if (mapControls(row).some((field) => !field.matches(':disabled') && field.type === 'file' && field.files.length)) return true;
            const course = row.querySelector('[data-course-map-value]:not(:disabled)');
            if (!course?.value.trim()) return false;
            // This input contains the saved/applied route, never the editor's
            // unreviewed draft. Use the editor's own format and geometry rules.
            try { validateCourse(JSON.parse(course.value)); return true; }
            catch { return false; }
        };
        const paid = () => rows().some((row) =>
            Number(row.querySelector('[name$="[price_amount]"]')?.value || 0) > 0
            && row.querySelector('[name$="[status]"]')?.value === 'open');
        const refreshPayment = () => {
            if (!payment) return;
            const required = paid();
            payment.querySelector('[data-requirement-symbol]').textContent = required ? '*' : '(Optional)';
            payment.querySelector('[data-requirement-label]').textContent = required ? 'Required' : 'Optional';
            payment.dataset.fieldRequirement = required ? 'required' : 'optional';
            payment.classList.toggle('text-rose-600', required);
            payment.classList.toggle('text-slate-500', !required);
        };

        // Follow the same rendered markers as the labels, including cloned rows.
        // Do not add native constraints: Save Draft and no-JS requests retain
        // their existing server validation, and inactive panels stay ignored.
        const requiredFields = () => {
            const fields = new Set();
            form.querySelectorAll('[data-field-requirement="required"]').forEach((marker) => {
                if (marker.closest('[data-category-map-requirement]')) return; // Image OR drawn course, not both.
                const label = marker.closest('label');
                if (!label) return; // Payment options are validated as a group.
                const sibling = label.nextElementSibling;
                const controls = label.control ? [label.control]
                    : (sibling?.matches('input, select, textarea') ? [sibling]
                        : [...(sibling?.querySelectorAll('input, select, textarea') || [])]);
                controls.filter((field) => form.contains(field) && available(field)
                    && field.dataset.reviewSatisfied !== 'true').forEach((field) => fields.add(field));
            });
            form.querySelectorAll('input, select, textarea').forEach((field) => {
                if (available(field) && field.required) fields.add(field);
                if (fields.has(field)) {
                    field.setAttribute('aria-required', 'true');
                    field.dataset.eventReviewRequired = 'true';
                } else if (field.dataset.eventReviewRequired) {
                    field.removeAttribute('aria-required');
                    delete field.dataset.eventReviewRequired;
                }
            });
            return fields;
        };
        const fieldContainer = (field) => {
            let container = field.closest('div');
            if (container?.classList.contains('relative')) container = container.parentElement;
            return container && form.contains(container) ? container : field.parentElement;
        };
        const clear = (field) => {
            const error = painted.get(field);
            if (!error) return;
            field.classList.remove('event-field-invalid');
            if (error.previousInvalid === null) field.removeAttribute('aria-invalid');
            else field.setAttribute('aria-invalid', error.previousInvalid);
            const described = (field.getAttribute('aria-describedby') || '').split(/\s+/).filter((id) => id && id !== error.node.id);
            if (described.length) field.setAttribute('aria-describedby', described.join(' '));
            else field.removeAttribute('aria-describedby');
            error.node.remove();
            painted.delete(field);
        };
        const show = (field, issue) => {
            let error = painted.get(field);
            if (!error) {
                const node = document.createElement('p');
                node.id = 'event-inline-error-' + (++nextError);
                node.className = 'mt-2 col-span-full text-xs text-rose-600 [overflow-wrap:anywhere]';
                node.dataset.eventInlineError = 'true';
                error = { node, previousInvalid: field.getAttribute('aria-invalid') };
                painted.set(field, error);
                const described = new Set((field.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean));
                described.add(node.id);
                field.setAttribute('aria-describedby', [...described].join(' '));
            }
            field.classList.add('event-field-invalid');
            field.setAttribute('aria-invalid', 'true');
            error.node.textContent = issue.message;
            (issue.container || fieldContainer(field)).appendChild(error.node);
        };

        const serverIssues = [...form.querySelectorAll('[data-event-error-field]')].map((link, index) => {
            const key = link.dataset.eventErrorField;
            const parts = key.split('.');
            const name = parts[0] + parts.slice(1).map((part) => '[' + part + ']').join('');
            const paymentError = key === 'payment_choices' || key.startsWith('payment_choices.');
            const mapError = /^categories\.\d+\.(course_map|checkpoint_map_image_upload)(\.|$)/.test(key);
            const category = () => rows().find((row) => [...row.querySelectorAll('[name]')]
                .some((field) => field.name.startsWith('categories[' + parts[1] + ']')));
            const fields = () => {
                if (paymentError) return [...form.querySelectorAll('[name="payment_choices[]"]')];
                if (key === 'categories') return rows().flatMap((row) => [...row.querySelectorAll('[name$="[status]"]')]);
                if (mapError && category()) return mapControls(category());
                return [...form.elements].filter((field) => field.name === name || field.name === name + '[]');
            };
            link.id = 'event-field-error-' + index;
            const issue = { link, fields, paymentError, mapError, category, active: true, message: link.textContent.trim(), key };
            link.addEventListener('click', () => issue.target?.focus());
            return issue;
        });

        const update = (focus = false) => {
            refreshPayment();
            const required = requiredFields();
            const issues = new Map();
            const add = (field, message, container) => {
                if (available(field) && !issues.has(field)) issues.set(field, { message, container });
            };
            if (attempted) {
                form.querySelectorAll('input, select, textarea').forEach((field) => {
                    if (!available(field)) return;
                    if (required.has(field) && (field.type === 'file' ? !field.files.length : !field.value.trim())) {
                        add(field, 'This field is required.');
                    } else if (field.willValidate && !field.validity.valid) {
                        add(field, field.validationMessage || 'Check this field.');
                    }
                });
                const categoryRows = rows();
                categoryRows.forEach((row) => {
                    const group = mapGroup(row);
                    if (available(group) && mapControls(row).some((field) => !field.matches(':disabled')) && !mapSatisfied(row)) {
                        add(mapTarget(row), 'Upload a map image or review and apply a drawn course for this category.', group);
                    }
                });
                const startDate = form.querySelector('[name="event_date"]');
                const endDate = form.querySelector('[name="event_end_date"]');
                const startTime = form.querySelector('[name="start_time"]');
                const endTime = form.querySelector('[name="end_time"]');
                if ([startDate, endDate, startTime, endTime].every((field) => available(field) && field.value && field.validity.valid)
                    && new Date(endDate.value + 'T' + endTime.value) <= new Date(startDate.value + 'T' + startTime.value)) {
                    add(endTime, 'The event end date and time must be after its start date and time.');
                }
                if (!categoryRows.some((row) => row.querySelector('[name$="[status]"]')?.value === 'open')) {
                    add(categoryRows[0]?.querySelector('[name$="[status]"]') || form.querySelector('[data-add-category]'),
                        'Add at least one open category.', form.querySelector('[data-category-list]'));
                }
                if (paid() && !form.querySelector('[name="payment_choices[]"]:checked:not(:disabled)')) {
                    add(form.querySelector('[name="payment_choices[]"]:not(:disabled)')
                        || form.querySelector('[data-event-payment-selection] a'),
                    'Select a payment option for paid open categories.', form.querySelector('[data-event-payment-selection]'));
                }
            }
            serverIssues.forEach((issue) => {
                const fields = (issue.mapError && issue.category() ? [mapTarget(issue.category())] : issue.fields()).filter(available);
                issue.target = fields[0] || (issue.paymentError ? form.querySelector('[data-event-payment-selection] a')
                    : (issue.key === 'categories' ? form.querySelector('[data-add-category]') : null));
                if (issue.target) {
                    issue.target.id ||= 'event-field-target-' + issue.link.id;
                    issue.link.href = '#' + issue.target.id;
                }
                const item = issue.link.closest('li');
                if (item.hidden === issue.active) item.hidden = !issue.active;
                if (issue.active) fields.forEach((field) => add(field, issue.message,
                    issue.paymentError ? form.querySelector('[data-event-payment-selection]')
                        : (issue.mapError ? mapGroup(issue.category()) : undefined)));
            });
            const noServerErrors = !serverIssues.some((issue) => issue.active);
            if (serverSummary && serverSummary.hidden !== noServerErrors) serverSummary.hidden = noServerErrors;
            for (const field of painted.keys()) if (!issues.has(field)) clear(field);
            issues.forEach((issue, field) => show(field, issue));
            if (summary) {
                const invalid = attempted && issues.size > 0;
                summary.classList.toggle('hidden', !invalid);
                summary.textContent = invalid ? 'Please correct the highlighted fields.' : '';
            }
            const first = [...form.querySelectorAll('input, select, textarea, button, a, [data-category-map-requirement]')].find((field) => issues.has(field));
            if (focus) (first || (serverIssues.some((issue) => issue.active) ? serverSummary : null))?.focus();
            return issues.size === 0;
        };

        const closeConfirmation = (restoreFocus = true) => {
            const returnTo = pendingSubmitter || primary;
            pendingSubmitter = null;
            if (dialog?.open) dialog.close();
            if (restoreFocus && available(returnTo)) returnTo.focus();
        };
        const confirmSubmission = () => {
            const submitter = pendingSubmitter;
            if (submitting || !submitter || submitter.form !== form || submitter.disabled) return;
            closeConfirmation(false);
            attempted = true;
            if (!update(true)) return;
            confirmedSubmitter = submitter;
            try {
                // Re-run native validation and every existing submit guard.
                // Preserve the original submitter so intent=submit is serialized.
                form.requestSubmit(submitter);
            } finally {
                confirmedSubmitter = null;
            }
        };
        const restoreSubmission = () => {
            submitting = false;
            acceptedEvent = confirmedSubmitter = null;
            if (previousBusy === null) form.removeAttribute('aria-busy');
            else form.setAttribute('aria-busy', previousBusy);
            busyButtons.forEach((previous, button) => {
                if (previous === null) button.removeAttribute('aria-disabled');
                else button.setAttribute('aria-disabled', previous);
            });
            busyButtons.clear();
            if (primary) primary.textContent = primaryText;
            if (confirmButton) confirmButton.disabled = false;
        };
        lifecycle.set(form, {
            close: () => closeConfirmation(false),
            restore: () => { closeConfirmation(false); restoreSubmission(); update(); },
            finalize: (event) => {
                if (acceptedEvent !== event) return;
                acceptedEvent = null;
                if (event.defaultPrevented) return;
                submitting = true;
                form.setAttribute('aria-busy', 'true');
                form.querySelectorAll('button[name="intent"]').forEach((button) => {
                    busyButtons.set(button, button.getAttribute('aria-disabled'));
                    // Native disabled would drop intent from the request and
                    // could turn the confirmed review submission into a draft.
                    button.setAttribute('aria-disabled', 'true');
                });
                if (primary) primary.textContent = 'Submitting…';
                if (confirmButton) confirmButton.disabled = true;
            },
        });
        confirmButton?.addEventListener('click', confirmSubmission);
        cancelButton?.addEventListener('click', () => closeConfirmation());
        dialog?.addEventListener('cancel', (event) => {
            event.preventDefault();
            closeConfirmation();
        });
        dialog?.addEventListener('close', () => {
            if (!dialog.open) pendingSubmitter = null;
        });
        dialog?.addEventListener('keydown', (event) => {
            if (event.key !== 'Tab') return;
            const buttons = [...dialog.querySelectorAll('button:not(:disabled)')].filter(available);
            const first = buttons[0];
            const last = buttons.at(-1);
            if (!first) return;
            if (event.shiftKey && (document.activeElement === first || !dialog.contains(document.activeElement))) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && (document.activeElement === last || !dialog.contains(document.activeElement))) {
                event.preventDefault();
                first.focus();
            }
        });

        const changed = (event) => {
            serverIssues.forEach((issue) => {
                if (issue.fields().includes(event.target)) issue.active = false;
            });
            // Existing cutoff/venue/category listeners finish conditional state first.
            queueMicrotask(() => update());
        };
        form.addEventListener('input', changed);
        form.addEventListener('change', changed);
        form.addEventListener('click', (event) => {
            const button = event.target.closest('button');
            if (button?.name === 'intent') {
                if (submitting) { event.preventDefault(); return; }
                if (button.value === 'draft') {
                    attempted = false;
                    update();
                } else {
                    attempted = true;
                    if (!update(true)) event.preventDefault();
                }
            }
            if (event.target.closest('[data-add-category], [data-remove-category]')) queueMicrotask(() => update());
            if (event.target.closest('[data-course-action]')) {
                const course = event.target.closest('[data-course-map-editor]')?.querySelector('[data-course-map-value]');
                const previous = course?.value;
                // The editor handles apply/remove at the document level after
                // this listener. Refresh only its applied value, without
                // bypassing its reviewed/dirty-draft submission protection.
                queueMicrotask(() => {
                    if (course && course.value !== previous) changed({ target: course });
                    else update();
                });
            }
        });
        form.addEventListener('submit', (event) => {
            if (submitting) { event.preventDefault(); return; }
            attempted = event.submitter?.value !== 'draft';
            if (!update(attempted) && attempted) event.preventDefault();
            if (!attempted) { closeConfirmation(false); return; }
            if (event.defaultPrevented) return;
            const submitter = event.submitter || primary;
            if (confirmedSubmitter === submitter && submitter) {
                acceptedEvent = event;
                return;
            }
            event.preventDefault();
            if (dialog?.open || pendingSubmitter || !submitter) return;
            pendingSubmitter = submitter;
            if (typeof dialog?.showModal === 'function') {
                dialog.showModal();
                cancelButton?.focus();
            } else {
                const message = ['#event-review-confirmation-title', '#event-review-confirmation-description']
                    .map((selector) => dialog?.querySelector(selector)?.textContent.trim()).filter(Boolean).join('\n\n');
                // A synchronous confirm returns inside the original submit
                // event. Browsers suppress re-entrant requestSubmit calls.
                if (window.confirm(message || 'Submit this event to Admin for review? Editing will be locked until you withdraw it.')) setTimeout(confirmSubmission, 0);
                else closeConfirmation();
            }
        });
        form.addEventListener('invalid', (event) => {
            if (!attempted) return;
            event.preventDefault();
            queueMicrotask(() => update(true));
        }, true);
        form.addEventListener('reset', () => {
            closeConfirmation(false);
            attempted = false;
            queueMicrotask(() => update());
        });
        // Dependent location selects become enabled asynchronously.
        new MutationObserver(() => update()).observe(form, {
            subtree: true, attributes: true, attributeFilter: ['disabled', 'hidden'],
        });
        update(serverIssues.length > 0);
    });
    document.addEventListener('livewire:navigated', initialize);
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize, { once: true });
    else initialize();
})();
