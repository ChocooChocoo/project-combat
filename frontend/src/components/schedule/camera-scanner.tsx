import { useEffect, useRef, useState } from 'react'
import { Button } from '@/components/ui/button'

type Detector = { detect: (source: HTMLVideoElement) => Promise<{ rawValue: string }[]> }
type DetectorWindow = Window & { BarcodeDetector: (new (options: { formats: string[] }) => Detector) & { getSupportedFormats: () => Promise<string[]> } }

/** R-52: native camera scanning, with a jsQR fallback for browsers without it (R-126). Typing works everywhere. */
export function CameraScanner({ onRead, onClose }: { onRead: (code: string) => void; onClose: () => void }) {
  const video = useRef<HTMLVideoElement>(null)
  const [error, setError] = useState('')
  useEffect(() => {
    let live = true
    let stream: MediaStream | null = null
    let timer: ReturnType<typeof setTimeout>
    async function start() {
      try {
        // R-126: use the native reader only if it can read QR codes; otherwise load jsQR on demand
        const Native = 'BarcodeDetector' in window ? (window as unknown as DetectorWindow).BarcodeDetector : null
        const detector = Native && (await Native.getSupportedFormats()).includes('qr_code') ? new Native({ formats: ['qr_code'] }) : null
        const fallback = detector ? null : await import('@/lib/qr-scan')
        const canvas = document.createElement('canvas')
        const read = async (el: HTMLVideoElement): Promise<string | undefined> => {
          if (detector) return (await detector.detect(el))[0]?.rawValue
          if (!el.videoWidth) return undefined
          canvas.width = Math.min(640, el.videoWidth)
          canvas.height = Math.round(el.videoHeight * canvas.width / el.videoWidth)
          const ctx = canvas.getContext('2d', { willReadFrequently: true })
          if (!ctx) return undefined
          ctx.drawImage(el, 0, 0, canvas.width, canvas.height)
          const image = ctx.getImageData(0, 0, canvas.width, canvas.height)
          return fallback?.decodeQr(image.data, image.width, image.height) ?? undefined
        }
        stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' }, audio: false })
        if (!live) { stream.getTracks().forEach((track) => track.stop()); return }
        if (!video.current) return
        video.current.srcObject = stream
        await video.current.play()
        async function scan() {
          if (!live || !video.current) return
          try {
            const code = await read(video.current)
            if (!live) return
            if (code) { onRead(code); return }
            timer = setTimeout(scan, 250)
          } catch {
            if (live) setError('Camera scanning failed. Type the short code instead.')
            stream?.getTracks().forEach((track) => track.stop())
          }
        }
        scan()
      } catch {
        if (live) setError('Camera unavailable or permission denied. Type the short code instead.')
        stream?.getTracks().forEach((track) => track.stop())
      }
    }
    start()
    return () => { live = false; clearTimeout(timer); stream?.getTracks().forEach((track) => track.stop()) }
  }, [onRead])
  return <div className="flex flex-col gap-3 rounded-xl border p-4">
    <p className="text-sm">Point the camera at the member's QR code.</p>
    {error ? <p role="alert" className="text-sm">{error}</p> : <video ref={video} muted playsInline className="max-h-72 w-full rounded-lg" aria-label="Check-in camera preview" />}
    <Button variant="outline" className="self-start" onClick={onClose}>Close camera</Button>
  </div>
}
