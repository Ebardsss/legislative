'use strict';

document.addEventListener('DOMContentLoaded', () => {
    const modalElement =
        document.getElementById(
            'publicSubmissionModal'
        );

    const modal =
        modalElement &&
        typeof bootstrap !== 'undefined'
            ? new bootstrap.Modal(
                modalElement
            )
            : null;

    const form =
        document.getElementById(
            'publicSubmissionForm'
        );

    document.querySelectorAll(
        '[data-open-public-form]'
    ).forEach((button) => {
        button.addEventListener('click', () => {
            form?.reset();

            setText(
                'publicSubmissionTitle',
                button.dataset.title ||
                'Citizen Submission'
            );

            setValue(
                'publicSubmissionType',
                button.dataset.openPublicForm ||
                'feedback'
            );

            modal?.show();
        });
    });

    form?.addEventListener(
        'submit',
        (event) => {
            event.preventDefault();

            const subject =
                document.getElementById(
                    'publicSubject'
                )?.value.trim() || '';

            const type =
                document.getElementById(
                    'publicSubmissionType'
                )?.value || 'feedback';

            const previewReference =
                `PREVIEW-${type.toUpperCase()}-${Date.now()
                    .toString()
                    .slice(-6)}`;

            showDialog({
                icon: 'success',
                title: 'Submission Preview Complete',
                html: `
                    <div class="text-start small">
                        <p>
                            <strong>Preview reference:</strong>
                            ${escapeHtml(previewReference)}
                        </p>

                        <p>
                            <strong>Subject:</strong>
                            ${escapeHtml(subject)}
                        </p>

                        <p class="text-muted">
                            This navigation package does not save
                            submissions. Database storage, email
                            acknowledgement, reference generation,
                            file upload, and tracking will be added
                            during backend development.
                        </p>
                    </div>
                `
            }).then(() => {
                modal?.hide();
            });
        }
    );

    document.getElementById(
        'publicTrackForm'
    )?.addEventListener(
        'submit',
        (event) => {
            event.preventDefault();

            document.getElementById(
                'publicTrackResult'
            )?.classList.remove(
                'd-none'
            );
        }
    );

    function setText(id, value) {
        const element =
            document.getElementById(id);

        if (element) {
            element.textContent =
                String(value ?? '');
        }
    }

    function setValue(id, value) {
        const element =
            document.getElementById(id);

        if (element) {
            element.value =
                String(value ?? '');
        }
    }

    function escapeHtml(value) {
        const element =
            document.createElement('div');

        element.textContent =
            String(value ?? '');

        return element.innerHTML;
    }

    function showDialog(options) {
        if (typeof Swal !== 'undefined') {
            return Swal.fire(options);
        }

        window.alert(
            options.title ||
            'Submission preview complete.'
        );

        return Promise.resolve();
    }
});
