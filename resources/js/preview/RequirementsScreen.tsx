import { useState } from 'react';
import StudentWorkspace from './StudentWorkspace';
import type { PreviewProps, WorkspaceState } from './types';

const defaultExamples = [
    { key: 'questionnaire', title: 'Profiling questionnaire', text: 'Continue the example wizard and review your answers.', status: 'In progress', icon: 'file', profile: true },
    { key: 'academic_id', title: 'Academic identity evidence', text: 'University ID, COR, or an approved equivalent may be required under the accepted catalogue.', status: 'Not provided', icon: 'folder' },
    { key: 'portrait', title: 'Individual portrait', text: 'Portrait requirements and permitted capture need the applicable approved policy.', status: 'Not provided', icon: 'user' },
    { key: 'review', title: 'Academic identity review', text: 'Email verification does not establish enrollment or academic identity.', status: 'Pending review', icon: 'clock' },
];

export default function RequirementsScreen({
    simulatedDocuments,
    onSimulateDocument,
    onResetDocument,
    onNavigate,
    onMessage,
}: Pick<PreviewProps, 'simulatedDocuments' | 'onSimulateDocument' | 'onResetDocument' | 'onNavigate' | 'onMessage'>) {
    const [state, setState] = useState<WorkspaceState>('pending');
    const unavailable = state === 'unavailable';
    const readOnly = state === 'read-only';

    const academicDoc = simulatedDocuments['academic_id'];

    const items = defaultExamples.map(item => {
        if (item.key === 'academic_id' && academicDoc) {
            if (academicDoc.status === 'clean') {
                return {
                    ...item,
                    status: 'Clean & Preview Ready (synthetic)',
                    text: 'Synthetic JPEG verified. Scanner passed clean, distinct preview generated. Original bytes quarantined.',
                    isClean: true,
                };
            }
            if (academicDoc.status === 'unsafe') {
                return {
                    ...item,
                    status: 'Quarantined · Unsafe (synthetic)',
                    text: 'Security alert: Active content or malware marker detected. File isolated; preview aborted.',
                    isUnsafe: true,
                };
            }
        }
        return item;
    });

    return (
        <StudentWorkspace page="requirements" state={state} onStateChange={setState} onNavigate={onNavigate}>
            <div className={`workspace-banner ${readOnly ? 'readonly' : ''}`} role={unavailable ? 'alert' : 'status'}>
                <strong>{unavailable ? 'Requirement status unavailable' : readOnly ? 'Read-only example checklist' : 'Some example steps still need attention'}</strong>
                <p>
                    {unavailable
                        ? 'Current catalogue, evidence status, and file clearance cannot be confirmed. No completion or approval can be inferred.'
                        : readOnly
                            ? 'View a synthetic historical example. New evidence and changes remain unavailable.'
                            : 'Missing or pending documents are separate from questionnaire progress. Nothing here establishes approval or clearance.'}
                </p>
            </div>
            <div className="checklist-grid">
                <section className="card">
                    <div className="card-heading">
                        <h2>{readOnly ? 'Historical checklist · example only' : 'Your next requirements'}</h2>
                        <span className="pill neutral">Illustrative catalogue</span>
                    </div>
                    {items.map(item => {
                        const isClean = 'isClean' in item && item.isClean;
                        const isUnsafe = 'isUnsafe' in item && item.isUnsafe;
                        const pillClass = unavailable
                            ? 'pending'
                            : readOnly
                                ? 'neutral'
                                : isClean
                                    ? 'mint'
                                    : isUnsafe || item.status === 'Pending review'
                                        ? 'pending'
                                        : 'neutral';

                        return (
                            <div className="task-row checklist-row" key={item.title}>
                                <span className="task-icon">
                                    <svg aria-hidden="true"><use href={`#i-${item.icon}`} /></svg>
                                </span>
                                <div className="task-copy">
                                    <h3>{item.title}</h3>
                                    <p>{item.text}</p>
                                    <span className={`pill ${pillClass}`}>
                                        {unavailable ? 'Status unavailable' : readOnly ? 'Historical example · not current' : item.status}
                                    </span>
                                </div>
                                {item.profile && !unavailable && !readOnly && (
                                    <button type="button" className="btn light" data-open-questionnaire onClick={() => onNavigate('questionnaire')}>
                                        Open example
                                    </button>
                                )}
                            </div>
                        );
                    })}
                </section>
                <aside className="secondary-column">
                    <section className="card question-card">
                        <h2>Document actions</h2>
                        <p className="muted">File submission, security scanning, and academic review are separate steps.</p>
                        <button className="btn primary full" type="button" data-official-action disabled>
                            File submission unavailable
                        </button>
                        <p className="field-note">No camera, file picker, upload, download, or document URL is connected.</p>

                        {!unavailable && !readOnly && (
                            <div className="sim-panel">
                                <h3>FND-06 Pipeline Simulation</h3>
                                <p>Simulate local synthetic document staging, antimalware scanning, and preview isolation.</p>
                                <div className="sim-actions">
                                    <button
                                        type="button"
                                        className="btn light"
                                        onClick={() => onSimulateDocument('academic_id', 'clean', 'image/jpeg')}
                                    >
                                        ✓ Simulate clean student ID (JPEG)
                                    </button>
                                    <button
                                        type="button"
                                        className="btn light"
                                        onClick={() => onSimulateDocument('academic_id', 'unsafe', 'application/pdf')}
                                    >
                                        ⚠ Simulate unsafe file (malware/script)
                                    </button>
                                    {academicDoc && (
                                        <button
                                            type="button"
                                            className="btn ghost"
                                            onClick={() => onResetDocument('academic_id')}
                                        >
                                            Reset simulation
                                        </button>
                                    )}
                                </div>

                                {academicDoc && (
                                    <div className={`sim-result ${academicDoc.status}`}>
                                        <strong>{academicDoc.status === 'clean' ? 'Scan Clean · Preview Created' : 'Scan Alert · Quarantined'}</strong>
                                        <p>{academicDoc.message}</p>
                                        <code>Object ID: {academicDoc.objectId}</code>
                                        <code>Scan ID: {academicDoc.scanId}</code>
                                        {academicDoc.previewId && <code>Preview ID: {academicDoc.previewId}</code>}
                                    </div>
                                )}
                            </div>
                        )}
                    </section>
                    <section className="card question-card">
                        <h2>What happens next?</h2>
                        <p className="muted">The accepted catalogue will determine applicable evidence. A pending review is not an accepted document.</p>
                        <button
                            type="button"
                            className="btn ghost"
                            onClick={() => onMessage('This is a synthetic checklist. Exact evidence, review policy, and operational file safeguards still require acceptance.')}
                        >
                            About this example →
                        </button>
                    </section>
                </aside>
            </div>
        </StudentWorkspace>
    );
}
