export type SeoReference = {
    label: string;
    url: string;
    summary: string;
};

export const seoReferences: SeoReference[] = [
    {
        label: "SEO Starter Guide",
        url: "https://developers.google.com/search/docs/fundamentals/seo-starter-guide",
        summary: "Nội dung hữu ích, cấu trúc rõ và metadata riêng cho từng trang.",
    },
    {
        label: "Sitemaps",
        url: "https://developers.google.com/search/docs/crawling-indexing/sitemaps/overview",
        summary: "Sitemap chỉ nên liệt kê những URL public quan trọng.",
    },
    {
        label: "Article structured data",
        url: "https://developers.google.com/search/docs/appearance/structured-data/article",
        summary: "Bài viết nên có title, image, ngày xuất bản và tác giả rõ ràng.",
    },
];

export const seoTechnicalChecklist = [
    "Mỗi trang có title và meta description duy nhất.",
    "Game và bài viết public dùng canonical tự trỏ.",
    "Trang đơn hàng, thanh toán, tài khoản và admin phải noindex.",
    "Sitemap gồm trang chủ, game, nội dung tĩnh và bài đã xuất bản.",
    "Schema chỉ dùng khi dữ liệu hiển thị thực sự phù hợp.",
];

export const seoToneClasses: Record<string, string> = {
    blue: "border-sky-100 bg-sky-50 text-sky-700",
    emerald: "border-emerald-100 bg-emerald-50 text-emerald-700",
    violet: "border-violet-100 bg-violet-50 text-violet-700",
    amber: "border-amber-100 bg-amber-50 text-amber-700",
};

export const seoToneIconClasses: Record<string, string> = {
    blue: "from-sky-500 to-blue-600",
    emerald: "from-emerald-500 to-teal-600",
    violet: "from-violet-500 to-indigo-600",
    amber: "from-amber-500 to-orange-500",
};
