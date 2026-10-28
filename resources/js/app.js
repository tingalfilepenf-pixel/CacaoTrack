import * as bootstrap from 'bootstrap';
import * as echarts from 'echarts';

window.echarts = echarts;
window.bootstrap = bootstrap;

document.addEventListener('DOMContentLoaded', function () {
    // Translucent toast notifications (session-flash feedback, PRD §19).
    document.querySelectorAll('.toast[data-autoshow]').forEach(function (toastElement) {
        new bootstrap.Toast(toastElement, { autohide: true, delay: 5000 }).show();
    });

    // Sidebar toggle logic
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('mainContent');

    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function (e) {
            e.preventDefault();
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('expanded');
        });
    }

    // Chart resizing
    window.addEventListener('resize', function () {
        const charts = document.querySelectorAll('div[_echarts_instance_]');
        charts.forEach(chartElement => {
            const chart = echarts.getInstanceByDom(chartElement);
            if (chart) {
                chart.resize();
            }
        });
    });

    // Show/hide password toggles (Users Management, Profile).
    const togglePasswordVisibility = function (toggle) {
        const input = toggle.closest('.input-group')?.querySelector('input');
        if (!input) { return; }
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        const icon = toggle.querySelector('i');
        if (icon) {
            icon.classList.toggle('bi-eye', !show);
            icon.classList.toggle('bi-eye-slash', show);
        }
        toggle.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    };

    document.addEventListener('click', function (event) {
        const toggle = event.target.closest('.toggle-password');
        if (toggle) {
            togglePasswordVisibility(toggle);
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Enter' && event.key !== ' ') { return; }
        const toggle = event.target.closest('.toggle-password');
        if (toggle) {
            event.preventDefault();
            togglePasswordVisibility(toggle);
        }
    });

    document.querySelectorAll('form[data-auto-filter]').forEach(function (form) {
        const searchInput = form.querySelector('input[name="search"]');
        let searchTimer = null;
        let submitting = false;

        const submitForm = function () {
            if (submitting) {
                return;
            }

            submitting = true;
            form.submit();
        };

        if (searchInput) {
            searchInput.addEventListener('input', function () {
                window.clearTimeout(searchTimer);
                searchTimer = window.setTimeout(submitForm, 400);
            });
        }

        form.querySelectorAll('select, input[type="date"], input[type="number"]').forEach(function (field) {
            field.addEventListener('change', submitForm);
        });

        form.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' && event.target.tagName !== 'TEXTAREA') {
                event.preventDefault();
                window.clearTimeout(searchTimer);
                submitForm();
            }
        });
    });
});
