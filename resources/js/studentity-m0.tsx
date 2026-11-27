import React from 'react';
import { createRoot } from 'react-dom/client';
import PreviewApp from './preview/PreviewApp';

const root = document.getElementById('app');
if (!root) throw new Error('Missing app root');
createRoot(root).render(<React.StrictMode><PreviewApp /></React.StrictMode>);
