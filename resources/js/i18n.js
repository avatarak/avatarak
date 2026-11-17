import { reactive, computed } from 'vue';

const brand = window.__BRAND__ || {};
const saved = (() => { try { return localStorage.getItem('locale'); } catch { return null; } })();

export const i18n = reactive({ locale: saved || brand.locale || 'fa' });
export const dir = computed(() => (i18n.locale === 'fa' ? 'rtl' : 'ltr'));

export function setLocale(l) {
    i18n.locale = l;
    try { localStorage.setItem('locale', l); } catch { /* storage may be unavailable */ }
    applyDir();
}
export function applyDir() {
    document.documentElement.lang = i18n.locale;
    document.documentElement.dir = dir.value;
}

const M = {
    fa: {
        login: 'ورود', register: 'ثبت‌نام', logout: 'خروج', start: 'شروع رایگان', learn: 'چطور کار می‌کند',
        heroSub: 'ایمیل شما، گذرنامه‌ی دیجیتال شما: یک عکس، یک بیوگرافی، شبکه‌های اجتماعی و لینک‌ها، در هر سایت متصل.',
        emailPlaceholder: 'ایمیل خود را وارد کنید', claim: 'ساخت پروفایل',
        how1: 'یک بار ثبت کنید', how1d: 'عکس، نام فارسی و انگلیسی و لینک‌ها را یک‌جا بسازید.',
        how2: 'ایمیل‌تان را تأیید کنید', how2d: 'ایمیل شخصی و کاری را به یک هویت وصل کنید.',
        how3: 'همه‌جا دیده شوید', how3d: 'وردپرس و اپ‌های متصل، خودکار عکس شما را نشان می‌دهند.',
        name: 'نام', email: 'ایمیل', password: 'گذرواژه', username: 'نام کاربری', needAccount: 'حساب ندارید؟', haveAccount: 'حساب دارید؟',
        passwordHint: 'حداقل ۸ کاراکتر', close: 'بستن', cancel: 'انصراف', save: 'ذخیره', saved: 'ذخیره شد', saving: 'در حال ذخیره…',
        nav_avatars: 'آواتارها', nav_about: 'درباره‌ی من', nav_links: 'لینک‌ها', nav_emails: 'ایمیل‌ها', nav_design: 'ظاهر', nav_privacy: 'حریم خصوصی', nav_developers: 'توسعه‌دهندگان',
        d_avatars: 'عکس‌های پروفایل خود را مدیریت کنید.', d_about: 'اطلاعاتی که در صفحه‌ی عمومی شما نمایش داده می‌شود.',
        d_links: 'لینک‌های دلخواه و شبکه‌های اجتماعی خود را اضافه کنید.', d_emails: 'چند ایمیل به یک هویت وصل کنید. عکس فقط برای ایمیل‌های تأییدشده نمایش داده می‌شود.',
        d_design: 'رنگ و چیدمان صفحه‌ی عمومی خود را شخصی‌سازی کنید.', d_privacy: 'تعیین کنید چه چیزی و کجا نمایش داده شود.', d_developers: 'برای هر سایت یک کلید API بسازید.',
        displayNameFa: 'نام نمایشی (فارسی)', displayNameEn: 'نام نمایشی (انگلیسی)', headline: 'عنوان (مثلاً طراح محصول در شرکت X)', location: 'موقعیت',
        bio: 'درباره‌ی من', website: 'وب‌سایت', usernameHint: 'نشانی صفحه‌ی عمومی شما', yourName: 'نام شما', yourBio: 'بیوگرافی کوتاه شما',
        avatarTitle: 'آواتارها', addAvatar: 'افزودن عکس', active: 'فعال', use: 'استفاده', deleteAvatar: 'حذف عکس', noAvatarUse: 'بدون عکس (پیش‌فرض)',
        emptyAvatars: 'هنوز عکسی اضافه نکرده‌اید. عکس را اینجا رها کنید یا روی «افزودن عکس» بزنید.',
        pasteHint: 'می‌توانید عکس را کپی و با Ctrl+V هم جای‌گذاری کنید.', avatarLimit: 'حداکثر ۱۰ عکس',
        cropTitle: 'برش عکس', zoom: 'بزرگ‌نمایی', rotate: 'چرخش', reset: 'بازنشانی', cropHint: 'برای جابه‌جایی بکشید؛ برای بزرگ‌نمایی از اسلایدر یا چرخ ماوس استفاده کنید.',
        setAsAvatar: 'ثبت به‌عنوان آواتار', uploading: 'در حال آپلود…', uploadFailed: 'آپلود ناموفق بود.',
        errType: 'فقط فایل‌های JPG، PNG یا WebP پذیرفته می‌شوند.', errSize: 'حجم فایل نباید بیشتر از ۲۰ مگابایت باشد.', errDim: 'عکس باید حداقل ۶۴×۶۴ پیکسل باشد.', errLoad: 'این فایل به‌عنوان تصویر باز نشد.',
        whereShown: 'کجا نمایش داده می‌شود', whereShownHint: 'این آدرس‌ها را هر سایتی می‌تواند استفاده کند. فقط ایمیل‌های تأییدشده و عمومی کار می‌کنند.',
        noPublicEmail: 'هنوز ایمیل تأییدشده‌ی عمومی ندارید؛ تا آن موقع عکس شما بیرون نمایش داده نمی‌شود.', copy: 'کپی', copied: 'کپی شد',
        addEmail: 'افزودن ایمیل', primary: 'اصلی', verified: 'تأییدشده', pending: 'در انتظار تأیید', makePrimary: 'اصلی کن', resend: 'ارسال دوباره', delete: 'حذف',
        publicEmail: 'نمایش عمومی', emailSent: 'ایمیل تأیید ارسال شد.',
        yourLinks: 'لینک‌های شما', addLink: 'افزودن لینک', linkLabel: 'عنوان', linkUrl: 'آدرس (https://…)', socials: 'شبکه‌های اجتماعی', socialsHint: 'فقط نام کاربری را بنویسید.',
        colors: 'رنگ‌ها', accent: 'رنگ اصلی', sections: 'بخش‌ها', dragReorder: 'برای مرتب‌سازی بکشید', intro: 'معرفی', pinned: 'ثابت',
        th_classic: 'کلاسیک', th_mocha: 'موکا', th_mint: 'نعنایی', th_mustard: 'خردلی', th_midnight: 'نیمه‌شب', th_molten: 'مذاب', th_earth: 'خاکی', th_cyber: 'سایبر', th_custom: 'دلخواه',
        sec_bio: 'متن درباره‌ی من', sec_links: 'لینک‌ها', show: 'نمایش', hide: 'مخفی', moveUp: 'بالا', moveDown: 'پایین',
        showAvatar: 'نمایش عکس در سایت‌های متصل', showAvatarD: 'اگر خاموش باشد همه‌جا عکس پیش‌فرض نشان داده می‌شود.',
        showProfile: 'صفحه‌ی پروفایل عمومی', showProfileD: 'خاموش کردن، صفحه و API پروفایل را برای همه پنهان می‌کند.',
        allowIndexing: 'ایندکس شدن در گوگل', allowIndexingD: 'به‌طور پیش‌فرض خاموش است و صفحه‌ی شما noindex می‌ماند.',
        siteName: 'نام سایت', siteDomain: 'دامنه (مثلاً blog.example.ir)', createKey: 'ساخت کلید', keyOnce: 'این کلید را همین حالا ذخیره کنید؛ دوباره نمایش داده نمی‌شود.',
        lastUsed: 'آخرین استفاده', never: 'هرگز', example: 'نمونه درخواست', confirmDelete: 'حذف شود؟', empty: 'هنوز چیزی نیست.',
        loading: 'در حال بارگذاری…', error: 'خطایی رخ داد.', viewPublic: 'مشاهده‌ی صفحه‌ی عمومی', share: 'اشتراک‌گذاری', visitSite: 'مشاهده‌ی وب‌سایت',
        verifyNotice: 'ایمیل شما هنوز تأیید نشده؛ تا آن موقع تیک تأیید نمایش داده نمی‌شود و سایت‌های متصل (مثل وردپرس) عکس شما را نشان نمی‌دهند.', verifyAction: 'مدیریت ایمیل‌ها',
        showMore: 'نمایش بیشتر', showLess: 'نمایش کمتر', advanced: 'جزئیات بیشتر', showImages: 'نمایش تصویر کنار لینک‌ها', showImagesD: 'تصویر پیش‌نمایش هر سایت به‌طور خودکار گرفته می‌شود.', linkDesc: 'توضیح کوتاه (اختیاری)', editProfile: 'ویرایش پروفایل', sec_intro: 'معرفی',
        nav_verified: 'حساب‌های تأییدشده', d_verified: 'ثابت کنید صاحب این حساب‌ها هستید تا کنارشان تیک آبی بگذاریم.', sec_verified: 'حساب‌های تأییدشده', vWebsite: 'وب‌سایت', vTelegram: 'تلگرام', vDomain: 'دامنه (مثلاً example.com)', vStart: 'شروع تأیید', vCheck: 'بررسی کن', vChecking: 'در حال بررسی…', vAnyOne: 'یکی از سه روش زیر کافی است. بعد از انجام، «بررسی کن» را بزنید.', vMeta: 'تگ meta', vMetaHelp: 'این خط را داخل <head> صفحه‌ی اصلی سایت بگذارید:', vFile: 'فایل', vFileHelp: 'فایلی با این آدرس بسازید که فقط همین متن در آن باشد:', vDns: 'رکورد DNS', vDnsHelp: 'یک رکورد TXT روی دامنه با این مقدار اضافه کنید (رسیدن DNS ممکن است چند دقیقه طول بکشد):', vHandle: 'نام کاربری تلگرام', vTelegramBtn: 'تأیید با تلگرام', vOpenTelegram: 'باز کردن تلگرام', vWaiting: 'در انتظار تأیید… در تلگرام روی Start بزنید.', vTgOff: 'تأیید تلگرام روی این سرور فعال نیست (توکن ربات تنظیم نشده).', vVerified: 'تأییدشده', vRemove: 'حذف تأیید', vNone: 'هنوز حساب تأییدشده‌ای ندارید.', vDone: 'تأیید شد ✓', vMethod: 'روش',
        preview: 'پیش‌نمایش', back: 'بازگشت', menu: 'منو', yourAddress: 'نشانی پروفایل شما', linkCopied: 'لینک کپی شد',
    },
    en: {
        login: 'Log in', register: 'Sign up', logout: 'Log out', start: 'Get started', learn: 'How it works',
        heroSub: 'Transform your email address into your digital passport: one avatar, one bio, social connections and verified links.',
        emailPlaceholder: 'Enter email address', claim: 'Claim your profile',
        how1: 'Set it up once', how1d: 'Photo, Persian and English names and links in one place.',
        how2: 'Verify your email', how2d: 'Link personal and work addresses to one identity.',
        how3: 'Be seen everywhere', how3d: 'WordPress and connected apps show your photo automatically.',
        name: 'Name', email: 'Email', password: 'Password', username: 'Username', needAccount: 'No account?', haveAccount: 'Have an account?',
        passwordHint: 'At least 8 characters', close: 'Close', cancel: 'Cancel', save: 'Save', saved: 'Saved', saving: 'Saving…',
        nav_avatars: 'Avatars', nav_about: 'About', nav_links: 'Links', nav_emails: 'Emails', nav_design: 'Design', nav_privacy: 'Privacy', nav_developers: 'Developers',
        d_avatars: 'Manage your profile photos.', d_about: 'What appears on your public profile.',
        d_links: 'Add your favorite links and social accounts.', d_emails: 'Link several emails to one identity. Your photo only shows for verified emails.',
        d_design: 'Customize your public profile with colors and layout.', d_privacy: 'Choose what is shown, and where.', d_developers: 'Create an API key for each site.',
        displayNameFa: 'Display name (Persian)', displayNameEn: 'Display name (English)', headline: 'Headline (e.g. Product designer at X)', location: 'Location',
        bio: 'About me', website: 'Website', usernameHint: 'Your public page address', yourName: 'Your name', yourBio: 'Your short bio',
        avatarTitle: 'Avatars', addAvatar: 'Add photo', active: 'Active', use: 'Use', deleteAvatar: 'Delete photo', noAvatarUse: 'No photo (use default)',
        emptyAvatars: 'No photos yet. Drop a photo here or press “Add photo”.',
        pasteHint: 'You can also paste an image with Ctrl+V.', avatarLimit: 'Up to 10 photos',
        cropTitle: 'Crop photo', zoom: 'Zoom', rotate: 'Rotate', reset: 'Reset', cropHint: 'Drag to reposition; use the slider or mouse wheel to zoom.',
        setAsAvatar: 'Set as avatar', uploading: 'Uploading…', uploadFailed: 'Upload failed.',
        errType: 'Only JPG, PNG or WebP files are accepted.', errSize: 'The file must be 20 MB or smaller.', errDim: 'The image must be at least 64×64 pixels.', errLoad: 'This file could not be opened as an image.',
        whereShown: 'Where it shows', whereShownHint: 'Any site can use these URLs. Only verified, public emails work.',
        noPublicEmail: 'You have no verified public email yet, so your photo is not shown anywhere.', copy: 'Copy', copied: 'Copied',
        addEmail: 'Add email', primary: 'Primary', verified: 'Verified', pending: 'Pending', makePrimary: 'Make primary', resend: 'Resend', delete: 'Delete',
        publicEmail: 'Public', emailSent: 'Verification email sent.',
        yourLinks: 'Your links', addLink: 'Add link', linkLabel: 'Label', linkUrl: 'URL (https://…)', socials: 'Social accounts', socialsHint: 'Enter just the handle.',
        colors: 'Colors', accent: 'Accent color', sections: 'Sections', dragReorder: 'Drag to reorder', intro: 'Introduction', pinned: 'Pinned',
        th_classic: 'Classic', th_mocha: 'Mocha', th_mint: 'Mint', th_mustard: 'Mustard', th_midnight: 'Midnight', th_molten: 'Molten', th_earth: 'Earth', th_cyber: 'Cyber', th_custom: 'Custom',
        sec_bio: 'About me text', sec_links: 'Links', show: 'Show', hide: 'Hide', moveUp: 'Move up', moveDown: 'Move down',
        showAvatar: 'Show photo on connected sites', showAvatarD: 'When off, a default image is shown everywhere.',
        showProfile: 'Public profile page', showProfileD: 'Turning this off hides your page and API profile from everyone.',
        allowIndexing: 'Search engine indexing', allowIndexingD: 'Off by default; your page stays noindex.',
        siteName: 'Site name', siteDomain: 'Domain (e.g. blog.example.com)', createKey: 'Create key', keyOnce: 'Save this key now; it will not be shown again.',
        lastUsed: 'Last used', never: 'Never', example: 'Example request', confirmDelete: 'Delete?', empty: 'Nothing here yet.',
        loading: 'Loading…', error: 'Something went wrong.', viewPublic: 'View public profile', share: 'Share', visitSite: 'Visit website',
        verifyNotice: 'Your email is not verified yet. Until it is, the verified badge is hidden and connected sites (like WordPress) will not show your photo.', verifyAction: 'Manage emails',
        showMore: 'Show more', showLess: 'Show less', advanced: 'Advanced details', showImages: 'Show images with links', showImagesD: 'A preview image of each site is fetched automatically.', linkDesc: 'Short description (optional)', editProfile: 'Edit profile', sec_intro: 'Introduction',
        nav_verified: 'Verified accounts', d_verified: 'Prove you own these accounts and we show a blue check next to them.', sec_verified: 'Verified Accounts', vWebsite: 'Website', vTelegram: 'Telegram', vDomain: 'Domain (e.g. example.com)', vStart: 'Start verification', vCheck: 'Check now', vChecking: 'Checking…', vAnyOne: 'Any one of the three ways below is enough. Then press “Check now”.', vMeta: 'Meta tag', vMetaHelp: 'Put this line inside the <head> of your home page:', vFile: 'File', vFileHelp: 'Create a file at this URL containing only this text:', vDns: 'DNS record', vDnsHelp: 'Add a TXT record on the domain with this value (DNS can take a few minutes):', vHandle: 'Telegram username', vTelegramBtn: 'Verify with Telegram', vOpenTelegram: 'Open Telegram', vWaiting: 'Waiting… press Start in Telegram.', vTgOff: 'Telegram verification is not enabled on this server (no bot token configured).', vVerified: 'Verified', vRemove: 'Remove verification', vNone: 'No verified accounts yet.', vDone: 'Verified ✓', vMethod: 'Method',
        preview: 'Preview', back: 'Back', menu: 'Menu', yourAddress: 'Your profile address', linkCopied: 'Link copied',
    },
};

export function t(key) {
    return M[i18n.locale]?.[key] ?? M.fa[key] ?? key;
}
