import type { ReactNode } from 'react'
import { createPortal } from 'react-dom'
import type { Meta } from '@/lib/decision-support'

export function PrintSheet({ gymName, title, meta, children }: { gymName: string; title: string; meta: Meta; children: ReactNode }) {
  return createPortal(<article className="print-area print-report hidden space-y-6 print:block">
    <header className="space-y-2"><h1 className="text-2xl font-semibold">{title}</h1><p>{gymName}</p><p>{meta.from} to {meta.to} ({meta.timezone})</p><p className="text-sm">Generated: {meta.generated_at}</p></header>
    {children}
    <section aria-label="Report notes"><h2 className="font-semibold">Notes</h2><ul className="list-disc space-y-1 pl-5 text-sm">{meta.notes.map((note, index) => <li key={index}>{note}</li>)}</ul></section>
  </article>, document.body)
}
