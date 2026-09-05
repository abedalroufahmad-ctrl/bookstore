/** Well-known placeholder / dummy image hosts that don't count as real covers. */
const PLACEHOLDER_HOSTS = [
  'via.placeholder.com',
  'placeholder.com',
  'placehold.co',
  'placehold.it',
  'placekitten.com',
  'dummyimage.com',
]

function isRealCoverUrl(url: string): boolean {
  if (!url) return false
  const lower = url.toLowerCase()
  if (lower === 'null' || lower === 'undefined') return false
  return !PLACEHOLDER_HOSTS.some((h) => lower.includes(h))
}

/** True when the book has at least one cover image URL (thumb or full). */
export function hasCover(book: { cover_image?: string; cover_image_thumb?: string }): boolean {
  return (
    isRealCoverUrl((book.cover_image_thumb || '').trim()) ||
    isRealCoverUrl((book.cover_image || '').trim())
  )
}

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

export type PublisherRef = { _id?: string; id?: string; name?: string }

/** All publishers on a book (multi + legacy single). */
export function getPublisherEntries(
  book: {
    publisher_ids?: string[]
    publishers?: PublisherRef[] | null
    publisher_id?: string
    publisher?: string | PublisherRef | null
  }
): { id?: string; name: string }[] {
  const out: { id?: string; name: string }[] = []
  const seen = new Set<string>()

  const push = (id?: string, name?: string) => {
    const n = (name ?? '').trim()
    if (!n) return
    const key = id ? `id:${id}` : `name:${n.toLowerCase()}`
    if (seen.has(key)) return
    seen.add(key)
    out.push({ id, name: n })
  }

  if (Array.isArray(book.publishers) && book.publishers.length > 0) {
    for (const p of book.publishers) {
      if (!p) continue
      push(
        p._id != null ? String(p._id) : p.id != null ? String(p.id) : undefined,
        p.name
      )
    }
  }

  // Fall back / merge legacy single publisher when relation is missing or empty.
  if (book.publisher) {
    if (typeof book.publisher === 'string') {
      push(book.publisher_id ? String(book.publisher_id) : undefined, book.publisher)
    } else {
      push(
        book.publisher._id != null
          ? String(book.publisher._id)
          : book.publisher.id != null
            ? String(book.publisher.id)
            : book.publisher_id
              ? String(book.publisher_id)
              : undefined,
        book.publisher.name
      )
    }
  }

  return out
}

export function getPublisherLabel(
  book: {
    publishers?: PublisherRef[] | null
    publisher?: string | { name?: string } | null
  }
): string | undefined {
  const entries = getPublisherEntries(book)
  if (entries.length) return entries.map((e) => e.name).join('، ')
  return undefined
}

export function getPublisherId(
  book: {
    publisher_ids?: string[]
    publisher_id?: string
    publishers?: PublisherRef[] | null
    publisher?: string | { _id?: string; id?: string } | null
  }
): string | undefined {
  const entries = getPublisherEntries(book)
  if (entries[0]?.id) return entries[0].id
  if (book.publisher_ids?.[0]) return String(book.publisher_ids[0])
  if (book.publisher_id) return String(book.publisher_id)
  if (!book.publisher || typeof book.publisher === 'string') return undefined
  const id = book.publisher._id ?? book.publisher.id
  return id ? String(id) : undefined
}

export function getWarehouseId(
  book: { warehouse_id?: string; warehouse?: { _id?: string; id?: string } | null }
): string | undefined {
  if (book.warehouse_id) return String(book.warehouse_id)
  const id = book.warehouse?._id ?? book.warehouse?.id
  return id ? String(id) : undefined
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
