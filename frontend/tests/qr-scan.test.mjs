import assert from 'node:assert/strict'
import test from 'node:test'
import QRCode from 'qrcode'
import { decodeQr } from '../src/lib/qr-scan.ts'

// Same shape as CheckInCode::issue payload: gym.user.window.hmac32 (R-126)
const payload = '1.2.29000000.0123456789abcdef0123456789abcdef'

test('decodeQr reads a check-in QR and returns null for a blank image', () => {
  const { size, data: modules } = QRCode.create(payload, { errorCorrectionLevel: 'M' }).modules
  const scale = 4, border = 4, side = (size + border * 2) * scale
  const pixels = new Uint8ClampedArray(side * side * 4).fill(255)
  for (let y = 0; y < side; y++) for (let x = 0; x < side; x++) {
    const mx = Math.floor(x / scale) - border, my = Math.floor(y / scale) - border
    if (mx >= 0 && my >= 0 && mx < size && my < size && modules[my * size + mx]) {
      const i = (y * side + x) * 4
      pixels[i] = pixels[i + 1] = pixels[i + 2] = 0
    }
  }
  assert.equal(decodeQr(pixels, side, side), payload)
  assert.equal(decodeQr(new Uint8ClampedArray(side * side * 4).fill(255), side, side), null)
})
