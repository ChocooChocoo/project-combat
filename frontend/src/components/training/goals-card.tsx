import { Plus, Trash2 } from 'lucide-react'
import { useEffect, useState } from 'react'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardAction, CardDescription, CardHeader, CardPanel, CardTitle } from '@/components/ui/card'
import { Checkbox } from '@/components/ui/checkbox'
import { Input } from '@/components/ui/input'
import { Progress } from '@/components/ui/progress'
import { toastManager } from '@/components/ui/toast'
import { api, ApiError } from '@/lib/api'
import { dateLabel } from '@/lib/membership'

type Milestone = { id: number; title: string; due_on: string | null; done_on: string | null; done_by: string | null }
type Goal = {
  id: number
  title: string
  description: string | null
  target_on: string | null
  status: 'active' | 'achieved' | 'dropped'
  achieved_on: string | null
  created_by: string | null
  done_count: number
  milestones: Milestone[]
}

const STATUS = { active: { label: 'Active', variant: 'info' }, achieved: { label: 'Achieved', variant: 'success' }, dropped: { label: 'Dropped', variant: 'secondary' } } as const

/** R-118: an athlete's goals and milestones. The same card serves coaches (member record) and the athlete (My record). */
export function GoalsCard({ gymId, athleteId, canEdit }: { gymId: number; athleteId: number; canEdit: boolean }) {
  const [goals, setGoals] = useState<Goal[] | null>(null)
  const [error, setError] = useState('')
  const [title, setTitle] = useState('')
  const [target, setTarget] = useState('')
  const [steps, setSteps] = useState<Record<number, string>>({})
  const [busy, setBusy] = useState(false)

  useEffect(() => {
    let live = true
    api<{ goals: Goal[] }>(`/gyms/${gymId}/athletes/${athleteId}/goals`)
      .then((result) => live && setGoals(result.goals))
      .catch((failure) => live && setError(failure instanceof ApiError ? failure.message : 'Goals could not be loaded.'))
    return () => { live = false }
  }, [gymId, athleteId])

  async function send(path: string, method: string, body?: unknown, done?: () => void) {
    setBusy(true)
    try {
      const result = await api<{ goals: Goal[] }>(`/gyms/${gymId}${path}`, { method, body })
      setGoals(result.goals)
      done?.()
    } catch (failure) {
      const first = failure instanceof ApiError ? Object.values(failure.errors).flat()[0] ?? failure.message : 'Could not save.'
      toastManager.add({ title: first, type: 'error' })
    } finally {
      setBusy(false)
    }
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle render={<h2 />}>Goals</CardTitle>
        <CardDescription>Coach and athlete see the same goals. A goal is achieved when its last milestone is done.</CardDescription>
      </CardHeader>
      <CardPanel className="flex flex-col gap-4">
        {error && <p className="text-destructive text-sm">{error}</p>}
        {goals && goals.length === 0 && <p className="text-muted-foreground text-sm">No goals yet.</p>}
        {goals?.map((goal) => {
          const total = goal.milestones.length
          return (
            <section key={goal.id} className="flex flex-col gap-3 rounded-xl border p-4 text-sm" aria-label={goal.title}>
              <div className="flex flex-wrap items-start justify-between gap-2">
                <div className="min-w-0">
                  <div className="flex flex-wrap items-center gap-2"><h3 className="break-words font-medium">{goal.title}</h3><Badge variant={STATUS[goal.status].variant}>{STATUS[goal.status].label}</Badge></div>
                  <p className="text-muted-foreground text-xs">
                    {goal.target_on ? `Target ${dateLabel(goal.target_on)}` : 'No target date'}
                    {goal.achieved_on && ` · achieved ${dateLabel(goal.achieved_on)}`}
                    {goal.created_by && ` · set by ${goal.created_by}`}
                  </p>
                </div>
                {canEdit && (
                  <CardAction className="flex gap-1">
                    {goal.status === 'active' && total === 0 && <Button size="xs" variant="outline" disabled={busy} onClick={() => send(`/goals/${goal.id}`, 'PUT', { title: goal.title, status: 'achieved' })}>Mark achieved</Button>}
                    {goal.status === 'active' && <Button size="xs" variant="ghost" disabled={busy} onClick={() => send(`/goals/${goal.id}`, 'PUT', { title: goal.title, status: 'dropped' })}>Drop</Button>}
                    {goal.status !== 'active' && <Button size="xs" variant="ghost" disabled={busy} onClick={() => send(`/goals/${goal.id}`, 'PUT', { title: goal.title, status: 'active' })}>Reopen</Button>}
                    <Button size="icon-xs" variant="ghost" disabled={busy} aria-label={`Delete ${goal.title}`} onClick={() => send(`/goals/${goal.id}`, 'DELETE')}><Trash2 aria-hidden /></Button>
                  </CardAction>
                )}
              </div>
              {total > 0 && (
                <>
                  <Progress value={Math.round((goal.done_count / total) * 100)} max={100} aria-label={`${goal.title}: ${goal.done_count} of ${total} milestones done`} />
                  <p className="text-muted-foreground text-xs">{goal.done_count} of {total} milestones done</p>
                </>
              )}
              <ul className="flex flex-col gap-2">
                {goal.milestones.map((milestone) => (
                  <li key={milestone.id} className="flex items-center gap-3">
                    <Checkbox checked={milestone.done_on !== null} disabled={!canEdit || busy} aria-label={milestone.title}
                      onCheckedChange={(on) => send(`/goal-milestones/${milestone.id}`, 'PUT', { done: on === true })} />
                    <span className={milestone.done_on ? 'text-muted-foreground line-through' : ''}>{milestone.title}</span>
                    {milestone.due_on && <span className="text-muted-foreground text-xs">due {dateLabel(milestone.due_on)}</span>}
                    {canEdit && <Button size="icon-xs" variant="ghost" className="ms-auto" disabled={busy} aria-label={`Remove ${milestone.title}`} onClick={() => send(`/goal-milestones/${milestone.id}`, 'DELETE')}><Trash2 aria-hidden /></Button>}
                  </li>
                ))}
              </ul>
              {canEdit && goal.status !== 'dropped' && (
                <form className="flex gap-2" onSubmit={(event) => { event.preventDefault(); if (steps[goal.id]?.trim()) send(`/goals/${goal.id}/milestones`, 'POST', { title: steps[goal.id].trim() }, () => setSteps((current) => ({ ...current, [goal.id]: '' }))) }}>
                  <Input aria-label={`New milestone for ${goal.title}`} placeholder="Add a milestone" maxLength={150} value={steps[goal.id] ?? ''} onChange={(event) => setSteps((current) => ({ ...current, [goal.id]: event.target.value }))} />
                  <Button type="submit" size="sm" variant="outline" disabled={busy}><Plus aria-hidden /> Add</Button>
                </form>
              )}
            </section>
          )
        })}
        {canEdit && (
          <form className="flex flex-wrap items-end gap-2 border-t pt-4" onSubmit={(event) => { event.preventDefault(); if (title.trim()) send(`/athletes/${athleteId}/goals`, 'POST', { title: title.trim(), target_on: target || null }, () => { setTitle(''); setTarget('') }) }}>
            <label className="flex min-w-48 flex-1 flex-col gap-1 text-sm">New goal<Input value={title} onChange={(event) => setTitle(event.target.value)} maxLength={150} placeholder="For example: blue belt by March" /></label>
            <label className="flex flex-col gap-1 text-sm">Target date<Input type="date" value={target} onChange={(event) => setTarget(event.target.value)} /></label>
            <Button type="submit" disabled={busy || !title.trim()}><Plus aria-hidden /> Add goal</Button>
          </form>
        )}
      </CardPanel>
    </Card>
  )
}
