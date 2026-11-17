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
        login: 'ورود', register: 'ثبت‌نام', logout: 'خروج', start: 'ساخت هویت رایگان', learn: 'چطور کار می‌کند',
        heroSub: 'عکس و اطلاعاتتان را یک‌بار ثبت کنید؛ در هر سایتی که متصل باشد، با ایمیل شما نمایش داده می‌شود.',
        how1: 'یک بار ثبت کنید', how1d: 'عکس، نام فارسی و انگلیسی و لینک‌ها را یک‌جا بسازید.',
        how2: 'ایمیل‌تان را تأیید کنید', how2d: 'ایمیل شخصی و کاری را به یک هویت وصل کنید.',
        how3: 'همه‌جا دیده شوید', how3d: 'وردپرس و اپ‌های متصل، خودکار عکس شما را نشان می‌دهند.',
        name: 'نام', email: 'ایمیل', password: 'گذرواژه', username: 'نام کاربری', needAccount: 'حساب ندارید؟', haveAccount: 'حساب دارید؟',
        passwordHint: 'حداقل ۸ کاراکتر', close: 'بستن', save: 'ذخیره', saved: 'ذخیره شد', saving: 'در حال ذخیره…',
        panelTitle: 'هویت دیجیتال',
        nav_profile: 'پروفایل', nav_avatars: 'آواتارها', nav_emails: 'ایمیل‌ها', nav_links: 'لینک‌ها', nav_appearance: 'ظاهر', nav_privacy: 'حریم خصوصی', nav_developers: 'توسعه‌دهندگان',
        editProfile: 'ویرایش پروفایل', displayNameFa: 'نام نمایشی (فارسی)', displayNameEn: 'نام نمایشی (انگلیسی)', bio: 'بیوگرافی', website: 'وب‌سایت',
        usernameHint: 'نشانی صفحه‌ی عمومی شما', preview: 'پیش‌نمایش زنده', yourName: 'نام شما', yourBio: 'بیوگرافی کوتاه شما',
        avatarTitle: 'عکس پروفایل', avatarHint: 'JPG، PNG یا WebP؛ حداقل ۶۴ پیکسل، حداکثر ۵ مگابایت. تصویر مربع‌برش می‌شود.',
        upload: 'انتخاب عکس', dropHere: 'عکس را اینجا رها کنید', removeAvatar: 'حذف عکس', uploading: 'در حال آپلود…',
        whereShown: 'کجا نمایش داده می‌شود', whereShownHint: 'این آدرس‌ها را هر سایتی می‌تواند استفاده کند. فقط ایمیل‌های تأییدشده و عمومی کار می‌کنند.',
        noPublicEmail: 'هنوز ایمیل تأییدشده‌ی عمومی ندارید؛ تا آن موقع عکس شما بیرون نمایش داده نمی‌شود.', copy: 'کپی', copied: 'کپی شد',
        emailsTitle: 'ایمیل‌ها', emailsHint: 'چند ایمیل به یک هویت وصل کنید. عکس فقط برای ایمیل‌های تأییدشده نمایش داده می‌شود.',
        addEmail: 'افزودن ایمیل', primary: 'اصلی', verified: 'تأییدشده', pending: 'در انتظار تأیید', makePrimary: 'اصلی کن', resend: 'ارسال دوباره', delete: 'حذف',
        publicEmail: 'نمایش عمومی', emailSent: 'ایمیل تأیید ارسال شد.',
        linksTitle: 'لینک‌ها', linksHint: 'فقط نام کاربری را بنویسید؛ لینک‌ها خودکار ساخته می‌شوند.',
        appearanceTitle: 'ظاهر صفحه‌ی عمومی', accent: 'رنگ اصلی', defaultColor: 'پیش‌فرض', sectionOrder: 'ترتیب بخش‌ها', up: 'بالا', down: 'پایین',
        sec_bio: 'بیوگرافی', sec_links: 'لینک‌ها', sec_website: 'وب‌سایت',
        privacyTitle: 'حریم خصوصی', showAvatar: 'نمایش عکس در سایت‌های متصل', showAvatarD: 'اگر خاموش باشد همه‌جا عکس پیش‌فرض نشان داده می‌شود.',
        showProfile: 'صفحه‌ی پروفایل عمومی', showProfileD: 'خاموش کردن، صفحه و API پروفایل را برای همه پنهان می‌کند.',
        allowIndexing: 'ایندکس شدن در گوگل', allowIndexingD: 'به‌طور پیش‌فرض خاموش است و صفحه‌ی شما noindex می‌ماند.',
        devTitle: 'سایت‌ها و کلید API', devHint: 'برای هر سایت یک کلید بسازید. کلید فقط یک‌بار نمایش داده می‌شود.',
        siteName: 'نام سایت', siteDomain: 'دامنه (مثلاً blog.example.ir)', createKey: 'ساخت کلید', keyOnce: 'این کلید را همین حالا ذخیره کنید؛ دوباره نمایش داده نمی‌شود.',
        lastUsed: 'آخرین استفاده', never: 'هرگز', example: 'نمونه درخواست', confirmDelete: 'حذف شود؟', empty: 'هنوز چیزی نیست.',
        loading: 'در حال بارگذاری…', error: 'خطایی رخ داد.', viewPublic: 'مشاهده‌ی صفحه‌ی عمومی',
    },
    en: {
        login: 'Log in', register: 'Sign up', logout: 'Log out', start: 'Create your free identity', learn: 'How it works',
        heroSub: 'Set your photo and details once. Every connected site shows them, matched by your email.',
        how1: 'Set it up once', how1d: 'Photo, Persian and English names and links in one place.',
        how2: 'Verify your email', how2d: 'Link personal and work addresses to one identity.',
        how3: 'Be seen everywhere', how3d: 'WordPress and connected apps show your photo automatically.',
        name: 'Name', email: 'Email', password: 'Password', username: 'Username', needAccount: 'No account?', haveAccount: 'Have an account?',
        passwordHint: 'At least 8 characters', close: 'Close', save: 'Save', saved: 'Saved', saving: 'Saving…',
        panelTitle: 'Digital identity',
        nav_profile: 'Profile', nav_avatars: 'Avatars', nav_emails: 'Emails', nav_links: 'Links', nav_appearance: 'Appearance', nav_privacy: 'Privacy', nav_developers: 'Developers',
        editProfile: 'Edit profile', displayNameFa: 'Display name (Persian)', displayNameEn: 'Display name (English)', bio: 'Bio', website: 'Website',
        usernameHint: 'Your public page address', preview: 'Live preview', yourName: 'Your name', yourBio: 'Your short bio',
        avatarTitle: 'Profile photo', avatarHint: 'JPG, PNG or WebP; at least 64px, up to 5 MB. Cropped to a square.',
        upload: 'Choose photo', dropHere: 'Drop a photo here', removeAvatar: 'Remove photo', uploading: 'Uploading…',
        whereShown: 'Where it shows', whereShownHint: 'Any site can use these URLs. Only verified, public emails work.',
        noPublicEmail: 'You have no verified public email yet, so your photo is not shown anywhere.', copy: 'Copy', copied: 'Copied',
        emailsTitle: 'Emails', emailsHint: 'Link several emails to one identity. Your photo only shows for verified emails.',
        addEmail: 'Add email', primary: 'Primary', verified: 'Verified', pending: 'Pending', makePrimary: 'Make primary', resend: 'Resend', delete: 'Delete',
        publicEmail: 'Public', emailSent: 'Verification email sent.',
        linksTitle: 'Links', linksHint: 'Enter just the handle; links are built for you.',
        appearanceTitle: 'Public page appearance', accent: 'Accent color', defaultColor: 'Default', sectionOrder: 'Section order', up: 'Up', down: 'Down',
        sec_bio: 'Bio', sec_links: 'Links', sec_website: 'Website',
        privacyTitle: 'Privacy', showAvatar: 'Show photo on connected sites', showAvatarD: 'When off, a default image is shown everywhere.',
        showProfile: 'Public profile page', showProfileD: 'Turning this off hides your page and API profile from everyone.',
        allowIndexing: 'Search engine indexing', allowIndexingD: 'Off by default; your page stays noindex.',
        devTitle: 'Sites & API keys', devHint: 'Create a key per site. A key is shown only once.',
        siteName: 'Site name', siteDomain: 'Domain (e.g. blog.example.com)', createKey: 'Create key', keyOnce: 'Save this key now; it will not be shown again.',
        lastUsed: 'Last used', never: 'Never', example: 'Example request', confirmDelete: 'Delete?', empty: 'Nothing here yet.',
        loading: 'Loading…', error: 'Something went wrong.', viewPublic: 'View public page',
    },
};

export function t(key) {
    return M[i18n.locale]?.[key] ?? M.fa[key] ?? key;
}
