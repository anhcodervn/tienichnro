<template>
    <div>
        <textarea
            v-if="useFallback"
            v-model="fallbackContent"
            class="w-full rounded-[10px] border border-slate-200 bg-white px-4 py-3 text-sm leading-7 text-slate-700 outline-none transition focus:border-slate-900"
            :style="{ minHeight: `${height}px` }"
            placeholder="Nhập nội dung HTML hoặc văn bản tại đây..."
            @input="handleFallbackInput"
        />
        <div v-else ref="editorContainer"></div>
    </div>
</template>

<script lang="ts">
import { uploadEditorImageFile } from '@/utils/editor-image-upload';
import Swal from 'sweetalert2';
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';

type EditorInlineNode = {
    text?: string;
    bold?: boolean;
    italic?: boolean;
    underline?: boolean;
    strike?: boolean;
    color?: string;
    background?: string;
    href?: string;
    target?: '_blank' | '_self';
};

type EditorContentNode = {
    type?: string;
    tag?: string;
    src?: string;
    alt?: string;
    width?: number | null;
    height?: number | null;
    ordered?: boolean;
    level?: number;
    children?: EditorInlineNode[] | EditorContentNode[];
    items?: EditorInlineNode[][];
};

type TinyMceBlobInfo = {
    blob: () => Blob;
    filename: () => string;
};

declare global {
    interface Window {
        tinymce?: {
            init: (config: Record<string, unknown>) => void;
            remove: (editor: unknown) => void;
        };
    }
}

export default {
    name: 'TinyMceEditor',

    props: {
        value: {
            type: [Array, String],
            default: undefined,
        },
        modelValue: {
            type: [Array, String],
            default: undefined,
        },
        debounce: {
            type: Number,
            default: 800,
        },
        format: {
            type: String,
            default: 'json',
            validator: (value: string) => ['json', 'html'].includes(value),
        },
        height: {
            type: Number,
            default: 500,
        },
        allowImages: {
            type: Boolean,
            default: true,
        },
    },

    emits: ['update:value', 'update:modelValue'],

    setup(
        props: {
            value?: unknown[] | string;
            modelValue?: unknown[] | string;
            debounce: number;
            format: 'json' | 'html';
            height: number;
            allowImages: boolean;
        },
        { emit }: { emit: (event: 'update:value' | 'update:modelValue', value: EditorContentNode[] | string) => void },
    ) {
        const editorContainer = ref<HTMLElement | null>(null);
        const useFallback = ref(false);
        const fallbackContent = ref('');
        let editorInstance: {
            getContent: () => string;
            setContent: (value: string) => void;
            on: (event: string, callback: () => void) => void;
        } | null = null;
        let isApplyingExternalValue = false;
        let lastEmittedFingerprint: string | null = null;
        let saveTimer: ReturnType<typeof setTimeout> | null = null;
        let uploadedImageSyncTimer: ReturnType<typeof setTimeout> | null = null;

        const getTinyMce = () =>
            typeof window !== 'undefined' && window.tinymce && typeof window.tinymce.init === 'function' ? window.tinymce : null;

        const currentValue = () => props.modelValue ?? props.value ?? (props.format === 'html' ? '' : []);

        const valueFingerprint = (value: unknown): string => {
            if (typeof value === 'string') {
                return `html:${value}`;
            }

            try {
                return `json:${JSON.stringify(value)}`;
            } catch {
                return 'json:[]';
            }
        };

        const emitValue = (value: EditorContentNode[] | string) => {
            lastEmittedFingerprint = valueFingerprint(value);
            emit('update:value', value);
            emit('update:modelValue', value);
        };

        const emitDebounced = (value: EditorContentNode[] | string) => {
            if (saveTimer) {
                clearTimeout(saveTimer);
            }

            if (props.debounce <= 0) {
                emitValue(value);
                return;
            }

            saveTimer = setTimeout(() => {
                emitValue(value);
            }, props.debounce);
        };

        const hasPendingLocalImages = (html: string): boolean => /<img\b[^>]*\bsrc=["'](?:data:image\/|blob:)/i.test(html);

        const emitCurrentEditorContent = (): void => {
            if (!editorInstance || isApplyingExternalValue) {
                return;
            }

            const html = editorInstance.getContent();
            if (hasPendingLocalImages(html)) {
                return;
            }

            emitDebounced(htmlToValue(html));
        };

        const syncUploadedImageContent = (attempt = 0): void => {
            if (!editorInstance) {
                return;
            }

            if (hasPendingLocalImages(editorInstance.getContent()) && attempt < 40) {
                uploadedImageSyncTimer = window.setTimeout(() => syncUploadedImageContent(attempt + 1), 50);
                return;
            }

            emitCurrentEditorContent();
        };

        const commitUploadedImage = (
            uploadedUrl: string,
            callback: (url: string, meta?: Record<string, string>) => void,
            meta?: Record<string, string>,
        ): void => {
            callback(uploadedUrl, meta);
            syncUploadedImageContent();
        };

        const handleImageUpload = (
            blobInfo: TinyMceBlobInfo,
            success: (url: string) => void,
            failure: (message: string) => void,
            progress?: (percent: number) => void,
        ): void => {
            uploadEditorImageFile(blobInfo.blob(), blobInfo.filename(), progress)
                .then((uploadedUrl) => {
                    commitUploadedImage(uploadedUrl, success);
                })
                .catch((error: unknown) => {
                    failure(error instanceof Error ? error.message : 'Không thể tải ảnh lên. Vui lòng thử lại.');
                });
        };

        const pickAndUploadImage = (callback: (url: string, meta?: Record<string, string>) => void): void => {
            const input = document.createElement('input');
            input.type = 'file';
            input.accept = 'image/jpeg,image/png,image/webp';

            input.addEventListener('change', () => {
                const file = input.files?.[0];

                if (!file) {
                    return;
                }

                uploadEditorImageFile(file, file.name)
                    .then((uploadedUrl) => {
                        commitUploadedImage(uploadedUrl, callback, {
                            alt: file.name.replace(/\.[^.]+$/, ''),
                        });
                    })
                    .catch((error: unknown) => {
                        void Swal.fire('', error instanceof Error ? error.message : 'Không thể tải ảnh lên. Vui lòng thử lại.', 'error');
                    });
            });

            input.click();
        };

        const normalizeValue = (value: unknown): EditorContentNode[] => (Array.isArray(value) ? (value as EditorContentNode[]) : []);
        const valueToHtml = (value: unknown): string => {
            if (props.format === 'html') {
                return typeof value === 'string' ? value : '';
            }

            const normalizedValue = normalizeValue(value);
            return normalizedValue.length > 0 ? jsonToHtml(normalizedValue) : '';
        };
        const htmlToValue = (html: string): EditorContentNode[] | string => (props.format === 'html' ? html : htmlToJson(html));

        function htmlToJson(html: string): EditorContentNode[] {
            const root = document.createElement('div');
            root.innerHTML = html;

            return parseNodes(root);
        }

        function parseNodes(parent: HTMLElement): EditorContentNode[] {
            const nodes: EditorContentNode[] = [];

            parent.childNodes.forEach((node) => {
                if (node.nodeType === Node.TEXT_NODE) {
                    if (node.textContent?.trim()) {
                        nodes.push({
                            type: 'paragraph',
                            children: parseInline(node),
                        });
                    }
                    return;
                }

                if (node.nodeType !== Node.ELEMENT_NODE) {
                    return;
                }

                const element = node as HTMLElement;
                const tag = element.tagName.toLowerCase();

                if (tag === 'img') {
                    nodes.push({
                        type: 'image',
                        src: element.getAttribute('src') ?? '',
                        alt: element.getAttribute('alt') ?? '',
                        width: element.getAttribute('width') ? Number(element.getAttribute('width')) : null,
                        height: element.getAttribute('height') ? Number(element.getAttribute('height')) : null,
                    });
                    return;
                }

                if (['article', 'div', 'section'].includes(tag)) {
                    nodes.push({
                        type: 'container',
                        tag,
                        children: parseNodes(element),
                    });
                    return;
                }

                parseBlock(element, nodes);
            });

            return nodes;
        }

        function parseBlock(node: HTMLElement, blocks: EditorContentNode[]) {
            const tag = node.tagName.toLowerCase();

            if (tag === 'p') {
                const images = Array.from(node.children).filter((element) => element.tagName.toLowerCase() === 'img');

                if (images.length > 0 && node.textContent?.trim() === '') {
                    images.forEach((image) => {
                        blocks.push({
                            type: 'image',
                            src: image.getAttribute('src') ?? '',
                            alt: image.getAttribute('alt') ?? '',
                            width: image.getAttribute('width') ? Number(image.getAttribute('width')) : null,
                            height: image.getAttribute('height') ? Number(image.getAttribute('height')) : null,
                        });
                    });
                    return;
                }

                blocks.push({
                    type: 'paragraph',
                    children: parseInline(node),
                });
                return;
            }

            if (/h[1-6]/.test(tag)) {
                blocks.push({
                    type: 'heading',
                    level: Number(tag[1]),
                    children: parseInline(node),
                });
                return;
            }

            if (tag === 'img') {
                blocks.push({
                    type: 'image',
                    src: node.getAttribute('src') ?? '',
                    alt: node.getAttribute('alt') ?? '',
                    width: node.getAttribute('width') ? Number(node.getAttribute('width')) : null,
                    height: node.getAttribute('height') ? Number(node.getAttribute('height')) : null,
                });
                return;
            }

            if (tag === 'ul' || tag === 'ol') {
                blocks.push({
                    type: 'list',
                    ordered: tag === 'ol',
                    items: Array.from(node.children)
                        .filter((element) => element.tagName.toLowerCase() === 'li')
                        .map((element) => parseInline(element as HTMLElement)),
                });
                return;
            }

            const blockCountBeforeChildren = blocks.length;

            node.childNodes.forEach((child) => {
                if (child.nodeType === Node.ELEMENT_NODE) {
                    parseBlock(child as HTMLElement, blocks);
                }
            });

            if (blocks.length === blockCountBeforeChildren && node.textContent?.trim()) {
                blocks.push({
                    type: 'paragraph',
                    children: parseInline(node),
                });
            }
        }

        function parseInline(node: Node, style: EditorInlineNode = {}): EditorInlineNode[] {
            if (node.nodeType === Node.TEXT_NODE) {
                if (!node.textContent) {
                    return [];
                }

                return [{ text: node.textContent, ...style }];
            }

            if (node.nodeType !== Node.ELEMENT_NODE) {
                return [];
            }

            const element = node as HTMLElement;
            const tag = element.tagName.toLowerCase();
            const next: EditorInlineNode = { ...style };

            if (tag === 'strong' || tag === 'b') next.bold = true;
            if (tag === 'em' || tag === 'i') next.italic = true;
            if (tag === 'u') next.underline = true;
            if (tag === 's' || tag === 'strike') next.strike = true;

            if (tag === 'a') {
                const href = normalizeSafeHref(element.getAttribute('href'));
                const target = element.getAttribute('target');

                if (href) {
                    next.href = href;
                    if (target === '_blank' || target === '_self') {
                        next.target = target;
                    }
                }
            }

            if (element.style?.color) next.color = element.style.color;
            if (element.style?.backgroundColor) next.background = element.style.backgroundColor;

            if (tag === 'br') {
                return [{ text: '\n', ...next }];
            }

            return Array.from(element.childNodes).flatMap((child) => parseInline(child, next));
        }

        function jsonToHtml(nodes: unknown): string {
            if (!Array.isArray(nodes)) {
                void Swal.fire('', 'Nội dung không hợp lệ, không thể tải dữ liệu.', 'error');
                return '';
            }

            return (nodes as EditorContentNode[]).map(renderNode).join('');
        }

        function applyEditorValue(value: unknown): void {
            const nextHtml = valueToHtml(value);

            if (useFallback.value) {
                if (fallbackContent.value !== nextHtml) {
                    fallbackContent.value = nextHtml;
                }
                return;
            }

            if (!editorInstance) {
                return;
            }

            if (nextHtml === editorInstance.getContent()) {
                return;
            }

            isApplyingExternalValue = true;
            editorInstance.setContent(nextHtml);

            queueMicrotask(() => {
                isApplyingExternalValue = false;
            });
        }

        function renderNode(node: EditorContentNode): string {
            if (node.type === 'container') {
                const children = Array.isArray(node.children) ? (node.children as EditorContentNode[]) : [];
                const tag = ['article', 'div', 'section'].includes(node.tag ?? '') ? node.tag : 'div';
                return `<${tag}>${children.map(renderNode).join('')}</${tag}>`;
            }

            return renderBlock(node);
        }

        function renderBlock(block: EditorContentNode): string {
            switch (block.type) {
                case 'heading': {
                    const level = Math.max(1, Math.min(Number(block.level) || 2, 6));
                    return `<h${level}>${renderInline(block.children as EditorInlineNode[] | undefined)}</h${level}>`;
                }
                case 'paragraph':
                    return `<p>${renderInline(block.children as EditorInlineNode[] | undefined)}</p>`;
                case 'image':
                    return `<img src="${escapeHtml(block.src ?? '')}" alt="${escapeHtml(block.alt ?? '')}" />`;
                case 'list': {
                    const tag = block.ordered ? 'ol' : 'ul';
                    return `<${tag}>${(block.items ?? []).map((item) => `<li>${renderInline(item)}</li>`).join('')}</${tag}>`;
                }
                default:
                    return '';
            }
        }

        function renderInline(children: EditorInlineNode[] = []): string {
            return children
                .map((item) => {
                    let text = escapeHtml(item.text ?? '').replace(/\n/g, '<br>');

                    if (item.bold) text = `<strong>${text}</strong>`;
                    if (item.italic) text = `<em>${text}</em>`;
                    if (item.underline) text = `<u>${text}</u>`;
                    if (item.strike) text = `<s>${text}</s>`;

                    let style = '';
                    if (item.color) style += `color:${item.color};`;
                    if (item.background) style += `background-color:${item.background};`;

                    if (style) {
                        text = `<span style="${style}">${text}</span>`;
                    }

                    const href = normalizeSafeHref(item.href);
                    if (href) {
                        const target = item.target === '_blank' || item.target === '_self' ? ` target="${item.target}"` : '';
                        const rel = item.target === '_blank' ? ' rel="noopener noreferrer"' : '';
                        text = `<a href="${escapeHtml(href)}"${target}${rel}>${text}</a>`;
                    }

                    return text;
                })
                .join('');
        }

        function normalizeSafeHref(value: string | null | undefined): string | null {
            if (!value) {
                return null;
            }

            const href = value.trim();
            if (!href || href.length > 2048 || /[\u0000-\u0020\u007f\\]/.test(href) || href.startsWith('//')) {
                return null;
            }

            if (href.startsWith('/') || href.startsWith('#')) {
                return href;
            }

            if (/^(?:https?:\/\/|mailto:|tel:)/i.test(href)) {
                return href;
            }

            if (/^(?:www\.)?(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}(?::\d{1,5})?(?:[/?#].*)?$/i.test(href)) {
                return `https://${href}`;
            }

            return null;
        }

        function escapeHtml(value: string): string {
            return value.replace(/[&<>"']/g, (character) => {
                const entities: Record<string, string> = {
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#039;',
                };

                return entities[character];
            });
        }

        function handleFallbackInput(): void {
            emitDebounced(htmlToValue(fallbackContent.value));
        }

        onMounted(() => {
            const tinymce = getTinyMce();

            if (!tinymce) {
                useFallback.value = true;
                fallbackContent.value = valueToHtml(currentValue());
                return;
            }

            tinymce.init({
                target: editorContainer.value,
                language: 'vi',
                language_url: '/assets/libs/tinymce/langs/vi.js',
                height: props.height,
                menubar: true,
                plugins: props.allowImages
                    ? [
                          'advlist autolink lists link image charmap print preview anchor',
                          'searchreplace visualblocks code fullscreen',
                          'insertdatetime media table paste code help wordcount',
                          'emoticons hr pagebreak nonbreaking toc',
                          'save autosave directionality textcolor',
                      ]
                    : [
                          'advlist autolink lists link charmap print preview anchor',
                          'searchreplace visualblocks code fullscreen',
                          'insertdatetime table paste code help wordcount',
                          'emoticons hr pagebreak nonbreaking toc',
                          'save autosave directionality textcolor',
                      ],
                toolbar: props.allowImages
                    ? [
                          'undo redo | formatselect | fontselect fontsizeselect | bold italic underline strikethrough | forecolor backcolor',
                          'alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image emoticons | table | code fullscreen preview | removeformat',
                      ]
                    : [
                          'undo redo | formatselect | fontselect fontsizeselect | bold italic underline strikethrough | forecolor backcolor',
                          'alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link emoticons | table | code fullscreen preview | removeformat',
                      ],
                ...(props.allowImages
                    ? {
                          paste_data_images: true,
                          automatic_uploads: true,
                          images_file_types: 'jpg,jpeg,png,webp',
                          images_reuse_filename: false,
                          images_upload_credentials: true,
                          images_upload_handler: handleImageUpload,
                          file_picker_types: 'image',
                          file_picker_callback: pickAndUploadImage,
                      }
                    : {}),
                setup(editor: typeof editorInstance) {
                    editorInstance = editor;

                    editor.on('input change keyup undo redo', () => {
                        emitCurrentEditorContent();
                    });
                },
                init_instance_callback(editor: typeof editorInstance) {
                    const initialHtml = valueToHtml(currentValue());
                    if (initialHtml) {
                        editor?.setContent(initialHtml);
                    }
                },
            });
        });

        onBeforeUnmount(() => {
            if (saveTimer) {
                clearTimeout(saveTimer);
            }

            if (uploadedImageSyncTimer) {
                clearTimeout(uploadedImageSyncTimer);
            }

            const tinymce = getTinyMce();
            if (editorInstance && tinymce) {
                tinymce.remove(editorInstance);
                editorInstance = null;
            }
        });

        watch(
            () => currentValue(),
            (value) => {
                const fingerprint = valueFingerprint(value);

                if (fingerprint === lastEmittedFingerprint) {
                    return;
                }

                lastEmittedFingerprint = null;
                applyEditorValue(value);
            },
            { deep: true },
        );

        return {
            editorContainer,
            fallbackContent,
            handleFallbackInput,
            useFallback,
        };
    },
};
</script>
