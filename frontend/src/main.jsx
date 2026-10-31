import { createRoot } from 'react-dom/client';
import 'bootstrap/dist/css/bootstrap.min.css';
import '@fontsource/orbitron/latin-500.css';
import '@fontsource/orbitron/latin-700.css';
import '@fontsource/share-tech-mono/latin-400.css';
import './theme.css';
import App from './App.jsx';

createRoot(document.getElementById('root')).render(<App />);
