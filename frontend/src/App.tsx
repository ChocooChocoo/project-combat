import { Navigate, Outlet, Route, Routes, useLocation } from 'react-router'
import { AppShell } from '@/components/app-shell'
import { Spinner } from '@/components/ui/spinner'
import { useAuth } from '@/lib/auth'
import FirstPassword from '@/pages/FirstPassword'
import ForgotPassword from '@/pages/ForgotPassword'
import Home from '@/pages/Home'
import Notifications from '@/pages/Notifications'
import Profile from '@/pages/Profile'
import SignIn from '@/pages/SignIn'
import SignUp from '@/pages/SignUp'
import VerifyEmail from '@/pages/VerifyEmail'

/**
 * Sends each person to the one place they are allowed to be right now.
 * The backend enforces the same rules; this only spares them a refused request.
 */
function Guard({ need }: { need: 'guest' | 'signed-in' | 'first-password' }) {
  const { user, loading } = useAuth()
  const location = useLocation()

  if (loading) {
    return <div className="flex min-h-svh items-center justify-center"><Spinner /></div>
  }

  if (need === 'guest') return user ? <Navigate to="/" replace /> : <Outlet />
  if (!user) return <Navigate to="/sign-in" replace state={{ from: location }} />
  if (user.password_change_required) return need === 'first-password' ? <Outlet /> : <Navigate to="/first-password" replace />
  if (need === 'first-password') return <Navigate to="/" replace />
  if (!user.email_verified) return <Navigate to={`/verify-email?email=${encodeURIComponent(user.email)}`} replace />

  return <Outlet />
}

export default function App() {
  return (
    <Routes>
      <Route element={<Guard need="guest" />}>
        <Route path="/sign-in" element={<SignIn />} />
        <Route path="/sign-up" element={<SignUp />} />
        <Route path="/forgot-password" element={<ForgotPassword />} />
      </Route>
      <Route path="/verify-email" element={<VerifyEmail />} />
      <Route element={<Guard need="first-password" />}>
        <Route path="/first-password" element={<FirstPassword />} />
      </Route>
      <Route element={<Guard need="signed-in" />}>
        <Route element={<AppShell />}>
          <Route path="/" element={<Home />} />
          <Route path="/profile" element={<Profile />} />
          <Route path="/notifications" element={<Notifications />} />
        </Route>
      </Route>
      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  )
}
