import { OTPField, OTPFieldInput } from '@/components/ui/otp-field'

/** Six-digit code entry, shared by email confirmation and password reset. */
export function CodeInput({ value, onChange }: { value: string; onChange: (value: string) => void }) {
  return (
    <OTPField
      length={6}
      validationType="numeric"
      autoComplete="one-time-code"
      value={value}
      onValueChange={onChange}
      size="lg"
    >
      {Array.from({ length: 6 }, (_, i) => (
        <OTPFieldInput key={i} aria-label={`Digit ${i + 1}`} />
      ))}
    </OTPField>
  )
}
