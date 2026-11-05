import { useEffect, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router'
import { PageHeader } from '@/components/page-header'
import { BookingEmpty, BookingError, BookingLoading } from '@/components/schedule/booking-parts'
import { TextField } from '@/components/text-field'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Field, FieldLabel } from '@/components/ui/field'
import { Select, SelectItem, SelectPopup, SelectTrigger, SelectValue } from '@/components/ui/select'
import { toastManager } from '@/components/ui/toast'
import { api, ApiError } from '@/lib/api'
import { dayLabel, type OpenSlot, type Timetable } from '@/lib/bookings'
import { dateKey, sessionFee, sessionTime } from '@/lib/sessions'

/** R-44: show only available private slots, then recheck them when confirming. */
export default function PrivateCoaching() {
  const { gymId } = useParams()
  const navigate = useNavigate()
  const [data, setData] = useState<Timetable | null>(null)
  const [error, setError] = useState('')
  const [version, setVersion] = useState(0)
  const [serviceId, setServiceId] = useState('')
  const [coachId, setCoachId] = useState('')
  const [today] = useState(() => dateKey(new Date()))
  const [date, setDate] = useState(today)
  const [slots, setSlots] = useState<{ key: string; values: OpenSlot[] } | null>(null)
  const [slotError, setSlotError] = useState('')
  const [selected, setSelected] = useState<{ key: string; slot: OpenSlot } | null>(null)
  const [confirming, setConfirming] = useState(false)
  const [busy, setBusy] = useState(false)
  const [actionError, setActionError] = useState('')
  const key = `${gymId}/${serviceId}/${coachId}/${date}`
  useEffect(() => {
    let live = true
    setData(null)
    setError('')
    api<Timetable>(`/gyms/${gymId}/timetable`)
      .then((result) => live && setData(result))
      .catch((failure) => live && setError(failure instanceof ApiError ? failure.message : 'Private coaching could not be loaded.'))
    return () => { live = false }
  }, [gymId, version])
  useEffect(() => {
    let live = true
    setSlots(null)
    setSlotError('')
    if (!serviceId || !coachId || !date) return
    api<{ slots: OpenSlot[] }>(`/gyms/${gymId}/coaches/${coachId}/open-slots?date=${date}&service_id=${serviceId}`)
      .then((result) => live && setSlots({ key, values: result.slots }))
      .catch((failure) => live && setSlotError(failure instanceof ApiError ? failure.message : 'Open slots could not be loaded.'))
    return () => { live = false }
  }, [gymId, serviceId, coachId, date, key, version])
  const service = data?.private_services.find((item) => String(item.id) === serviceId)
  const coach = data?.coaches.find((item) => String(item.id) === coachId)
  const chosen = selected?.key === key ? selected.slot : null
  async function book() {
    if (!chosen) return
    setBusy(true)
    setActionError('')
    try {
      const result = await api<{ session: { id: number } }>(`/gyms/${gymId}/private-bookings`, {
        body: { gym_service_id: Number(serviceId), coach_id: Number(coachId), starts_at: chosen.starts_at },
      })
      toastManager.add({ title: 'Private booking saved.', type: 'success' })
      navigate(`/gyms/${gymId}/sessions/${result.session.id}`)
    } catch (failure) {
      setActionError(failure instanceof ApiError ? Object.values(failure.errors).flat().join(' ') || failure.message : 'The private booking could not be made.')
      setSelected(null)
      setConfirming(false)
      setVersion((v) => v + 1)
    } finally {
      setBusy(false)
    }
  }
  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title="Book private coaching"
        description="Choose a service, coach and date. Only open times with a free room are shown."
        actions={<Button variant="outline" render={<Link to={`/gyms/${gymId}/sessions`} />}>Back to sessions</Button>}
      />
      {actionError && (
        <Alert variant="error">
          <AlertTitle>Booking could not be made</AlertTitle>
          <AlertDescription>{actionError}</AlertDescription>
        </Alert>
      )}
      {error ? (
        <BookingError message={error} retry={() => setVersion((v) => v + 1)} />
      ) : !data ? (
        <BookingLoading />
      ) : data.private_services.length === 0 || data.coaches.length === 0 ? (
        <BookingEmpty title="Private coaching is not available" description="The gym needs a private coaching service and a coach before you can book." />
      ) : (
        <>
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <Field name="gym_service_id">
              <FieldLabel>Service</FieldLabel>
              <Select
                value={serviceId || null}
                onValueChange={(value) => setServiceId(String(value ?? ''))}
                items={data.private_services.map((item) => ({ value: String(item.id), label: item.name }))}
              >
                <SelectTrigger><SelectValue placeholder="Choose a service" /></SelectTrigger>
                <SelectPopup>
                  {data.private_services.map((item) => <SelectItem key={item.id} value={String(item.id)}>{item.name}</SelectItem>)}
                </SelectPopup>
              </Select>
            </Field>
            <Field name="coach_id">
              <FieldLabel>Coach</FieldLabel>
              <Select
                value={coachId || null}
                onValueChange={(value) => setCoachId(String(value ?? ''))}
                items={data.coaches.map((item) => ({ value: String(item.id), label: item.name }))}
              >
                <SelectTrigger><SelectValue placeholder="Choose a coach" /></SelectTrigger>
                <SelectPopup>
                  {data.coaches.map((item) => <SelectItem key={item.id} value={String(item.id)}>{item.name}</SelectItem>)}
                </SelectPopup>
              </Select>
            </Field>
            <TextField name="date" label="Date" type="date" min={today} value={date} onChange={(e) => setDate(e.target.value)} />
          </div>
          {service && (
            <p className="text-sm">
              {service.duration_minutes || 60} minutes · {service.price_included ? 'Included in membership' : sessionFee(service.price)}.
              {' '}Times use the gym's local clock.
            </p>
          )}
          {!serviceId || !coachId || !date ? (
            <BookingEmpty title="Choose your session" description="Select a service, coach and date to see open times." />
          ) : slotError ? (
            <BookingError message={slotError} retry={() => setVersion((v) => v + 1)} />
          ) : slots?.key !== key ? (
            <BookingLoading />
          ) : slots.values.length === 0 ? (
            <BookingEmpty title="No open times on this date" description="Try another day or coach. Leave, holidays and existing sessions are excluded." />
          ) : (
            <section className="flex flex-col gap-4" aria-label="Open slots">
              <h2 className="font-semibold">{dayLabel(date)}</h2>
              <div className="flex flex-wrap gap-3">
                {slots.values.map((slot) => (
                  <Button
                    key={slot.starts_at}
                    variant={chosen?.starts_at === slot.starts_at ? 'default' : 'outline'}
                    aria-pressed={chosen?.starts_at === slot.starts_at}
                    onClick={() => setSelected({ key, slot })}
                  >
                    {sessionTime(slot.starts_at)}–{sessionTime(slot.ends_at)}
                  </Button>
                ))}
              </div>
              <Button className="self-start" disabled={!chosen} onClick={() => setConfirming(true)}>Confirm private booking</Button>
            </section>
          )}
        </>
      )}
      <Dialog open={confirming && Boolean(chosen)} onOpenChange={(open) => !busy && setConfirming(open)}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Confirm private coaching?</DialogTitle>
            <DialogDescription>
              {service?.name} with {coach?.name}, {dayLabel(date)} at {chosen && sessionTime(chosen.starts_at)}.
              {' '}{service?.price_included ? 'Included in membership.' : `${sessionFee(service?.price ?? null)} fee.`}
              {' '}The gym's approval rule applies.
            </DialogDescription>
          </DialogHeader>
          <DialogFooter>
            <Button variant="outline" disabled={busy} onClick={() => setConfirming(false)}>Go back</Button>
            <Button loading={busy} onClick={book}>Book this time</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  )
}
