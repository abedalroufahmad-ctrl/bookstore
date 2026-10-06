interface PaginationProps {
  currentPage: number
  lastPage: number
  total: number
  perPage: number
  onPageChange: (page: number) => void
  /** Cap how far offset navigation can go (large catalogs). */
  maxNavigablePage?: number
}

/** Build a small list of page buttons without allocating `lastPage` entries. */
function visiblePages(current: number, last: number): number[] {
  if (last <= 1) return []
  if (last <= 7) {
    return Array.from({ length: last }, (_, i) => i + 1)
  }

  const pages = new Set<number>([1, last, current])
  for (let p = current - 1; p <= current + 1; p++) {
    if (p >= 1 && p <= last) pages.add(p)
  }

  return [...pages].sort((a, b) => a - b)
}

export function Pagination({
  currentPage,
  lastPage,
  total,
  perPage,
  onPageChange,
  maxNavigablePage,
}: PaginationProps) {
  if (lastPage <= 1) return null

  const navLast = maxNavigablePage && maxNavigablePage > 0
    ? Math.min(lastPage, maxNavigablePage)
    : lastPage
  const safeCurrent = Math.min(Math.max(1, currentPage), navLast)
  const start = (safeCurrent - 1) * perPage + 1
  const end = Math.min(safeCurrent * perPage, total)
  const pages = visiblePages(safeCurrent, navLast)

  return (
    <div className="flex items-center justify-between gap-4 flex-wrap py-4">
      <p className="text-sm" style={{ color: 'var(--color-text-muted)' }}>
        {start}-{end} {total > 0 ? `of ${total.toLocaleString()}` : ''}
        {navLast < lastPage ? ` · first ${navLast.toLocaleString()} pages` : ''}
      </p>
      <div className="flex gap-1 items-center">
        <button
          type="button"
          onClick={() => onPageChange(safeCurrent - 1)}
          disabled={safeCurrent <= 1}
          className="px-3 py-1.5 rounded border bg-white disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
          style={{ borderColor: 'var(--color-border)', color: 'var(--color-text)' }}
        >
          ←
        </button>
        {pages.map((p, idx) => {
          const prev = pages[idx - 1]
          const showEllipsis = prev !== undefined && p - prev > 1
          return (
            <span key={p} className="flex gap-1">
              {showEllipsis && (
                <span className="px-2 py-1" style={{ color: 'var(--color-text-muted)' }}>…</span>
              )}
              <button
                type="button"
                onClick={() => onPageChange(p)}
                className={`px-3 py-1.5 rounded border ${
                  p === safeCurrent ? 'text-white' : 'bg-white'
                }`}
                style={
                  p === safeCurrent
                    ? { borderColor: 'var(--color-primary)', background: 'var(--color-primary)' }
                    : { borderColor: 'var(--color-border)', color: 'var(--color-text)' }
                }
              >
                {p}
              </button>
            </span>
          )
        })}
        <button
          type="button"
          onClick={() => onPageChange(safeCurrent + 1)}
          disabled={safeCurrent >= navLast}
          className="px-3 py-1.5 rounded border bg-white disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
          style={{ borderColor: 'var(--color-border)', color: 'var(--color-text)' }}
        >
          →
        </button>
      </div>
    </div>
  )
}
