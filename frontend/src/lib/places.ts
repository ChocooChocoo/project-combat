/** (R-130) Match a reverse-lookup place to the Cavite city and barangay lists. */
const clean = (name?: string | null) =>
  (name ?? '').toLowerCase().replace(/^city of /, '').replace(/ city$/, '').replace(/\s+/g, ' ').trim()

export function matchCavitePlace(
  place: { city?: string | null; barangay?: string | null },
  municipalities: { name: string; barangays: string[] }[],
): { city: string; barangay: string } {
  const city = clean(place.city)
  const town = city ? municipalities.find((m) => clean(m.name) === city) : undefined
  if (!town) return { city: '', barangay: '' }
  const barangay = clean(place.barangay)
  return { city: town.name, barangay: (barangay && town.barangays.find((b) => clean(b) === barangay)) || '' }
}
