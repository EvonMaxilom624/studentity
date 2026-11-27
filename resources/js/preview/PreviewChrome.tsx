import type { PreviewProps } from './types';

export default function PreviewChrome({ screen, device, method, passwordVisible, onNavigate, onDeviceChange, onMethodChange, onPasswordVisibilityChange, onMessage, onDialog }: PreviewProps) {
 return (<>

<svg style={{"display": "none"}} aria-hidden="true"><defs>
<symbol id="i-brand" viewBox="0 0 24 24"><path d="m3 8 9-4 9 4-9 4-9-4Z"></path><path d="M7 10v6c3 2 7 2 10 0v-6M21 8v7"></path></symbol>
<symbol id="i-arrow" viewBox="0 0 24 24"><path d="M5 12h14m-5-5 5 5-5 5"></path></symbol>
<symbol id="i-check" viewBox="0 0 24 24"><path d="m5 12 4 4L19 6"></path></symbol>
<symbol id="i-shield" viewBox="0 0 24 24"><path d="m12 3 8 3v6c0 5-8 9-8 9s-8-4-8-9V6l8-3Z"></path><path d="m8 12 3 3 5-6"></path></symbol>
<symbol id="i-lock" viewBox="0 0 24 24"><rect x="5" y="10" width="14" height="11" rx="2"></rect><path d="M8 10V7a4 4 0 0 1 8 0v3m-4 5v2"></path></symbol>
<symbol id="i-eye" viewBox="0 0 24 24"><path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12Z"></path><circle cx="12" cy="12" r="3"></circle></symbol>
<symbol id="i-user" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"></circle><path d="M4 21v-2a8 8 0 0 1 16 0v2"></path></symbol>
<symbol id="i-grid" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1.4"></rect><rect x="14" y="3" width="7" height="7" rx="1.4"></rect><rect x="3" y="14" width="7" height="7" rx="1.4"></rect><rect x="14" y="14" width="7" height="7" rx="1.4"></rect></symbol>
<symbol id="i-file" viewBox="0 0 24 24"><path d="M14 3H5v18h14V8l-5-5Z"></path><path d="M14 3v5h5M8 12h8m-8 4h6"></path></symbol>
<symbol id="i-folder" viewBox="0 0 24 24"><path d="M3 6h7l2 3h9v11H3V6Z"></path><path d="M3 6V4h7l2 2h7v3"></path></symbol>
<symbol id="i-bell" viewBox="0 0 24 24"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9m-9 12h6"></path></symbol>
<symbol id="i-settings" viewBox="0 0 24 24"><path d="m10 3-.7 3-2.6 1L4 6l-2 3 2 2v3l-2 2 2 3 3-1 2 1 1 3h4l1-3 2-1 3 1 2-3-2-2v-3l2-2-2-3-3 1-2-1-.7-3H10Z" transform="translate(1 0) scale(.9)"></path><circle cx="12" cy="12" r="3"></circle></symbol>
<symbol id="i-clock" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"></circle><path d="M12 7v5l3 2"></path></symbol>
<symbol id="i-help" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"></circle><path d="M9 9a3 3 0 0 1 6 0c0 2-3 2-3 4m0 4h.01"></path></symbol>
<symbol id="i-chevron" viewBox="0 0 24 24"><path d="m9 5 7 7-7 7"></path></symbol>
<symbol id="i-calendar" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="16" rx="2"></rect><path d="M7 3v5m10-5v5M3 11h18"></path></symbol>
<symbol id="i-desktop" viewBox="0 0 24 24"><rect x="2" y="3" width="20" height="14" rx="2"></rect><path d="M8 21h8m-4-4v4"></path></symbol>
<symbol id="i-phone" viewBox="0 0 24 24"><rect x="6" y="2" width="12" height="20" rx="2"></rect><path d="M11 18h2"></path></symbol>
<symbol id="i-info" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"></circle><path d="M12 10v7m0-11h.01"></path></symbol>
</defs></svg>
<header className="review-bar">
 <div className="review-brand"><div className="draft-mark">s.</div><div><strong>Studentity · First look</strong><small>Visual direction 01 / university portal</small></div></div>
 <nav className="review-tabs" aria-label="Preview screen"><button type="button" data-screen="login" aria-pressed={screen === "login"} onClick={(event) => { event.preventDefault(); onNavigate('login'); }}>01 · Login</button><button type="button" data-screen="register" aria-pressed={screen === "register"} onClick={(event) => { event.preventDefault(); onNavigate('register'); }}>02 · Registration</button><button type="button" data-screen="dashboard" aria-pressed={screen === "dashboard"} onClick={(event) => { event.preventDefault(); onNavigate('dashboard'); }}>03 · Dashboard</button><button type="button" data-screen="questionnaire" aria-pressed={screen === 'questionnaire'} onClick={() => onNavigate('questionnaire')}>04 / Questionnaire</button><button type="button" data-screen="requirements" aria-pressed={screen === 'requirements'} onClick={() => onNavigate('requirements')}>05 / Requirements</button></nav>
 <div className="device-toggle" role="group" aria-label="Preview width"><button type="button" data-device="desktop" aria-pressed={device === "desktop"} onClick={(event) => { event.preventDefault(); onDeviceChange('desktop'); }}><svg><use href="#i-desktop"></use></svg>Desktop</button><button type="button" data-device="mobile" aria-pressed={device === "mobile"} onClick={(event) => { event.preventDefault(); onDeviceChange('mobile'); }}><svg><use href="#i-phone"></use></svg>Mobile</button></div>
</header>
<p className="preview-note"><span></span>DESIGN PREVIEW · All names and records are synthetic. No sign-in, submissions, or data storage.</p>

</>);
}
