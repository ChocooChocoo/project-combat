/** R-122: read the browser's location once. Rejects with a message that is ready to show. */
export function currentPosition(): Promise<{ lat: number; lng: number }> {
  return new Promise((resolve, reject) => {
    if (!('geolocation' in navigator)) {
      reject(new Error('This browser cannot share your location.'))
      return
    }
    // The browser's own timeout does not count time spent on its permission prompt, so guard that too.
    let settled = false
    const guard = setTimeout(() => {
      if (settled) return
      settled = true
      reject(new Error('No answer from your browser about your location.'))
    }, 15_000)
    navigator.geolocation.getCurrentPosition(
      (position) => {
        if (settled) return
        settled = true
        clearTimeout(guard)
        resolve({ lat: position.coords.latitude, lng: position.coords.longitude })
      },
      (failure) => {
        if (settled) return
        settled = true
        clearTimeout(guard)
        reject(
          new Error(
            failure.code === failure.PERMISSION_DENIED
              ? 'Location access is blocked in your browser. Allow it to clock in.'
              : 'Your location could not be read.',
          ),
        )
      },
      // Fresh reading only: a cached position would judge staff by where they were a minute ago.
      { timeout: 10_000, maximumAge: 0 },
    )
  })
}
