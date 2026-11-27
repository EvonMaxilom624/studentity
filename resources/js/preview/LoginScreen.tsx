import type { PreviewProps } from './types';

export default function LoginScreen({ screen, device, method, passwordVisible, onNavigate, onDeviceChange, onMethodChange, onPasswordVisibilityChange, onMessage, onDialog }: PreviewProps) {
 return (
<section className="screen" id="screen-login" aria-label="Login screen mockup" hidden={screen !== "login"}>
 <div className="auth-shell">
  <aside className="welcome">
   <div className="brand"><span className="brand-symbol"><svg aria-hidden="true"><use href="#i-brand"></use></svg></span>studentity<em>.</em></div>
   <div className="welcome-copy"><p className="eyebrow">Your student journey, connected</p><h1>Less paperwork.<br />More <span>possibility.</span></h1><p>One place for your student profile, requirements, and the next step in your university journey.</p></div>
   <div className="journey-art" aria-hidden="true"><div className="art-orbit"></div><div className="art-card"><div className="art-card-head"><span className="art-avatar"><svg><use href="#i-user"></use></svg></span><div className="art-lines"><i></i><i></i></div></div><div className="art-progress"><i></i><i></i><i></i><i></i><i></i></div><div className="art-small">YOUR JOURNEY, ONE STEP AT A TIME</div></div><div className="art-label"><svg><use href="#i-check"></use></svg>A little more organized.</div></div>
   <div className="welcome-footer"><svg aria-hidden="true"><use href="#i-shield"></use></svg>Designed around your student journey.</div>
  </aside>
  <div className="auth-side">
   <div className="auth-top">New to Studentity? <button className="text-button" type="button" data-go="register" onClick={(event) => { event.preventDefault(); onNavigate('register'); }}>Create an account <span aria-hidden="true">↗</span></button></div>
   <div className="auth-form">
    <div className="brand mobile-brand"><span className="brand-symbol"><svg aria-hidden="true"><use href="#i-brand"></use></svg></span>studentity<span style={{"color": "var(--teal)"}}>.</span></div>
    <div className="eyebrow">Student portal</div><h2>Welcome back.</h2><p>Sign in to pick up where you left off.</p>
    <div className="login-modes" role="group" aria-label="Sign-in method"><button type="button" data-mode="password" aria-pressed={method === "password"} onClick={(event) => { event.preventDefault(); onMethodChange('password'); }}>Password</button><button type="button" data-mode="code" aria-pressed={method === "code"} onClick={(event) => { event.preventDefault(); onMethodChange('code'); }}>Email code</button></div>
    <div className="field"><label htmlFor="login-id" id="identifier-label">{method === "code" ? "Verified personal email" : "Email or verified student identifier"}</label><input id="login-id" type="text" placeholder="e.g. alex@example.invalid" autoComplete="off" aria-describedby="identifier-hint" /><div className="field-note" id="identifier-hint">{method === "code" ? "Use your verified personal email for a single-use code." : "Use personal email, System ID, or a verified university ID."}</div></div>
    <div id="password-fields" hidden={method === "code"}><div className="field"><label htmlFor="login-password">Password</label><div className="input-wrap"><input id="login-password" placeholder="Enter your password" autoComplete="off" type={passwordVisible ? "text" : "password"} /><button type="button" id="password-visibility" aria-pressed={passwordVisible} aria-label={passwordVisible ? "Hide password" : "Show password"} onClick={(event) => { event.preventDefault(); onPasswordVisibilityChange(!passwordVisible); }}><svg aria-hidden="true"><use href="#i-eye"></use></svg></button></div></div><div className="login-options"><span className="muted">Using a shared device? Sign out when finished.</span><button type="button" className="text-button" data-info="Password recovery is a separate account-security workflow. This mockup does not send email or accept recovery requests." onClick={(event) => { event.preventDefault(); onMessage("Password recovery is a separate account-security workflow. This mockup does not send email or accept recovery requests."); }}>Forgot password?</button></div></div>
    <p id="code-hint" className="small muted" style={{"margin": "17px 0 23px"}} hidden={method !== "code"}>We’ll send a single-use code to your verified personal email. No email is sent in this preview.</p>
    <button type="button" className="btn primary full" id="login-action" data-info="This is a screen design, not a working sign-in. No credentials have been sent or stored. Use the Dashboard tab to review the synthetic dashboard." onClick={(event) => { event.preventDefault(); onMessage("This is a screen design, not a working sign-in. No credentials have been sent or stored. Use the Dashboard tab to review the synthetic dashboard."); }}><span id="login-action-label">{method === "code" ? "Send email code" : "Sign in"}</span><svg aria-hidden="true"><use href="#i-arrow"></use></svg></button>
    <div className="auth-hint"><svg aria-hidden="true"><use href="#i-info"></use></svg><div>Your university ID becomes a sign-in option only after it has been verified. Staff access requires an additional security step.</div></div>
    <div className="security-foot"><svg aria-hidden="true"><use href="#i-lock"></use></svg>Never share your password or one-time code.</div>
   </div>
  </div>
 </div>
</section>

 );
}
