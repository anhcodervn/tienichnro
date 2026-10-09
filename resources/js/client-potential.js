import axios from 'axios';
import Swal from 'sweetalert2';

export function potentialResultText(result) {
    const format = new Intl.NumberFormat('vi-VN', { maximumFractionDigits: 2 });
    return [
        `Hành tinh: ${result.planet}`,
        ...Object.entries({ hp: 'HP', ki: 'KI', attack: 'Sức đánh', armor: 'Giáp', critical: 'Chí mạng' }).map(
            ([key, label]) => `${label}: ${format.format(result.breakdown[key])} tiềm năng`,
        ),
        '',
        `Tổng tiềm năng đã nâng: ${format.format(result.total)}`,
        `Cấp độ: ${result.level || 'Chưa đạt Tân Binh'}`,
    ].join('\n');
}

export function initializePotentialCalculator(root = document, http = axios, alerts = Swal) {
    const form = root.querySelector('[data-potential-form]');
    if (!form || form.dataset.initialized === 'true') return;
    form.dataset.initialized = 'true';
    const planet = form.elements.namedItem('planet');
    const button = form.querySelector('[type="submit"]');
    const label = button.textContent;
    let pending = false;

    planet.addEventListener('change', () => {
        const base = planet.selectedOptions[0]?.dataset;
        for (const field of ['hp', 'ki', 'attack']) {
            form.elements.namedItem(field).min = base?.[field] || (field === 'attack' ? 12 : 100);
        }
        form.querySelector('[data-potential-base]').textContent = planet.value
            ? `Chỉ số tối thiểu: HP ${base.hp}, KI ${base.ki}, sức đánh ${base.attack}. Giáp và chí mạng từ 0.`
            : 'Chọn hành tinh để xem chỉ số gốc tối thiểu.';
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (pending || !form.reportValidity()) return;
        pending = true;
        button.disabled = true;
        button.textContent = 'Đang tính…';
        form.setAttribute('aria-busy', 'true');
        try {
            const { data } = await http.post(form.action, new FormData(form), {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (data?.status !== true || !data.data?.breakdown) throw new Error('Invalid calculation response');
            await alerts.fire({
                icon: 'success',
                titleText: 'Kết quả tính tiềm năng',
                text: potentialResultText(data.data),
                confirmButtonText: 'Đã hiểu',
                confirmButtonColor: '#059669',
                heightAuto: false,
                customClass: { popup: 'client-swal-popup' },
            });
        } catch (error) {
            const status = error.response?.status;
            const data = error.response?.data;
            const messages = Object.values(data?.errors || {})
                .flat()
                .filter((value) => typeof value === 'string');
            const fallback =
                status === 419
                    ? 'Phiên làm việc đã hết hạn. Vui lòng tải lại trang.'
                    : status === 429
                      ? 'Bạn thao tác quá nhiều lần. Vui lòng đợi một lúc rồi thử lại.'
                      : 'Không thể tính tiềm năng. Vui lòng kiểm tra kết nối rồi thử lại.';
            await alerts.fire({
                icon: 'error',
                titleText: 'Chưa thể kiểm tra',
                text: messages.join('\n') || ([422, 503].includes(status) && typeof data?.message === 'string' ? data.message : fallback),
                confirmButtonText: 'Đã hiểu',
                confirmButtonColor: '#059669',
                heightAuto: false,
                customClass: { popup: 'client-swal-popup' },
            });
        } finally {
            pending = false;
            button.disabled = false;
            button.textContent = label;
            form.removeAttribute('aria-busy');
        }
    });
}
