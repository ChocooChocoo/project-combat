import { Link } from 'react-router'
import { PageHeader } from '@/components/page-header'
import { BarList, ChartCard, ColumnChart, DashboardGrid, DashboardPage, DashboardState, DonutChart, RateRing, RingRow, StatStrip } from '@/components/dashboard/widgets'
import { Button } from '@/components/ui/button'
import { api } from '@/lib/api'
import { monthLabel, percent, useDashboardData, type PlatformStats } from '@/lib/dashboard'

/** P07: platform dashboard. R-19: built from gym counts only, never a gym's members, money or staff. */
export default function AdminDashboard() {
  const { data, error, retry } = useDashboardData(() => api<PlatformStats>('/admin/stats'), [])
  const header = <PageHeader title="Platform dashboard" description="Gym applications, verified gyms and plan mix across the platform." actions={<Button variant="outline" render={<Link to="/admin" />}>Review applications</Button>} />

  if (!data) return <DashboardPage header={header}><DashboardState error={error} retry={retry} /></DashboardPage>

  const approvalRate = percent(data.gyms_verified, data.gyms_verified + data.applications_rejected)
  const sent = data.monthly.reduce((sum, m) => sum + m.submitted, 0)
  const approved = data.monthly.reduce((sum, m) => sum + m.approved, 0)
  const subs = data.subscriptions
  const count = (n: number) => String(n)

  return <DashboardPage header={header}>
    <StatStrip items={[
      { label: 'Verified gyms', value: data.gyms_verified, hint: `${data.gyms_registered} registered in total`, trend: data.monthly.map((m) => m.approved), trendLabel: 'Gyms approved per month, last 12 months' },
      { label: 'Waiting for review', value: data.applications_pending, hint: `${data.applications_returned} returned for changes` },
      { label: 'Approval rate', value: approvalRate === null ? 'None yet' : `${approvalRate}%`, hint: `${data.gyms_verified} approved, ${data.applications_rejected} rejected` },
      { label: 'Gyms on a paid plan', value: subs.paid, hint: `${subs.trial} on trial, ${subs.free} on the free plan` },
    ]} />

    <DashboardGrid>
      <ChartCard title="Applications sent" description={`${sent} in the last 12 months, by the month the owner sent it.`}>
        <ColumnChart caption="Applications sent per month" points={data.monthly.map((m) => ({ label: monthLabel(m.period), value: m.submitted, text: count(m.submitted) }))} />
      </ChartCard>
      <ChartCard title="Gyms approved" description={`${approved} in the last 12 months, by review month.`}>
        <ColumnChart caption="Gyms approved per month" points={data.monthly.map((m) => ({ label: monthLabel(m.period), value: m.approved, text: count(m.approved) }))} />
      </ChartCard>
    </DashboardGrid>

    <DashboardGrid>
      <ChartCard title="Application status" description="Every sent application, by where it is now.">
        <DonutChart caption="Applications by status" centerLabel="sent" slices={[
          { label: 'Waiting for review', value: data.applications_pending, tone: 'chart1' },
          { label: 'Returned for changes', value: data.applications_returned, tone: 'warning' },
          { label: 'Approved', value: data.gyms_verified, tone: 'success' },
          { label: 'Rejected', value: data.applications_rejected, tone: 'destructive' },
        ]} />
      </ChartCard>
      <ChartCard title="Plan mix" description="Verified gyms by subscription state today.">
        <DonutChart caption="Verified gyms by plan" centerLabel="live gyms" slices={[
          { label: 'Trial', value: subs.trial, tone: 'chart1' },
          { label: 'Paid plan', value: subs.paid, tone: 'chart2' },
          { label: 'Free plan', value: subs.free, tone: 'chart3' },
          ...(subs.unmanaged ? [{ label: 'Not on a plan', value: subs.unmanaged, tone: 'muted' as const }] : []),
        ]} />
      </ChartCard>
    </DashboardGrid>

    <DashboardGrid>
      <ChartCard title="Platform rates" description="Review outcomes and plan conversion.">
        <RingRow>
          <RateRing label="Approval rate" value={approvalRate} hint="Approved of decided" />
          <RateRing label="Paid conversion" value={percent(subs.paid, data.gyms_verified)} hint="Verified gyms paying" />
          <RateRing label="Still on trial" value={percent(subs.trial, data.gyms_verified)} hint="Verified gyms on trial" />
        </RingRow>
      </ChartCard>
      <ChartCard title="Verified gyms by tier" description="Verification level given at approval.">
        <BarList caption="Verified gyms by tier" points={Object.entries(data.verified_by_tier).map(([tier, n]) => ({ label: `Tier ${tier}`, value: n, text: count(n) }))} />
      </ChartCard>
    </DashboardGrid>
  </DashboardPage>
}
