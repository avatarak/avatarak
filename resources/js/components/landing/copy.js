import { i18n } from '../../i18n';

/**
 * Landing page copy. Every claim here must be something the product really does today
 * (no fake customers, no features that are not built yet).
 */
const COPY = {
    fa: {
        navFeatures: 'ویژگی‌ها', navDevelopers: 'توسعه‌دهنده‌ها', navDocs: 'مستندات', navFaq: 'سؤالات متداول',
        heroTitle: 'یک عکس، یک هویت، همه‌جا',
        heroSub: 'ایمیل شما، گذرنامه‌ی دیجیتال شما: یک عکس، یک بیوگرافی، شبکه‌های اجتماعی و لینک‌های تأییدشده.',
        emailPh: 'ایمیل خود را وارد کنید', claim: 'ساخت پروفایل رایگان',

        heroCards: [
            { name: 'سارا احمدی', role: 'طراح محصول', city: 'تهران', bio: 'طراح محصول و نویسنده. عاشق تایپوگرافی فارسی و ابزارهایی که کار را ساده می‌کنند.' },
            { name: 'امیر رضایی', role: 'توسعه‌دهنده‌ی وب', city: 'اصفهان', bio: 'توسعه‌دهنده‌ی وب و علاقه‌مند به متن‌باز. این روزها روی ابزارهای توسعه‌دهنده کار می‌کنم.' },
            { name: 'مینا کریمی', role: 'نویسنده و مترجم', city: 'شیراز', bio: 'نویسنده و مترجم. درباره‌ی کتاب، سفر و زبان فارسی می‌نویسم.' },
        ],
        stripLabel: 'همه‌چیز در یک نگاه',
        strip: [['gift', 'رایگان برای کاربران'], ['shield', 'حریم خصوصی در اولویت'], ['verified', 'حساب‌های تأییدشده'], ['code', 'API برای توسعه‌دهنده‌ها']],

        syncTitle: 'یک بار به‌روزرسانی، همه‌جا همگام',
        syncText: 'وقتی عکس یا پروفایلتان را عوض می‌کنید، در همه‌ی سایت‌هایی که با API ما وصل‌اند همان لحظه به‌روز می‌شود. وقت کمتر، حضور یکدست‌تر.',
        winForum: 'انجمن', winProfile: 'پروفایل', winChat: 'گفتگو',

        profTitle: 'پروفایل شما، به سبک شما',
        profText: 'شبکه‌های اجتماعی، وب‌سایت، نمونه‌کار و لینک‌های دیگرتان را یک‌جا بگذارید. پروفایل شما مثل یک کارت ویزیت دیجیتال است: ساده، شیک و فقط خودتان. مناسب برای لینک در بیو.',
        profCta: 'ساخت پروفایل رایگان', mockName: 'سارا احمدی', mockCity: 'تهران',
        mockBio: 'طراح محصول و نویسنده. عاشق تایپوگرافی فارسی و ابزارهایی که کار را ساده می‌کنند.',
        mockShare: 'اشتراک‌گذاری', mockSite: 'وب‌سایت', mockLinks: 'لینک‌ها', mockLink1: 'نمونه‌کارها', mockLink2: 'مقاله‌ها',
        mockLinkD1: 'پروژه‌های طراحی من در سال‌های اخیر.', mockLinkD2: 'یادداشت‌هایی درباره‌ی طراحی و تایپوگرافی.',

        verTitle: 'حساب‌های تأییدشده',
        verText: 'ثابت کنید صاحب وب‌سایت و حساب تلگرام خودتان هستید؛ کنارشان تیک آبی می‌نشیند و دیگران مطمئن می‌شوند که شما واقعی هستید.',
        verHead: 'حساب‌های تأییدشده', verWebsite: 'وب‌سایت', verTelegram: 'Telegram',

        idTitle: 'چند ایمیل، یک هویت',
        idText: 'هویت شما به ایمیلتان وصل است، نه فقط یک نام. ایمیل شخصی و کاری را به یک حساب وصل کنید، هرکدام را عمومی یا خصوصی کنید و تا ۱۰ عکس نگه دارید.',
        idView: 'مشاهده‌ی پروفایل',

        privTitle: 'حریم خصوصی در اولویت',
        privText: 'شما کنترل دارید: نمایش عکس، نمایش صفحه‌ی پروفایل و ایندکس‌شدن در گوگل را جدا جدا تعیین کنید. ایمیل شما هرگز در صفحه‌ی عمومی یا خروجی‌ها نمی‌آید.',
        privSwitch: 'پروفایل عمومی',

        devEyebrow: 'پروفایل به‌عنوان سرویس', devTitle: 'برای توسعه‌دهنده‌ها',
        devText: 'تجربه‌ی کاربرانتان را شخصی‌سازی کنید و جامعه‌تان را با پروفایل‌های تأییدشده، شامل عکس و لینک‌های اجتماعی، بسازید. اتصال ساده است: یک آدرس برای عکس و یک REST API برای اطلاعات پروفایل.',
        devCta: 'ساخت کلید API', devDocs: 'خواندن مستندات',

        faqTitle: 'پرسیدید، پاسخ دادیم',
        faq: [
            ['آیا واقعاً رایگان است؟', 'بله. حساب کاربران و سایت‌های کوچک کاملاً رایگان است. برای کسب‌وکارهای بزرگ هم استفاده رایگان است و فقط حذف برند ما (وایت‌لیبل) پولی خواهد بود.'],
            ['چه کسی می‌تواند ایمیل من را ببیند؟', 'هیچ‌کس. آدرس عکس شما از هش SHA-256 ایمیل ساخته می‌شود و خود ایمیل هرگز در صفحه‌ی عمومی یا API نمی‌آید. عکس هم فقط برای ایمیل‌های تأییدشده‌ای نشان داده می‌شود که عمومی کرده باشید.'],
            ['می‌توانم چند ایمیل داشته باشم؟', 'بله. ایمیل شخصی و کاری را به یک هویت وصل کنید و برای هرکدام تعیین کنید عمومی باشد یا خصوصی.'],
            ['تأیید حساب‌ها چطور کار می‌کند؟', 'برای وب‌سایت، یک کد را در سایتتان (تگ meta، فایل یا رکورد DNS) می‌گذارید. برای تلگرام، روی ربات ما Start می‌زنید و ما می‌فهمیم حساب واقعاً مال شماست.'],
            ['کد منبع در دسترس است؟', 'بله، کد پروژه روی GitHub است: github.com/avatarak/avatarak.'],
        ],

        ctaTitle: 'پروفایل رایگان خود را بسازید!', ctaBtn: 'همین حالا شروع کنید',
        footFeatures: 'ویژگی‌ها', footDevelopers: 'توسعه‌دهنده‌ها', footSupport: 'پشتیبانی', footAccount: 'حساب',
        fProfile: 'پروفایل عمومی', fVerified: 'حساب‌های تأییدشده', fPrivacy: 'حریم خصوصی', fApi: 'API', fDocs: 'مستندات', fFaq: 'سؤالات متداول', fLogin: 'ورود', fRegister: 'ثبت‌نام',
        langLabel: 'زبان', rights: 'همه‌ی حقوق محفوظ است.',
    },
    en: {
        navFeatures: 'Features', navDevelopers: 'Developers', navDocs: 'Docs', navFaq: 'FAQ',
        heroTitle: 'One photo, one identity, everywhere',
        heroSub: 'Your email is your digital passport: one avatar, one bio, social connections and verified links.',
        emailPh: 'Enter email address', claim: 'Claim your free profile',

        heroCards: [
            { name: 'Sara Ahmadi', role: 'Product designer', city: 'Tehran', bio: 'Product designer and writer. Loves typography and tools that make work simpler.' },
            { name: 'Amir Rezaei', role: 'Web developer', city: 'Isfahan', bio: 'Web developer and open-source fan. These days I build tools for developers.' },
            { name: 'Mina Karimi', role: 'Writer & translator', city: 'Shiraz', bio: 'Writer and translator. I write about books, travel and the Persian language.' },
        ],
        stripLabel: 'At a glance',
        strip: [['gift', 'Free for people'], ['shield', 'Privacy first'], ['verified', 'Verified accounts'], ['code', 'A developer API']],

        syncTitle: 'Update once, sync everywhere',
        syncText: 'When you change your avatar or profile, it updates instantly on every site connected through our API. Save time and keep a consistent presence.',
        winForum: 'Forum', winProfile: 'Profile', winChat: 'Chat',

        profTitle: 'Your profile, your way',
        profText: 'Share your social profiles, website, portfolio and other links in one place. Your profile works like a digital business card: simple, elegant and uniquely you. Perfect for your link-in-bio.',
        profCta: 'Claim your free profile', mockName: 'Sara Ahmadi', mockCity: 'Tehran',
        mockBio: 'Product designer and writer. Loves typography and tools that make work simpler.',
        mockShare: 'Share', mockSite: 'Website', mockLinks: 'Links', mockLink1: 'Portfolio', mockLink2: 'Articles',
        mockLinkD1: 'My design projects from the last few years.', mockLinkD2: 'Notes on design and typography.',

        verTitle: 'Verified accounts',
        verText: 'Prove you own your website and Telegram account; a blue check appears next to them so people know it is really you.',
        verHead: 'Verified Accounts', verWebsite: 'Website', verTelegram: 'Telegram',

        idTitle: 'Several emails, one identity',
        idText: 'Your identity is tied to your email, not just a name. Link your personal and work addresses to one account, make each public or private, and keep up to 10 photos.',
        idView: 'View profile',

        privTitle: 'Privacy first',
        privText: 'You are in control: choose separately whether your photo, your profile page and search-engine indexing are visible. Your email is never shown on the public page or in exports.',
        privSwitch: 'Public profile',

        devEyebrow: 'Profiles as a service', devTitle: 'For developers',
        devText: 'Customize your users\' experience and bootstrap your community with verified profiles, including avatars and social links. Integration is simple: one URL for the image and a REST API for profile data.',
        devCta: 'Create an API key', devDocs: 'Read the docs',

        faqTitle: 'You asked, we answered',
        faq: [
            ['Is it really free?', 'Yes. Accounts for people and small sites are completely free. Large businesses can use it free too; only removing our branding (white-label) will be paid.'],
            ['Who can see my email?', 'No one. Your avatar URL is built from a SHA-256 hash of your email and the email itself never appears on the public page or in the API. The photo is only served for verified emails you made public.'],
            ['Can I have several emails?', 'Yes. Link your personal and work addresses to one identity and choose, per address, whether it is public or private.'],
            ['How does account verification work?', 'For a website you publish a code on your site (meta tag, file or DNS record). For Telegram you press Start on our bot and we confirm the account is really yours.'],
            ['Is the source code available?', 'Yes, the project is on GitHub: github.com/avatarak/avatarak.'],
        ],

        ctaTitle: 'Claim your free profile today!', ctaBtn: 'Get started now',
        footFeatures: 'Features', footDevelopers: 'Developers', footSupport: 'Support', footAccount: 'Account',
        fProfile: 'Public profile', fVerified: 'Verified accounts', fPrivacy: 'Privacy', fApi: 'API', fDocs: 'Documentation', fFaq: 'FAQ', fLogin: 'Log in', fRegister: 'Sign up',
        langLabel: 'Language', rights: 'All rights reserved.',
    },
};

export const lt = (key) => COPY[i18n.locale]?.[key] ?? COPY.fa[key] ?? key;
