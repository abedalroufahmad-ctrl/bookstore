import { Link } from 'react-router-dom'

interface BookCardProps {
  id: string
  title: string
  price: number
  coverImage?: string
  authorName?: string
  discountPercent?: number
}

export function BookCard({
  id,
  title,
  price,
  coverImage,
  authorName,
  discountPercent = 20,
}: BookCardProps) {
  const apiBase = import.meta.env.VITE_API_URL || '/api/v1'
  const resolveCoverUrl = (path: string) =>
    path.startsWith('http://') || path.startsWith('https://')
      ? path
      : `${apiBase.replace(/\/api\/v1$/, '')}${path.startsWith('/') ? path : `/${path}`}`

  const originalPrice = price
  const discountedPrice = +(price * (1 - discountPercent / 100)).toFixed(2)

  return (
    <Link to={`/books/${id}`} className="book-card" style={{ textDecoration: 'none' }}>
      <div className="book-cover-wrapper">
        {coverImage ? (
          <img
            src={resolveCoverUrl(coverImage)}
            alt={title}
            loading="lazy"
            onError={(e) => {
              e.currentTarget.style.display = 'none'
              const placeholder = e.currentTarget.nextElementSibling as HTMLElement
              if (placeholder) placeholder.style.display = 'flex'
            }}
          />
        ) : null}
        <div
          className="placeholder-cover"
          style={{ display: coverImage ? 'none' : 'flex' }}
        >
          {title}
        </div>

        {authorName && (
          <div
            style={{
              position: 'absolute',
              top: 8,
              right: 8,
              background: 'rgba(0,0,0,0.55)',
              color: '#fff',
              fontSize: 10,
              padding: '2px 8px',
              borderRadius: 3,
              maxWidth: '80%',
              overflow: 'hidden',
              whiteSpace: 'nowrap',
              textOverflow: 'ellipsis',
            }}
          >
            {authorName}
          </div>
        )}

        {discountPercent > 0 && (
          <div className="discount-badge">خصم {discountPercent}%</div>
        )}
      </div>

      <div className="book-price-row">
        {discountPercent > 0 && (
          <span className="original-price">${originalPrice.toFixed(2)}</span>
        )}
        <span className="discounted-price">
          ${discountPercent > 0 ? discountedPrice.toFixed(2) : originalPrice.toFixed(2)}
        </span>
      </div>

      <div className="book-title-text">{title}</div>
    </Link>
  )
}
