import QuestionnaireScreen from './QuestionnaireScreen';
import RequirementsScreen from './RequirementsScreen';
import { useEffect, useState } from 'react';
import DashboardScreen from './DashboardScreen';
import LoginScreen from './LoginScreen';
import PreviewChrome from './PreviewChrome';
import RegistrationScreen from './RegistrationScreen';
import type { Device, Method, PreviewProps, QuestionnaireData, Screen, SimulatedDocument } from './types';
import './preview.css';
import './workspace.css';

const captions: Record<Screen, string> = {
    login: '01 / Login. Password and email-code layouts. No credentials are sent or stored.',
    register: '02 / Registration. Academic selections are provisional; legal acknowledgment is unavailable in this preview.',
    questionnaire: '04 / Questionnaire. Example answers are temporary, never saved or officially submitted.',
    requirements: '05 / Requirements. Sample pending, unavailable and read-only evidence states; no file operations.',
    dashboard: '03 / Student dashboard. Synthetic tasks and questionnaire progress do not establish academic verification or clearance.',
};

const initialQuestionnaire: QuestionnaireData = {
    preference: '',
    goal: '',
    note: '',
    step: 0,
    completed: false,
};

/** Synthetic-only presentation. No API client, credential state, account action, or storage. */
export default function PreviewApp() {
    const [screen, setScreen] = useState<Screen>('login');
    const [device, setDevice] = useState<Device>('desktop');
    const [method, setMethod] = useState<Method>('password');
    const [passwordVisible, setPasswordVisible] = useState(false);
    const [message, setMessage] = useState<string | null>(null);

    const [questionnaireData, setQuestionnaireData] = useState<QuestionnaireData>(initialQuestionnaire);
    const [simulatedDocuments, setSimulatedDocuments] = useState<Record<string, SimulatedDocument>>({});

    useEffect(() => {
        if (!message) return;
        const timer = window.setTimeout(() => setMessage(null), 6500);
        return () => window.clearTimeout(timer);
    }, [message]);

    const navigate = (next: Screen) => {
        setScreen(next);
        setPasswordVisible(false);
        setMessage(null);
        window.scrollTo({ top: 0, behavior: 'instant' });
    };

    const handleSimulateDocument = (itemKey: string, scenario: 'clean' | 'unsafe', mediaType: string) => {
        const randomHex = () => Math.random().toString(16).substring(2, 10);
        const objectId = `syn-obj-${randomHex()}-${randomHex()}`;
        const scanId = `syn-scn-${randomHex()}-${randomHex()}`;

        if (scenario === 'clean') {
            const previewId = `syn-prv-${randomHex()}-${randomHex()}`;
            setSimulatedDocuments(prev => ({
                ...prev,
                [itemKey]: {
                    itemKey,
                    mediaType,
                    status: 'clean',
                    objectId,
                    scanId,
                    previewId,
                    message: 'Scan clean · Preview generated with separate object ID',
                },
            }));
            setMessage('FND-06 Pipeline Simulation: File scanned clean; immutable preview generated.');
        } else {
            setSimulatedDocuments(prev => ({
                ...prev,
                [itemKey]: {
                    itemKey,
                    mediaType,
                    status: 'unsafe',
                    objectId,
                    scanId,
                    message: 'Scan unsafe · File isolated in quarantine; no preview produced',
                },
            }));
            setMessage('FND-06 Pipeline Simulation: Active content/malware marker detected. Object quarantined.');
        }
    };

    const handleResetDocument = (itemKey: string) => {
        setSimulatedDocuments(prev => {
            const next = { ...prev };
            delete next[itemKey];
            return next;
        });
        setMessage('Simulated document cleared.');
    };

    const props: PreviewProps = {
        screen,
        device,
        method,
        passwordVisible,
        questionnaireData,
        simulatedDocuments,
        onNavigate: navigate,
        onDeviceChange: setDevice,
        onMethodChange: setMethod,
        onPasswordVisibilityChange: setPasswordVisible,
        onQuestionnaireChange: setQuestionnaireData,
        onSimulateDocument: handleSimulateDocument,
        onResetDocument: handleResetDocument,
        onMessage: setMessage,
        onDialog: kind => navigate(kind === 'profile' ? 'questionnaire' : 'requirements'),
    };

    return <>
        <PreviewChrome {...props} />
        <main className="stage-wrap">
            <div className={`stage${device === 'mobile' ? ' mobile' : ''}`}>
                {screen === 'login' && <LoginScreen {...props} />}
                {screen === 'register' && <RegistrationScreen {...props} />}
                {screen === 'dashboard' && <DashboardScreen {...props} />}
                {screen === 'questionnaire' && <QuestionnaireScreen {...props} />}
                {screen === 'requirements' && <RequirementsScreen {...props} />}
            </div>
            <p className="review-caption">{captions[screen]}</p>
            <p className="review-caption"><a href="/m0/health">M0 foundation health</a></p>
        </main>
        {message && <div className="toast" role="status" aria-live="polite">{message}</div>}
    </>;
}
