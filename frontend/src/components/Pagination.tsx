interface PaginationProps {
  currentPage: number
  lastPage: number
  total: number
  perPage: number
  onPageChange: (page: number) => void
}

export function Pagination({ currentPage, lastPage, total, perPage, onPageChange }: PaginationProps) {
  if (lastPage <= 1) return null

  const start = (currentPage - 1) * perPage + 1
  const end = Math.min(currentPage * perPage, total)

  return (
    <div className="flex items-center justify-between gap-4 flex-wrap py-4">
      <p className="text-sm" style={{ color: 'var(--color-text-muted)' }}>
        {start}-{end} {total > 0 ? `of ${total}` : ''}
      </p>
      <div className="flex gap-1">
        <button
          type="button"
          onClick={() => onPageChange(currentPage - 1)}
          disabled={currentPage <= 1}
          className="px-3 py-1.5 rounded border bg-white disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
          style={{ borderColor: 'var(--color-border)', color: 'var(--color-text)' }}
        >
          ←
        </button>
        {Array.from({ length: lastPage }, (_, i) => i + 1)
          .filter((p) => {
            if (lastPage <= 7) return true
            if (p === 1 || p === lastPage) return true
            if (Math.abs(p - currentPage) <= 1) return true
            return false
          })
          .map((p, idx, arr) => {
            const prev = arr[idx - 1]
            const showEllipsis = prev !== undefined && p - prev > 1
            return (
              <span key={p} className="flex gap-1">
                {showEllipsis && <span className="px-2 py-1" style={{ color: 'var(--color-text-muted)' }}>…</span>}
                <button
                  type="button"
                  onClick={() => onPageChange(p)}
                  className={`px-3 py-1.5 rounded border ${
                    p === currentPage ? 'text-white' : 'bg-white'
                  }`}
                  style={
                    p === currentPage
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
          onClick={() => onPageChange(currentPage + 1)}
          disabled={currentPage >= lastPage}
          className="px-3 py-1.5 rounded border bg-white disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
          style={{ borderColor: 'var(--color-border)', color: 'var(--color-text)' }}
        >
          →
        </button>
      </div>
    </div>
  )
}
