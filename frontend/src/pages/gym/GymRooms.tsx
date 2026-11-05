import { Plus, RotateCcw } from 'lucide-react'
import { useEffect, useState } from 'react'
import { useParams } from 'react-router'
import { PageHeader } from '@/components/page-header'
import { TextField } from '@/components/text-field'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import {
  AlertDialog,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogPanel,
  DialogTitle,
} from '@/components/ui/dialog'
import { Empty, EmptyContent, EmptyDescription, EmptyHeader, EmptyTitle } from '@/components/ui/empty'
import { Form } from '@/components/ui/form'
import { Skeleton } from '@/components/ui/skeleton'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { toastManager } from '@/components/ui/toast'
import { api, ApiError, type FieldErrors } from '@/lib/api'
import { useAuth } from '@/lib/auth'
import { approvedGym, can } from '@/lib/gym-access'
import type { Room } from '@/lib/workspace'

type DialogState = { room: Room | null }
type RoomAction = { room: Room; action: 'retire' | 'reactivate' }

/** R-21, S-16: training spaces. Retiring hides a room from new sessions but keeps its history. */
export default function GymRooms() {
  const { gymId } = useParams()
  const { user } = useAuth()
  const gym = approvedGym(user, Number(gymId))
  const [rooms, setRooms] = useState<Room[] | null>(null)
  const [loadError, setLoadError] = useState('')
  const [version, setVersion] = useState(0)
  const [dialog, setDialog] = useState<DialogState | null>(null)
  const [target, setTarget] = useState<RoomAction | null>(null)
  const [busy, setBusy] = useState(false)

  useEffect(() => {
    if (!gymId) return
    let live = true
    setLoadError('')
    api<{ rooms: Room[] }>(`/gyms/${gymId}/rooms`)
      .then((result) => {
        if (live) setRooms(result.rooms)
      })
      .catch((error) => {
        if (live) setLoadError(error instanceof ApiError ? error.message : 'The rooms could not be loaded.')
      })
    return () => {
      live = false
    }
  }, [gymId, version])

  if (!gym) return null

  const canManage = can(gym, 'gym_setup.rooms.manage')
  const reload = () => {
    setRooms(null)
    setVersion((value) => value + 1)
  }

  async function changeActive(target: RoomAction) {
    if (!gymId) return
    setBusy(true)
    try {
      if (target.action === 'retire') {
        await api(`/gyms/${gymId}/rooms/${target.room.id}`, { method: 'DELETE' })
        toastManager.add({ title: 'Room retired.', type: 'success' })
      } else {
        await api(`/gyms/${gymId}/rooms/${target.room.id}`, {
          method: 'PUT',
          body: { name: target.room.name, is_active: true },
        })
        toastManager.add({ title: 'Room is active again.', type: 'success' })
      }
      setTarget(null)
      reload()
    } catch (error) {
      toastManager.add({
        title: error instanceof ApiError ? error.message : 'The room could not be changed.',
        type: 'error',
      })
    } finally {
      setBusy(false)
    }
  }

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title="Rooms"
        description="Training areas inside the gym, such as the main mat, the ring or the weights room."
        actions={
          canManage && (
            <Button onClick={() => setDialog({ room: null })}>
              <Plus aria-hidden /> Add room
            </Button>
          )
        }
      />

      {loadError ? (
        <Alert variant="error">
          <AlertTitle>Rooms could not be loaded</AlertTitle>
          <AlertDescription>
            <p>{loadError}</p>
            <Button size="sm" variant="outline" onClick={reload}>
              Try again
            </Button>
          </AlertDescription>
        </Alert>
      ) : !rooms ? (
        <div className="flex flex-col gap-2" aria-busy="true">
          <Skeleton className="h-11" />
          <Skeleton className="h-11" />
          <Skeleton className="h-11" />
        </div>
      ) : rooms.length === 0 ? (
        <Empty className="rounded-xl border">
          <EmptyHeader>
            <EmptyTitle>No rooms yet</EmptyTitle>
            <EmptyDescription>Add the spaces sessions can use. You can retire one later without losing its history.</EmptyDescription>
          </EmptyHeader>
          {canManage && (
            <EmptyContent>
              <Button onClick={() => setDialog({ room: null })}>
                <Plus aria-hidden /> Add the first room
              </Button>
            </EmptyContent>
          )}
        </Empty>
      ) : (
        <div className="overflow-x-auto">
          <Table variant="card">
            <TableHeader>
              <TableRow>
                <TableHead>Room</TableHead>
                <TableHead>Status</TableHead>
                {canManage && (
                  <TableHead>
                    <span className="sr-only">Actions</span>
                  </TableHead>
                )}
              </TableRow>
            </TableHeader>
            <TableBody>
              {rooms.map((room) => (
                <TableRow key={room.id} className={room.is_active ? undefined : 'text-muted-foreground'}>
                  <TableCell>
                    <span className="font-medium">{room.name}</span>
                    {!room.is_active && <span className="block pt-1 text-xs">Not available for new sessions</span>}
                  </TableCell>
                  <TableCell>
                    <Badge variant={room.is_active ? 'success' : 'secondary'}>{room.is_active ? 'Active' : 'Retired'}</Badge>
                  </TableCell>
                  {canManage && (
                    <TableCell className="text-right">
                      <div className="flex flex-wrap justify-end gap-1">
                        <Button size="xs" variant="ghost" onClick={() => setDialog({ room })}>
                          Rename
                        </Button>
                        {room.is_active ? (
                          <Button size="xs" variant="destructive-outline" onClick={() => setTarget({ room, action: 'retire' })}>
                            Retire
                          </Button>
                        ) : (
                          <Button size="xs" variant="outline" onClick={() => setTarget({ room, action: 'reactivate' })}>
                            <RotateCcw aria-hidden /> Reactivate
                          </Button>
                        )}
                      </div>
                    </TableCell>
                  )}
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </div>
      )}

      {dialog && gymId && (
        <RoomDialog
          gymId={gymId}
          room={dialog.room}
          onClose={() => setDialog(null)}
          onSaved={() => {
            setDialog(null)
            reload()
          }}
        />
      )}

      <AlertDialog open={Boolean(target)} onOpenChange={(open) => !open && setTarget(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{target?.action === 'retire' ? 'Retire this room?' : 'Reactivate this room?'}</AlertDialogTitle>
            <AlertDialogDescription>
              {target?.action === 'retire'
                ? `${target.room.name} will stay on record for sessions that already used it, but it cannot be chosen for new sessions.`
                : `${target?.room.name} will be available for new sessions again.`}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <Button variant="outline" onClick={() => setTarget(null)}>
              Cancel
            </Button>
            <Button
              variant={target?.action === 'retire' ? 'destructive' : 'default'}
              loading={busy}
              onClick={() => target && changeActive(target)}
            >
              {target?.action === 'retire' ? 'Retire room' : 'Reactivate room'}
            </Button>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  )
}

/** Add or rename, using the same small form so the two actions never drift apart. */
function RoomDialog({
  gymId,
  room,
  onClose,
  onSaved,
}: {
  gymId: string | number
  room: Room | null
  onClose: () => void
  onSaved: () => void
}) {
  const [name, setName] = useState(room?.name ?? '')
  const [errors, setErrors] = useState<FieldErrors>({})
  const [busy, setBusy] = useState(false)

  async function save(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setBusy(true)
    try {
      if (room) {
        await api(`/gyms/${gymId}/rooms/${room.id}`, { method: 'PUT', body: { name } })
        toastManager.add({ title: 'Room renamed.', type: 'success' })
      } else {
        await api(`/gyms/${gymId}/rooms`, { body: { name } })
        toastManager.add({ title: 'Room added.', type: 'success' })
      }
      onSaved()
    } catch (error) {
      setErrors(error instanceof ApiError ? error.errors : {})
      if (!(error instanceof ApiError) || Object.keys(error.errors).length === 0) {
        toastManager.add({
          title: error instanceof ApiError ? error.message : 'The room could not be saved.',
          type: 'error',
        })
      }
    } finally {
      setBusy(false)
    }
  }

  return (
    <Dialog open onOpenChange={(open) => !open && onClose()}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{room ? 'Rename room' : 'Add room'}</DialogTitle>
          <DialogDescription>
            {room ? 'The new name applies to every session that uses this room.' : 'Give the space the name members and staff already use.'}
          </DialogDescription>
        </DialogHeader>
        <Form errors={errors} onSubmit={save}>
          <DialogPanel>
            <TextField
              name="name"
              label="Room name"
              maxLength={80}
              value={name}
              onChange={(e) => setName(e.target.value)}
              placeholder="Main mat"
              required
              autoFocus
            />
          </DialogPanel>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={onClose}>
              Cancel
            </Button>
            <Button type="submit" loading={busy}>
              {room ? 'Save name' : 'Add room'}
            </Button>
          </DialogFooter>
        </Form>
      </DialogContent>
    </Dialog>
  )
}
