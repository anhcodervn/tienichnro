<script setup lang="ts">
import {
    ArrowLeft,
    ChevronDown,
    ChevronUp,
    Circle,
    Copy,
    Download,
    Eye,
    EyeOff,
    FileJson,
    GripVertical,
    Hand,
    ImagePlus,
    Layers,
    Lock,
    Maximize2,
    Menu,
    Move,
    Plus,
    Redo2,
    Save,
    Share2,
    Square,
    Trash2,
    Type,
    Undo2,
    Unlock,
    X,
    ZoomIn,
    ZoomOut,
} from 'lucide-vue-next';
import { computed, nextTick, onMounted, onUnmounted, ref, shallowRef, watch } from 'vue';
import { createAutoSave } from '../../game-image-autosave.js';
import { captureItemRanges, itemCrop, loadCaptureImage, PANEL_FILES, parseItemIds } from '../../game-image-capture.js';
import { applyLayers, createHistory, reorderLayer, RESIZE_HANDLES, resizeLayer, validateProject } from '../../game-image-editor-model.js';
import {
    canvasPoint, createScene, detailFile, drawScene, fitComposition, hitTest,
    limitCompositionSize, moveBlock, restoreHighlights, sceneSize,
} from '../../game-image-scene.js';
import { handleStudioShortcut } from '../../game-image-shortcuts.js';
type Point = { x: number; y: number };
type Size = { width: number; height: number };
type Highlight = { id: string; name?: string; item_ids: number[]; show_details: boolean; columns: number };
type TextBlock = { id: string; content: string; font_size: number; color: string; bold: boolean; width: number };
type Shape = { id: string; kind: string; width: number; height: number; fill: string; stroke: string; stroke_width: number };
type ImportedImage = { id: string; name: string; src: string; width: number; height: number };
type Layer = {
    hidden?: boolean;
    locked?: boolean;
    opacity?: number;
    scale?: number;
    scale_x?: number;
    scale_y?: number;
    rotation?: number;
    name?: string;
};
type Layout = {
    item_ids: number[];
    show_details: boolean;
    positions: Record<string, Point>;
    groups?: Highlight[];
    texts?: TextBlock[];
    layers?: Record<string, Layer>;
    order?: string[];
    shapes?: Shape[];
    images?: ImportedImage[];
    document?: { cname: string; background: string; width?: number; height?: number };
};
type Account = {
    id: number;
    username: string;
    cName?: string | null;
    server: string;
    layout: Layout | null;
    update_url?: string;
    share_url?: string;
    has_share?: boolean;
    capture_urls?: Record<string, string> | null;
};
const props = withDefaults(
    defineProps<{ account: Account; demoBase: string; indexUrl: string; homeUrl?: string; logoUrl?: string; readOnly?: boolean }>(),
    {
        homeUrl: '/',
        logoUrl: '',
        readOnly: false,
    },
);
const clone = <T,>(value: T): T => JSON.parse(JSON.stringify(value));
const restored = restoreHighlights(props.account.layout);
const groupInputs = ref(
    (restored.groups as Highlight[]).map((group) => ({
        id: group.id,
        name: group.name ?? '',
        input: group.item_ids.join(','),
        show_details: group.show_details,
        columns: group.columns,
    })),
);
const texts = ref<TextBlock[]>(clone(props.account.layout?.texts ?? []));
const shapes = ref<Shape[]>(clone(props.account.layout?.shapes ?? []));
const imported = ref<ImportedImage[]>(clone(props.account.layout?.images ?? []));
const positions = ref<Record<string, Point>>({ ...restored.positions });
const layerSettings = ref<Record<string, Layer>>(clone(props.account.layout?.layers ?? {}));
const order = ref<string[]>([...(props.account.layout?.order ?? [])]);
const documentSettings = ref<NonNullable<Layout['document']>>({
    cname: props.account.layout?.document?.cname ?? props.account.cName ?? 'kztiger',
    background: props.account.layout?.document?.background ?? '#ffffff',
    ...(props.account.layout?.document?.width ? { width: props.account.layout.document.width, height: props.account.layout.document.height } : {}),
});
const selectedIds = ref<string[]>([]);
const selected = computed(() => selectedIds.value[0] ?? '');
const tool = ref('select');
const spacePan = ref(false);
const shapeTool = ref('rectangle');
const leftOpen = ref(false);
const rightOpen = ref(false);
const tab = ref('layers');
const showShare = ref(false);
const shareUrl = ref('');
const sharing = ref(false);
const hasShare = ref(Boolean(props.account.has_share));
const snap = ref(false);
const zoom = ref(0.4);
const fitMode = ref(true);
const format = ref('image/png');
const canvas = ref<HTMLCanvasElement | null>(null);
const viewport = ref<HTMLDivElement | null>(null);
const layerList = ref<HTMLDivElement | null>(null);
const layerDrag = ref<{ pointerId: number; source: string; target: string; placement: 'before' | 'after'; startY: number; moved: boolean } | null>(
    null,
);
const uploadInput = ref<HTMLInputElement | null>(null);
const projectInput = ref<HTMLInputElement | null>(null);
const images = shallowRef<Record<string, HTMLImageElement>>({});
const loading = ref(false);
const saving = ref(false);
const exporting = ref(false);
const message = ref('');
const assetError = ref('');
const drag = shallowRef<{
    pointerId: number;
    mode: string;
    start: Point;
    size: Size;
    projection: { scale: number; x: number; y: number };
    scroll: Point;
    points: Record<string, Point>;
    scale: number;
    width: number;
    height: number;
    handle?: { x: number; y: number };
    resize?: {
        block: { key: string; x: number; y: number; width: number; height: number; originalWidth: number; originalHeight: number; rotation: number };
        handle: { x: number; y: number };
    };
} | null>(null);
const cache = new Map<string, Promise<HTMLImageElement>>();
const urls = new Set<string>();
let loadRevision = 0,
    frame = 0;
let disposed = false,
    fitted = false;
let observer: ResizeObserver | undefined;
let historyTimer: ReturnType<typeof setTimeout> | undefined;
const historyTick = ref(0);
function idError(input: string): string {
    try {
        parseItemIds(input);
        return '';
    } catch (error) {
        return error instanceof Error ? error.message : 'ID không hợp lệ.';
    }
}
const groups = computed<Highlight[]>(() =>
    groupInputs.value.map((group) => ({
        id: group.id,
        name: group.name.trim(),
        item_ids: idError(group.input) ? [] : parseItemIds(group.input),
        show_details: group.show_details,
        columns: Number(group.columns) || 2,
    })),
);
const textConfigs = computed(() =>
    texts.value.map((text) => ({
        ...text,
        font_size: Math.max(12, Math.min(72, Math.round(Number(text.font_size) || 24))),
        width: Math.max(120, Math.min(1000, Math.round(Number(text.width) || 360))),
    })),
);
const shapeConfigs = computed(() =>
    shapes.value.map((shape) => ({
        ...shape,
        width: Math.max(20, Math.min(2000, Math.round(Number(shape.width) || 320))),
        height: Math.max(20, Math.min(2000, Math.round(Number(shape.height) || 180))),
        stroke_width: Math.max(0, Math.min(12, Math.round(Number(shape.stroke_width) || 0))),
    })),
);
const sources = computed(() => [
    ...[
        ...new Set([
            ...[...PANEL_FILES, 'equipment.png'].filter((key) => !props.account.capture_urls || props.account.capture_urls[key]),
            ...groups.value.filter((group) => group.show_details).flatMap((group) => group.item_ids.map(detailFile)),
        ]),
    ].map((key) => ({ key, url: props.account.capture_urls ? (props.account.capture_urls[key] ?? '') : `${props.demoBase}/${key}` })),
    ...imported.value.map((image) => ({ key: image.id, url: image.src })),
]);
const ready = computed(() => sources.value.every((source) => images.value[source.key]));
const itemRanges = computed(() => captureItemRanges(images.value));
const rowSourceError = computed(() => {
    if (!ready.value) return '';
    for (const group of groups.value.filter((group) => !group.show_details)) {
        for (const id of group.item_ids) {
            try {
                if (!images.value[itemCrop(id, images.value).file])
                    return `Bộ ảnh chưa có nguồn để cắt item #${id}. Hãy dùng ảnh detail hoặc tải lên đủ ảnh nguồn.`;
            } catch {
                return `Item #${id} không có trong bộ ảnh tài khoản này.`;
            }
        }
    }
    return '';
});
const rawBlocks = computed(() =>
    ready.value && !rowSourceError.value
        ? createScene(images.value, [], true, positions.value, groups.value, textConfigs.value, {
              images: imported.value,
              shapes: shapeConfigs.value,
          })
        : [],
);
const blocks = computed(() => applyLayers(rawBlocks.value, layerSettings.value, order.value));
const composition = computed(() =>
    fitComposition(
        blocks.value,
        documentSettings.value.width && documentSettings.value.height
            ? { width: documentSettings.value.width, height: documentSettings.value.height }
            : undefined,
    ),
);
const dimensions = computed(() => composition.value.size);
const projection = computed(() => drag.value?.projection ?? composition.value);
const stageSize = computed(() => drag.value?.size ?? { width: dimensions.value.width + 240, height: dimensions.value.height + 240 });
const active = computed(() => blocks.value.find((block) => block.key === selected.value));
const activeGroup = computed(() => groupInputs.value.find((group) => group.id === selected.value));
const activeText = computed(() => texts.value.find((text) => text.id === selected.value));
const activeShape = computed(() => shapes.value.find((shape) => shape.id === selected.value));
const layout = computed<Layout>(() => ({
    item_ids: groups.value[0]?.item_ids ?? [],
    show_details: groups.value[0]?.show_details ?? true,
    positions: positions.value,
    groups: groups.value,
    texts: textConfigs.value,
    layers: layerSettings.value,
    order: order.value,
    shapes: shapeConfigs.value,
    images: imported.value,
    document: documentSettings.value,
}));
const savedSnapshot = ref(JSON.stringify(layout.value));
const dirty = computed(() => JSON.stringify(layout.value) !== savedSnapshot.value);
const needsPreview = ref(!props.account.layout);
const autoSave = createAutoSave({
    save: () => save(true),
    canSave: () => !props.readOnly && !disabled.value && !saving.value && !drag.value && !layerDrag.value && Boolean(props.account.update_url),
    isDirty: () => dirty.value || needsPreview.value,
});
const history = createHistory(savedSnapshot.value);
const canUndo = computed(() => {
    void historyTick.value;
    return history.canUndo;
});
const canRedo = computed(() => {
    void historyTick.value;
    return history.canRedo;
});
const disabled = computed(
    () =>
        !ready.value || loading.value || Boolean(assetError.value || rowSourceError.value) || groupInputs.value.some((group) => idError(group.input)),
);
function commit() {
    clearTimeout(historyTimer);
    history.commit(JSON.stringify(layout.value));
    historyTick.value++;
}
function restore(snapshot: string) {
    const value = JSON.parse(snapshot) as Layout;
    const old = restoreHighlights(value);
    groupInputs.value = (old.groups as Highlight[]).map((group) => ({
        id: group.id,
        name: group.name ?? '',
        input: group.item_ids.join(','),
        show_details: group.show_details,
        columns: group.columns,
    }));
    positions.value = { ...old.positions };
    texts.value = value.texts ?? [];
    shapes.value = value.shapes ?? [];
    imported.value = value.images ?? [];
    layerSettings.value = value.layers ?? {};
    order.value = value.order ?? [];
    documentSettings.value = value.document ?? { cname: 'kztiger', background: '#ffffff' };
    selectedIds.value = [];
    historyTick.value++;
}
function undo() {
    if (props.readOnly || drag.value) return;
    commit();
    restore(history.undo());
}
function redo() {
    if (props.readOnly || drag.value) return;
    clearTimeout(historyTimer);
    restore(history.redo());
}
function freeId(prefix: string, values: Array<{ id: string }>, limit = 10) {
    return Array.from({ length: limit }, (_, index) => `${prefix}-${index + 1}`).find((id) => !values.some((value) => value.id === id));
}
function choose(key: string, additive = false) {
    selectedIds.value = additive
        ? selectedIds.value.includes(key)
            ? selectedIds.value.filter((id) => id !== key)
            : [...selectedIds.value, key]
        : [key];
    tab.value = 'properties';
}
function added(id: string) {
    choose(id);
    tool.value = 'select';
    leftOpen.value = false;
    rightOpen.value = true;
    nextTick(focusSelection);
}
function addGroup() {
    if (props.readOnly) return;
    const id = freeId('featured', groupInputs.value);
    if (!id) return;
    groupInputs.value.push({ id, name: '', input: '1,5,11,14', show_details: true, columns: 2 });
    added(id);
}
function addText() {
    if (props.readOnly) return;
    const id = freeId('text', texts.value);
    if (!id) return;
    texts.value.push({ id, content: 'Nội dung của bạn', font_size: 24, color: '#1e293b', bold: false, width: 360 });
    added(id);
}
function addShape(kind: string) {
    if (props.readOnly) return;
    const id = freeId('shape', shapes.value);
    if (!id) return;
    shapes.value.push({ id, kind, width: 320, height: 180, fill: '#dbeafe', stroke: '#2563eb', stroke_width: 2 });
    added(id);
}
function setLayer(id: string, patch: Layer) {
    if (!props.readOnly)
        layerSettings.value = {
            ...layerSettings.value,
            [id]: { ...layerSettings.value[id], ...patch, ...(patch.scale !== undefined ? { scale_x: patch.scale, scale_y: patch.scale } : {}) },
        };
}
function removeSelection() {
    if (props.readOnly) return;
    for (const id of selectedIds.value) {
        if (layerSettings.value[id]?.locked) continue;
        if (!/^(text|featured|image|shape)-/.test(id)) {
            setLayer(id, { hidden: true });
            continue;
        }
        groupInputs.value = groupInputs.value.filter((group) => group.id !== id);
        texts.value = texts.value.filter((text) => text.id !== id);
        shapes.value = shapes.value.filter((shape) => shape.id !== id);
        imported.value = imported.value.filter((image) => image.id !== id);
        const next = { ...positions.value };
        delete next[id];
        positions.value = next;
        const layers = { ...layerSettings.value };
        delete layers[id];
        layerSettings.value = layers;
        order.value = order.value.filter((key) => key !== id);
    }
    selectedIds.value = [];
    commit();
}
function duplicate() {
    if (props.readOnly || !active.value || active.value.locked) return;
    let id: string | undefined;
    if (activeText.value) {
        id = freeId('text', texts.value);
        if (id) texts.value.push({ ...activeText.value, id });
    } else if (activeGroup.value) {
        id = freeId('featured', groupInputs.value);
        if (id) groupInputs.value.push({ ...activeGroup.value, id });
    } else if (activeShape.value) {
        id = freeId('shape', shapes.value);
        if (id) shapes.value.push({ ...activeShape.value, id });
    } else if (active.value.panel) {
        id = freeId('image', imported.value, 3);
        const source = images.value[active.value.pieces[0].file];
        if (id && source) {
            const output = document.createElement('canvas');
            output.width = source.naturalWidth;
            output.height = source.naturalHeight;
            output.getContext('2d')?.drawImage(source, 0, 0);
            const src = output.toDataURL('image/png');
            if (src.length > 1400000 || output.width > 1600 || output.height > 1600) {
                message.value = 'Panel quá lớn để nhân bản thành layer ảnh.';
                return;
            }
            imported.value.push({ id, name: active.value.label, src, width: output.width, height: output.height });
        } else id = undefined;
    } else {
        const image = imported.value.find((item) => item.id === selected.value);
        if (image) {
            id = freeId('image', imported.value, 3);
            if (id) imported.value.push({ ...image, id });
        }
    }
    if (!id) {
        message.value = 'Chỉ nhân bản text, item, hình hoặc ảnh nhập; có giới hạn số khối.';
        return;
    }
    positions.value = { ...positions.value, [id]: moveBlock(active.value, { x: 20, y: 20 }) };
    layerSettings.value = { ...layerSettings.value, [id]: { ...layerSettings.value[selected.value] } };
    added(id);
    commit();
}
function reorder(step: number) {
    if (props.readOnly) return;
    const keys = blocks.value.map((block) => block.key),
        index = keys.indexOf(selected.value),
        next = Math.max(0, Math.min(keys.length - 1, index + step));
    if (index < 0 || index === next) return;
    keys.splice(next, 0, keys.splice(index, 1)[0]);
    order.value = keys;
    commit();
}
function startLayerDrag(event: PointerEvent, source: string) {
    if (props.readOnly || event.button !== 0 || layerDrag.value || drag.value) return;
    event.preventDefault();
    selectedIds.value = [source];
    layerDrag.value = { pointerId: event.pointerId, source, target: source, placement: 'before', startY: event.clientY, moved: false };
    (event.currentTarget as HTMLElement).setPointerCapture(event.pointerId);
}
function moveLayerDrag(event: PointerEvent) {
    const current = layerDrag.value;
    const list = layerList.value;
    if (!current || current.pointerId !== event.pointerId || !list) return;
    if (Math.abs(event.clientY - current.startY) > 4) current.moved = true;
    const row = list.ownerDocument.elementFromPoint(event.clientX, event.clientY)?.closest<HTMLElement>('[data-layer-key]');
    if (!row || !list.contains(row)) {
        current.target = '';
        return;
    }
    current.target = row.dataset.layerKey ?? '';
    const rect = row.getBoundingClientRect();
    current.placement = event.clientY < rect.top + rect.height / 2 ? 'before' : 'after';
    const bounds = list.getBoundingClientRect();
    if (event.clientY < bounds.top + 28) list.scrollTop -= 16;
    else if (event.clientY > bounds.bottom - 28) list.scrollTop += 16;
}
function endLayerDrag(event: PointerEvent) {
    const current = layerDrag.value;
    if (!current || current.pointerId !== event.pointerId) return;
    if (!props.readOnly && current.moved && current.target && current.source !== current.target) {
        commit();
        order.value = reorderLayer(
            blocks.value.map((block) => block.key),
            current.source,
            current.target,
            current.placement,
        );
        commit();
    }
    cancelLayerDrag(event);
}
function cancelLayerDrag(event: PointerEvent) {
    if (layerDrag.value?.pointerId !== event.pointerId) return;
    layerDrag.value = null;
    const handle = event.currentTarget as HTMLElement;
    if (handle.hasPointerCapture(event.pointerId)) handle.releasePointerCapture(event.pointerId);
}
function moveSelection(dx: number, dy: number) {
    if (props.readOnly) return;
    const next = { ...positions.value };
    for (const block of blocks.value.filter((item) => selectedIds.value.includes(item.key) && !item.locked))
        next[block.key] = moveBlock(block, { x: dx, y: dy });
    positions.value = next;
}
function align(mode: string) {
    if (props.readOnly) return;
    const items = blocks.value.filter((block) => selectedIds.value.includes(block.key) && !block.locked);
    if (items.length < 2) return;
    const minX = Math.min(...items.map((item) => item.x)),
        maxX = Math.max(...items.map((item) => item.x + item.width)),
        minY = Math.min(...items.map((item) => item.y)),
        maxY = Math.max(...items.map((item) => item.y + item.height));
    const next = { ...positions.value };
    for (const block of items)
        next[block.key] = {
            x: Math.round(
                mode === 'left' ? minX : mode === 'right' ? maxX - block.width : mode === 'center' ? (minX + maxX - block.width) / 2 : block.x,
            ),
            y: Math.round(mode === 'top' ? minY : mode === 'bottom' ? maxY - block.height : block.y),
        };
    positions.value = next;
    commit();
}
const alignNames: Record<string, string> = { left: 'Trái', center: 'Giữa ngang', right: 'Phải', top: 'Trên', bottom: 'Dưới' };
function inputNumber(event: Event) {
    return Number((event.target as HTMLInputElement).value);
}
function coordinate(axis: 'x' | 'y', event: Event) {
    if (!active.value || active.value.locked) return;
    const value = inputNumber(event);
    if (Number.isFinite(value))
        positions.value = {
            ...positions.value,
            [selected.value]: moveBlock({ x: active.value.x, y: active.value.y, [axis]: value }, { x: 0, y: 0 }),
        };
}
function focusSelection() {
    const block = active.value;
    if (block && viewport.value) {
        const { scale, x, y } = projection.value;
        viewport.value.scrollTo({
            left: Math.max(0, (block.x * scale + x) * zoom.value - 40),
            top: Math.max(0, (block.y * scale + y) * zoom.value - 40),
            behavior: 'smooth',
        });
    }
}
function resetPositions() {
    if (props.readOnly) return;
    positions.value = {};
    commit();
    nextTick(fit);
}
async function loadAssets() {
    const revision = ++loadRevision;
    loading.value = true;
    assetError.value = '';
    try {
        const entries = await Promise.all(
            sources.value.map(async (source) => {
                if (!cache.has(source.url))
                    cache.set(
                        source.url,
                        loadCaptureImage(source.url).catch((error) => {
                            cache.delete(source.url);
                            throw error;
                        }),
                    );
                return [source.key, await cache.get(source.url)];
            }),
        );
        if (disposed || revision !== loadRevision) return;
        images.value = Object.fromEntries(entries);
        await nextTick();
        if (!fitted) {
            if (!props.readOnly && props.account.capture_urls && documentSettings.value.width && documentSettings.value.height) {
                const required = limitCompositionSize(sceneSize(blocks.value));
                if (required.width > dimensions.value.width || required.height > dimensions.value.height) {
                    setBackgroundSize(Math.max(required.width, dimensions.value.width), Math.max(required.height, dimensions.value.height));
                    needsPreview.value = true;
                    await nextTick();
                }
            }
            fit();
            fitted = true;
        }
    } catch (error) {
        if (!disposed && revision === loadRevision) assetError.value = error instanceof Error ? error.message : 'Không tải được ảnh.';
    } finally {
        if (!disposed && revision === loadRevision) loading.value = false;
    }
}
function paint() {
    cancelAnimationFrame(frame);
    frame = requestAnimationFrame(() => {
        if (canvas.value && ready.value)
            drawScene(canvas.value, images.value, blocks.value, {
                selected: null,
                backgroundSize: dimensions.value,
                size: stageSize.value,
                projection: projection.value,
                ...documentSettings.value,
            });
    });
}
function fit() {
    fitMode.value = true;
    zoom.value = Math.max(0.08, Math.min(1, ((viewport.value?.clientWidth ?? 800) - 100) / stageSize.value.width));
}
function point(event: PointerEvent, background = false) {
    const point = canvasPoint(event, canvas.value!.getBoundingClientRect(), stageSize.value);
    const { scale, x, y } = projection.value;
    return background ? point : { x: (point.x - x) / scale, y: (point.y - y) / scale };
}
function setBackgroundSize(width: number, height: number, ratio?: number) {
    if (props.readOnly) return;
    let nextWidth = Math.max(64, Math.min(6000, Math.round(width)));
    let nextHeight = Math.max(64, Math.min(6000, Math.round(height)));
    if (ratio) {
        const uniformHeight = Math.max(64, 64 / ratio, Math.min(6000, 6000 / ratio, Math.max(width / ratio, height)));
        nextWidth = Math.round(uniformHeight * ratio);
        nextHeight = Math.round(uniformHeight);
    }
    if (nextWidth * nextHeight > 32000000) nextHeight = Math.floor(32000000 / nextWidth);
    documentSettings.value = { ...documentSettings.value, width: nextWidth, height: nextHeight };
}
function fitBackground() {
    if (props.readOnly) return;
    const size = limitCompositionSize(sceneSize(blocks.value));
    setBackgroundSize(size.width, size.height);
    commit();
}
function startResize(event: PointerEvent, handleId: string, background = false) {
    if (props.readOnly || disabled.value || drag.value || event.button !== 0 || (!background && (!active.value || active.value.locked))) return;
    const handle = RESIZE_HANDLES.find((item) => item.id === handleId) ?? (handleId === 's' ? { x: 0, y: 1 } : undefined);
    if (!handle) return;
    event.preventDefault();
    commit();
    fitMode.value = false;
    drag.value = {
        pointerId: event.pointerId,
        mode: background ? 'background' : 'resize',
        start: point(event, background),
        handle,
        size: { ...stageSize.value },
        projection: { ...projection.value },
        scroll: { x: 0, y: 0 },
        points: {},
        scale: 1,
        width: background ? dimensions.value.width : active.value!.width,
        height: background ? dimensions.value.height : active.value!.height,
        ...(!background ? { resize: { block: { ...active.value! }, handle } } : {}),
    };
    (event.currentTarget as HTMLElement).setPointerCapture(event.pointerId);
}
function selectionStyle() {
    if (!active.value) return {};
    const block = active.value;
    const { scale, x, y } = projection.value;
    return {
        left: `${(block.x * scale + x) * zoom.value}px`,
        top: `${(block.y * scale + y) * zoom.value}px`,
        width: `${block.width * scale * zoom.value}px`,
        height: `${block.height * scale * zoom.value}px`,
        transform: `rotate(${block.rotation}deg)`,
    };
}
function startPointer(event: PointerEvent) {
    if (![0, 1].includes(event.button) || drag.value || disabled.value) return;
    const start = point(event),
        pan = spacePan.value || event.button === 1 || tool.value === 'hand' || (props.readOnly && tool.value !== 'zoom'),
        block = pan ? undefined : hitTest(blocks.value, start);
    if (!pan && tool.value === 'zoom') {
        event.preventDefault();
        changeZoom(event.altKey ? -1 : 1);
        return;
    }
    if (!pan && !props.readOnly && ['text', 'rectangle', 'ellipse'].includes(tool.value)) {
        event.preventDefault();
        if ((tool.value === 'text' && texts.value.length >= 10) || (tool.value !== 'text' && shapes.value.length >= 10)) return;
        if (tool.value === 'text') addText();
        else addShape(tool.value);
        if (selected.value) positions.value = { ...positions.value, [selected.value]: moveBlock(start, { x: 0, y: 0 }) };
        commit();
        return;
    }
    if (!pan && !block) {
        if (!event.shiftKey) selectedIds.value = [];
        return;
    }
    if (block && event.shiftKey) {
        choose(block.key, true);
        return;
    }
    if (block && !selectedIds.value.includes(block.key)) choose(block.key);
    if (!pan) commit();
    const resize =
        !pan &&
        (tool.value === 'resize' ||
            (active.value &&
                Math.abs(start.x - active.value.x - active.value.width) < 12 / (zoom.value * projection.value.scale) &&
                Math.abs(start.y - active.value.y - active.value.height) < 12 / (zoom.value * projection.value.scale)));
    event.preventDefault();
    fitMode.value = false;
    drag.value = {
        pointerId: event.pointerId,
        mode: pan ? 'pan' : resize ? 'resize' : 'move',
        start: pan ? { x: event.clientX, y: event.clientY } : start,
        size: { width: stageSize.value.width + (pan ? 0 : 300), height: stageSize.value.height + (pan ? 0 : 300) },
        projection: { ...projection.value },
        scroll: { x: viewport.value?.scrollLeft ?? 0, y: viewport.value?.scrollTop ?? 0 },
        points: Object.fromEntries(
            blocks.value.filter((item) => selectedIds.value.includes(item.key) && !item.locked).map((item) => [item.key, { x: item.x, y: item.y }]),
        ),
        scale: active.value?.scale ?? 1,
        width: active.value?.width ?? 1,
        height: active.value?.height ?? 1,
        ...(resize && active.value ? { resize: { block: { ...active.value }, handle: { x: 1, y: 1 } } } : {}),
    };
    canvas.value?.setPointerCapture(event.pointerId);
}
function movePointer(event: PointerEvent) {
    const current = drag.value;
    if (!current || current.pointerId !== event.pointerId) return;
    if (current.mode === 'pan') {
        if (viewport.value) {
            viewport.value.scrollLeft = current.scroll.x - event.clientX + current.start.x;
            viewport.value.scrollTop = current.scroll.y - event.clientY + current.start.y;
        }
        return;
    }
    const next = point(event, current.mode === 'background'),
        step = snap.value ? 10 : 1,
        dx = Math.round((next.x - current.start.x) / step) * step,
        dy = Math.round((next.y - current.start.y) / step) * step;
    if (current.mode === 'background') {
        setBackgroundSize(
            current.width + (current.handle?.x ? dx : 0),
            current.height + (current.handle?.y ? dy : 0),
            event.shiftKey ? current.width / current.height : undefined,
        );
        return;
    }
    if (current.mode === 'resize' && current.resize) {
        const { x, y, scale_x, scale_y } = resizeLayer(current.resize.block, current.resize.handle, { x: dx, y: dy }, event.shiftKey);
        setLayer(current.resize.block.key, { scale_x, scale_y });
        positions.value = { ...positions.value, [current.resize.block.key]: { x, y } };
        return;
    }
    positions.value = {
        ...positions.value,
        ...Object.fromEntries(Object.entries(current.points).map(([key, pos]) => [key, moveBlock(pos, { x: dx, y: dy })])),
    };
}
function stopPointer(event: PointerEvent) {
    if (!drag.value || drag.value.pointerId !== event.pointerId) return;
    const editing = drag.value.mode !== 'pan';
    drag.value = null;
    if (canvas.value?.hasPointerCapture(event.pointerId)) canvas.value.releasePointerCapture(event.pointerId);
    if (editing) commit();
}
async function request(url: string, method: string, body?: unknown) {
    const response = await fetch(url, {
        method,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
        },
        ...(body === undefined ? {} : { body: JSON.stringify(body) }),
    });
    if ([401, 419].includes(response.status)) throw new Error('Phiên đăng nhập đã hết hạn. Hãy đăng nhập lại.');
    const result = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(String(Object.values(result.errors ?? {}).flat()[0] ?? result.message ?? 'Thao tác thất bại. Hãy thử lại.'));
    return result;
}
async function save(automatic = false): Promise<boolean> {
    if (props.readOnly || disabled.value || saving.value || drag.value || layerDrag.value || !props.account.update_url) return false;
    saving.value = true;
    const snapshot = JSON.stringify(layout.value);
    try {
        if (dimensions.value.width * dimensions.value.height > 32000000) throw new Error('Ảnh quá lớn. Hãy thu gọn bố cục trước khi lưu.');
        const output = document.createElement('canvas');
        let preview: string;
        try {
            drawScene(output, images.value, blocks.value, { ...documentSettings.value, size: dimensions.value });
            preview = output.toDataURL('image/png');
        } finally {
            output.width = 0;
            output.height = 0;
        }
        await request(props.account.update_url, 'PATCH', { layout: JSON.parse(snapshot), preview });
        savedSnapshot.value = snapshot;
        needsPreview.value = false;
        message.value = automatic ? '' : 'Đã lưu bố cục và ảnh ghép.';
        return true;
    } catch (error) {
        message.value = error instanceof Error ? error.message : 'Không lưu được.';
        return false;
    } finally {
        saving.value = false;
    }
}
function downloadBlob(blob: Blob, name: string) {
    const url = URL.createObjectURL(blob);
    urls.add(url);
    const link = document.createElement('a');
    link.href = url;
    link.download = name;
    link.click();
}
async function download() {
    if (disabled.value || exporting.value) return;
    exporting.value = true;
    try {
        if (dimensions.value.width * dimensions.value.height > 32000000) throw new Error('Ảnh quá lớn. Hãy thu gọn các layer trước khi xuất.');
        const output = document.createElement('canvas');
        drawScene(output, images.value, blocks.value, { ...documentSettings.value, size: dimensions.value });
        const blob = await new Promise<Blob | null>((resolve) => output.toBlob(resolve, format.value, 0.95));
        output.width = 0;
        output.height = 0;
        if (!blob) throw new Error('Không tạo được ảnh. Hãy thu gọn bố cục rồi thử lại.');
        downloadBlob(blob, `nro-account-${props.account.id}.${format.value === 'image/jpeg' ? 'jpg' : 'png'}`);
    } catch (error) {
        message.value = error instanceof Error ? error.message : 'Không xuất được ảnh.';
    } finally {
        exporting.value = false;
    }
}
async function share() {
    if (props.readOnly || !props.account.share_url || sharing.value || disabled.value) return;
    sharing.value = true;
    try {
        if ((dirty.value || !props.account.layout) && !(await save())) return;
        const result = await request(props.account.share_url, 'POST');
        shareUrl.value = result.url;
        hasShare.value = true;
        showShare.value = true;
    } catch (error) {
        message.value = error instanceof Error ? error.message : 'Không tạo được link.';
    } finally {
        sharing.value = false;
    }
}
async function revoke() {
    if (!props.account.share_url) return;
    try {
        await request(props.account.share_url, 'DELETE');
        shareUrl.value = '';
        hasShare.value = false;
        showShare.value = false;
        message.value = 'Đã thu hồi link chia sẻ.';
    } catch (error) {
        message.value = error instanceof Error ? error.message : 'Không thu hồi được.';
    }
}
async function copyShare() {
    try {
        await navigator.clipboard.writeText(shareUrl.value);
        message.value = 'Đã sao chép link.';
    } catch {
        message.value = 'Hãy chọn và sao chép link trong hộp chia sẻ.';
    }
}
async function importImage(event: Event) {
    const input = event.target as HTMLInputElement,
        file = input.files?.[0];
    input.value = '';
    if (!file || props.readOnly) return;
    try {
        const id = freeId('image', imported.value, 3);
        if (!id) throw new Error('Tối đa 3 ảnh nhập cho mỗi bố cục.');
        if (!['image/png', 'image/jpeg', 'image/webp'].includes(file.type) || file.size > 1048576)
            throw new Error('Chọn PNG, JPEG hoặc WebP tối đa 1 MB.');
        const src = await new Promise<string>((resolve, reject) => {
            const reader = new FileReader();
            reader.onload = () => resolve(String(reader.result));
            reader.onerror = reject;
            reader.readAsDataURL(file);
        });
        const image = await loadCaptureImage(src);
        if (image.naturalWidth > 4096 || image.naturalHeight > 4096) throw new Error('Ảnh tối đa 4096 px mỗi chiều.');
        const ratio = Math.min(1, 480 / Math.max(image.naturalWidth, image.naturalHeight));
        imported.value.push({
            id,
            name: file.name.slice(0, 100),
            src,
            width: Math.max(1, Math.round(image.naturalWidth * ratio)),
            height: Math.max(1, Math.round(image.naturalHeight * ratio)),
        });
        added(id);
    } catch (error) {
        message.value = error instanceof Error ? error.message : 'Không đọc được ảnh.';
    }
}
function exportProject() {
    downloadBlob(new Blob([JSON.stringify(layout.value, null, 2)], { type: 'application/json' }), `nro-project-${props.account.id}.json`);
}
async function importProject(event: Event) {
    const input = event.target as HTMLInputElement,
        file = input.files?.[0];
    input.value = '';
    if (!file || props.readOnly) return;
    try {
        if (file.size > 6000000) throw new Error('Dự án JSON tối đa 6 MB.');
        const value = validateProject(JSON.parse(await file.text())) as Layout;
        if (
            !value ||
            !Array.isArray(value.item_ids) ||
            !value.positions ||
            typeof value.positions !== 'object' ||
            !Array.isArray(value.groups) ||
            !Array.isArray(value.texts)
        )
            throw new Error('Định dạng dự án không hợp lệ.');
        if (value.groups.length > 10 || value.texts.length > 10 || (value.images?.length ?? 0) > 3 || (value.shapes?.length ?? 0) > 10)
            throw new Error('Dự án có quá nhiều layer.');
        for (const group of value.groups) {
            parseItemIds(group.item_ids.join(','));
            if (
                !/^featured-([1-9]|10)$/.test(group.id) ||
                !Number.isInteger(group.columns) ||
                group.columns < 1 ||
                group.columns > 6 ||
                (group.name !== undefined && typeof group.name !== 'string')
            )
                throw new Error('Khối item không hợp lệ.');
        }
        for (const text of value.texts)
            if (
                !/^text-([1-9]|10)$/.test(text.id) ||
                typeof text.content !== 'string' ||
                text.content.length > 1000 ||
                text.font_size < 12 ||
                text.font_size > 72 ||
                text.width < 120 ||
                text.width > 1000 ||
                !/^#[a-f\d]{6}$/i.test(text.color)
            )
                throw new Error('Khối text không hợp lệ.');
        for (const image of value.images ?? [])
            if (
                !/^image-[1-3]$/.test(image.id) ||
                typeof image.name !== 'string' ||
                !/^data:image\/(png|jpeg|webp);base64,/.test(image.src) ||
                image.src.length > 1400000 ||
                !(image.width > 0 && image.width <= 1600 && image.height > 0 && image.height <= 1600)
            )
                throw new Error('Ảnh nhập không hợp lệ.');
        for (const shape of value.shapes ?? [])
            if (
                !/^shape-([1-9]|10)$/.test(shape.id) ||
                !['rectangle', 'ellipse'].includes(shape.kind) ||
                !(shape.width >= 20 && shape.width <= 2000 && shape.height >= 20 && shape.height <= 2000) ||
                !/^#[a-f\d]{6}$/i.test(shape.fill) ||
                !/^#[a-f\d]{6}$/i.test(shape.stroke)
            )
                throw new Error('Hình không hợp lệ.');
        for (const pos of Object.values(value.positions))
            if (!Number.isFinite(pos.x) || !Number.isFinite(pos.y) || pos.x < 0 || pos.x > 6000 || pos.y < 48 || pos.y > 6000)
                throw new Error('Tọa độ không hợp lệ.');
        commit();
        restore(JSON.stringify(value));
        commit();
        message.value = 'Đã mở dự án. Bấm Lưu để ghi vào account.';
    } catch (error) {
        message.value = error instanceof Error ? error.message : 'Không mở được dự án.';
    }
}
function changeZoom(direction: number) {
    zoom.value = Math.max(0.08, Math.min(2, zoom.value * (direction > 0 ? 1.2 : 1 / 1.2)));
    fitMode.value = false;
}
function keydown(event: KeyboardEvent) {
    if (showShare.value || drag.value || layerDrag.value) return;
    handleStudioShortcut(
        event,
        {
            save: () => void save(),
            export: () => void download(),
            toggleUndo: () => {
                commit();
                restore(history.toggle());
            },
            undo,
            redo,
            duplicate,
            deselect: () => {
                selectedIds.value = [];
            },
            selectAll: () => {
                selectedIds.value = blocks.value.filter((block) => !block.hidden && !block.locked).map((block) => block.key);
            },
            transform: () => {
                if (active.value && !active.value.locked) tool.value = 'resize';
            },
            finishTransform: () => {
                if (tool.value === 'resize') {
                    commit();
                    tool.value = 'select';
                }
            },
            reorder,
            remove: removeSelection,
            move: ([x, y]: number[]) => moveSelection(x, y),
            tool: (value: string) => {
                tool.value = value;
            },
            shape: (cycle: boolean) => {
                if (cycle) shapeTool.value = shapeTool.value === 'rectangle' ? 'ellipse' : 'rectangle';
                tool.value = shapeTool.value;
            },
            pan: () => {
                spacePan.value = true;
            },
            fit,
            actualSize: () => {
                zoom.value = 1;
                fitMode.value = false;
            },
            zoom: changeZoom,
            escape: () => {
                selectedIds.value = [];
                tool.value = 'select';
                leftOpen.value = false;
                rightOpen.value = false;
            },
        },
        props.readOnly,
    );
}
function releasePan(event?: Event) {
    if (!event || event.type === 'blur' || (event as KeyboardEvent).key === ' ') spacePan.value = false;
}
watch(sources, loadAssets, { immediate: true });
watch(disabled, () => {
    if (!disabled.value) autoSave.schedule();
});
watch([blocks, selectedIds, stageSize, documentSettings], paint, { deep: true, flush: 'post' });
watch(
    layout,
    () => {
        autoSave.schedule();
        if (props.readOnly || drag.value) return;
        clearTimeout(historyTimer);
        historyTimer = setTimeout(commit, 400);
    },
    { deep: true },
);
onMounted(() => {
    if (!props.readOnly && props.account.update_url) autoSave.start();
    tool.value = window.matchMedia('(pointer: fine)').matches && !props.readOnly ? 'select' : 'hand';
    observer = new ResizeObserver(() => {
        if (fitMode.value && !drag.value) fit();
    });
    if (viewport.value) observer.observe(viewport.value);
    window.addEventListener('keydown', keydown);
    window.addEventListener('keyup', releasePan);
    window.addEventListener('blur', releasePan);
    paint();
});
onUnmounted(() => {
    autoSave.stop();
    disposed = true;
    loadRevision++;
    clearTimeout(historyTimer);
    cancelAnimationFrame(frame);
    observer?.disconnect();
    window.removeEventListener('keydown', keydown);
    window.removeEventListener('keyup', releasePan);
    window.removeEventListener('blur', releasePan);
    urls.forEach((url) => URL.revokeObjectURL(url));
});
</script>
<template>
    <div class="studio" :class="{ 'is-viewer': readOnly }">
        <header class="studio-topbar">
            <a :href="homeUrl" class="studio-brand" title="Tiện ích NRO · Trở về website"
                ><img v-if="logoUrl" :src="logoUrl" alt="Tiện ích NRO" class="studio-brand-image" /><span v-else class="studio-brand-fallback"
                    >Tiện ích NRO</span
                ></a
            ><span class="studio-divider"></span>
            <div class="studio-file">
                <b>{{ readOnly ? 'Bản chia sẻ' : account.username }}</b
                ><small>{{ readOnly ? 'Chỉ xem · Không chứa thông tin đăng nhập' : dirty ? 'Có thay đổi chưa lưu' : 'Đã lưu' }}</small>
            </div>
            <div class="studio-top-actions">
                <template v-if="!readOnly"
                    ><button class="studio-icon" title="Hoàn tác Ctrl+Alt+Z" :disabled="!canUndo" @click="undo"><Undo2 :size="17" /></button
                    ><button class="studio-icon" title="Làm lại Ctrl+Shift+Z" :disabled="!canRedo" @click="redo"><Redo2 :size="17" /></button
                    ><button class="studio-button" :disabled="disabled || saving" @click="save()">
                        <Save :size="15" />{{ saving ? 'Đang lưu…' : 'Lưu' }}</button
                    ><button class="studio-button" :disabled="disabled || sharing" @click="share">
                        <Share2 :size="15" />{{ sharing ? 'Đang tạo…' : 'Chia sẻ' }}
                    </button></template
                >
                <select v-model="format" class="studio-format" aria-label="Định dạng xuất">
                    <option value="image/png">PNG</option>
                    <option value="image/jpeg">JPEG</option></select
                ><button class="studio-button studio-primary" :disabled="disabled || exporting" @click="download">
                    <Download :size="15" />{{ exporting ? 'Đang xuất…' : 'Xuất ảnh' }}</button
                ><a :href="homeUrl" class="studio-button studio-home"><ArrowLeft :size="15" />Về web</a>
            </div>
        </header>
        <div class="studio-optionsbar">
            <button
                v-if="!readOnly"
                class="studio-button studio-mobile-toggle"
                @click="
                    leftOpen = !leftOpen;
                    rightOpen = false;
                "
            >
                <Menu :size="16" />Công cụ</button
            ><span class="studio-tool-name">{{
                spacePan
                    ? 'Bàn tay tạm thời'
                    : ({
                          hand: 'Bàn tay / Cuộn xem',
                          resize: 'Đổi kích thước',
                          zoom: 'Thu phóng · Alt để thu nhỏ',
                          text: 'Text · Click để thêm',
                          rectangle: 'Hình chữ nhật · Click để thêm',
                          ellipse: 'Ellipse · Click để thêm',
                      }[tool] ?? 'Di chuyển / Chọn')
            }}</span>
            <template v-if="!readOnly"
                ><label class="studio-check"><input v-model="snap" type="checkbox" />Bám lưới 10 px</label
                ><span class="studio-options-hint">Shift + click để chọn nhiều</span></template
            >
            <div class="studio-zoom">
                <button
                    class="studio-icon"
                    aria-label="Thu nhỏ"
                    @click="
                        zoom = Math.max(0.08, zoom / 1.2);
                        fitMode = false;
                    "
                >
                    <ZoomOut :size="15" /></button
                ><input
                    :value="Math.round(zoom * 100)"
                    aria-label="Thu phóng phần trăm"
                    type="number"
                    min="8"
                    max="200"
                    @change="
                        zoom = Math.max(0.08, Math.min(2, inputNumber($event) / 100));
                        fitMode = false;
                    "
                /><span>%</span
                ><button
                    class="studio-icon"
                    aria-label="Phóng to"
                    @click="
                        zoom = Math.min(2, zoom * 1.2);
                        fitMode = false;
                    "
                >
                    <ZoomIn :size="15" /></button
                ><button class="studio-button" @click="fit"><Maximize2 :size="14" />Vừa khung</button>
            </div>
            <button
                v-if="!readOnly"
                class="studio-button studio-mobile-toggle"
                @click="
                    rightOpen = !rightOpen;
                    leftOpen = false;
                "
            >
                <Layers :size="16" />Layer
            </button>
        </div>
        <div class="studio-body">
            <nav v-if="!readOnly" class="studio-toolrail" aria-label="Công cụ chỉnh sửa">
                <button :class="{ active: tool === 'select' }" title="Chọn / Di chuyển (V)" aria-label="Chọn và di chuyển" @click="tool = 'select'">
                    <Move :size="20" /></button
                ><button :class="{ active: tool === 'hand' }" title="Bàn tay (H)" aria-label="Bàn tay" @click="tool = 'hand'">
                    <Hand :size="20" /></button
                ><button :class="{ active: tool === 'resize' }" title="Đổi kích thước (Ctrl+T)" aria-label="Đổi kích thước" @click="tool = 'resize'">
                    <Maximize2 :size="20" />
                </button>
                <button
                    :class="{ active: tool === 'zoom' }"
                    title="Thu phóng (Z), Alt + click để thu nhỏ"
                    aria-label="Công cụ thu phóng"
                    @click="tool = 'zoom'"
                >
                    <ZoomIn :size="20" />
                </button>
                <span></span
                ><button title="Thêm text (T)" aria-label="Thêm text" :disabled="texts.length >= 10" @click="tool = 'text'">
                    <Type :size="20" /></button
                ><button title="Thêm khối item" aria-label="Thêm khối item" :disabled="groupInputs.length >= 10" @click="addGroup">
                    <Layers :size="20" /></button
                ><button
                    title="Hình chữ nhật"
                    aria-label="Thêm hình chữ nhật"
                    :disabled="shapes.length >= 10"
                    @click="
                        tool = 'rectangle';
                        shapeTool = 'rectangle';
                    "
                >
                    <Square :size="20" /></button
                ><button
                    title="Ellipse"
                    aria-label="Thêm ellipse"
                    :disabled="shapes.length >= 10"
                    @click="
                        tool = 'ellipse';
                        shapeTool = 'ellipse';
                    "
                >
                    <Circle :size="20" /></button
                ><button title="Nhập ảnh PNG/JPEG/WebP" aria-label="Nhập ảnh" :disabled="imported.length >= 3" @click="uploadInput?.click()">
                    <ImagePlus :size="20" />
                </button>
                <span></span
                ><button title="Nhân bản Ctrl+J" aria-label="Nhân bản" :disabled="!active || active.locked" @click="duplicate">
                    <Copy :size="18" /></button
                ><button title="Xóa / Ẩn Delete" aria-label="Xóa layer đã chọn" :disabled="!selectedIds.length" @click="removeSelection">
                    <Trash2 :size="18" />
                </button>
            </nav>
            <aside v-if="!readOnly" class="studio-left studio-panel" :class="{ 'is-open': leftOpen }">
                <div class="studio-panel-heading">
                    <span>CÔNG CỤ & DỰ ÁN</span
                    ><button class="studio-icon studio-mobile-toggle" aria-label="Đóng công cụ" @click="leftOpen = false"><X :size="17" /></button>
                </div>
                <section class="studio-section">
                    <h2>Tài liệu</h2>
                    <label>Cname<input v-model="documentSettings.cname" maxlength="100" placeholder="kztiger" /></label>
                    <p class="studio-help">NRO - Cname - tienichnro.com</p>
                    <label class="studio-color">Nền ảnh<input v-model="documentSettings.background" type="color" /></label>
                    <div class="studio-field-grid">
                        <label
                            >Rộng nền<input
                                :value="dimensions.width"
                                type="number"
                                min="64"
                                max="6000"
                                @change="setBackgroundSize(inputNumber($event), dimensions.height)"
                        /></label>
                        <label
                            >Cao nền<input
                                :value="dimensions.height"
                                type="number"
                                min="64"
                                max="6000"
                                @change="setBackgroundSize(dimensions.width, inputNumber($event))"
                        /></label>
                    </div>
                    <button class="studio-button studio-wide" @click="fitBackground">Khít các ảnh</button>
                    <p class="studio-help">Kéo chấm ở viền nền để đổi kích thước. Giữ Shift khi kéo góc ảnh để giữ tỉ lệ.</p>
                    <p class="studio-help">
                        {{ dimensions.width }} × {{ dimensions.height }} px · {{ account.capture_urls ? 'Ảnh của tài khoản' : 'Ảnh capture demo' }}
                    </p>
                </section>
                <section class="studio-section">
                    <h2>Thêm đối tượng</h2>
                    <div class="studio-action-grid">
                        <button class="studio-button" :disabled="texts.length >= 10" @click="addText"><Type :size="16" />Text</button
                        ><button class="studio-button" :disabled="groupInputs.length >= 10" @click="addGroup"><Layers :size="16" />Item</button
                        ><button class="studio-button" :disabled="shapes.length >= 10" @click="addShape('rectangle')">
                            <Square :size="16" />Hình</button
                        ><button class="studio-button" :disabled="imported.length >= 3" @click="uploadInput?.click()">
                            <ImagePlus :size="16" />Ảnh
                        </button>
                    </div>
                    <p class="studio-help">Ảnh nhập tối đa 1 MB, 4096 px. Tối đa 3 ảnh / dự án.</p>
                </section>
                <section class="studio-section">
                    <h2>Căn chỉnh nhiều layer</h2>
                    <div class="studio-action-grid">
                        <button
                            v-for="(label, mode) in alignNames"
                            :key="mode"
                            class="studio-button"
                            :disabled="selectedIds.length < 2"
                            @click="align(mode)"
                        >
                            {{ label }}
                        </button>
                    </div>
                    <p class="studio-help">Giữ Shift hoặc Ctrl để chọn nhiều lớp từ bảng Layer.</p>
                </section>
                <section class="studio-section">
                    <h2>Dự án</h2>
                    <button class="studio-button studio-wide" @click="exportProject"><FileJson :size="16" />Tải dự án JSON</button
                    ><button class="studio-button studio-wide" @click="projectInput?.click()"><Plus :size="16" />Mở dự án JSON</button
                    ><button class="studio-button studio-wide" @click="resetPositions">Xếp lại vị trí ban đầu</button
                    ><a :href="indexUrl" class="studio-button studio-wide"><ArrowLeft :size="15" />Danh sách account</a
                    ><button v-if="hasShare" class="studio-button studio-danger studio-wide" @click="revoke">Thu hồi link chia sẻ</button>
                </section>
                <section class="studio-section">
                    <p class="studio-help">
                        V: Di chuyển · H / giữ Space: Bàn tay<br />Z: Zoom · Alt + click: Thu nhỏ<br />T: Text · U / Shift+U: Hình / đổi hình<br />
                        Ctrl+T: Đổi kích thước · Enter: Kết thúc<br />Ctrl+J: Nhân bản · Ctrl+D: Bỏ chọn<br />Ctrl+A: Chọn tất cả · Delete: Xóa /
                        ẩn<br />
                        Ctrl+Z: Hoàn tác / làm lại bước cuối<br />Ctrl+Alt+Z: Lùi · Ctrl+Shift+Z: Tiến<br />Ctrl+[ / ]: Đổi thứ tự layer · thêm Shift:
                        Đầu / cuối<br />
                        Ctrl+S: Lưu · Ctrl+Shift+S: Xuất ảnh<br />Ctrl+0: Vừa khung · Ctrl+1: 100%<br />Ctrl+ + / −: Zoom · Mũi tên: 1 px · Shift: 10
                        px
                    </p>
                </section>
            </aside>
            <main class="studio-workspace">
                <div v-if="message || assetError || rowSourceError" class="studio-notice" role="status" aria-live="polite">
                    <span>{{ assetError || rowSourceError || message }}</span
                    ><button v-if="assetError" @click="loadAssets">Thử lại</button
                    ><button v-else class="studio-icon" aria-label="Đóng thông báo" @click="message = ''"><X :size="15" /></button>
                </div>
                <div v-if="loading" class="studio-loading" role="status">Đang tải ảnh…</div>
                <div
                    ref="viewport"
                    class="studio-viewport"
                    :class="spacePan || tool === 'hand' || (readOnly && tool !== 'zoom') ? 'is-hand' : tool === 'zoom' ? 'is-zoom' : 'is-select'"
                >
                    <div class="studio-artboard" :style="{ width: `${stageSize.width * zoom}px`, height: `${stageSize.height * zoom}px` }">
                        <canvas
                            ref="canvas"
                            :style="{ width: `${stageSize.width * zoom}px`, height: `${stageSize.height * zoom}px`, touchAction: 'none' }"
                            role="img"
                            aria-label="Canvas chỉnh ảnh: kéo layer, giữ Shift chọn nhiều; dùng công cụ và bảng Layer để chỉnh sửa"
                            @pointerdown="startPointer"
                            @pointermove="movePointer"
                            @pointerup="stopPointer"
                            @pointercancel="stopPointer"
                            @lostpointercapture="stopPointer"
                        />
                        <div
                            v-if="!readOnly"
                            class="studio-background-bounds"
                            :style="{ width: `${dimensions.width * zoom}px`, height: `${dimensions.height * zoom}px` }"
                        >
                            <button
                                v-for="handle in ['e', 's', 'se']"
                                :key="handle"
                                class="studio-resize-dot"
                                :class="`handle-${handle}`"
                                :aria-label="`Đổi kích thước nền ${handle}`"
                                title="Kéo đổi kích thước nền"
                                @pointerdown.stop="startResize($event, handle, true)"
                                @pointermove.stop="movePointer"
                                @pointerup.stop="stopPointer"
                                @pointercancel.stop="stopPointer"
                                @lostpointercapture="stopPointer"
                            />
                        </div>
                        <div v-if="!readOnly && active && !active.hidden" class="studio-selection-bounds" :style="selectionStyle()">
                            <button
                                v-for="handle in active.locked ? [] : RESIZE_HANDLES"
                                :key="handle.id"
                                class="studio-resize-dot"
                                :class="`handle-${handle.id}`"
                                :aria-label="`Đổi kích thước ${active.label} ${handle.id}`"
                                title="Kéo đổi kích thước · Shift giữ tỉ lệ"
                                @pointerdown.stop="startResize($event, handle.id)"
                                @pointermove.stop="movePointer"
                                @pointerup.stop="stopPointer"
                                @pointercancel.stop="stopPointer"
                                @lostpointercapture="stopPointer"
                            />
                        </div>
                    </div>
                </div>
            </main>
            <aside v-if="!readOnly" class="studio-right studio-panel" :class="{ 'is-open': rightOpen }">
                <div class="studio-panel-heading">
                    <div class="studio-tabs">
                        <button :class="{ active: tab === 'properties' }" @click="tab = 'properties'">Thuộc tính</button
                        ><button :class="{ active: tab === 'layers' }" @click="tab = 'layers'">Layers</button>
                    </div>
                    <button class="studio-icon studio-mobile-toggle" aria-label="Đóng bảng layer" @click="rightOpen = false"><X :size="17" /></button>
                </div>
                <div v-if="tab === 'properties'" class="studio-properties">
                    <section v-if="active" class="studio-section">
                        <h2>{{ active.label }}</h2>
                        <label
                            >Tên layer<input
                                :value="layerSettings[selected]?.name ?? ''"
                                maxlength="60"
                                :placeholder="active.label"
                                @input="setLayer(selected, { name: ($event.target as HTMLInputElement).value })"
                        /></label>
                        <div class="studio-field-grid">
                            <label
                                >X<input
                                    :value="Math.round(active.x)"
                                    :disabled="active.locked"
                                    type="number"
                                    min="0"
                                    max="6000"
                                    @change="coordinate('x', $event)" /></label
                            ><label
                                >Y<input
                                    :value="Math.round(active.y)"
                                    :disabled="active.locked"
                                    type="number"
                                    min="48"
                                    max="6000"
                                    @change="coordinate('y', $event)" /></label
                            ><label
                                >Tỷ lệ %<input
                                    :value="Math.round(active.scale * 100)"
                                    :disabled="active.locked"
                                    type="number"
                                    min="20"
                                    max="300"
                                    @change="setLayer(selected, { scale: Math.max(0.2, Math.min(3, inputNumber($event) / 100)) })" /></label
                            ><label
                                >Xoay °<input
                                    :value="active.rotation"
                                    :disabled="active.locked"
                                    type="number"
                                    min="-180"
                                    max="180"
                                    @change="setLayer(selected, { rotation: Math.max(-180, Math.min(180, inputNumber($event))) })"
                            /></label>
                        </div>
                        <label
                            >Độ đậm {{ Math.round(active.opacity * 100) }}%<input
                                :value="active.opacity"
                                type="range"
                                min="0"
                                max="1"
                                step=".01"
                                :disabled="active.locked"
                                @input="setLayer(selected, { opacity: inputNumber($event) })"
                        /></label>
                    </section>
                    <p v-else class="studio-empty">Chọn một layer trên canvas hoặc trong danh sách để chỉnh thuộc tính.</p>
                    <section v-if="activeText" class="studio-section">
                        <h2>Nội dung text</h2>
                        <label>Chữ<textarea v-model="activeText.content" rows="5" maxlength="1000" :disabled="active?.locked" /></label>
                        <div class="studio-field-grid">
                            <label
                                >Cỡ chữ<input
                                    v-model.number="activeText.font_size"
                                    type="number"
                                    min="12"
                                    max="72"
                                    :disabled="active?.locked" /></label
                            ><label
                                >Độ rộng<input v-model.number="activeText.width" type="number" min="120" max="1000" :disabled="active?.locked"
                            /></label>
                        </div>
                        <label class="studio-color">Màu chữ<input v-model="activeText.color" type="color" :disabled="active?.locked" /></label
                        ><label class="studio-check"><input v-model="activeText.bold" type="checkbox" :disabled="active?.locked" />In đậm</label>
                    </section>
                    <section v-if="activeGroup" class="studio-section">
                        <h2>Khối item nổi bật</h2>
                        <label>Tên khối<input v-model="activeGroup.name" maxlength="60" :disabled="active?.locked" /></label
                        ><label
                            >Danh sách ID<input v-model="activeGroup.input" maxlength="500" placeholder="1,5,11,14" :disabled="active?.locked"
                        /></label>
                        <p class="studio-error" role="alert">{{ idError(activeGroup.input) }}</p>
                        <p class="studio-help">
                            <span v-for="(range, index) in itemRanges" :key="range.file"
                                >{{ index ? ' · ' : '' }}{{ ['Hành trang', 'Trang bị', 'Rương NPC 3', 'Rương NPC 84'][index] }} #{{ range.start }}–{{
                                    range.end
                                }}</span
                            >
                        </p>
                        <label
                            >Số cột<select v-model.number="activeGroup.columns" :disabled="active?.locked">
                                <option v-for="column in 6" :key="column" :value="column">{{ column }} cột</option>
                            </select></label
                        ><label class="studio-check"
                            ><input v-model="activeGroup.show_details" type="checkbox" :disabled="active?.locked" />Dùng ảnh detail</label
                        >
                    </section>
                    <section v-if="activeShape" class="studio-section">
                        <h2>Hình vẽ</h2>
                        <div class="studio-field-grid">
                            <label
                                >Rộng<input v-model.number="activeShape.width" type="number" min="20" max="2000" :disabled="active?.locked" /></label
                            ><label
                                >Cao<input v-model.number="activeShape.height" type="number" min="20" max="2000" :disabled="active?.locked"
                            /></label>
                        </div>
                        <label class="studio-color">Màu nền<input v-model="activeShape.fill" type="color" :disabled="active?.locked" /></label
                        ><label class="studio-color">Màu viền<input v-model="activeShape.stroke" type="color" :disabled="active?.locked" /></label
                        ><label
                            >Độ dày viền<input v-model.number="activeShape.stroke_width" type="number" min="0" max="12" :disabled="active?.locked"
                        /></label>
                    </section>
                </div>
                <div class="studio-layers-heading">
                    <b
                        >LAYERS <small>{{ blocks.length }}</small></b
                    >
                    <div>
                        <button class="studio-icon" aria-label="Đưa layer lên" :disabled="!active" @click="reorder(1)">
                            <ChevronUp :size="16" /></button
                        ><button class="studio-icon" aria-label="Đưa layer xuống" :disabled="!active" @click="reorder(-1)">
                            <ChevronDown :size="16" /></button
                        ><button class="studio-icon" aria-label="Nhân bản" :disabled="!active" @click="duplicate"><Copy :size="14" /></button
                        ><button class="studio-icon" aria-label="Xóa layer" :disabled="!active" @click="removeSelection">
                            <Trash2 :size="14" />
                        </button>
                    </div>
                </div>
                <div ref="layerList" class="studio-layer-list" role="list" aria-label="Danh sách layer từ trên xuống">
                    <div
                        v-for="block in [...blocks].reverse()"
                        :key="block.key"
                        class="studio-layer-row"
                        :class="{
                            selected: selectedIds.includes(block.key),
                            muted: block.hidden,
                            'is-dragging': layerDrag?.moved && layerDrag.source === block.key,
                            'drop-before':
                                layerDrag?.moved &&
                                layerDrag.target === block.key &&
                                layerDrag.source !== block.key &&
                                layerDrag.placement === 'before',
                            'drop-after':
                                layerDrag?.moved &&
                                layerDrag.target === block.key &&
                                layerDrag.source !== block.key &&
                                layerDrag.placement === 'after',
                        }"
                        :data-layer-key="block.key"
                        role="listitem"
                    >
                        <button
                            class="studio-layer-grip"
                            :aria-label="`Kéo để đổi thứ tự ${block.label}`"
                            title="Kéo lên / xuống để đổi thứ tự layer"
                            @pointerdown.stop="startLayerDrag($event, block.key)"
                            @pointermove="moveLayerDrag"
                            @pointerup="endLayerDrag"
                            @pointercancel="cancelLayerDrag"
                            @lostpointercapture="cancelLayerDrag"
                            @click.stop.prevent
                        >
                            <GripVertical :size="15" />
                        </button>
                        <button
                            class="studio-icon"
                            :aria-label="block.hidden ? `Hiện ${block.label}` : `Ẩn ${block.label}`"
                            :aria-pressed="!block.hidden"
                            @click="setLayer(block.key, { hidden: !block.hidden })"
                        >
                            <EyeOff v-if="block.hidden" :size="15" /><Eye v-else :size="15" /></button
                        ><button class="studio-layer-name" @click="choose(block.key, $event.shiftKey || $event.ctrlKey || $event.metaKey)">
                            <span class="studio-layer-glyph">{{
                                block.key.startsWith('text-') ? 'T' : block.key.startsWith('shape-') ? '▧' : '▣'
                            }}</span
                            ><span
                                >{{ block.label }}<small>{{ Math.round(block.width) }} × {{ Math.round(block.height) }} px</small></span
                            ></button
                        ><button
                            class="studio-icon"
                            :aria-label="block.locked ? `Mở khóa ${block.label}` : `Khóa ${block.label}`"
                            :aria-pressed="block.locked"
                            @click="setLayer(block.key, { locked: !block.locked })"
                        >
                            <Lock v-if="block.locked" :size="14" /><Unlock v-else :size="14" />
                        </button>
                    </div>
                </div>
            </aside>
        </div>
        <footer class="studio-status">
            <span><i :class="dirty ? 'is-dirty' : ''"></i>{{ readOnly ? 'Bản chia sẻ · Chỉ xem' : dirty ? 'Chưa lưu thay đổi' : 'Sẵn sàng' }}</span
            ><span>{{ selectedIds.length ? `${selectedIds.length} layer đã chọn` : 'Chưa chọn layer' }}</span
            ><span>{{ dimensions.width }} × {{ dimensions.height }} px · {{ Math.round(zoom * 100) }}%</span><a :href="homeUrl">tienichnro.com ↗</a>
        </footer>
        <input ref="uploadInput" type="file" accept="image/png,image/jpeg,image/webp" hidden @change="importImage" /><input
            ref="projectInput"
            type="file"
            accept="application/json,.json"
            hidden
            @change="importProject"
        />
        <div v-if="showShare" class="studio-modal-backdrop" @click.self="showShare = false">
            <section class="studio-modal" role="dialog" aria-modal="true" aria-labelledby="share-title">
                <div class="studio-panel-heading">
                    <h2 id="share-title">Chia sẻ bản chỉ xem</h2>
                    <button class="studio-icon" aria-label="Đóng" @click="showShare = false"><X :size="18" /></button>
                </div>
                <p>
                    Link có hiệu lực 7 ngày. Bản chia sẻ giữ bố cục tại thời điểm tạo link; người xem không thấy tài khoản/mật khẩu game và không thể
                    lưu đè.
                </p>
                <label>Link chia sẻ<input :value="shareUrl" readonly @focus="($event.target as HTMLInputElement).select()" /></label>
                <div class="studio-modal-actions">
                    <button class="studio-button studio-primary" @click="copyShare"><Copy :size="15" />Sao chép</button
                    ><a :href="shareUrl" target="_blank" rel="noreferrer" class="studio-button">Mở bản chia sẻ ↗</a
                    ><button class="studio-button studio-danger" @click="revoke">Thu hồi link</button>
                </div>
            </section>
        </div>
    </div>
</template>
