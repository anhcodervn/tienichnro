export function applyLayers(blocks, layers = {}, order = []) {
    const keys = [
        ...order.filter((key) => blocks.some((block) => block.key === key)),
        ...blocks.map((block) => block.key).filter((key) => !order.includes(key)),
    ];
    return keys.map((key) => {
        const block = blocks.find((item) => item.key === key);
        const layer = layers[key] ?? {};
        const scale = Math.max(0.2, Math.min(3, Number(layer.scale ?? 1)));
        const scaleX = Math.max(0.2, Math.min(3, Number(layer.scale_x ?? scale)));
        const scaleY = Math.max(0.2, Math.min(3, Number(layer.scale_y ?? scale)));
        return {
            ...block,
            label: layer.name?.trim() || block.label,
            originalWidth: block.width,
            originalHeight: block.height,
            width: block.width * scaleX,
            height: block.height * scaleY,
            scale,
            scaleX,
            scaleY,
            rotation: Number(layer.rotation ?? 0),
            opacity: Number(layer.opacity ?? 1),
            hidden: layer.hidden ?? false,
            locked: layer.locked ?? false,
        };
    });
}

export const RESIZE_HANDLES = [
    { id: 'nw', x: -1, y: -1 },
    { id: 'ne', x: 1, y: -1 },
    { id: 'w', x: -1, y: 0 },
    { id: 'e', x: 1, y: 0 },
    { id: 'sw', x: -1, y: 1 },
    { id: 'se', x: 1, y: 1 },
];

export function resizeLayer(block, handle, delta, uniform = false) {
    const angle = ((block.rotation ?? 0) * Math.PI) / 180;
    const dx = delta.x * Math.cos(angle) + delta.y * Math.sin(angle);
    const dy = -delta.x * Math.sin(angle) + delta.y * Math.cos(angle);
    const originalWidth = block.originalWidth ?? block.width;
    const originalHeight = block.originalHeight ?? block.height;
    let width = block.width + handle.x * dx;
    let height = block.height + handle.y * dy;
    if (uniform && handle.x && handle.y) {
        const factor = Math.max(
            Math.max((originalWidth * 0.2) / block.width, (originalHeight * 0.2) / block.height),
            Math.min(
                Math.min((originalWidth * 3) / block.width, (originalHeight * 3) / block.height),
                1 + (block.width * handle.x * dx + block.height * handle.y * dy) / (block.width ** 2 + block.height ** 2),
            ),
        );
        width = block.width * factor;
        height = block.height * factor;
    } else {
        width = Math.max(originalWidth * 0.2, Math.min(originalWidth * 3, width));
        height = Math.max(originalHeight * 0.2, Math.min(originalHeight * 3, height));
    }
    const cx = (handle.x * (width - block.width)) / 2;
    const cy = (handle.y * (height - block.height)) / 2;
    return {
        x: Math.max(0, Math.min(6000, Math.round(block.x + block.width / 2 + cx * Math.cos(angle) - cy * Math.sin(angle) - width / 2))),
        y: Math.max(48, Math.min(6000, Math.round(block.y + block.height / 2 + cx * Math.sin(angle) + cy * Math.cos(angle) - height / 2))),
        scale_x: width / originalWidth,
        scale_y: height / originalHeight,
    };
}

export function reorderLayer(keys, source, target, placement = 'before') {
    if (source === target || !keys.includes(source) || !keys.includes(target)) return keys;
    const visibleOrder = [...keys].reverse().filter((key) => key !== source);
    const index = visibleOrder.indexOf(target) + (placement === 'after' ? 1 : 0);
    visibleOrder.splice(index, 0, source);
    return visibleOrder.reverse();
}

export function createHistory(initial, limit = 30) {
    let entries = [initial];
    let index = 0;
    let toggleIndex = null;
    return {
        get canUndo() {
            return index > 0;
        },
        get canRedo() {
            return index < entries.length - 1;
        },
        commit(value) {
            if (entries[index] === value) return;
            toggleIndex = null;
            entries = entries.slice(0, index + 1);
            entries.push(value);
            if (entries.length > limit) entries.shift();
            index = entries.length - 1;
        },
        undo() {
            toggleIndex = null;
            if (index > 0) index--;
            return entries[index];
        },
        redo() {
            toggleIndex = null;
            if (index < entries.length - 1) index++;
            return entries[index];
        },
        toggle() {
            if (toggleIndex !== null) {
                [index, toggleIndex] = [toggleIndex, index];
            } else if (index > 0) {
                toggleIndex = index;
                index--;
            }
            return entries[index];
        },
    };
}

export function rotatedBounds(block) {
    const angle = ((block.rotation ?? 0) * Math.PI) / 180;
    const width = Math.abs(block.width * Math.cos(angle)) + Math.abs(block.height * Math.sin(angle));
    const height = Math.abs(block.width * Math.sin(angle)) + Math.abs(block.height * Math.cos(angle));
    return { x: block.x + (block.width - width) / 2, y: block.y + (block.height - height) / 2, width, height };
}

export function validateProject(value) {
    const object = (v) => v !== null && typeof v === 'object' && !Array.isArray(v);
    const number = (v, min, max) => typeof v === 'number' && Number.isFinite(v) && v >= min && v <= max;
    const color = (v) => typeof v === 'string' && /^#[a-f\d]{6}$/i.test(v);
    const key = (v) =>
        /^(panel-quest|panel-inventory|panel-skills|panel-tools|panel-pet|npc-4-magic-tree|npc-50-egg|panel-amulets|panel-radar|equipment|bag|box|box-84|featured|featured-([1-9]|10)|text-([1-9]|10)|shape-([1-9]|10)|image-[1-3])$/.test(
            v,
        );
    const check = (condition) => {
        if (!condition) throw new Error('Invalid project data.');
    };
    check(object(value) && Array.isArray(value.item_ids) && typeof value.show_details === 'boolean' && object(value.positions));
    for (const field of ['groups', 'texts', 'images', 'shapes', 'order']) {
        check(value[field] === undefined || Array.isArray(value[field]));
        const items = value[field] ?? [];
        check(new Set(items.map((item) => (field === 'order' ? item : item?.id))).size === items.length);
    }
    for (const [id, pos] of Object.entries(value.positions)) check(key(id) && object(pos) && number(pos.x, 0, 6000) && number(pos.y, 48, 6000));
    for (const id of value.order ?? []) check(typeof id === 'string' && key(id));
    check(value.layers === undefined || object(value.layers));
    for (const [id, layer] of Object.entries(value.layers ?? {})) {
        check(key(id) && object(layer));
        for (const flag of ['hidden', 'locked']) check(layer[flag] === undefined || typeof layer[flag] === 'boolean');
        for (const [field, min, max] of [
            ['scale', 0.2, 3],
            ['scale_x', 0.2, 3],
            ['scale_y', 0.2, 3],
            ['rotation', -180, 180],
            ['opacity', 0, 1],
        ])
            check(layer[field] === undefined || number(layer[field], min, max));
        check(layer.name === undefined || layer.name === null || (typeof layer.name === 'string' && layer.name.length <= 60));
    }
    if (value.document !== undefined)
        check(
            object(value.document) &&
                (value.document.cname === null || (typeof value.document.cname === 'string' && value.document.cname.length <= 100)) &&
                color(value.document.background),
        );
    if (value.document !== undefined) {
        const { width, height } = value.document;
        check(
            (width === undefined && height === undefined) ||
                (Number.isInteger(width) &&
                    Number.isInteger(height) &&
                    number(width, 64, 6000) &&
                    number(height, 64, 6000) &&
                    width * height <= 32000000),
        );
    }
    for (const group of value.groups ?? []) check(object(group) && Array.isArray(group.item_ids) && typeof group.show_details === 'boolean');
    for (const text of value.texts ?? [])
        check(object(text) && number(text.font_size, 12, 512) && number(text.width, 120, 6000) && typeof text.bold === 'boolean');
    for (const shape of value.shapes ?? []) check(object(shape) && number(shape.stroke_width, 0, 12));
    return value;
}
