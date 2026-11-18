// Messages for newer features, merged into the main table by i18n.js.
export default {
    fa: {
        // one-time email code
        codeLogin: 'ورود با کد ایمیلی', passwordLogin: 'ورود با گذرواژه', sendCode: 'ارسال کد', codeSent: 'کد ۶ رقمی به ایمیل شما فرستاده شد.',
        codeLabel: 'کد ۶ رقمی', codeEnter: 'ورود', codeResend: 'ارسال دوباره', codeResendIn: 'ارسال دوباره تا', codeHintLogin: 'گذرواژه‌تان را فراموش کرده‌اید؟ با کد ایمیلی وارد شوید و بعد گذرواژه‌ی جدید بگذارید.', seconds: 'ثانیه',
        // github verification
        vGithub: 'گیت‌هاب', vGhHandle: 'نام کاربری گیت‌هاب', vGhStart: 'شروع تأیید گیت‌هاب', vGhGistHelp: 'در گیت‌هاب با همین حساب یک Gist «عمومی» (Public) بسازید. نام فایل و محتوا را دقیقاً این‌طور بگذارید و بعد «بررسی» را بزنید:',
        vGhFilename: 'نام فایل', vGhContent: 'محتوا', vGhOpenGist: 'ساخت Gist در گیت‌هاب', vGhOauth: 'ورود با گیت‌هاب', vGhOr: 'یا', vGhRemoveGist: 'بعد از تأیید می‌توانید Gist را پاک کنید.',
        vGhOk: 'گیت‌هاب تأیید شد ✓', vGhFail: 'تأیید گیت‌هاب ناموفق بود.',
        // share / business card
        nav_share: 'کد QR و کارت', d_share: 'کد QR، کارت ویزیت چاپی و تصویر اشتراک‌گذاری پروفایل شما.', shareQr: 'کد QR پروفایل', shareLink: 'کپی لینک', shareQrSvg: 'دانلود QR (SVG)', shareQrPng: 'دانلود QR (PNG)',
        shareCardTitle: 'کارت ویزیت چاپی', shareCardD: 'کارت ۸۵×۵۵ میلی‌متر یا یک برگه‌ی A4 با ۱۰ کارت، آماده‌ی چاپ.', shareCardOpen: 'باز کردن کارت برای چاپ', shareImgTitle: 'تصویر اشتراک‌گذاری', shareImgD: 'یک کارت عمودی برای پست یا استوری، با عکس، نام و کد QR.',
        shareImgPng: 'دانلود PNG', shareImgSvg: 'دانلود SVG', shareVcard: 'دانلود کارت مخاطب (vCard)', shareHidden: 'پروفایل شما خصوصی است. برای ساخت QR و کارت، «نمایش پروفایل» را در بخش حریم خصوصی روشن کنید.', shareDownloading: 'در حال ساخت تصویر…',
        // statistics
        nav_stats: 'آمار', d_stats: 'ببینید آواتار و پروفایل شما چند بار و در کدام سایت‌ها دیده شده است.', stProfile: 'بازدید پروفایل', stAvatar: 'نمایش آواتار', stExport: 'خروجی‌ها (JSON، QR …)', stApi: 'درخواست API',
        stDays7: '۷ روز', stDays30: '۳۰ روز', stDays90: '۹۰ روز', stTop: 'سایت‌هایی که از شما استفاده کرده‌اند', stNoRef: 'هنوز سایتی ثبت نشده است. وقتی آواتارتان در سایتی نمایش داده شود، نام آن اینجا می‌آید.',
        stPrivacy: 'فقط شمارنده‌ی روزانه ذخیره می‌شود؛ نه IP، نه مرورگر و نه چیزی از بازدیدکننده. بازدید خودتان شمرده نمی‌شود.', stTotal: 'جمع', stPerDay: 'در روز',
        delAccount: 'حذف حساب', delAccountText: 'حساب، پروفایل، عکس‌ها، ایمیل‌ها و کلیدهای API شما برای همیشه پاک می‌شود. این کار برگشت‌ناپذیر است.', delAccountPass: 'برای تأیید، گذرواژه‌تان را وارد کنید', delAccountBtn: 'حذف همیشگی حساب', dangerZone: 'منطقه‌ی خطر',
        // two-step sign-in + sessions
        tfTitle: 'ورود دومرحله‌ای', tfOn: 'فعال', tfOff: 'غیرفعال', tfIntro: 'علاوه بر گذرواژه، هنگام ورود یک کد ۶ رقمی از برنامه‌ی احراز هویت (مثل Google Authenticator یا Aegis) می‌خواهیم. حتی اگر گذرواژه‌تان لو برود، حساب امن می‌ماند.',
        tfEnable: 'فعال‌سازی', tfPassword: 'گذرواژه‌ی فعلی', tfScan: 'این کد QR را با برنامه‌ی احراز هویت اسکن کنید، یا کلید زیر را دستی وارد کنید:', tfCodeLabel: 'کد ۶ رقمی برنامه', tfConfirm: 'تأیید و فعال‌سازی',
        tfCodesTitle: 'کدهای بازیابی', tfCodesText: 'این ۱۰ کد را همین حالا در جای امنی ذخیره کنید. اگر گوشی‌تان را گم کردید، هرکدام یک‌بار به‌جای کد برنامه کار می‌کند. دوباره نشان داده نمی‌شوند.', tfCopyCodes: 'کپی کدها', tfDownloadCodes: 'دانلود', tfSaved: 'ذخیره کردم',
        tfLeft: 'کد بازیابی باقی‌مانده', tfRegen: 'ساخت کدهای تازه', tfDisable: 'غیرفعال کردن', tfDisableText: 'برای غیرفعال کردن، گذرواژه و یک کد از برنامه (یا یک کد بازیابی) را وارد کنید.', tfCodeOrRecovery: 'کد برنامه یا کد بازیابی',
        tfEnabledToast: 'ورود دومرحله‌ای فعال شد', tfDisabledToast: 'ورود دومرحله‌ای غیرفعال شد', tfChallenge: 'کد ۶ رقمی برنامه‌ی احراز هویت را وارد کنید.', tfUseRecovery: 'استفاده از کد بازیابی', tfUseApp: 'استفاده از کد برنامه', tfRecoveryLabel: 'کد بازیابی', tfVerify: 'ادامه',
        ssTitle: 'نشست‌های فعال', ssIntro: 'دستگاه‌هایی که الان با حساب شما وارد شده‌اند. اگر یکی را نمی‌شناسید، خروجش دهید و گذرواژه را عوض کنید.', ssCurrent: 'این دستگاه', ssRevoke: 'خروج', ssRevokeOthers: 'خروج از همه‌ی دستگاه‌های دیگر', ssUnsupported: 'این سرور فهرست نشست‌ها را پشتیبانی نمی‌کند.', ssNone: 'نشست دیگری نیست.', ssRevoked: 'خروج انجام شد', ssUnknown: 'مرورگر ناشناس',
        // legal + export
        acceptPre: 'با ثبت‌نام،', acceptTerms: 'شرایط استفاده', acceptAnd: 'و', acceptPrivacy: 'سیاست حریم خصوصی', acceptPost: ' را می‌پذیرم.',
        exTitle: 'دریافت داده‌هایم', exText: 'یک فایل ZIP از همه‌ی چیزهایی که از شما داریم (پروفایل، ایمیل‌ها، حساب‌های تأییدشده، سایت‌ها، آمار و عکس‌ها). کلیدها و گذرواژه هرگز داخلش نیست.', exBtn: 'دانلود داده‌هایم', exPass: 'برای تأیید، گذرواژه‌تان را وارد کنید', exMaking: 'در حال ساخت فایل…', exDone: 'فایل دانلود شد',
        vLinkedin: 'لینکدین', vLiText: 'با ورود به لینکدین ثابت می‌کنید این حساب مال شماست. نام شما روی پروفایل نشان داده می‌شود (لینکدین آدرس پروفایل نمی‌دهد، پس لینکی گذاشته نمی‌شود).', vLiBtn: 'تأیید با لینکدین', vLiOk: 'لینکدین تأیید شد ✓', vLiFail: 'تأیید لینکدین ناموفق بود.',
        // security
        nav_security: 'امنیت', d_security: 'گذرواژه، ورود دومرحله‌ای و دستگاه‌های واردشده.', currentPassword: 'گذرواژه‌ی فعلی', newPassword: 'گذرواژه‌ی جدید', confirmPassword: 'تکرار گذرواژه‌ی جدید',
        changePassword: 'تغییر گذرواژه', passwordChanged: 'گذرواژه تغییر کرد', recentCodeNote: 'چون تازه با کد ایمیلی وارد شده‌اید، لازم نیست گذرواژه‌ی فعلی را وارد کنید.',
    },
    en: {
        codeLogin: 'Sign in with an email code', passwordLogin: 'Sign in with a password', sendCode: 'Send code', codeSent: 'A 6-digit code was sent to your email.',
        codeLabel: '6-digit code', codeEnter: 'Sign in', codeResend: 'Send again', codeResendIn: 'Send again in', codeHintLogin: 'Forgot your password? Sign in with an email code, then set a new password.', seconds: 's',
        vGithub: 'GitHub', vGhHandle: 'GitHub username', vGhStart: 'Start GitHub verification', vGhGistHelp: 'On GitHub, create a PUBLIC gist with this account. Use exactly this file name and content, then press “Check now”:',
        vGhFilename: 'File name', vGhContent: 'Content', vGhOpenGist: 'Create a gist on GitHub', vGhOauth: 'Sign in with GitHub', vGhOr: 'or', vGhRemoveGist: 'You can delete the gist once you are verified.',
        vGhOk: 'GitHub verified ✓', vGhFail: 'GitHub verification failed.',
        nav_share: 'QR code & card', d_share: 'A QR code, a printable business card and a shareable image of your profile.', shareQr: 'Profile QR code', shareLink: 'Copy link', shareQrSvg: 'Download QR (SVG)', shareQrPng: 'Download QR (PNG)',
        shareCardTitle: 'Printable business card', shareCardD: 'An 85×55 mm card, or an A4 sheet of ten, ready to print.', shareCardOpen: 'Open the card for printing', shareImgTitle: 'Shareable image', shareImgD: 'A portrait card for a post or story, with your photo, name and QR code.',
        shareImgPng: 'Download PNG', shareImgSvg: 'Download SVG', shareVcard: 'Download contact card (vCard)', shareHidden: 'Your profile is private. Turn on “Show profile” in Privacy to get a QR code and card.', shareDownloading: 'Making the image…',
        nav_stats: 'Statistics', d_stats: 'See how often your avatar and profile are shown, and on which sites.', stProfile: 'Profile views', stAvatar: 'Avatar views', stExport: 'Exports (JSON, QR…)', stApi: 'API lookups',
        stDays7: '7 days', stDays30: '30 days', stDays90: '90 days', stTop: 'Sites that used your data', stNoRef: 'No sites yet. When your avatar is shown on a site, it will appear here.',
        stPrivacy: 'Only daily counters are stored: no IP address, no browser, nothing about the visitor. Your own visits are not counted.', stTotal: 'Total', stPerDay: 'per day',
        delAccount: 'Delete account', delAccountText: 'Your account, profile, photos, emails and API keys are deleted for good. This cannot be undone.', delAccountPass: 'To confirm, enter your password', delAccountBtn: 'Delete my account for good', dangerZone: 'Danger zone',
        tfTitle: 'Two-step sign-in', tfOn: 'On', tfOff: 'Off', tfIntro: 'Besides your password, sign-in asks for a 6-digit code from an authenticator app (Google Authenticator, Aegis…). Your account stays safe even if your password leaks.',
        tfEnable: 'Turn on', tfPassword: 'Current password', tfScan: 'Scan this QR code with your authenticator app, or type the key by hand:', tfCodeLabel: '6-digit code from the app', tfConfirm: 'Confirm and turn on',
        tfCodesTitle: 'Recovery codes', tfCodesText: 'Save these 10 codes somewhere safe right now. If you lose your phone, each one works once in place of an app code. They will not be shown again.', tfCopyCodes: 'Copy codes', tfDownloadCodes: 'Download', tfSaved: 'I saved them',
        tfLeft: 'recovery codes left', tfRegen: 'Make new codes', tfDisable: 'Turn off', tfDisableText: 'To turn it off, enter your password and a code from the app (or a recovery code).', tfCodeOrRecovery: 'App code or recovery code',
        tfEnabledToast: 'Two-step sign-in is on', tfDisabledToast: 'Two-step sign-in is off', tfChallenge: 'Enter the 6-digit code from your authenticator app.', tfUseRecovery: 'Use a recovery code', tfUseApp: 'Use an app code', tfRecoveryLabel: 'Recovery code', tfVerify: 'Continue',
        ssTitle: 'Active sessions', ssIntro: 'Devices signed in to your account right now. If you do not recognise one, sign it out and change your password.', ssCurrent: 'This device', ssRevoke: 'Sign out', ssRevokeOthers: 'Sign out of all other devices', ssUnsupported: 'This server cannot list sessions.', ssNone: 'No other sessions.', ssRevoked: 'Signed out', ssUnknown: 'Unknown browser',
        acceptPre: 'By signing up you accept the', acceptTerms: 'Terms of Service', acceptAnd: 'and the', acceptPrivacy: 'Privacy Policy', acceptPost: '.',
        exTitle: 'Download my data', exText: 'A ZIP file of everything we hold about you (profile, emails, verified accounts, sites, statistics and photos). Passwords and keys are never included.', exBtn: 'Download my data', exPass: 'To confirm, enter your password', exMaking: 'Building the file…', exDone: 'File downloaded',
        vLinkedin: 'LinkedIn', vLiText: 'Sign in with LinkedIn to prove this account is yours. Your name is shown on the profile (LinkedIn gives no profile address, so there is no link).', vLiBtn: 'Verify with LinkedIn', vLiOk: 'LinkedIn verified ✓', vLiFail: 'LinkedIn verification failed.',
        nav_security: 'Security', d_security: 'Password, two-step sign-in and signed-in devices.', currentPassword: 'Current password', newPassword: 'New password', confirmPassword: 'Repeat the new password',
        changePassword: 'Change password', passwordChanged: 'Password changed', recentCodeNote: 'You just signed in with an email code, so the current password is not needed.',
    },
};
