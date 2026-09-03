export interface SettingApiResponse<T> {
    tab: string;
    settings: T;
}

export interface SystemSettingType {
    site_name: string;
    site_domain: string;
    site_description: string;
    site_active: boolean;
    allow_register: boolean;
    light_logo: string;
    dark_logo: string;
    favicon: string;
    og_image: string;
    color_primary?: string;
    color_accent?: string;
    color_surface?: string;
    support_email: string;
    hotline: string;
    address: string;
    facebook: string;
    zalo: string;
    youtube: string;
    meta_title: string;
    meta_description: string;
    robots: string;
    gtm_id: string;
    meta_pixel_id: string;
    [key: string]: unknown;
}

export interface GeneralSettingType {
    site_name: string;
    site_domain: string;
    site_description: string;
    site_active: boolean;
    allow_register: boolean;
    [key: string]: unknown;
}

export interface HomepageNoticeSettingType {
    home_notice_title: string;
    home_notice_content: unknown[];
    home_notice_is_published: boolean;
}

export interface PopupNoticeSettingType {
    home_popup_title: string;
    home_popup_content: unknown[];
    home_popup_is_published: boolean;
    home_popup_display_mode: 'modal' | 'popup';
    home_popup_allow_dismiss: boolean;
    home_popup_dismiss_hours: number;
}

export interface GameServiceMenuItem {
    label: string;
    url: string;
}

export interface ServiceArticlesSettingType {
    game_service_enabled: boolean;
    game_service_items: GameServiceMenuItem[];
}

export interface BioLinkItemType {
    label: string;
    url: string;
    icon: string;
    is_active: boolean;
}

export interface BioSettingType {
    bio_title: string;
    bio_description: string;
    bio_avatar_url: string;
    bio_links: BioLinkItemType[];
}

export interface BrandingSettingType {
    light_logo: string;
    dark_logo: string;
    favicon: string;
    og_image: string;
    color_primary: string;
    color_accent: string;
    color_surface: string;
    [key: string]: unknown;
}

export interface HomeCategoryItemType {
    id?: number | string;
    name?: string;
    slug?: string;
    is_active?: boolean;
    [key: string]: unknown;
}

export interface HomeCategorySettingType {
    category_ids?: number[];
    categories?: HomeCategoryItemType[];
    [key: string]: unknown;
}

export interface FeaturedSliderItemType {
    id?: number | string | null;
    title?: string;
    subtitle?: string;
    image?: string;
    link?: string;
    link_redirect?: string;
    is_active?: boolean;
    status?: boolean;
    sort_order?: number;
    [key: string]: unknown;
}

export interface SliderImageSettingType {
    items: FeaturedSliderItemType[];
    [key: string]: unknown;
}

export interface ContactSettingType {
    hotline: string;
    support_email: string;
    address: string;
    facebook: string;
    zalo: string;
    youtube: string;
    [key: string]: unknown;
}

export interface DiscordWebhookSettingItemType {
    name: string;
    url: string;
    is_active: boolean;
    events: string[];
    [key: string]: unknown;
}

export interface MonitoringSettingType {
    discord_webhooks: DiscordWebhookSettingItemType[];
    [key: string]: unknown;
}

export interface SecuritySettingType {
    turnstile_enabled: boolean;
    turnstile_site_key: string;
    turnstile_secret_key: string;
    turnstile_secret_configured: boolean;
}

export interface SeoSettingType {
    meta_title: string;
    meta_description: string;
    robots?: string;
    gtm_id?: string;
    meta_pixel_id?: string;
    meta_keywords?: string;
    og_image?: string;
    [key: string]: unknown;
}

export interface CustomCodeSettingType {
    custom_css: string;
    custom_css_enabled: boolean;
    custom_js: string;
    custom_js_enabled: boolean;
}

export interface OptionSettingType {
    terms_of_use?: unknown[];
    privacy_policy?: unknown[];
    refund_policy?: unknown[];
    [key: string]: unknown;
}

export type ContentPageBaseKey =
    | 'contact_page'
    | 'terms_page'
    | 'faq_page'
    | 'privacy_page'
    | 'about_page'
    | 'refund_policy'
    | 'payment_policy'
    | 'api_usage_policy'
    | 'disclaimer'
    | 'system_status'
    | 'system_updates';

export type ContentPageContentKey = `${ContentPageBaseKey}_content`;
export type ContentPageTitleKey = `${ContentPageBaseKey}_title`;
export type ContentPageExcerptKey = `${ContentPageBaseKey}_excerpt`;
export type ContentPageSeoTitleKey = `${ContentPageBaseKey}_seo_title`;
export type ContentPageSeoDescriptionKey = `${ContentPageBaseKey}_seo_description`;
export type ContentPagePublishedKey = `${ContentPageBaseKey}_is_published`;

export interface ContentPageSettingsType {
    contact_page_title: string;
    contact_page_excerpt: string;
    contact_page_content: unknown[];
    contact_page_seo_title: string;
    contact_page_seo_description: string;
    contact_page_is_published: boolean;
    terms_page_title: string;
    terms_page_excerpt: string;
    terms_page_content: unknown[];
    terms_page_seo_title: string;
    terms_page_seo_description: string;
    terms_page_is_published: boolean;
    faq_page_title: string;
    faq_page_excerpt: string;
    faq_page_content: unknown[];
    faq_page_seo_title: string;
    faq_page_seo_description: string;
    faq_page_is_published: boolean;
    privacy_page_title: string;
    privacy_page_excerpt: string;
    privacy_page_content: unknown[];
    privacy_page_seo_title: string;
    privacy_page_seo_description: string;
    privacy_page_is_published: boolean;
    about_page_title: string;
    about_page_excerpt: string;
    about_page_content: unknown[];
    about_page_seo_title: string;
    about_page_seo_description: string;
    about_page_is_published: boolean;
    refund_policy_title: string;
    refund_policy_excerpt: string;
    refund_policy_content: unknown[];
    refund_policy_seo_title: string;
    refund_policy_seo_description: string;
    refund_policy_is_published: boolean;
    payment_policy_title: string;
    payment_policy_excerpt: string;
    payment_policy_content: unknown[];
    payment_policy_seo_title: string;
    payment_policy_seo_description: string;
    payment_policy_is_published: boolean;
    api_usage_policy_title: string;
    api_usage_policy_excerpt: string;
    api_usage_policy_content: unknown[];
    api_usage_policy_seo_title: string;
    api_usage_policy_seo_description: string;
    api_usage_policy_is_published: boolean;
    disclaimer_title: string;
    disclaimer_excerpt: string;
    disclaimer_content: unknown[];
    disclaimer_seo_title: string;
    disclaimer_seo_description: string;
    disclaimer_is_published: boolean;
    system_status_title: string;
    system_status_excerpt: string;
    system_status_content: unknown[];
    system_status_seo_title: string;
    system_status_seo_description: string;
    system_status_is_published: boolean;
    system_updates_title: string;
    system_updates_excerpt: string;
    system_updates_content: unknown[];
    system_updates_seo_title: string;
    system_updates_seo_description: string;
    system_updates_is_published: boolean;
    [key: string]: unknown;
}

export type ContentPageTabKey = ContentPageBaseKey;
