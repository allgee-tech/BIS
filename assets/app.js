'use strict';

document.addEventListener('DOMContentLoaded', function () {
    var menuButton = document.querySelector('.menu-toggle');
    var navigation = document.getElementById('main-nav');
    if (menuButton && navigation) {
        menuButton.addEventListener('click', function () {
            var open = menuButton.getAttribute('aria-expanded') === 'true';
            menuButton.setAttribute('aria-expanded', String(!open));
            navigation.classList.toggle('is-open', !open);
        });
    }

    var registerForm = document.querySelector('[data-role-toggle]');
    if (registerForm) {
        var role = registerForm.querySelector('#account-role');
        var studentFields = registerForm.querySelector('#student-fields');
        var employerFields = registerForm.querySelector('#employer-fields');
        var currentStep = 1;
        var steps = Array.prototype.slice.call(registerForm.querySelectorAll('[data-auth-step]'));
        var wizardEnabled = registerForm.hasAttribute('data-auth-wizard') && steps.length > 0;
        var progress = registerForm.querySelector('[data-auth-progress]');
        var progressTrack = registerForm.querySelector('.auth-wizard-track');
        var progressBar = registerForm.querySelector('[data-auth-progress-bar]');
        var stepLabel = registerForm.querySelector('[data-auth-step-label]');
        var progressTitle = registerForm.querySelector('[data-auth-progress-title]');
        var stepItems = registerForm.querySelectorAll('.auth-wizard-steps li');
        var controls = registerForm.querySelector('[data-auth-controls]');
        var previousButton = registerForm.querySelector('[data-auth-previous]');
        var nextButton = registerForm.querySelector('[data-auth-next]');
        var createButton = registerForm.querySelector('[data-auth-create]');
        var stepTitles = ['About you', 'Your profile', 'Secure account'];
        var advanceStep = function () {
            var activeStep = steps[currentStep - 1];
            var fields = activeStep.querySelectorAll('input:not(:disabled), select:not(:disabled), textarea:not(:disabled)');
            for (var index = 0; index < fields.length; index += 1) {
                if (!fields[index].checkValidity()) {
                    fields[index].reportValidity();
                    fields[index].focus();
                    return false;
                }
            }
            showStep(Math.min(currentStep + 1, steps.length), true);
            return true;
        };

        var showStep = function (number, focusStep) {
            currentStep = number;
            steps.forEach(function (step) {
                var active = Number(step.getAttribute('data-auth-step')) === currentStep;
                step.hidden = !active;
                step.classList.toggle('is-entering', active);
                step.querySelectorAll('input, select, textarea').forEach(function (field) {
                    var roleGroup = field.closest('.student-fields, .employer-fields');
                    field.disabled = !active || (roleGroup !== null && roleGroup.hidden);
                });
            });

            if (progressTrack) {
                progressTrack.setAttribute('aria-valuenow', String(currentStep));
            }
            if (progressBar) {
                progressBar.style.width = String(currentStep / steps.length * 100) + '%';
            }
            if (stepLabel) {
                stepLabel.textContent = 'Step ' + currentStep + ' of ' + steps.length;
            }
            if (progressTitle) {
                progressTitle.textContent = stepTitles[currentStep - 1];
            }
            stepItems.forEach(function (item, index) {
                if (index + 1 === currentStep) {
                    item.setAttribute('aria-current', 'step');
                } else {
                    item.removeAttribute('aria-current');
                }
                item.classList.toggle('is-complete', index + 1 < currentStep);
            });
            if (previousButton) {
                previousButton.hidden = currentStep === 1;
            }
            if (nextButton) {
                nextButton.hidden = currentStep === steps.length;
            }
            if (createButton) {
                createButton.hidden = currentStep !== steps.length;
            }
            if (focusStep) {
                var firstField = steps[currentStep - 1].querySelector('input:not(:disabled), select:not(:disabled), textarea:not(:disabled)');
                if (firstField) {
                    firstField.focus();
                }
            }
        };

        var updateFields = function () {
            var student = role.value === 'student';
            studentFields.hidden = !student;
            employerFields.hidden = student;
            studentFields.querySelectorAll('input, select, textarea').forEach(function (field) {
                field.disabled = !student;
            });
            employerFields.querySelectorAll('input, select, textarea').forEach(function (field) {
                field.disabled = student;
            });
            if (wizardEnabled) {
                showStep(currentStep, false);
            }
        };
        role.addEventListener('change', updateFields);
        updateFields();

        if (wizardEnabled) {
            registerForm.classList.add('auth-wizard-ready');
            progress.hidden = false;
            controls.hidden = false;
            showStep(1, false);

            nextButton.addEventListener('click', advanceStep);

            previousButton.addEventListener('click', function () {
                showStep(Math.max(currentStep - 1, 1), true);
            });

            registerForm.addEventListener('submit', function (event) {
                if (currentStep < steps.length) {
                    event.preventDefault();
                    advanceStep();
                    return;
                }
                steps.forEach(function (step) {
                    step.querySelectorAll('input, select, textarea').forEach(function (field) {
                        var roleGroup = field.closest('.student-fields, .employer-fields');
                        field.disabled = roleGroup !== null && roleGroup.hidden;
                    });
                });
            });
        }
    }

    document.querySelectorAll('[data-password-toggle]').forEach(function (toggle) {
        toggle.addEventListener('click', function () {
            var input = toggle.parentElement.querySelector('input');
            var visible = input.type === 'password';
            input.type = visible ? 'text' : 'password';
            toggle.textContent = visible ? 'Hide' : 'Show';
            toggle.setAttribute('aria-label', visible ? 'Hide password' : 'Show password');
            toggle.setAttribute('aria-pressed', String(visible));
        });
    });

    document.querySelectorAll('form[data-auth-submit]').forEach(function (form) {
        form.addEventListener('submit', function () {
            var button = form.querySelector('button[type="submit"]:not([hidden])');
            if (!button) {
                return;
            }
            button.disabled = true;
            button.setAttribute('aria-busy', 'true');
            button.dataset.originalLabel = button.textContent;
            button.textContent = form.id === 'register-form' ? 'Creating account...' : 'Signing in...';
        });
    });

    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!window.confirm(form.dataset.confirm)) {
                event.preventDefault();
            }
        });
    });
});
