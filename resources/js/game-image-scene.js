import { itemCrop, PANEL_FILES } from './game-image-capture.js';
import { rotatedBounds } from './game-image-editor-model.js';

export const PANEL_LABELS = [
    'Nhiệm vụ',
    'Hành trang',
    'Kỹ năng',
    'Đệ tử',
    'Hành trang',
    'Rương ID 3',
    'Rương ID 84',
    'Cây đậu',
    'Trứng',
    'Bùa',
    'Radar',
];
export const detailFile = (id) => `details/item-${String(id).padStart(4, '0')}.png`;

export function restoreHighlights(layout) {
    const groups = layout?.groups ?? [
        ...(layout ? [{ id: 'featured-1', item_ids: layout.item_ids ?? [], show_details: layout.show_details ?? true, columns: 2 }] : []),
    ];
    const positions = { ...layout?.positions };
    if (!layout?.groups && positions.featured) positions['featured-1'] ??= positions.featured;
    delete positions.featured;
    return { groups, positions };
}

export function wrapText(content, width, fontSize) {
    const limit = Math.max(1, Math.floor((width - 24) / (fontSize * 0.62)));
    return content
        .replace(/\r\n?/g, '\n')
        .split('\n')
        .flatMap((line) => {
            const characters = Array.from(line);
            const lines = [];
            for (let index = 0; index < characters.length; index += limit) lines.push(characters.slice(index, index + limit).join(''));
            return lines.length ? lines : [''];
        });
}

/**
 * @param {Array<{id: string, name?: string, item_ids: number[], show_details: boolean, columns: number}> | null} [groups]
 * @param {Array<{id: string, content: string, font_size: number, color: string, bold: boolean, width: number}>} [texts]
 * @param {{images?: Array<{id: string, name: string, src: string, width: number, height: number}>, shapes?: Array<{id: string, kind: string, width: number, height: number, fill: string, stroke: string, stroke_width: number}>}} [extras]
 */
export function createScene(images, ids, showDetails, positions = {}, groups = null, texts = [], extras = {}) {
    let x = 20;
    let rowY = 48;
    let rowHeight = 0;
    const blocks = PANEL_FILES.flatMap((file, index) => {
        if (index === 7) {
            x = 20;
            rowY += rowHeight;
            rowHeight = 0;
        }
        const image = images[file];
        if (!image) return [];
        const key = file.replace('.png', '');
        const block = {
            key,
            label: PANEL_LABELS[index],
            x,
            y: rowY,
            width: image.naturalWidth,
            height: image.naturalHeight,
            panel: true,
            pieces: [{ file, x: 0, y: 0, width: image.naturalWidth, height: image.naturalHeight }],
        };
        x += block.width;
        rowHeight = Math.max(rowHeight, block.height);
        return block;
    });
    const highlights = groups ?? [{ id: 'featured', item_ids: ids, show_details: showDetails, columns: 2 }];
    const groupY = Math.max(48, ...blocks.map((block) => block.y + block.height)) + 12;
    let groupX = 20;
    for (const [index, group] of highlights.entries()) {
        if (!group.item_ids.length) continue;
        const pieces = [];
        const columns = group.show_details ? Math.min(group.columns, group.item_ids.length) : 1;
        const crops = group.show_details ? [] : group.item_ids.map((id) => itemCrop(id, images));
        const columnWidth = group.show_details
            ? Math.max(...group.item_ids.map((id) => images[detailFile(id)].naturalWidth))
            : Math.max(440, ...crops.map((crop) => crop.width));
        const heights = Array(columns).fill(24);
        for (const [index, id] of group.item_ids.entries()) {
            const column = heights.indexOf(Math.min(...heights));
            if (group.show_details) {
                const file = detailFile(id);
                const detail = images[file];
                pieces.push({ file, x: column * columnWidth, y: heights[column], width: detail.naturalWidth, height: detail.naturalHeight });
                heights[column] += detail.naturalHeight;
            } else {
                const crop = crops[index];
                pieces.push({ file: crop.file, x: 0, y: heights[column], width: crop.width, height: crop.height, crop });
                heights[column] += crop.height;
            }
        }
        const height = Math.max(...heights);
        blocks.push({
            key: group.id,
            featured: true,
            label: group.name?.trim() || `Khối nổi bật ${index + 1}`,
            x: groupX,
            y: groupY,
            width: columns * columnWidth,
            height,
            pieces,
        });
        groupX += columns * columnWidth + 12;
    }
    const natural = sceneSize(blocks);
    const fitted = limitCompositionSize(natural);
    const scale = Math.min((fitted.width - 40) / (natural.width - 40), (fitted.height - 68) / (natural.height - 68));
    if (scale < 1) {
        for (const block of blocks) {
            block.x = 20 + (block.x - 20) * scale;
            block.y = 48 + (block.y - 48) * scale;
            block.width *= scale;
            block.height *= scale;
            block.pieces = block.pieces.map((piece) => ({
                ...piece,
                x: piece.x * scale,
                y: piece.y * scale,
                width: piece.width * scale,
                height: piece.height * scale,
                resize: true,
            }));
        }
    }
    const textY = Math.max(48, ...blocks.map((block) => block.y + block.height)) + 20;
    for (const [index, text] of texts.entries()) {
        const lines = wrapText(text.content, text.width, text.font_size);
        const lineHeight = Math.ceil(text.font_size * 1.4);
        blocks.push({
            key: text.id,
            label: `Text ${index + 1}`,
            x: 20 + index * 20,
            y: textY + index * 20,
            width: text.width,
            height: lines.length * lineHeight + 24,
            pieces: [],
            text: { ...text, lines, lineHeight },
        });
    }
    for (const [index, image] of (extras.images ?? []).entries()) {
        blocks.push({
            key: image.id,
            label: image.name,
            x: 40 + index * 30,
            y: 100 + index * 30,
            width: Number(image.width),
            height: Number(image.height),
            pieces: [{ file: image.id, x: 0, y: 0, width: Number(image.width), height: Number(image.height), resize: true }],
        });
    }
    for (const [index, shape] of (extras.shapes ?? []).entries()) {
        blocks.push({
            key: shape.id,
            label: shape.kind === 'ellipse' ? `Ellipse ${index + 1}` : `Hình chữ nhật ${index + 1}`,
            x: 60 + index * 30,
            y: 120 + index * 30,
            width: Number(shape.width),
            height: Number(shape.height),
            pieces: [],
            shape,
        });
    }
    return blocks.map((block) => ({ ...block, ...(positions[block.key] ?? {}) }));
}

export function sceneSize(blocks) {
    const bounds = blocks.filter((block) => !block.hidden).map(rotatedBounds);
    return {
        width: Math.ceil(Math.max(600, ...bounds.map((block) => block.x + block.width + 20))),
        height: Math.ceil(Math.max(300, ...bounds.map((block) => block.y + block.height + 20))),
    };
}

export function limitCompositionSize({ width, height }) {
    const factor = Math.min(1, 6000 / width, 6000 / height, Math.sqrt(32000000 / (width * height)));
    return { width: Math.max(64, Math.floor(width * factor)), height: Math.max(64, Math.floor(height * factor)) };
}

export function fitComposition(blocks, requestedSize = sceneSize(blocks)) {
    const size = limitCompositionSize(requestedSize);
    const bounds = blocks.filter((block) => !block.hidden).map(rotatedBounds);
    const left = Math.min(0, ...bounds.map((block) => block.x));
    const top = Math.min(0, ...bounds.map((block) => block.y));
    const right = Math.max(0, ...bounds.map((block) => block.x + block.width));
    const bottom = Math.max(0, ...bounds.map((block) => block.y + block.height));
    const scale = Math.min(1, size.width / (right - left), size.height / (bottom - top));
    return { size, scale, x: -left * scale, y: -top * scale };
}

/** @param {{selected?: string | string[] | null, size?: {width: number, height: number}, cname?: string, background?: string, backgroundSize?: {width: number, height: number}, projection?: {scale: number, x: number, y: number}}} [options] */
export function drawScene(canvas, images, blocks, options = {}) {
    const { selected = null, cname = 'kztiger', background = '#ffffff' } = options;
    const frame = fitComposition(blocks, options.backgroundSize ?? options.size);
    const size = options.backgroundSize && options.size ? options.size : frame.size;
    const { scale, x, y } = options.projection ?? frame;
    if (canvas.width !== size.width) canvas.width = size.width;
    if (canvas.height !== size.height) canvas.height = size.height;
    const context = canvas.getContext('2d');
    if (!context) throw new Error('Trình duyệt không hỗ trợ canvas.');
    context.fillStyle = background;
    context.clearRect(0, 0, canvas.width, canvas.height);
    context.fillRect(0, 0, options.backgroundSize?.width ?? canvas.width, options.backgroundSize?.height ?? canvas.height);
    context.imageSmoothingEnabled = true;
    context.imageSmoothingQuality = 'high';
    context.fillStyle = '#492d16';
    context.font = 'bold 18px sans-serif';
    context.fillText(`NRO - ${cname.trim() || 'kztiger'} - tienichnro.com`, 20, 28);
    const projected = scale !== 1 || x !== 0 || y !== 0;
    if (projected) {
        context.save();
        context.translate(x, y);
        context.scale(scale, scale);
    }
    for (const rendered of blocks) {
        if (rendered.hidden) continue;
        const transformed =
            (rendered.scaleX ?? rendered.scale ?? 1) !== 1 ||
            (rendered.scaleY ?? rendered.scale ?? 1) !== 1 ||
            (rendered.rotation ?? 0) !== 0 ||
            (rendered.opacity ?? 1) !== 1;
        let block = rendered;
        if (transformed) {
            context.save();
            context.globalAlpha = rendered.opacity ?? 1;
            context.translate(rendered.x + rendered.width / 2, rendered.y + rendered.height / 2);
            context.rotate(((rendered.rotation ?? 0) * Math.PI) / 180);
            context.scale(rendered.scaleX ?? rendered.scale ?? 1, rendered.scaleY ?? rendered.scale ?? 1);
            context.translate(-rendered.x - rendered.originalWidth / 2, -rendered.y - rendered.originalHeight / 2);
            block = { ...rendered, width: rendered.originalWidth, height: rendered.originalHeight };
        }
        if (block.shape) {
            context.fillStyle = block.shape.fill;
            context.strokeStyle = block.shape.stroke;
            context.lineWidth = block.shape.stroke_width;
            if (block.shape.kind === 'ellipse') {
                context.beginPath();
                context.ellipse(
                    block.x + block.width / 2,
                    block.y + block.height / 2,
                    block.width / 2 - block.shape.stroke_width / 2,
                    block.height / 2 - block.shape.stroke_width / 2,
                    0,
                    0,
                    2 * Math.PI,
                );
                context.fill();
                if (block.shape.stroke_width) context.stroke();
            } else {
                context.fillRect(block.x, block.y, block.width, block.height);
                if (block.shape.stroke_width)
                    context.strokeRect(
                        block.x + block.shape.stroke_width / 2,
                        block.y + block.shape.stroke_width / 2,
                        block.width - block.shape.stroke_width,
                        block.height - block.shape.stroke_width,
                    );
            }
        } else if (block.text) {
            context.fillStyle = '#ffffff';
            context.fillRect(block.x, block.y, block.width, block.height);
            context.fillStyle = block.text.color;
            context.font = `${block.text.bold ? 'bold ' : ''}${block.text.font_size}px monospace`;
            for (const [index, line] of block.text.lines.entries()) {
                context.fillText(line, block.x + 12, block.y + 12 + block.text.font_size + index * block.text.lineHeight, block.width - 24);
            }
        } else if (!block.panel) {
            context.fillStyle = block.featured ? '#dc2626' : '#492d16';
            context.font = 'bold 14px sans-serif';
            context.fillText(block.label, block.x, block.y + 16, block.width);
        }
        for (const piece of block.pieces) {
            const image = images[piece.file];
            if (piece.crop) {
                const { x, y, width, height } = piece.crop;
                context.drawImage(image, x, y, width, height, block.x + piece.x, block.y + piece.y, piece.width, piece.height);
            } else if (piece.resize) {
                context.drawImage(image, block.x + piece.x, block.y + piece.y, piece.width, piece.height);
            } else {
                context.drawImage(image, block.x + piece.x, block.y + piece.y);
            }
        }
        if (block.featured) {
            context.strokeStyle = '#dc2626';
            context.lineWidth = 2;
            context.strokeRect(block.x + 1, block.y + 1, block.width - 2, block.height - 2);
        }
        if (selected === block.key || (Array.isArray(selected) && selected.includes(block.key))) {
            context.strokeStyle = '#059669';
            context.lineWidth = 3;
            context.strokeRect(block.x - 2, block.y - 2, block.width + 4, block.height + 4);
            context.fillStyle = '#059669';
            context.fillRect(block.x + block.width - 4, block.y + block.height - 4, 8, 8);
        }
        if (transformed) context.restore();
    }
    if (projected) context.restore();
}

export function hitTest(blocks, point) {
    return [...blocks].reverse().find((block) => {
        if (block.hidden || block.locked) return false;
        const angle = (-(block.rotation ?? 0) * Math.PI) / 180;
        const dx = point.x - block.x - block.width / 2;
        const dy = point.y - block.y - block.height / 2;
        const x = dx * Math.cos(angle) - dy * Math.sin(angle);
        const y = dx * Math.sin(angle) + dy * Math.cos(angle);
        return Math.abs(x) <= block.width / 2 && Math.abs(y) <= block.height / 2;
    });
}

export function canvasPoint(event, rect, size) {
    return { x: ((event.clientX - rect.left) * size.width) / rect.width, y: ((event.clientY - rect.top) * size.height) / rect.height };
}

export function moveBlock(position, delta) {
    return { x: Math.max(0, Math.min(6000, Math.round(position.x + delta.x))), y: Math.max(48, Math.min(6000, Math.round(position.y + delta.y))) };
}
