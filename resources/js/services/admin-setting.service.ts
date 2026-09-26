import api from '@/config/axios';
import type {
    BioSettingType,
    BrandingSettingType,
    ContactSettingType,
    ContentPageSettingsType,
    CustomCodeSettingType,
    GeneralSettingType,
    HomeCategorySettingType,
    HomepageNoticeSettingType,
    MaintenanceSettingType,
    MonitoringSettingType,
    OptionSettingType,
    PopupNoticeSettingType,
    SecuritySettingType,
    SeoSettingType,
    ServiceArticlesSettingType,
    SettingApiResponse,
    SliderImageSettingType,
    SupportChannelSettingType,
    SystemSettingType,
    TaxSettingType,
} from '@/types/setting.type';

const getTab = async <T>(tab: string): Promise<SettingApiResponse<T>> => {
    const res = await api.get(`/api/admin-api/settings/${tab}`);
    return res.data.data as SettingApiResponse<T>;
};

const updateTab = async <T>(tab: string, payload: T): Promise<SettingApiResponse<T>> => {
    const res = await api.patch(`/api/admin-api/settings/${tab}`, payload);
    return res.data.data as SettingApiResponse<T>;
};

export const adminSettingService = {
    getSystem() {
        return getTab<SystemSettingType>('system');
    },
    updateSystem(payload: SystemSettingType) {
        return updateTab<SystemSettingType>('system', payload);
    },
    getGeneral() {
        return getTab<GeneralSettingType>('general');
    },
    updateGeneral(payload: GeneralSettingType) {
        return updateTab<GeneralSettingType>('general', payload);
    },
    getMaintenance() {
        return getTab<MaintenanceSettingType>('maintenance');
    },
    updateMaintenance(payload: MaintenanceSettingType) {
        return updateTab<MaintenanceSettingType>('maintenance', payload);
    },
    getHomepage() {
        return getTab<HomepageNoticeSettingType>('homepage');
    },
    updateHomepage(payload: HomepageNoticeSettingType) {
        return updateTab<HomepageNoticeSettingType>('homepage', payload);
    },
    getPopupNotice() {
        return getTab<PopupNoticeSettingType>('popup-notice');
    },
    updatePopupNotice(payload: PopupNoticeSettingType) {
        return updateTab<PopupNoticeSettingType>('popup-notice', payload);
    },
    getServiceArticles() {
        return getTab<ServiceArticlesSettingType>('service-articles');
    },
    updateServiceArticles(payload: ServiceArticlesSettingType) {
        return updateTab<ServiceArticlesSettingType>('service-articles', payload);
    },
    getBio() {
        return getTab<BioSettingType>('bio');
    },
    updateBio(payload: BioSettingType) {
        return updateTab<BioSettingType>('bio', payload);
    },
    getBranding() {
        return getTab<BrandingSettingType>('branding');
    },
    updateBranding(payload: BrandingSettingType) {
        return updateTab<BrandingSettingType>('branding', payload);
    },
    getHomeCategory() {
        return getTab<HomeCategorySettingType>('home-category');
    },
    updateHomeCategory(payload: HomeCategorySettingType) {
        return updateTab<HomeCategorySettingType>('home-category', payload);
    },
    getFeaturedSliders() {
        return getTab<SliderImageSettingType>('slider-images');
    },
    updateFeaturedSliders(payload: SliderImageSettingType) {
        return updateTab<SliderImageSettingType>('slider-images', payload);
    },
    getContact() {
        return getTab<ContactSettingType>('contact');
    },
    updateContact(payload: ContactSettingType) {
        return updateTab<ContactSettingType>('contact', payload);
    },
    getSupportChannels() {
        return getTab<SupportChannelSettingType>('support-channels');
    },
    updateSupportChannels(payload: SupportChannelSettingType) {
        return updateTab<SupportChannelSettingType>('support-channels', payload);
    },
    getSeo() {
        return getTab<SeoSettingType>('seo');
    },
    updateSeo(payload: SeoSettingType) {
        return updateTab<SeoSettingType>('seo', payload);
    },
    getCustomCode() {
        return getTab<CustomCodeSettingType>('custom-code');
    },
    updateCustomCode(payload: Partial<CustomCodeSettingType>) {
        return updateTab<CustomCodeSettingType>('custom-code', payload);
    },
    getMonitoring() {
        return getTab<MonitoringSettingType>('monitoring');
    },
    updateMonitoring(payload: MonitoringSettingType) {
        return updateTab<MonitoringSettingType>('monitoring', payload);
    },
    getSecurity() {
        return getTab<SecuritySettingType>('security');
    },
    updateSecurity(payload: SecuritySettingType) {
        return updateTab<SecuritySettingType>('security', payload);
    },
    getTax() {
        return getTab<TaxSettingType>('tax');
    },
    updateTax(payload: TaxSettingType) {
        return updateTab<TaxSettingType>('tax', payload);
    },
    getOptions() {
        return getTab<OptionSettingType>('options');
    },
    updateOptions(payload: OptionSettingType) {
        return updateTab<OptionSettingType>('options', payload);
    },
    getContentPages() {
        return getTab<ContentPageSettingsType>('content-pages');
    },
    updateContentPages(payload: ContentPageSettingsType) {
        return updateTab<ContentPageSettingsType>('content-pages', payload);
    },
};
