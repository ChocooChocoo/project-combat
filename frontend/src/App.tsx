import { Navigate, Outlet, Route, Routes, useLocation, useParams } from 'react-router'
import { AppShell } from '@/components/app-shell'
import { workspaceHome } from '@/components/gym-nav'
import { GymWorkspaceLayout } from '@/components/gym-workspace-layout'
import { Skeleton } from '@/components/ui/skeleton'
import { useAuth } from '@/lib/auth'
import { NotificationsProvider } from '@/lib/notifications'
import { approvedGym, homePath } from '@/lib/gym-access'
import AcceptInvite from '@/pages/AcceptInvite'
import AdminHome from '@/pages/admin/AdminHome'
import AdminPermissions from '@/pages/admin/AdminPermissions'
import AdminNotices from '@/pages/admin/AdminNotices'
import ReviewApplication from '@/pages/admin/ReviewApplication'
import ApplicationDetail from '@/pages/ApplicationDetail'
import FirstPassword from '@/pages/FirstPassword'
import ForgotPassword from '@/pages/ForgotPassword'
import GymApply from '@/pages/GymApply'
import GymPublic from '@/pages/GymPublic'
import EmployeeRecord from '@/pages/gym/EmployeeRecord'
import GymApplications from '@/pages/gym/GymApplications'
import GymAnnouncements from '@/pages/gym/GymAnnouncements'
import GymBookings from '@/pages/gym/GymBookings'
import GymCheckIn from '@/pages/gym/GymCheckIn'
import GymAttendance from '@/pages/gym/GymAttendance'
import CustomerRecord from '@/pages/gym/CustomerRecord'
import GymCustomers from '@/pages/gym/GymCustomers'
import GymFinance from '@/pages/gym/GymFinance'
import GymLeave from '@/pages/gym/GymLeave'
import GymMembers from '@/pages/gym/GymMembers'
import GymPayments from '@/pages/gym/GymPayments'
import GymPayroll from '@/pages/gym/GymPayroll'
import GymPayrollRun from '@/pages/gym/GymPayrollRun'
import GymPlans from '@/pages/gym/GymPlans'
import GymPurchasing from '@/pages/gym/GymPurchasing'
import GymRooms from '@/pages/gym/GymRooms'
import GymSchedule from '@/pages/gym/GymSchedule'
import GymSession from '@/pages/gym/GymSession'
import GymServices from '@/pages/gym/GymServices'
import GymSettings from '@/pages/gym/GymSettings'
import GymStaff from '@/pages/gym/GymStaff'
import GymStaffAttendance from '@/pages/gym/GymStaffAttendance'
import GymStock from '@/pages/gym/GymStock'
import MemberRecord from '@/pages/gym/MemberRecord'
import MyWork from '@/pages/gym/MyWork'
import PurchaseOrder from '@/pages/gym/PurchaseOrder'
import MembershipApply from '@/pages/member/MembershipApply'
import MyMembership, { MyMembershipList } from '@/pages/member/MyMemberships'
import MyRecord from '@/pages/member/MyRecord'
import MyBookings from '@/pages/member/MyBookings'
import PrivateCoaching from '@/pages/member/PrivateCoaching'
import SessionDetails from '@/pages/member/SessionDetails'
import Timetable from '@/pages/member/Timetable'
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

/** Screens for one account type only. The backend refuses the other type too (403). */
function AccountOnly({ type }: { type: 'owner' | 'member' }) {
  const { user } = useAuth()
  return user?.account_type === type ? <Outlet /> : <Navigate to="/" replace />
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
    <NotificationsProvider>
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
          <Route element={<AccountOnly type="owner" />}>
            <Route path="/apply" element={<GymApply />} />
            <Route path="/applications" element={<MyApplications />} />
            <Route path="/applications/:id" element={<ApplicationDetail />} />
            <Route path="/applications/:id/edit" element={<GymApply />} />
          </Route>
          <Route element={<AccountOnly type="member" />}>
            <Route path="/gyms/:gymId/apply" element={<MembershipApply />} />
          </Route>
          <Route path="/memberships" element={<MyMembershipList />} />
          <Route path="/bookings" element={<MyBookings />} />
          <Route path="/memberships/:id" element={<MyMembership />} />
          <Route element={<GymMember />}>
            <Route path="/gyms/:gymId/my-record" element={<MyRecord />} />
            <Route path="/gyms/:gymId/sessions" element={<Timetable />} />
            <Route path="/gyms/:gymId/sessions/:sessionId" element={<SessionDetails />} />
            <Route path="/gyms/:gymId/private-coaching" element={<PrivateCoaching />} />
            <Route path="/gyms/:gymId/manage" element={<GymWorkspaceLayout />}>
              <Route index element={<WorkspaceHome />} />
              <Route path="settings" element={<GymSettings />} />
              <Route path="rooms" element={<GymRooms />} />
              <Route path="schedule" element={<GymSchedule />} />
              <Route path="schedule/:sessionId" element={<GymSession />} />
              <Route path="bookings" element={<GymBookings />} />
              <Route path="check-in" element={<GymCheckIn />} />
              <Route path="attendance" element={<GymAttendance />} />
              <Route path="staff" element={<GymStaff />} />
              <Route path="staff/:userId" element={<EmployeeRecord />} />
              <Route path="staff-attendance" element={<GymStaffAttendance />} />
              <Route path="payroll" element={<GymPayroll />} />
              <Route path="payroll/:runId" element={<GymPayrollRun />} />
              <Route path="leave" element={<GymLeave />} />
              <Route path="services" element={<GymServices />} />
              <Route path="plans" element={<GymPlans />} />
              <Route path="applications" element={<GymApplications />} />
              <Route path="announcements" element={<GymAnnouncements />} />
              <Route path="members" element={<GymMembers />} />
              <Route path="members/:membershipId" element={<MemberRecord />} />
              <Route path="customers" element={<GymCustomers />} />
              <Route path="customers/:customerId" element={<CustomerRecord />} />
              <Route path="finance" element={<GymFinance />} />
              <Route path="payments" element={<GymPayments />} />
              <Route path="stock" element={<GymStock />} />
              <Route path="purchasing" element={<GymPurchasing />} />
              <Route path="purchasing/orders/:orderId" element={<PurchaseOrder />} />
              <Route path="my-work" element={<MyWork />} />
            </Route>
          </Route>
          <Route element={<AdminOnly />}>
            <Route path="/admin" element={<AdminHome />} />
            <Route path="/admin/permissions" element={<AdminPermissions />} />
            <Route path="/admin/notices" element={<AdminNotices />} />
            <Route path="/admin/applications/:id" element={<ReviewApplication />} />
          </Route>
        </Route>
      </Route>
      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
    </NotificationsProvider>
  )
}
