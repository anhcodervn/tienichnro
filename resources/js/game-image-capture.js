export const PANEL_FILES = [
    'panel-quest.png',
    'panel-inventory.png',
    'panel-skills.png',
    'panel-pet.png',
    'bag.png',
    'box.png',
    'box-84.png',
    'npc-4-magic-tree.png',
    'npc-50-egg.png',
    'panel-amulets.png',
    'panel-radar.png',
];

export function parseItemIds(value) {
    if (!value.trim()) return [];
    const parts = value.split(',').map((part) => part.trim());
    if (parts.some((part) => !/^\d+$/.test(part))) {
        throw new Error('Nhập các ID nguyên, phân cách bằng dấu phẩy. Ví dụ: 1,5,11,14.');
    }
    const ids = [...new Set(parts.map(Number))];
    const invalid = ids.filter((id) => !Number.isSafeInteger(id) || id < 1 || id > 85);
    if (invalid.length) throw new Error(`Không có item với ID: ${invalid.join(', ')}. Demo kztiger hỗ trợ #1–85.`);
    return ids;
}

const demoRows = {
    'bag.png': {
        height: 1259,
        starts: [
            26, 61, 91, 126, 163, 200, 239, 276, 311, 348, 385, 422, 459, 496, 531, 568, 605, 642, 681, 710, 745, 771, 806, 845, 883, 914, 952, 987,
            1013, 1051, 1088, 1125, 1154, 1189, 1225,
        ],
    },
    'equipment.png': { height: 431, starts: [26, 63, 100, 137, 174, 211, 248, 283, 319, 355, 394] },
    'box.png': { height: 775, starts: [26, 65, 102, 138, 174, 205, 242, 278, 306, 342, 378, 414, 443, 477, 506, 537, 566, 602, 641, 669, 696, 736] },
    'box-84.png': { height: 658, starts: [26, 65, 98, 136, 172, 207, 242, 277, 316, 351, 389, 427, 466, 505, 544, 582, 620] },
};
const indexedRows = new WeakMap();
const rowsFromStarts = (starts, height) => starts.map((y, index) => ({ y, height: (starts[index + 1] ?? height) - y }));

export function captureRowsFromPixels(pixels, height) {
    const badge = (y) => pixels[y * 4] === 57 && pixels[y * 4 + 1] === 49 && pixels[y * 4 + 2] === 37;
    const starts = [];
    for (let y = 26; y + 13 <= height; y++) {
        if (!badge(y) || badge(y - 1)) continue;
        if (Array.from({ length: 13 }, (_, index) => y + index).every(badge)) starts.push(y);
    }
    return rowsFromStarts(starts, height);
}

export function captureItemRanges(images = {}) {
    let start = 1;
    const legacy = images['bag.png']?.naturalHeight === 774;
    return ['bag.png', 'equipment.png', 'box.png', 'box-84.png'].map((file, index) => {
        const image = images[file];
        const profile = demoRows[file];
        const height = image?.naturalHeight ?? (legacy ? 26 + [22, 6, 17, 1][index] * 34 : profile.height);
        const fallback =
            height === profile.height ? profile.starts : Array.from({ length: Math.max(0, Math.floor((height - 26) / 34)) }, (_, i) => 26 + i * 34);
        const rows = (image && indexedRows.get(image)) ?? rowsFromStarts(fallback, height);
        const range = { file, start, end: start + rows.length - 1, rows };
        start = range.end + 1;
        return range;
    });
}

export function itemCrop(id, images = {}) {
    const range = captureItemRanges(images).find((range) => id >= range.start && id <= range.end);
    if (!Number.isInteger(id) || !range) throw new Error('ID không thuộc bộ ảnh hành trang, trang bị hoặc rương.');
    return { file: range.file, x: 0, ...range.rows[id - range.start], width: images[range.file]?.naturalWidth ?? 440 };
}

/** @returns {Promise<HTMLImageElement>} */
export function loadCaptureImage(url) {
    return new Promise((resolve, reject) => {
        const image = new Image();
        image.onload = () => {
            if (/\/(?:bag|equipment|box|box-84)\.png(?:[?#]|$)/.test(url) && typeof document !== 'undefined') {
                try {
                    const canvas = document.createElement('canvas');
                    canvas.width = 1;
                    canvas.height = image.naturalHeight;
                    const context = canvas.getContext('2d');
                    context.drawImage(image, 0, 0, 1, image.naturalHeight, 0, 0, 1, image.naturalHeight);
                    const rows = captureRowsFromPixels(context.getImageData(0, 0, 1, image.naturalHeight).data, image.naturalHeight);
                    if (rows.length) indexedRows.set(image, rows);
                } catch {
                    /* Keep the known capture profile when pixel access is unavailable. */
                }
            }
            resolve(image);
        };
        image.onerror = () => reject(new Error('Không tải được ảnh demo. Kiểm tra lại bộ ảnh capture.'));
        image.src = url;
    });
}
