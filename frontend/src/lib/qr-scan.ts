import jsQR from 'jsqr'

/** R-126: read a QR code from camera pixels (fallback for browsers without BarcodeDetector). */
export function decodeQr(data: Uint8ClampedArray, width: number, height: number): string | null {
  return jsQR(data, width, height, { inversionAttempts: 'dontInvert' })?.data ?? null
}
