import { Navigate, Outlet, Route, Routes, useLocation, useParams } from 'react-router'
import { AppShell } from '@/components/app-shell'
import { workspaceHome } from '@/components/gym-nav'
import { GymWorkspaceLayout } from '@/components/gym-workspace-layout'
import { Skeleton } from '@/components/ui/skeleton'
import { useAuth } from '@/lib/auth'
import { approvedGym, homePath } from '@/lib/gym-access'
import AcceptInvite from '@/pages/AcceptInvite'
import AdminHome from '@/pages/admin/AdminHome'
import ReviewApplication from '@/pages/admin/ReviewApplication'
import ApplicationDetail from '@/pages/ApplicationDetail'
import FirstPassword from '@/pages/FirstPassword'
import ForgotPassword from '@/pages/ForgotPassword'
import GymApply from '@/pages/GymApply'
import GymPublic from '@/pages/GymPublic'
import EmployeeRecord from '@/pages/gym/EmployeeRecord'
import GymApplications from '@/pages/gym/GymApplications'
import GymLeave from '@/pages/gym/GymLeave'
import GymMembers from '@/pages/gym/GymMembers'
import GymPayments from '@/pages/gym/GymPayments'
import GymPlans from '@/pages/gym/GymPlans'
import GymRooms from '@/pages/gym/GymRooms'
import GymServices from '@/pages/gym/GymServices'
import GymSettings from '@/pages/gym/GymSettings'
import GymStaff from '@/pages/gym/GymStaff'
import MemberRecord from '@/pages/gym/MemberRecord'
import MyWork from '@/pages/gym/MyWork'
import MembershipApply from '@/pages/member/MembershipApply'
import MyMembership, { MyMembershipList } from '@/pages/member/MyMemberships'
import MyRecord from '@/pages/member/MyRecord'
import Home from '@/pages/Home'
import MyApplications from '@/pages/MyApplications'
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
    return (
      <div className="flex min-h-svh" aria-busy="true" aria-label="Loading">
        <Skeleton className="hidden w-64 shrink-0 rounded-none md:block" />
        <div className="flex flex-1 flex-col">
          <Skeleton className="h-14 rounded-none" />
          <div className="mx-auto flex w-full max-w-6xl flex-col gap-6 px-4 pt-6 md:px-8">
            <Skeleton className="h-9 w-64" />
            <Skeleton className="h-40" />
            <Skeleton className="h-64" />
          </div>
        </div>
      </div>
    )
  }

  if (need === 'guest') return user ? <Navigate to={homePath(user)} replace /> : <Outlet />
  if (!user) return <Navigate to="/sign-in" replace state={{ from: location }} />
  if (user.password_change_required) return need === 'first-password' ? <Outlet /> : <Navigate to="/first-password" replace />
  if (need === 'first-password') return <Navigate to="/" replace />
  if (!user.email_verified) return <Navigate to={`/verify-email?email=${encodeURIComponent(user.email)}`} replace />

  return <Outlet />
}

/** Platform administrator screens. The backend refuses everyone else too (403). */
function AdminOnly() {
  const { user } = useAuth()
  return user?.is_platform_admin ? <Outlet /> : <Navigate to="/" replace />
}

/** The workspace belongs to one approved gym the person actually belongs to. */
function GymMember() {
  const { user } = useAuth()
  const { gymId } = useParams()
  return approvedGym(user, Number(gymId)) ? <Outlet /> : <Navigate to="/" replace />
}

/** Opens the first workspace section this person may view. Owners start at Setup. */
function WorkspaceHome() {
  const { user } = useAuth()
  const { pathname } = useLocation()
  const gym = approvedGym(user, Number(pathname.match(/^\/gyms\/(\d+)(?:\/|$)/)?.[1]))
  return <Navigate to={gym ? workspaceHome(gym) : '/'} replace />
}

export default function App() {
  return (
    <Routes>
      <Route element={<Guard need="guest" />}>
        <Route path="/sign-in" element={<SignIn />} />
        <Route path="/sign-up" element={<SignUp />} />
        <Route path="/forgot-password" element={<ForgotPassword />} />
        <Route path="/accept-invite" element={<AcceptInvite />} />
      </Route>
      <Route path="/verify-email" element={<VerifyEmail />} />
      <Route path="/gyms/:gymId" element={<GymPublic />} />
      <Route element={<Guard need="first-password" />}>
        <Route path="/first-password" element={<FirstPassword />} />
      </Route>
      <Route element={<Guard need="signed-in" />}>
        <Route element={<AppShell />}>
          <Route path="/" element={<Home />} />
          <Route path="/profile" element={<Profile />} />
          <Route path="/notifications" element={<Notifications />} />
          <Route path="/apply" element={<GymApply />} />
          <Route path="/applications" element={<MyApplications />} />
          <Route path="/applications/:id" element={<ApplicationDetail />} />
          <Route path="/applications/:id/edit" element={<GymApply />} />
          <Route path="/gyms/:gymId/apply" element={<MembershipApply />} />
          <Route path="/memberships" element={<MyMembershipList />} />
          <Route path="/memberships/:id" element={<MyMembership />} />
          <Route element={<GymMember />}>
            <Route path="/gyms/:gymId/my-record" element={<MyRecord />} />
            <Route path="/gyms/:gymId/manage" element={<GymWorkspaceLayout />}>
              <Route index element={<WorkspaceHome />} />
              <Route path="settings" element={<GymSettings />} />
              <Route path="rooms" element={<GymRooms />} />
              <Route path="staff" element={<GymStaff />} />
              <Route path="staff/:userId" element={<EmployeeRecord />} />
              <Route path="leave" element={<GymLeave />} />
              <Route path="services" element={<GymServices />} />
              <Route path="plans" element={<GymPlans />} />
              <Route path="applications" element={<GymApplications />} />
              <Route path="members" element={<GymMembers />} />
              <Route path="members/:membershipId" element={<MemberRecord />} />
              <Route path="payments" element={<GymPayments />} />
              <Route path="my-work" element={<MyWork />} />
            </Route>
          </Route>
          <Route element={<AdminOnly />}>
            <Route path="/admin" element={<AdminHome />} />
            <Route path="/admin/applications/:id" element={<ReviewApplication />} />
          </Route>
        </Route>
      </Route>
      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  )
}
