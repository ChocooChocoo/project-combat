import { Star } from 'lucide-react'
import { useEffect, useRef, useState, type FormEvent } from 'react'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { AlertDialog, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle, AlertDialogTrigger } from '@/components/ui/alert-dialog'
import { Button } from '@/components/ui/button'
import { Dialog, DialogDescription, DialogFooter, DialogHeader, DialogPanel, DialogPopup, DialogTitle, DialogTrigger } from '@/components/ui/dialog'
import { Field, FieldLabel } from '@/components/ui/field'
import { Textarea } from '@/components/ui/textarea'
import { api, ApiError } from '@/lib/api'
import { dateLabel } from '@/lib/membership'
import { cn } from '@/lib/utils'

type Review = { id: number; rating: number; comment: string | null; created_at: string | null }
type Reviews = {
  rating_count: number
  average_rating: number | null
  reviews: Review[]
  has_more: boolean
  can_review: boolean
  my_review: Review | null
}

const SCORES = [1, 2, 3, 4, 5]

export function GymPublicReviews({ gymId }: { gymId: number }) {
  const url = `/gyms/${gymId}/reviews`
  const [query, setQuery] = useState({ page: 1 })
  const [data, setData] = useState<Reviews | null>(null)
  const [loading, setLoading] = useState(true)
  const [loadError, setLoadError] = useState(false)
  const [action, setAction] = useState<'write' | 'delete' | null>(null)
  const [rating, setRating] = useState(0)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')
  const heading = useRef<HTMLHeadingElement>(null)
  const deleted = useRef(false)
  const mine = data?.my_review

  useEffect(() => {
    let live = true
    api<Reviews>(`${url}?page=${query.page}`)
      .then((result) => { if (live) setData(result) })
      .catch(() => { if (live) setLoadError(true) })
      .finally(() => { if (live) setLoading(false) })
    return () => { live = false }
  }, [url, query])

  function load(page: number) {
    setLoading(true)
    setLoadError(false)
    setQuery({ page })
  }

  async function change(method: 'PUT' | 'DELETE', body?: { rating: number; comment: string }) {
    setBusy(true)
    setError('')
    setNotice('')
    try {
      await api(`${url}/mine`, { method, body })
      deleted.current = method === 'DELETE'
      setNotice(deleted.current ? 'Your review was deleted.' : 'Your review was saved.')
      setAction(null)
      load(1)
    } catch (failure) {
      setError(failure instanceof ApiError
        ? failure.errors.rating?.[0] ?? failure.errors.comment?.[0] ?? failure.message
        : 'Could not update your review. Try again.')
    } finally {
      setBusy(false)
    }
  }

  function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const values = new FormData(event.currentTarget)
    void change('PUT', { rating, comment: String(values.get('comment') ?? '').trim() })
  }

  return (
    <section className="flex flex-col gap-4" aria-labelledby="gym-reviews">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h2 ref={heading} id="gym-reviews" tabIndex={-1} className="font-semibold text-xl tracking-tight">Reviews</h2>
          {!loadError && data && data.average_rating !== null && (
            <p className="flex flex-wrap items-center gap-x-2 text-sm">
              <Star aria-hidden="true" className="size-4 fill-current" />
              <strong className="font-semibold tabular-nums">{data.average_rating.toFixed(1)} out of 5</strong>
              <span className="text-muted-foreground">{data.rating_count} {data.rating_count === 1 ? 'rating' : 'ratings'}</span>
            </p>
          )}
        </div>

        {!loadError && data && (
          <div className="flex flex-wrap gap-2">
            {data.can_review && (
              <Dialog open={action === 'write'} onOpenChange={(open) => {
                if (busy) return
                if (open) {
                  setRating(mine?.rating ?? 0)
                  setError('')
                }
                setAction(open ? 'write' : null)
              }}>
                <DialogTrigger render={<Button variant="outline" className="min-h-11" />}>
                  {mine ? 'Edit your review' : 'Write a review'}
                </DialogTrigger>
                <DialogPopup showCloseButton={false}>
                  <form onSubmit={submit}>
                    <DialogHeader>
                      <DialogTitle>{mine ? 'Edit your review' : 'Review this gym'}</DialogTitle>
                      <DialogDescription>Signed-in members can see your review, but not your name. Leave out personal details.</DialogDescription>
                    </DialogHeader>
                    <DialogPanel className="flex flex-col gap-4">
                      <fieldset disabled={busy}>
                        <legend className="font-medium text-sm">Rating</legend>
                        <div className="mt-2 flex flex-wrap gap-1">
                          {SCORES.map((score) => (
                            <label key={score} className="relative">
                              <input type="radio" className="peer sr-only" name="rating" value={score} required checked={rating === score} onChange={() => setRating(score)} />
                              <span className="sr-only">{score} {score === 1 ? 'star' : 'stars'}</span>
                              <span aria-hidden="true" className="grid size-11 cursor-pointer place-items-center rounded-lg hover:bg-muted peer-focus-visible:ring-2 peer-focus-visible:ring-ring">
                                <Star className={cn('size-5', rating >= score && 'fill-current')} />
                              </span>
                            </label>
                          ))}
                        </div>
                      </fieldset>
                      <Field disabled={busy}>
                        <FieldLabel>Comment (optional)</FieldLabel>
                        <Textarea name="comment" maxLength={1000} defaultValue={mine?.comment ?? ''} />
                      </Field>
                      {error && <Alert variant="error"><AlertDescription>{error}</AlertDescription></Alert>}
                    </DialogPanel>
                    <DialogFooter>
                      <Button variant="outline" className="min-h-11" disabled={busy} onClick={() => setAction(null)}>Cancel</Button>
                      <Button type="submit" className="min-h-11" loading={busy}>Save review</Button>
                    </DialogFooter>
                  </form>
                </DialogPopup>
              </Dialog>
            )}

            {mine && (
              <AlertDialog open={action === 'delete'} onOpenChange={(open) => {
                if (busy) return
                if (open) {
                  deleted.current = false
                  setError('')
                }
                setAction(open ? 'delete' : null)
              }}>
                <AlertDialogTrigger render={<Button variant="ghost" className="min-h-11" />}>Delete your review</AlertDialogTrigger>
                <AlertDialogContent finalFocus={() => deleted.current ? heading.current : true}>
                  <AlertDialogHeader>
                    <AlertDialogTitle>Delete your review?</AlertDialogTitle>
                    <AlertDialogDescription>Your rating and any comment will be removed from this gym page.</AlertDialogDescription>
                    {error && <Alert variant="error"><AlertDescription>{error}</AlertDescription></Alert>}
                  </AlertDialogHeader>
                  <AlertDialogFooter>
                    <Button variant="outline" className="min-h-11" disabled={busy} onClick={() => setAction(null)}>Cancel</Button>
                    <Button variant="destructive" className="min-h-11" loading={busy} onClick={() => void change('DELETE')}>Delete review</Button>
                  </AlertDialogFooter>
                </AlertDialogContent>
              </AlertDialog>
            )}
          </div>
        )}
      </div>

      <div role="status" className={notice || loading ? 'text-sm' : 'sr-only'}>
        {loading ? 'Loading reviews…' : notice}
      </div>
      {loadError ? (
        <Alert variant="error">
          <AlertDescription>Reviews could not be loaded.</AlertDescription>
          <Button variant="outline" className="mt-2 min-h-11" onClick={() => load(query.page)}>Try again</Button>
        </Alert>
      ) : data && (
        <>
          {!data.can_review && !mine && <p className="text-muted-foreground text-sm">You need an active membership at this gym to leave a review.</p>}
          {data.rating_count === 0 ? (
            <p className="rounded-xl border p-4 text-sm">No ratings yet.</p>
          ) : (
            <ul className="divide-y rounded-xl border">
              {data.reviews.map((review) => (
                <li key={review.id} className="space-y-2 p-4 sm:p-5">
                  <div className="flex flex-wrap items-baseline gap-x-3 gap-y-1 text-sm">
                    <span className="font-medium">{mine?.id === review.id ? 'Your review' : 'Verified member'}</span>
                    <span className="tabular-nums">{review.rating} out of 5</span>
                    {review.created_at && <time dateTime={review.created_at} className="text-muted-foreground">{dateLabel(review.created_at)}</time>}
                  </div>
                  {review.comment && <p className="max-w-prose whitespace-pre-wrap text-sm wrap-anywhere">{review.comment}</p>}
                </li>
              ))}
            </ul>
          )}
          {(query.page > 1 || data.has_more) && (
            <div className="flex flex-wrap gap-2">
              <Button variant="outline" className="min-h-11" disabled={loading || query.page <= 1} onClick={() => load(query.page - 1)}>Newer reviews</Button>
              <Button variant="outline" className="min-h-11" disabled={loading || !data.has_more} onClick={() => load(query.page + 1)}>Older reviews</Button>
            </div>
          )}
        </>
      )}
    </section>
  )
}
