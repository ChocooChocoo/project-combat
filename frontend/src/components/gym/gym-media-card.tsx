import { FileText, ImagePlus, Trash2 } from 'lucide-react'
import { useState } from 'react'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Card, CardDescription, CardHeader, CardPanel, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { toastManager } from '@/components/ui/toast'
import { api, ApiError } from '@/lib/api'
import type { GymMedia } from '@/lib/discovery'
import type { WorkspaceOptions } from '@/lib/workspace'

/** R-134, R-135: the gym's cover photo, gallery photos and policy PDFs. Each upload is saved at once. */
export function GymMediaCard({
  gymId,
  media,
  options,
  onChange,
}: {
  gymId: number | string
  media: GymMedia
  options: Pick<WorkspaceOptions, 'gym_photos' | 'policy_files'>
  onChange: (media: GymMedia) => void
}) {
  const [busy, setBusy] = useState<string | null>(null)
  const [error, setError] = useState('')
  const photoMb = Math.round(options.gym_photos.max_kb / 1024)
  const policyMb = Math.round(options.policy_files.max_kb / 1024)

  async function send(key: string, path: string, body: FormData | null, done: string, method = 'POST') {
    setBusy(key)
    setError('')
    try {
      const result = await api<{ media: GymMedia }>(`/gyms/${gymId}/${path}`, body ? { body } : { method })
      onChange(result.media)
      toastManager.add({ title: done, type: 'success' })
    } catch (e) {
      setError(e instanceof ApiError ? (e.errors.file?.[0] ?? e.errors.photo?.[0] ?? e.message) : 'The change could not be saved.')
    } finally {
      setBusy(null)
    }
  }

  function pick(event: React.ChangeEvent<HTMLInputElement>, kind: 'cover' | 'photo' | 'policy') {
    const file = event.target.files?.[0]
    event.target.value = ''
    if (!file) return
    setError('')

    if (kind === 'policy') {
      if (media.policy_files.length >= options.policy_files.max) {
        return setError(`You can add at most ${options.policy_files.max} policy files. Delete one first to add another.`)
      }
      if (file.type !== 'application/pdf') return setError('Choose a PDF file.')
      if (file.size > options.policy_files.max_kb * 1024) return setError(`This file is too big. The largest accepted size is ${policyMb} MB.`)
    } else {
      if (kind === 'photo' && media.photos.length >= options.gym_photos.max) {
        return setError(`You can add at most ${options.gym_photos.max} photos. Delete one first to add another.`)
      }
      if (!file.type.startsWith('image/')) return setError('Choose an image file.')
      if (file.size > options.gym_photos.max_kb * 1024) return setError(`This photo is too big. The largest accepted size is ${photoMb} MB.`)
    }

    const body = new FormData()
    if (kind === 'cover') {
      body.append('photo', file)
      void send('cover', 'media/cover', body, 'Cover photo updated.')
    } else {
      body.append('kind', kind)
      body.append('file', file)
      void send(kind, 'media/files', body, kind === 'photo' ? 'Photo added.' : 'File added.')
    }
  }

  const remove = (id: number, done: string) => send(`del-${id}`, `media/files/${id}`, null, done, 'DELETE')

  return (
    <Card>
      <CardHeader>
        <CardTitle render={<h2 />}>Photos and files</CardTitle>
        <CardDescription>Members see these on your gym page before they apply. Changes save as soon as you upload.</CardDescription>
      </CardHeader>
      <CardPanel className="flex flex-col gap-6">
        {error && (
          <Alert variant="error">
            <AlertDescription>{error}</AlertDescription>
          </Alert>
        )}

        <div className="flex flex-col gap-2">
          <h3 className="font-medium text-sm">Cover photo</h3>
          {media.cover_photo_url ? (
            <img className="aspect-video w-full max-w-md rounded-lg border object-cover" src={media.cover_photo_url} alt="Gym cover" />
          ) : (
            <div className="flex aspect-video w-full max-w-md items-center justify-center rounded-lg border bg-muted text-muted-foreground">
              <ImagePlus className="size-7" aria-hidden />
            </div>
          )}
          <Input
            type="file"
            accept="image/*"
            className="max-w-md"
            disabled={busy !== null}
            onChange={(e) => pick(e, 'cover')}
            aria-label={media.cover_photo_url ? 'Change cover' : 'Add cover'}
          />
          <p className="text-muted-foreground text-xs">Change cover. Image file, up to {photoMb} MB.</p>
        </div>

        <div className="flex flex-col gap-2">
          <h3 className="font-medium text-sm">
            Gallery ({media.photos.length} of {options.gym_photos.max})
          </h3>
          {media.photos.length > 0 ? (
            <ul className="grid grid-cols-3 gap-2 sm:grid-cols-4">
              {media.photos.map((photo) => (
                <li key={photo.id} className="relative">
                  <img className="aspect-square w-full rounded-md border object-cover" src={photo.url} alt="" loading="lazy" />
                  <Button
                    type="button"
                    size="icon-xs"
                    variant="destructive"
                    className="absolute end-1 top-1"
                    loading={busy === `del-${photo.id}`}
                    onClick={() => remove(photo.id, 'Photo removed.')}
                    aria-label="Delete photo"
                  >
                    <Trash2 aria-hidden />
                  </Button>
                </li>
              ))}
            </ul>
          ) : (
            <p className="text-muted-foreground text-sm">No photos yet.</p>
          )}
          <Input
            type="file"
            accept="image/*"
            className="max-w-md"
            disabled={busy !== null || media.photos.length >= options.gym_photos.max}
            onChange={(e) => pick(e, 'photo')}
            aria-label="Add photo"
          />
        </div>

        <div className="flex flex-col gap-2">
          <h3 className="font-medium text-sm">Policies and waiver (PDF)</h3>
          {media.policy_files.length > 0 ? (
            <ul className="flex flex-col gap-2">
              {media.policy_files.map((file) => (
                <li key={file.id} className="flex items-center justify-between gap-2 rounded-md border px-3 py-2 text-sm">
                  <a href={file.url} target="_blank" rel="noreferrer" className="flex min-w-0 items-center gap-2 hover:underline">
                    <FileText className="size-4 shrink-0" aria-hidden />
                    <span className="truncate">{file.name}</span>
                  </a>
                  <Button
                    type="button"
                    size="icon-xs"
                    variant="destructive"
                    loading={busy === `del-${file.id}`}
                    onClick={() => remove(file.id, 'File removed.')}
                    aria-label={`Delete ${file.name}`}
                  >
                    <Trash2 aria-hidden />
                  </Button>
                </li>
              ))}
            </ul>
          ) : (
            <p className="text-muted-foreground text-sm">No files yet.</p>
          )}
          <Input
            type="file"
            accept="application/pdf,.pdf"
            className="max-w-md"
            disabled={busy !== null || media.policy_files.length >= options.policy_files.max}
            onChange={(e) => pick(e, 'policy')}
            aria-label="Add file"
          />
          <p className="text-muted-foreground text-xs">
            Add file. PDF, up to {policyMb} MB, at most {options.policy_files.max} files. Members accept these before joining.
          </p>
        </div>
      </CardPanel>
    </Card>
  )
}
