import { clientSystemSettingService } from '@/services/client-system-setting.service';
import type { SystemSettingType } from '@/types/setting.type';
import { ref } from 'vue';

const defaultSettings: SystemSettingType = {
    site_name: 'Nạp Carot',
    site_domain: '',
    site_description: '',
    site_active: true,
    allow_register: true,
    light_logo: '',
    dark_logo: '',
    favicon: '',
    og_image: '',
    support_email: '',
    hotline: '',
    address: '',
    facebook: '',
    zalo: '',
    youtube: '',
    meta_title: '',
    meta_description: '',
    robots: 'index,follow',
    gtm_id: '',
    meta_pixel_id: '',
};

const settings = ref<SystemSettingType>({ ...defaultSettings });
const loaded = ref(false);
const loading = ref(false);

export const useSystemSetting = () => {
    const fetchSettings = async (force = false): Promise<SystemSettingType> => {
        if (loaded.value && !force) {
            return settings.value;
        }

        if (loading.value) {
            return settings.value;
        }

        loading.value = true;

        try {
            const response = await clientSystemSettingService.get();
            settings.value = { ...defaultSettings, ...response };
            loaded.value = true;
        } finally {
            loading.value = false;
        }

        return settings.value;
    };

    return {
        settings,
        loaded,
        loading,
        fetchSettings,
    };
};
