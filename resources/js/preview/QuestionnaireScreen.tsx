import { useEffect, useRef, useState } from 'react';
import StudentWorkspace from './StudentWorkspace';
import type { PreviewProps, WorkspaceState } from './types';

export default function QuestionnaireScreen({
    questionnaireData,
    onQuestionnaireChange,
    onNavigate,
    onMessage,
}: Pick<PreviewProps, 'questionnaireData' | 'onQuestionnaireChange' | 'onNavigate' | 'onMessage'>) {
    const [state, setState] = useState<WorkspaceState>('pending');
    const [error, setError] = useState('');
    const headingRef = useRef<HTMLHeadingElement>(null);
    const previousStep = useRef(questionnaireData.step);

    useEffect(() => {
        if (previousStep.current !== questionnaireData.step) {
            headingRef.current?.focus();
        }
        previousStep.current = questionnaireData.step;
    }, [questionnaireData.step]);

    const { preference, goal, note, step } = questionnaireData;
    const answered = Number(preference !== '') + Number(goal.trim() !== '');
    const readOnly = state === 'read-only';

    const next = () => {
        if (step === 0 && !preference) {
            setError('Choose a synthetic example selection to continue.');
            return;
        }
        if (step === 1 && !goal.trim()) {
            setError('Enter a synthetic example goal to continue.');
            return;
        }
        setError('');
        const nextStep = Math.min(2, step + 1);
        onQuestionnaireChange({
            ...questionnaireData,
            step: nextStep,
            completed: nextStep === 2 && !!preference && !!goal.trim(),
        });
    };

    const previous = () => {
        setError('');
        const prevStep = Math.max(0, step - 1);
        onQuestionnaireChange({
            ...questionnaireData,
            step: prevStep,
        });
    };

    const changeState = (value: WorkspaceState) => {
        setState(value);
        setError('');
    };

    return (
        <StudentWorkspace page="questionnaire" state={state} onStateChange={changeState} onNavigate={onNavigate}>
            {state === 'unavailable' ? (
                <section className="card state-card" role="alert">
                    <span className="pill pending">Service unavailable</span>
                    <h2>Your draft cannot be checked right now.</h2>
                    <p>We cannot confirm saved answers, current form rules, or submission status. This preview does not recover a draft or treat uncertain data as current.</p>
                    <button type="button" className="btn light" onClick={() => onMessage('Retry is not connected in this preview. Use the preview-state controls to explore another layout.')}>Preview retry</button>
                </section>
            ) : (
                <>
                    <div className={`workspace-banner ${readOnly ? 'readonly' : ''}`} role="status">
                        <strong>{readOnly ? 'Read-only example record' : 'Pending example draft · not saved'}</strong>
                        <p>{readOnly ? 'Viewing a synthetic historical example. Editing and official acknowledgment are unavailable.' : 'Edits exist only while this session is active. Nothing has been saved or officially submitted.'}</p>
                    </div>
                    <div className="questionnaire-grid">
                        <aside className="card section-list" aria-label="Questionnaire sections">
                            {['Study planning', 'Learning goals', 'Final review'].map((label, index) => (
                                <div key={label} className={step === index ? 'current-section' : ''} aria-current={step === index ? 'step' : undefined}>
                                    <span>{index + 1}</span>{label}
                                </div>
                            ))}
                            <div className="question-progress">
                                <label htmlFor="required-progress">Example required answers</label>
                                <progress id="required-progress" max={2} value={readOnly ? 2 : answered} />
                                <p>{readOnly ? 2 : answered} of 2 applicable required examples</p>
                                <small>Optional answers, portrait, documents, and identity checks are excluded.</small>
                            </div>
                        </aside>
                        <section className="card question-card">
                            <div className="section-heading">
                                <h2 id="question-step-heading" ref={headingRef} tabIndex={-1}>
                                    {readOnly ? 'Historical example answers' : ['Study planning', 'Learning goals', 'Review your sample answers'][step]}
                                </h2>
                                <span className="pill neutral">{readOnly ? 'Read-only' : `${step + 1} / 3`}</span>
                            </div>
                            {readOnly ? (
                                <dl className="answer-review">
                                    <dt>Study planning · example</dt>
                                    <dd>Example selection A</dd>
                                    <dt>Learning goal · example</dt>
                                    <dd>Organize my sample study schedule.</dd>
                                    <dt>Official status</dt>
                                    <dd>No official submission exists in this preview.</dd>
                                </dl>
                            ) : step === 0 ? (
                                <div className="field">
                                    <label htmlFor="sample-preference">Study planning · example question <span className="required-label">Required</span></label>
                                    <select
                                        id="sample-preference"
                                        value={preference}
                                        aria-invalid={!!error}
                                        aria-describedby={error ? 'question-error' : 'preference-help'}
                                        onChange={event => {
                                            onQuestionnaireChange({ ...questionnaireData, preference: event.target.value });
                                            setError('');
                                        }}
                                    >
                                        <option value="">Select a synthetic example</option>
                                        <option value="sample-a">Example selection A</option>
                                        <option value="sample-b">Example selection B</option>
                                    </select>
                                    <p id="preference-help" className="field-note">A neutral example for reviewing the wizard layout, not a published university question.</p>
                                </div>
                            ) : step === 1 ? (
                                <>
                                    <div className="field">
                                        <label htmlFor="sample-goal">Learning goal · example question <span className="required-label">Required</span></label>
                                        <textarea
                                            id="sample-goal"
                                            maxLength={240}
                                            value={goal}
                                            aria-invalid={!!error}
                                            aria-describedby={error ? 'question-error' : 'goal-help'}
                                            onChange={event => {
                                                onQuestionnaireChange({ ...questionnaireData, goal: event.target.value });
                                                setError('');
                                            }}
                                            placeholder="Use a fictional learning goal"
                                        />
                                        <p id="goal-help" className="field-note">Synthetic text only. Maximum 240 characters for this example.</p>
                                    </div>
                                    <div className="field">
                                        <label htmlFor="sample-note">Example note <span className="muted">Optional</span></label>
                                        <textarea
                                            id="sample-note"
                                            maxLength={240}
                                            value={note}
                                            onChange={event => onQuestionnaireChange({ ...questionnaireData, note: event.target.value })}
                                            placeholder="Optional synthetic note"
                                        />
                                        <p className="field-note">Optional answers do not change required-field completion.</p>
                                    </div>
                                </>
                            ) : (
                                <div data-review>
                                    <dl className="answer-review">
                                        <dt>Study planning · example</dt>
                                        <dd>{preference === 'sample-a' ? 'Example selection A' : 'Example selection B'}</dd>
                                        <dt>Learning goal · example</dt>
                                        <dd>{goal}</dd>
                                        <dt>Optional example note</dt>
                                        <dd>{note || 'Not provided'}</dd>
                                    </dl>
                                    <div className="notice-placeholder">Review layout only. The approved declaration text, authenticated student acknowledgment, and official form version are not connected.</div>
                                    <label className="check-row" style={{ marginTop: 16 }}>
                                        <input type="checkbox" disabled />Official acknowledgment is unavailable in this preview.
                                    </label>
                                </div>
                            )}
                            {error && <p id="question-error" className="validation-message" data-validation role="alert">{error}</p>}
                            <div className="wizard-actions">
                                {!readOnly && step > 0 ? (
                                    <button type="button" className="btn light" data-previous onClick={previous}>← Previous</button>
                                ) : (
                                    <button type="button" className="btn light" onClick={() => onNavigate('dashboard')}>Back to overview</button>
                                )}
                                {!readOnly && step < 2 ? (
                                    <button type="button" className="btn primary" data-next onClick={next}>{step === 1 ? 'Review example answers' : 'Next section'} →</button>
                                ) : (
                                    <button type="button" className="btn primary" data-official-action disabled>Official submission unavailable</button>
                                )}
                            </div>
                            <p className="field-note">Required-answer completion is not academic approval or certificate clearance.</p>
                        </section>
                    </div>
                </>
            )}
        </StudentWorkspace>
    );
}
