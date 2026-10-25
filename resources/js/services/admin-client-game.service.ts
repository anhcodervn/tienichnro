import api from '@/config/axios';

export type ClientGameKind = 'original' | 'update';
export type ClientGameRelease = {
    version: string;
    name: string;
    size: number;
    sha256: string;
    uploaded_at: string;
    available: boolean;
    download_url: string;
};
export type ClientGameCatalog = Record<ClientGameKind, ClientGameRelease | null>;
const endpoint = '/api/admin-api/license/client-game';

export const adminClientGame = {
    async catalog(): Promise<ClientGameCatalog> {
        return (await api.get(endpoint)).data.data;
    },
    async upload(kind: ClientGameKind, version: string, file: File, onProgress: (value: number) => void): Promise<ClientGameCatalog> {
        const body = new FormData();
        body.append('version', version);
        body.append('zip', file);
        return (
            await api.post(`${endpoint}/${kind}`, body, {
                onUploadProgress: (event) => {
                    if (event.total) onProgress(Math.round((event.loaded / event.total) * 100));
                },
            })
        ).data.data;
    },
};
