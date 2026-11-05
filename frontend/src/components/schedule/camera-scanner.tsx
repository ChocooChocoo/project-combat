import { useEffect, useRef, useState } from 'react'
import { Button } from '@/components/ui/button'

type Detector = { detect: (source: HTMLVideoElement) => Promise<{ rawValue: string }[]> }
type DetectorWindow = Window & { BarcodeDetector: new (options: { formats: string[] }) => Detector }

/** R-52: native camera scanning only; typing remains available in every browser. */
export function CameraScanner({ onRead, onClose }: { onRead: (code: string) => void; onClose: () => void }) {
  const video = useRef<HTMLVideoElement>(null)
  const [error, setError] = useState('')
  useEffect(() => {
    let live = true
    let stream: MediaStream | null = null
    let timer: ReturnType<typeof setTimeout>
    async function start() {
      try {
        const detector = new (window as unknown as DetectorWindow).BarcodeDetector({ formats: ['qr_code'] })
        stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' }, audio: false })
        if (!live) { stream.getTracks().forEach((track) => track.stop()); return }
        if (!video.current) return
        video.current.srcObject = stream
        await video.current.play()
        async function scan() {
          if (!live || !video.current) return
          try {
            const codes = await detector.detect(video.current)
            if (!live) return
            if (codes[0]?.rawValue) { onRead(codes[0].rawValue); return }
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
