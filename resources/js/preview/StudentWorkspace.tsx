import type { ReactNode } from 'react';
import type { Screen, WorkspaceState } from './types';

interface Props {
    page: 'questionnaire' | 'requirements';
    state: WorkspaceState;
    onStateChange: (state: WorkspaceState) => void;
    onNavigate: (screen: Screen) => void;
    children: ReactNode;
}

export default function StudentWorkspace({ page, state, onStateChange, onNavigate, children }: Props) {
    return <section className="screen app-shell workspace-shell" id={`screen-${page}`} aria-label={`${page} synthetic preview`}>
        <aside className="sidebar">
            <div className="brand"><span className="brand-symbol"><svg aria-hidden="true"><use href="#i-brand" /></svg></span>studentity<em>.</em></div>
            <div className="nav-label">YOUR WORKSPACE</div>
            <nav className="side-nav" aria-label="Student navigation">
                {([['dashboard', 'Overview', 'grid'], ['questionnaire', 'My profile', 'user'], ['requirements', 'Requirements', 'file']] as const).map(([target, label, icon]) =>
                    <button key={target} type="button" className={page === target ? 'active' : ''} aria-current={page === target ? 'page' : undefined} onClick={() => onNavigate(target)}><svg aria-hidden="true"><use href={`#i-${icon}`} /></svg>{label}</button>)}
            </nav>
            <div className="side-support"><h3>A preview, at your pace.</h3><p>Explore sample steps. Nothing is saved, uploaded, or submitted.</p></div>
            <div className="sidebar-profile"><span className="avatar">AR</span><div><strong>Alex Rivera</strong><small>Synthetic student</small></div></div>
        </aside>
        <div className="app-main">
            <header className="app-header"><button type="button" className="text-button" onClick={() => onNavigate('dashboard')}>← Overview</button><span className="pill neutral">Synthetic workspace</span></header>
            <div className="workspace-content">
                <div className="page-heading"><div><div className="eyebrow">Your student journey</div><h1>{page === 'questionnaire' ? 'Your profiling questionnaire' : 'Your requirements checklist'}</h1><p>{page === 'questionnaire' ? 'One section at a time. Review before anything becomes official.' : 'See what is missing, pending, or waiting for review.'}</p></div></div>
                <div className="state-controls"><span>Preview state</span><div role="group" aria-label="Synthetic page state">{(['pending', 'unavailable', 'read-only'] as const).map(value => <button type="button" key={value} data-state={value} aria-pressed={state === value} onClick={() => onStateChange(value)}>{value === 'pending' ? 'Pending' : value === 'unavailable' ? 'Unavailable' : 'Read-only'}</button>)}</div></div>
                <p className="sample-guidance">Example content only. Use synthetic answers. These are not approved university questions or document rules.</p>
                {children}
            </div>
        </div>
    </section>;
}
