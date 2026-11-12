import { FileText, Trash2 } from 'lucide-react'
import { useEffect, useState } from 'react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { toastManager } from '@/components/ui/toast'
import { api, apiBlobUrl, apiFile, ApiError } from '@/lib/api'
import { PROOF_ACCEPT, uploadProofs, type Proof } from '@/lib/procurement'
import { dateTimeLabel } from '@/lib/membership'

function proofError(failure: unknown, fallback: string): string {
  if (failure instanceof ApiError) return Object.values(failure.errors).flat()[0] ?? failure.message
  return fallback
}

function Thumbnail({ path, name }: { path: string; name: string }) {
  const [url, setUrl] = useState<string | null>(null)
  useEffect(() => {
    let live = true
    let made: string | null = null
    apiBlobUrl(path).then((blob) => { made = blob; if (live) setUrl(blob); else URL.revokeObjectURL(blob) }).catch(() => undefined)
    return () => { live = false; if (made) URL.revokeObjectURL(made) }
  }, [path])
  return url ? <img src={url} alt={name} className="size-full object-cover" /> : <span className="size-full animate-pulse bg-muted" aria-hidden />
}

/** R-114: receipt and delivery photos or PDFs on a purchase order. */
export function ProofGallery({ gymId, orderId, canUpload, version }: { gymId: number; orderId: number; canUpload: boolean; version: number }) {
  const [proofs, setProofs] = useState<Proof[] | null>(null)
  const [busy, setBusy] = useState(false)
  const base = `/gyms/${gymId}/purchase-orders/${orderId}/proofs`

  useEffect(() => {
    let live = true
    api<{ proofs: Proof[] }>(base).then((result) => live && setProofs(result.proofs)).catch(() => live && setProofs([]))
    return () => { live = false }
  }, [base, version])

  async function upload(files: File[]) {
    if (files.length === 0) return
    setBusy(true)
    try {
      setProofs(await uploadProofs(gymId, orderId, files))
      toastManager.add({ title: files.length === 1 ? 'Proof added.' : `${files.length} proofs added.`, type: 'success' })
    } catch (failure) {
      toastManager.add({ title: proofError(failure, 'The files could not be uploaded.'), type: 'error' })
    } finally {
      setBusy(false)
    }
  }

  async function remove(proof: Proof) {
    setBusy(true)
    try {
      setProofs((await api<{ proofs: Proof[] }>(`${base}/${proof.id}`, { method: 'DELETE' })).proofs)
    } catch (failure) {
      toastManager.add({ title: proofError(failure, 'The file could not be removed.'), type: 'error' })
    } finally {
      setBusy(false)
    }
  }

  async function open(proof: Proof) {
    try {
      await apiFile(`${base}/${proof.id}`)
    } catch (failure) {
      toastManager.add({ title: proofError(failure, 'The file could not be opened.'), type: 'error' })
    }
  }

  return (
    <section aria-labelledby="order-proofs-heading" className="flex min-w-0 flex-col gap-3">
      <div className="flex flex-wrap items-end justify-between gap-3">
        <div>
          <h2 id="order-proofs-heading" className="font-heading font-semibold text-base">Receipt proof</h2>
          <p className="text-muted-foreground text-sm">Photos or PDFs of receipts and deliveries. Up to 10 files at a time, 5 MB each.</p>
        </div>
        {canUpload && (
          <label className="text-sm">
            <span className="sr-only">Add proof files</span>
            <Input type="file" multiple accept={PROOF_ACCEPT} disabled={busy} onChange={(event) => { upload(Array.from(event.target.files ?? [])); event.target.value = '' }} />
          </label>
        )}
      </div>
      {proofs === null ? (
        <p className="text-muted-foreground text-sm" aria-busy="true">Loading proof files…</p>
      ) : proofs.length === 0 ? (
        <p className="rounded-xl border px-4 py-3 text-muted-foreground text-sm">No proof files yet.</p>
      ) : (
        <ul className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
          {proofs.map((proof) => (
            <li key={proof.id} className="flex min-w-0 flex-col overflow-hidden rounded-xl border bg-card">
              <button type="button" onClick={() => open(proof)} className="flex aspect-square items-center justify-center bg-muted/40 focus-visible:outline-2 focus-visible:outline-ring" aria-label={`Open ${proof.original_name}`}>
                {proof.is_image ? <Thumbnail path={`${base}/${proof.id}`} name={proof.original_name} /> : <FileText className="size-10 text-muted-foreground" aria-hidden />}
              </button>
              <div className="flex items-start justify-between gap-2 p-2">
                <div className="min-w-0 text-xs">
                  <p className="truncate font-medium" title={proof.original_name}>{proof.original_name}</p>
                  <p className="truncate text-muted-foreground">{[proof.uploaded_by, proof.uploaded_at ? dateTimeLabel(proof.uploaded_at) : null].filter(Boolean).join(' · ')}</p>
                </div>
                {canUpload && (
                  <Button size="icon-xs" variant="ghost" disabled={busy} onClick={() => remove(proof)} aria-label={`Remove ${proof.original_name}`}>
                    <Trash2 aria-hidden />
                  </Button>
                )}
              </div>
            </li>
          ))}
        </ul>
      )}
    </section>
  )
}
