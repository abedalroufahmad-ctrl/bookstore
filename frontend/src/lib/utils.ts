/**
 * Resolves cover image URL to work when accessed from network (e.g. 192.168.1.109).
 * Converts full URLs with localhost/127.0.0.1 to relative paths so they go through
 * the Vite proxy and load correctly from any device on the LAN.
 */
export function resolveCoverUrl(path: string | undefined, apiBase?: string): string {
  if (!path) return ''
  if (path.startsWith('http://') || path.startsWith('https://')) {
    try {
      const url = new URL(path)
      const pageOrigin = typeof window !== 'undefined' ? window.location.origin : ''
      // Use relative path when API origin differs from page (e.g. localhost:8000 vs :5173, or different host)
      if (pageOrigin && url.origin !== pageOrigin) {
        return url.pathname
      }
      if (url.hostname === 'localhost' || url.hostname === '127.0.0.1') {
        return url.pathname
      }
      return path
    } catch {
      return path
    }
  }
  const base = apiBase || import.meta.env.VITE_API_URL || '/api/v1'
  const baseWithoutApi = base.replace(/\/api\/v1$/, '')
  const normalizedPath = path.startsWith('/') ? path : `/${path}`
  return baseWithoutApi ? `${baseWithoutApi}${normalizedPath}` : normalizedPath
}

export function calculateDiscountedPrice(
    originalPrice: number,
    bookDiscount: number | undefined,
    globalDiscount: number
): { finalPrice: number; discountUsed: number; isSpecial: boolean } {
    // Use book specific discount if it's explicitly set (even if it's 0, but usually we check if > 0)
    // Actually, per implementation plan: "Use book-specific discount if > 0, otherwise use global discount."

    if (bookDiscount !== undefined && bookDiscount > 0) {
        return {
            finalPrice: originalPrice * (1 - bookDiscount / 100),
            discountUsed: bookDiscount,
            isSpecial: true,
        }
    }

    return {
        finalPrice: originalPrice * (1 - globalDiscount / 100),
        discountUsed: globalDiscount,
        isSpecial: false,
    }
}
