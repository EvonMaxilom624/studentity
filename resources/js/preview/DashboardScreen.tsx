import type { PreviewProps } from './types';

export default function DashboardScreen({
    screen,
    questionnaireData,
    simulatedDocuments,
    onDialog,
    onMessage,
}: PreviewProps) {
    const isCompleted = questionnaireData.completed;
    const progressPercent = isCompleted
        ? 100
        : (questionnaireData.step === 1 ? 50 : (questionnaireData.preference ? 25 : 0));

    const completedSections = isCompleted ? 3 : (questionnaireData.step === 1 ? 1 : (questionnaireData.preference ? 1 : 0));
    const remainingSections = Math.max(0, 3 - completedSections);

    const docStatus = simulatedDocuments['academic_id']?.status;

    return (
        <section className="screen" id="screen-dashboard" aria-label="Synthetic student dashboard mockup" hidden={screen !== 'dashboard'}>
            <div className="app-shell">
                <aside className="sidebar">
                    <div className="brand"><span className="brand-symbol"><svg aria-hidden="true"><use href="#i-brand" /></svg></span>studentity<em>.</em></div>
                    <div className="nav-label">YOUR WORKSPACE</div>
                    <nav className="side-nav" aria-label="Student navigation">
                        <button type="button" className="active" aria-current="page"><svg aria-hidden="true"><use href="#i-grid" /></svg>Overview</button>
                        <button type="button" data-dialog="profile" onClick={(event) => { event.preventDefault(); onDialog('profile'); }}><svg aria-hidden="true"><use href="#i-user" /></svg>My profile</button>
                        <button type="button" data-dialog="checklist" onClick={(event) => { event.preventDefault(); onDialog('checklist'); }}><svg aria-hidden="true"><use href="#i-file" /></svg>Requirements<span className="nav-count">2</span></button>
                        <button type="button" data-info="Submission history is a future screen. There are no submitted records in this synthetic design." onClick={(event) => { event.preventDefault(); onMessage('Submission history is a future screen. There are no submitted records in this synthetic design.'); }}><svg aria-hidden="true"><use href="#i-folder" /></svg>Submissions</button>
                        <button type="button" data-info="Notifications are sample UI elements. No university message or delivery is represented." onClick={(event) => { event.preventDefault(); onMessage('Notifications are sample UI elements. No university message or delivery is represented.'); }}><svg aria-hidden="true"><use href="#i-bell" /></svg>Notifications<span className="nav-count">1</span></button>
                    </nav>
                    <div className="nav-label" style={{ marginTop: '27px' }}>PREFERENCES</div>
                    <nav className="side-nav" aria-label="Account navigation">
                        <button type="button" data-info="Account and notification settings will be designed separately. No account or preference is stored in this preview." onClick={(event) => { event.preventDefault(); onMessage('Account and notification settings will be designed separately. No account or preference is stored in this preview.'); }}><svg aria-hidden="true"><use href="#i-settings" /></svg>Account settings</button>
                    </nav>
                    <div className="side-support">
                        <h3>A little guidance?</h3>
                        <p>Find the next step and understand what your profile needs.</p>
                        <button type="button" data-dialog="checklist" onClick={(event) => { event.preventDefault(); onDialog('checklist'); }}>Visit help & guidance ↗</button>
                    </div>
                    <div className="sidebar-profile">
                        <span className="avatar">AR</span>
                        <div><strong>Alex Rivera</strong><small>Synthetic student</small></div>
                        <svg aria-hidden="true"><use href="#i-chevron" /></svg>
                    </div>
                </aside>
                <div className="app-main">
                    <header className="app-header">
                        <div className="breadcrumb">Student portal <span style={{ margin: '0 9px', color: '#bac7cc' }}>/</span> <strong>Overview</strong></div>
                        <div className="mobile-menu"><span className="brand-symbol"><svg aria-hidden="true"><use href="#i-brand" /></svg></span>studentity.</div>
                        <div className="header-actions">
                            <span className="period"><svg aria-hidden="true"><use href="#i-calendar" /></svg>Sample academic period</span>
                            <button className="icon-button" type="button" aria-label="View sample notifications" data-info="You’re viewing synthetic sample notifications. No real university message has been sent." onClick={(event) => { event.preventDefault(); onMessage('You’re viewing synthetic sample notifications. No real university message has been sent.'); }}><svg aria-hidden="true"><use href="#i-bell" /></svg><span className="dot" /></button>
                            <span className="avatar header-avatar" aria-label="Alex Rivera, synthetic student">AR</span>
                        </div>
                    </header>
                    <div className="dashboard-content">
                        <div className="page-heading">
                            <div>
                                <div className="eyebrow">Your space to move forward</div>
                                <h1>Good morning, Alex.</h1>
                                <p>Here’s where things stand with your student profile.</p>
                            </div>
                            <button type="button" className="btn light" data-dialog="profile" onClick={(event) => { event.preventDefault(); onDialog('profile'); }}><svg aria-hidden="true"><use href="#i-user" /></svg>View my profile</button>
                        </div>
                        <div className="dashboard-grid">
                            <div className="primary-column">
                                <section className="profile-banner">
                                    <div className="eyebrow">One step at a time</div>
                                    <h2>A little progress<br />goes a long way.</h2>
                                    <p>Continue your questionnaire and help your university get to know you better.</p>
                                    <button type="button" className="btn primary" data-dialog="profile" onClick={(event) => { event.preventDefault(); onDialog('profile'); }}>Continue my profile<svg aria-hidden="true"><use href="#i-arrow" /></svg></button>
                                    <div className="banner-art" aria-hidden="true" />
                                </section>
                                <section className="card">
                                    <div className="card-heading">
                                        <h2>Your next steps</h2>
                                        <span className="pill neutral" style={{ fontSize: '8px', padding: '3px 6px' }}>2 actions</span>
                                    </div>
                                    <div className="task-row">
                                        <span className="task-icon"><svg aria-hidden="true"><use href="#i-file" /></svg></span>
                                        <div className="task-copy">
                                            <h3>Complete your profiling questionnaire</h3>
                                            <p>Tell us about your background and support needs.</p>
                                            {isCompleted ? (
                                                <span className="pill mint">Completed</span>
                                            ) : progressPercent > 0 ? (
                                                <span className="pill mint">In progress</span>
                                            ) : (
                                                <span className="pill neutral">Not started</span>
                                            )}
                                        </div>
                                        <button className="task-arrow" type="button" aria-label="Preview profiling questionnaire" data-dialog="profile" onClick={(event) => { event.preventDefault(); onDialog('profile'); }}><svg aria-hidden="true"><use href="#i-arrow" /></svg></button>
                                    </div>
                                    <div className="task-row">
                                        <span className="task-icon"><svg aria-hidden="true"><use href="#i-folder" /></svg></span>
                                        <div className="task-copy">
                                            <h3>Check your identity requirements</h3>
                                            <p>See what evidence is needed for academic review.</p>
                                            {docStatus === 'clean' ? (
                                                <span className="pill mint">Evidence staged (clean)</span>
                                            ) : docStatus === 'unsafe' ? (
                                                <span className="pill pending">Scan alert (quarantined)</span>
                                            ) : (
                                                <span className="pill neutral">Not submitted</span>
                                            )}
                                        </div>
                                        <button className="task-arrow" type="button" aria-label="View sample requirements checklist" data-dialog="checklist" onClick={(event) => { event.preventDefault(); onDialog('checklist'); }}><svg aria-hidden="true"><use href="#i-arrow" /></svg></button>
                                    </div>
                                    <div className="task-row waiting">
                                        <span className="task-icon"><svg aria-hidden="true"><use href="#i-clock" /></svg></span>
                                        <div className="task-copy">
                                            <h3>Academic identity review</h3>
                                            <p>Your academic selections remain provisional until reviewed.</p>
                                            <span className="pill pending">Not yet reviewed</span>
                                        </div>
                                    </div>
                                </section>
                                <section className="card">
                                    <div className="card-heading">
                                        <h2>Recent activity</h2>
                                        <span className="muted" style={{ fontSize: '8px' }}>Sample activity</span>
                                    </div>
                                    <div className="recent-row">
                                        <span className="timeline-dot" />
                                        <div>
                                            <h3>{isCompleted ? 'Questionnaire completed · review step' : progressPercent > 0 ? 'Profiling questionnaire in progress' : 'Workspace opened'}</h3>
                                            <p>Sample event · in-memory session only</p>
                                        </div>
                                    </div>
                                </section>
                            </div>
                            <aside className="secondary-column" aria-label="Student profile summary">
                                <section className="card student-card">
                                    <div className="eyebrow">Your student profile <svg aria-hidden="true" style={{ height: '13px', width: '13px' }}><use href="#i-user" /></svg></div>
                                    <h2>Alex Rivera</h2>
                                    <p className="program">BS Information Technology · sample</p>
                                    <span className="pill pending"><svg aria-hidden="true"><use href="#i-clock" /></svg>Academic details provisional</span>
                                    <dl>
                                        <div><dt>Classification</dt><dd>Continuing/Regular</dd></div>
                                        <div><dt>Academic status</dt><dd>Not verified</dd></div>
                                        <div><dt>System ID</dt><dd>Not issued in preview</dd></div>
                                        <div><dt>Record</dt><dd>Synthetic example</dd></div>
                                    </dl>
                                    <p className="footnote">Email verification and academic identity review are separate steps. This card does not certify enrollment.</p>
                                </section>
                                <section className="card progress-card">
                                    <h2>Your profile, taking shape</h2>
                                    <div className="progress-top">
                                        <span>Sample questionnaire completion</span>
                                        <strong>{progressPercent}%</strong>
                                    </div>
                                    <div className="progress-track" role="img" aria-label={`Synthetic questionnaire progress: ${progressPercent} percent`}>
                                        <span style={{ width: `${progressPercent}%` }} />
                                    </div>
                                    <div className="progress-steps">
                                        <span className={completedSections > 0 ? 'done' : ''}><i></i>{completedSections} section{completedSections === 1 ? '' : 's'} complete</span>
                                        <span><i></i>{remainingSections} remaining</span>
                                    </div>
                                    <p>Questionnaire progress does not indicate verified identity, academic approval, or clearance.</p>
                                </section>
                                <section className="notice-card">
                                    <svg aria-hidden="true"><use href="#i-help" /></svg>
                                    <div>
                                        <h3>Need a hand getting started?</h3>
                                        <p>Review the checklist to see how the pieces fit together.</p>
                                    </div>
                                </section>
                            </aside>
                        </div>
                        <footer className="app-footer">
                            <span>Studentity · Student workspace design</span>
                            <div>
                                <button type="button" className="text-button" style={{ fontSize: '8px', color: '#7e959c' }} data-info="Help, privacy and terms screens are not part of this first visual draft. Approved legal text has not been supplied." onClick={(event) => { event.preventDefault(); onMessage('Help, privacy and terms screens are not part of this first visual draft. Approved legal text has not been supplied.'); }}>Help · Privacy · Terms</button>
                            </div>
                        </footer>
                    </div>
                    <nav className="mobile-bottom" aria-label="Mobile student navigation">
                        <button type="button" className="active" aria-current="page"><svg aria-hidden="true"><use href="#i-grid" /></svg>Overview</button>
                        <button type="button" data-dialog="profile" onClick={(event) => { event.preventDefault(); onDialog('profile'); }}><svg aria-hidden="true"><use href="#i-user" /></svg>My profile</button>
                        <button type="button" data-dialog="checklist" onClick={(event) => { event.preventDefault(); onDialog('checklist'); }}><svg aria-hidden="true"><use href="#i-file" /></svg>Requirements</button>
                        <button type="button" data-info="Account settings are a future screen. This preview stores no preferences." onClick={(event) => { event.preventDefault(); onMessage('Account settings are a future screen. This preview stores no preferences.'); }}><svg aria-hidden="true"><use href="#i-settings" /></svg>Settings</button>
                    </nav>
                </div>
            </div>
        </section>
    );
}
