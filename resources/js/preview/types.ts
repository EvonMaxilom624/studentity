export type Screen = 'login' | 'register' | 'dashboard' | 'questionnaire' | 'requirements';
export type WorkspaceState = 'pending' | 'unavailable' | 'read-only';
export type Device = 'desktop' | 'mobile';
export type Method = 'password' | 'code';
export type DialogKind = 'profile' | 'checklist';

export interface QuestionnaireData {
    preference: string;
    goal: string;
    note: string;
    step: number;
    completed: boolean;
}

export type DocumentScanStatus = 'not_provided' | 'clean' | 'unsafe';

export interface SimulatedDocument {
    itemKey: string;
    mediaType: string;
    status: DocumentScanStatus;
    objectId?: string;
    previewId?: string;
    scanId?: string;
    message?: string;
}

export interface PreviewProps {
    screen: Screen;
    device: Device;
    method: Method;
    passwordVisible: boolean;
    questionnaireData: QuestionnaireData;
    simulatedDocuments: Record<string, SimulatedDocument>;
    onNavigate: (screen: Screen) => void;
    onDeviceChange: (device: Device) => void;
    onMethodChange: (method: Method) => void;
    onPasswordVisibilityChange: (visible: boolean) => void;
    onQuestionnaireChange: (data: QuestionnaireData) => void;
    onSimulateDocument: (itemKey: string, scenario: 'clean' | 'unsafe', mediaType: string) => void;
    onResetDocument: (itemKey: string) => void;
    onMessage: (message: string) => void;
    onDialog: (kind: DialogKind) => void;
}
