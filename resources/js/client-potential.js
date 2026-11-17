import axios from 'axios';

export function initializePotentialCalculator(root = document, http = axios) {
    const form = root.querySelector('[data-potential-form]');
    if (!form || form.dataset.initialized === 'true') return;
    form.dataset.initialized = 'true';
    const planet = form.elements.namedItem('planet');
    const button = form.querySelector('[type="submit"]');
    const label = button.textContent;
    const output = root.querySelector('[data-potential-result]');
    const empty = root.querySelector('[data-potential-empty]');
    const errorMessage = form.querySelector('[data-potential-error]');
    const format = new Intl.NumberFormat('vi-VN', { maximumFractionDigits: 2 });
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
        errorMessage.hidden = true;
        errorMessage.textContent = '';
        output.hidden = true;
        empty.hidden = false;
        empty.textContent = 'Đang tính tiềm năng…';
        try {
            const { data } = await http.post(form.action, new FormData(form), {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (data?.status !== true || !data.data?.breakdown) throw new Error('Invalid calculation response');
            const result = data.data;
            output.querySelector('[data-potential-total]').textContent = format.format(result.total);
            output.querySelector('[data-potential-planet]').textContent = result.planet;
            output.querySelector('[data-potential-level]').textContent = result.level || 'Chưa đạt Tân Binh';
            for (const field of ['hp', 'ki', 'attack', 'armor', 'critical']) {
                output.querySelector(`[data-potential-cost="${field}"]`).textContent = format.format(result.breakdown[field]);
            }
            empty.hidden = true;
            output.hidden = false;
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
            errorMessage.textContent =
                messages.join('\n') || ([422, 503].includes(status) && typeof data?.message === 'string' ? data.message : fallback);
            errorMessage.hidden = false;
            empty.textContent = 'Chưa có kết quả. Kiểm tra thông tin rồi thử lại.';
        } finally {
            pending = false;
            button.disabled = false;
            button.textContent = label;
            form.removeAttribute('aria-busy');
        }
    });
}
