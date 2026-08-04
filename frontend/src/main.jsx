import { createRoot } from 'react-dom/client'
import App from './App.jsx'
import Toaster from './Toaster.jsx'
import GlobalLoader from './GlobalLoader.jsx'
import ErrorBoundary from './ErrorBoundary.jsx'

createRoot(document.getElementById('root')).render(
  <ErrorBoundary>
    <Toaster />
    <GlobalLoader />
    <App />
  </ErrorBoundary>,
)
