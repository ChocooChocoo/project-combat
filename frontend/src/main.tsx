import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import { BrowserRouter } from 'react-router'
import { ToastProvider } from '@/components/ui/toast'
import { AuthProvider } from '@/lib/auth'
import { initTheme } from '@/lib/theme'
import App from './App.tsx'
import 'leaflet/dist/leaflet.css'
import './index.css'

const stopTheme = initTheme()
if (import.meta.hot) import.meta.hot.dispose(stopTheme)

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <ToastProvider>
      <BrowserRouter>
        <AuthProvider>
          <App />
        </AuthProvider>
      </BrowserRouter>
    </ToastProvider>
  </StrictMode>,
)
