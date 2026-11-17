import React from 'react';
import { createRoot } from 'react-dom/client';

function M0App() {
  return <div style={{maxWidth: 750, margin: '4rem auto', fontFamily: 'system-ui', padding: '0 1rem'}}>
    <h1>Studentity</h1>
    <p><strong>M0 application foundation</strong> — synthetic-data-only local development.</p>
    <p>Student, coordinator, document and certificate features are intentionally disabled until their security and transaction gates pass.</p>
    <p><a href="/m0/health">View local health response</a></p>
  </div>;
}

const root = document.getElementById('app');
if (!root) throw new Error('Missing app root');
createRoot(root).render(<React.StrictMode><M0App /></React.StrictMode>);
