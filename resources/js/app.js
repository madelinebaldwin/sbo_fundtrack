import Chart from 'chart.js/auto';

Chart.defaults.font.family = "'Poppins', Arial, sans-serif";
Chart.defaults.color = '#5f5d66';

document.querySelectorAll('[data-password-toggle]').forEach((toggle) => {
    toggle.addEventListener('click', () => {
        const input = document.getElementById(toggle.dataset.passwordToggle);

        if (!input) {
            return;
        }

        const isVisible = input.type === 'text';
        input.type = isVisible ? 'password' : 'text';
        toggle.setAttribute('aria-pressed', String(!isVisible));
        toggle.setAttribute('aria-label', isVisible ? 'Show password' : 'Hide password');
    });
});

document.querySelectorAll('[data-expense-calculator]').forEach((form) => {
    const quantityInput = form.querySelector('[name="quantity"]');
    const unitPriceInput = form.querySelector('[name="unit_price"]');
    const amountInput = form.querySelector('[name="amount"]');

    if (!quantityInput || !unitPriceInput || !amountInput) {
        return;
    }

    const updateAmount = () => {
        const hasUnitPrice = unitPriceInput.value.trim() !== '';

        amountInput.readOnly = hasUnitPrice;
        amountInput.classList.toggle('calculated-amount--readonly', hasUnitPrice);

        if (!hasUnitPrice) {
            return;
        }

        const quantity = Number.parseFloat(quantityInput.value);
        const unitPrice = Number.parseFloat(unitPriceInput.value);
        const amount = quantity * unitPrice;

        amountInput.value = Number.isFinite(amount) && quantity > 0 && unitPrice > 0
            ? amount.toFixed(2)
            : '';
    };

    quantityInput.addEventListener('input', updateAmount);
    unitPriceInput.addEventListener('input', updateAmount);
    updateAmount();
});

const eventStartDate = document.querySelector('[data-event-start-date]');
const eventEndDate = document.querySelector('[data-event-end-date]');

if (eventStartDate && eventEndDate) {
    const updateEventEndDateMinimum = () => {
        const earliestEndDate = eventStartDate.value && eventStartDate.value > eventStartDate.min
            ? eventStartDate.value
            : eventStartDate.min;

        eventEndDate.min = earliestEndDate;
    };

    eventStartDate.addEventListener('change', updateEventEndDateMinimum);
    updateEventEndDateMinimum();
}

const academicYearPicker = document.querySelector('[data-academic-year-picker]');

if (academicYearPicker) {
    const academicYearInput = academicYearPicker.querySelector('#event-academic-year');
    const calendar = academicYearPicker.querySelector('[data-year-picker-calendar]');
    const trigger = academicYearPicker.querySelector('[data-year-picker-trigger]');
    const rangeLabel = academicYearPicker.querySelector('[data-year-picker-range]');
    const options = academicYearPicker.querySelector('[data-year-picker-options]');
    const previousButton = academicYearPicker.querySelector('[data-year-picker-previous]');
    const nextButton = academicYearPicker.querySelector('[data-year-picker-next]');
    const academicYearMatch = academicYearInput?.value.match(/^(\d{4})-(\d{4})$/);
    let decadeStart = academicYearMatch ? Math.floor(Number(academicYearMatch[1]) / 10) * 10 : Math.floor(new Date().getFullYear() / 10) * 10;

    const renderAcademicYearOptions = () => {
        const lastYearInRange = Math.min(decadeStart + 9, 2200);
        rangeLabel.textContent = decadeStart === lastYearInRange
            ? String(decadeStart)
            : `${decadeStart}–${lastYearInRange}`;
        options.replaceChildren();

        for (let year = decadeStart; year <= lastYearInRange; year += 1) {
            const option = document.createElement('button');
            option.className = 'event-create-year-option';
            option.type = 'button';
            option.dataset.year = String(year);
            option.textContent = `${year}-${year + 1}`;
            option.addEventListener('click', () => {
                academicYearInput.value = option.textContent;
                calendar.hidden = true;
                trigger.setAttribute('aria-expanded', 'false');
                trigger.focus();
            });
            options.append(option);
        }

        previousButton.disabled = decadeStart <= 2000;
        nextButton.disabled = decadeStart >= 2200;
    };

    const setDecade = (offset) => {
        decadeStart = Math.max(2000, Math.min(2200, decadeStart + offset));
        decadeStart = Math.floor(decadeStart / 10) * 10;
        renderAcademicYearOptions();
    };

    trigger.addEventListener('click', () => {
        calendar.hidden = !calendar.hidden;
        trigger.setAttribute('aria-expanded', String(!calendar.hidden));
        if (!calendar.hidden) {
            renderAcademicYearOptions();
        }
    });
    previousButton.addEventListener('click', () => setDecade(-10));
    nextButton.addEventListener('click', () => setDecade(10));

    document.addEventListener('click', (event) => {
        if (!academicYearPicker.contains(event.target)) {
            calendar.hidden = true;
            trigger.setAttribute('aria-expanded', 'false');
        }
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !calendar.hidden) {
            calendar.hidden = true;
            trigger.setAttribute('aria-expanded', 'false');
            trigger.focus();
        }
    });
}

const passwordStrength = document.querySelector('[data-password-strength]');

if (passwordStrength) {
    const passwordInput = document.getElementById('password');
    const strengthTrack = passwordStrength.querySelector('[role="progressbar"]');
    const strengthBar = passwordStrength.querySelector('[data-password-strength-bar]');
    const strengthLabel = passwordStrength.querySelector('[data-password-strength-label]');

    passwordInput?.addEventListener('input', () => {
        const password = passwordInput.value;

        if (password.length === 0) {
            strengthTrack.setAttribute('aria-valuenow', '0');
            strengthTrack.setAttribute('aria-valuetext', 'No password entered');
            strengthBar.style.width = '0%';
            passwordStrength.dataset.level = 'empty';
            strengthLabel.textContent = 'Enter a password to check its strength.';

            return;
        }

        const score = [
            password.length >= 8,
            password.length >= 12,
            /[a-z]/.test(password),
            /[A-Z]/.test(password),
            /\d/.test(password),
            /[^A-Za-z0-9]/.test(password),
        ].filter(Boolean).length;
        const hasStrongMix = password.length >= 12
            && /[a-z]/.test(password)
            && /[A-Z]/.test(password)
            && /\d/.test(password)
            && /[^A-Za-z0-9]/.test(password);
        const level = hasStrongMix ? 'strong' : score <= 2 ? 'weak' : 'fair';
        const label = level[0].toUpperCase() + level.slice(1);

        strengthTrack.setAttribute('aria-valuenow', String(score));
        strengthTrack.setAttribute('aria-valuetext', `${label} password`);
        strengthBar.style.width = `${(score / 6) * 100}%`;
        passwordStrength.dataset.level = level;
        strengthLabel.textContent = `Password strength: ${label}`;
    });
}

const sidebar = document.querySelector('.app-sidebar');
const openSidebarButton = document.querySelector('[data-sidebar-open]');

const closeSidebar = () => {
    document.body.classList.remove('sidebar-open');
    openSidebarButton?.setAttribute('aria-expanded', 'false');
};

openSidebarButton?.addEventListener('click', () => {
    document.body.classList.add('sidebar-open');
    openSidebarButton.setAttribute('aria-expanded', 'true');
});

document.querySelectorAll('[data-sidebar-close], .primary-nav a').forEach((element) => {
    element.addEventListener('click', closeSidebar);
});

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
        closeSidebar();
    }
});

document.querySelectorAll('[data-confirm]').forEach((element) => {
    element.addEventListener('click', (event) => {
        if (!window.confirm(element.dataset.confirm)) {
            event.preventDefault();
            event.stopPropagation();
        }
    });
});

const currency = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' });
const chartColors = ['#36a2eb', '#ff6384', '#ff9f40', '#ffcd56', '#4bc0c0', '#9966ff', '#6c8ebf'];

document.querySelectorAll('canvas[data-fundtrack-chart]').forEach((canvas) => {
    const chartType = canvas.dataset.fundtrackChart;
    const values = JSON.parse(canvas.dataset.chart);

    if (chartType === 'bar') {
        new Chart(canvas, {
            type: 'bar',
            data: {
                labels: values.labels,
                datasets: [
                    { label: 'Contributions', data: values.contributions, backgroundColor: '#6c8ebf', borderRadius: 3 },
                    { label: 'Expenses', data: values.expenses, backgroundColor: '#f4513b', borderRadius: 3 },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: { tooltip: { callbacks: { label: (context) => `${context.dataset.label}: ${currency.format(context.parsed.y || 0)}` } } },
                scales: {
                    y: { beginAtZero: true, ticks: { callback: (value) => currency.format(value) } },
                    x: { grid: { display: false } },
                },
            },
        });
    }

    if (chartType === 'doughnut') {
        const labels = values.map((item) => item.label ?? item.event_name);
        const amounts = values.map((item) => Number(item.amount));
        const total = amounts.reduce((sum, amount) => sum + amount, 0);

        new Chart(canvas, {
            type: 'doughnut',
            data: {
                labels,
                datasets: [{ data: amounts, backgroundColor: chartColors, borderColor: '#e7e7e7', borderWidth: 2, hoverOffset: 5 }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '55%',
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            usePointStyle: false,
                            boxWidth: 16,
                            generateLabels(chart) {
                                const dataset = chart.data.datasets[0];

                                return chart.data.labels.map((label, index) => {
                                    const value = Number(dataset.data[index]);
                                    const percent = total ? Math.round((value / total) * 100) : 0;

                                    return {
                                        text: `${label}  ${percent}%`,
                                        fillStyle: dataset.backgroundColor[index],
                                        strokeStyle: dataset.borderColor,
                                        lineWidth: dataset.borderWidth,
                                        hidden: false,
                                        index,
                                    };
                                });
                            },
                        },
                    },
                    tooltip: {
                        callbacks: {
                            label: (context) => {
                                const amount = Number(context.parsed);
                                const percent = total ? ((amount / total) * 100).toFixed(1) : '0.0';

                                return `${context.label}: ${currency.format(amount)} (${percent}%)`;
                            },
                        },
                    },
                },
            },
        });
    }
});

document.querySelectorAll('form.auth-form').forEach((form) => {
    form.addEventListener('submit', () => {
        const submitButton = form.querySelector('button[type="submit"]');

        if (submitButton) {
            submitButton.disabled = true;
            submitButton.dataset.originalLabel = submitButton.textContent;
            submitButton.textContent = 'Please wait…';
        }
    });
});

const signatureList = document.querySelector('[data-signature-list]');
const addSignatureButton = document.querySelector('[data-signature-add]');

const reindexSignatures = () => {
    signatureList?.querySelectorAll('[data-signature-row]').forEach((row, index) => {
        row.querySelectorAll('input').forEach((input) => {
            input.name = input.name.replace(/signatures\[\d+\]/, `signatures[${index}]`);
        });
    });
};

addSignatureButton?.addEventListener('click', () => {
    const firstRow = signatureList?.querySelector('[data-signature-row]');

    if (!firstRow || !signatureList) {
        return;
    }

    if (signatureList.querySelectorAll('[data-signature-row]').length >= 8) {
        return;
    }

    const row = firstRow.cloneNode(true);
    const rowIndex = signatureList.querySelectorAll('[data-signature-row]').length;
    row.querySelectorAll('input').forEach((input) => {
        input.value = '';
        input.name = input.name.replace(/signatures\[\d+\]/, `signatures[${rowIndex}]`);
    });
    signatureList.append(row);
});

signatureList?.addEventListener('click', (event) => {
    const removeButton = event.target.closest('[data-signature-remove]');

    if (!removeButton) {
        return;
    }

    const rows = signatureList.querySelectorAll('[data-signature-row]');
    if (rows.length > 1) {
        removeButton.closest('[data-signature-row]').remove();
        reindexSignatures();
        return;
    }

    rows[0].querySelectorAll('input').forEach((input) => {
        input.value = '';
    });
});
