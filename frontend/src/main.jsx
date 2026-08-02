import { createRoot } from 'react-dom/client'
import App from './App.jsx'
import Toaster from './Toaster.jsx'
import GlobalLoader from './GlobalLoader.jsx'

createRoot(document.getElementById('root')).render(
  <>
    <Toaster />
    <GlobalLoader />
    <App />
  </>,
)
