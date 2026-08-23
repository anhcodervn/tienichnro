import api from '@/config/axios';

type EditorInlineNode = {
    text?: string;
    bold?: boolean;
    italic?: boolean;
    underline?: boolean;
    strike?: boolean;
    color?: string;
    background?: string;
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
    [key: string]: unknown;
};

const isBase64ImageSource = (src: unknown): src is string => {
    return typeof src === 'string' && src.startsWith('data:image/');
};

const dataUrlToBlob = async (dataUrl: string): Promise<Blob> => {
    const response = await fetch(dataUrl);
    return response.blob();
};

const convertBlobToWebp = (blob: Blob): Promise<Blob> => {
    return new Promise((resolve) => {
        const img = new Image();
        const objectUrl = URL.createObjectURL(blob);

        img.onload = () => {
            const canvas = document.createElement('canvas');
            const context = canvas.getContext('2d');

            if (!context) {
                URL.revokeObjectURL(objectUrl);
                resolve(blob);
                return;
            }

            const maxDimension = 1800;
            const ratio = Math.min(maxDimension / img.width, maxDimension / img.height, 1);
            canvas.width = Math.max(1, Math.round(img.width * ratio));
            canvas.height = Math.max(1, Math.round(img.height * ratio));

            context.drawImage(img, 0, 0, canvas.width, canvas.height);

            canvas.toBlob(
                (webpBlob) => {
                    URL.revokeObjectURL(objectUrl);
                    resolve(webpBlob ?? blob);
                },
                'image/webp',
                0.82,
            );
        };

        img.onerror = () => {
            URL.revokeObjectURL(objectUrl);
            resolve(blob);
        };

        img.src = objectUrl;
    });
};

const extensionForMimeType = (mimeType: string): string => {
    if (mimeType === 'image/jpeg') {
        return 'jpg';
    }

    if (mimeType === 'image/png') {
        return 'png';
    }

    return 'webp';
};

const uploadErrorMessage = (error: unknown): string => {
    if (typeof error === 'object' && error !== null && 'response' in error) {
        const response = (error as { response?: { data?: { message?: unknown } } }).response;
        const message = response?.data?.message;

        if (typeof message === 'string' && message.trim() !== '') {
            return message;
        }
    }

    if (error instanceof Error && error.message.trim() !== '') {
        return error.message;
    }

    return 'Không thể tải ảnh lên. Vui lòng thử lại.';
};

export const uploadEditorImageFile = async (
    sourceBlob: Blob,
    originalName = 'editor-image',
    progress?: (percent: number) => void,
): Promise<string> => {
    if (!['image/jpeg', 'image/png', 'image/webp'].includes(sourceBlob.type)) {
        throw new Error('Chỉ hỗ trợ ảnh JPG, PNG hoặc WebP.');
    }

    if (sourceBlob.size > 10 * 1024 * 1024) {
        throw new Error('Ảnh tải lên không được vượt quá 10 MB.');
    }

    const optimizedBlob = await convertBlobToWebp(sourceBlob);
    const extension = extensionForMimeType(optimizedBlob.type);
    const basename = (originalName.replace(/\.[^.]+$/, '').trim() || 'editor-image').slice(0, 120);
    const file = new File([optimizedBlob], `${basename}.${extension}`, {
        type: optimizedBlob.type || 'image/webp',
    });
    const formData = new FormData();

    formData.append('image', file);
    formData.append('name', basename);

    try {
        const response = await api.post('/api/uploads/image', formData, {
            onUploadProgress: (event) => {
                if (progress && event.total) {
                    progress(Math.round((event.loaded / event.total) * 100));
                }
            },
        });

        const uploadedUrl = response.data?.data?.url;

        if (typeof uploadedUrl !== 'string' || uploadedUrl === '') {
            throw new Error('Không nhận được URL ảnh sau khi tải lên.');
        }

        return uploadedUrl;
    } catch (error) {
        throw new Error(uploadErrorMessage(error));
    }
};

const uploadEditorImage = async (dataUrl: string, cache: Map<string, string>): Promise<string> => {
    const cachedUrl = cache.get(dataUrl);

    if (cachedUrl) {
        return cachedUrl;
    }

    const sourceBlob = await dataUrlToBlob(dataUrl);
    const uploadedUrl = await uploadEditorImageFile(sourceBlob, `editor-image-${Date.now()}`);
    cache.set(dataUrl, uploadedUrl);

    return uploadedUrl;
};

const transformNode = async (node: EditorContentNode, cache: Map<string, string>): Promise<EditorContentNode> => {
    const clonedNode: EditorContentNode = {
        ...node,
    };

    if (isBase64ImageSource(clonedNode.src)) {
        clonedNode.src = await uploadEditorImage(clonedNode.src, cache);
    }

    if (Array.isArray(clonedNode.children) && clonedNode.children.length > 0) {
        const looksLikeContentNodes = clonedNode.children.some(
            (child) => typeof child === 'object' && child !== null && ('type' in child || 'src' in child || 'children' in child),
        );

        if (looksLikeContentNodes) {
            clonedNode.children = await Promise.all((clonedNode.children as EditorContentNode[]).map((child) => transformNode(child, cache)));
        }
    }

    return clonedNode;
};

export const uploadEditorImages = async (nodes: unknown[]): Promise<unknown[]> => {
    const normalizedNodes = Array.isArray(nodes) ? (nodes as EditorContentNode[]) : [];
    const cache = new Map<string, string>();

    return Promise.all(normalizedNodes.map((node) => transformNode(node, cache)));
};
