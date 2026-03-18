import { Link } from 'react-router-dom'
import { useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { calculateDiscountedPrice, resolveCoverUrl } from '../lib/utils'

const FALLBACK_COVER_URL = '/favicon.png'

interface AuthorRef {
  _id?: string
  id?: string
  name?: string
  photo?: string
}

interface BookCardProps {
  id: string
  title: string
  price: number
  coverImage?: string
  coverImageThumb?: string
  authorName?: string
  authors?: AuthorRef[]
  discountPercent?: number
  globalDiscount: number
  /** When provided, an "Add to cart" button is shown. Call with book id on click. */
  onAddToCart?: (bookId: string) => void
  /** When true, show loading state on the Add to cart button. */
  isAddingToCart?: boolean
  /** When true, show the Add to cart button as green (in cart). */
  isInCart?: boolean
}

function getAuthorColor(name: string) {
  const colors = ['#cd071e', '#1d4ed8', '#0e7490', '#4d7c0f', '#6d28d9', '#be123c']
  let hash = 0
  for (let i = 0; i < name.length; i++) hash = name.charCodeAt(i) + ((hash << 5) - hash)
  return colors[Math.abs(hash) % colors.length]
}

function getInitials(name: string) {
  const parts = name.trim().split(/\s+/)
  if (parts.length >= 2) return parts[0][0] + parts[parts.length - 1][0]
  return name.substring(0, 2)
}

export function BookCard({
  id,
  title,
  price,
  coverImage,
  coverImageThumb,
  authorName,
  authors,
  discountPercent,
  globalDiscount,
  onAddToCart,
  isAddingToCart,
  isInCart,
}: BookCardProps) {
  const { t } = useTranslation()
  const [useFallbackCover, setUseFallbackCover] = useState(false)

  const { finalPrice, discountUsed, isSpecial } = calculateDiscountedPrice(
    price,
    discountPercent,
    globalDiscount
  )

  const authorId = (a: AuthorRef) => a._id ?? a.id
  const coverUrl = useMemo(() => (coverImageThumb || coverImage || '').trim(), [coverImageThumb, coverImage])
  const isNullLike = coverUrl && (coverUrl.toLowerCase() === 'null' || coverUrl.toLowerCase() === 'undefined')
  const showRealCover = coverUrl && !isNullLike && !useFallbackCover

  return (
    <div className="book-card">
      <Link to={`/books/${id}`} style={{ textDecoration: 'none' }}>
        <div className="book-cover-wrapper">
          {showRealCover ? (
            <img
              src={resolveCoverUrl(coverUrl)}
              alt={title}
              loading="lazy"
              onError={() => setUseFallbackCover(true)}
            />
          ) : (
            <img
              src={FALLBACK_COVER_URL}
              alt=""
              className="w-full h-full object-cover"
              style={{ objectFit: 'cover' }}
            />
          )}

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

          {isSpecial && discountUsed > 0 && (
            <div className="discount-badge">{t('discount.special', { percent: discountUsed })}</div>
          )}

          {!isSpecial && discountUsed > 0 && (
            <div className="global-discount-badge">{t('discount.save', { percent: discountUsed })}</div>
          )}
        </div>

        <div className="book-price-row">
          {discountUsed > 0 && (
            <span className="original-price">${price.toFixed(2)}</span>
          )}
          <span className="discounted-price">
            ${finalPrice.toFixed(2)}
          </span>
        </div>

        <div className="book-title-text">{title}</div>
      </Link>
      {onAddToCart && (
        <div className="mt-3" style={{ position: 'relative', zIndex: 1 }}>
          <button
            type="button"
            onClick={(e) => {
              e.preventDefault()
              e.stopPropagation()
              onAddToCart(id)
            }}
            disabled={isAddingToCart}
            className="w-full py-2 px-3 rounded-lg text-sm font-medium transition"
            style={{
              background: isInCart ? '#16a34a' : 'var(--color-primary)',
              color: '#fff',
              border: 'none',
              cursor: isAddingToCart ? 'wait' : 'pointer',
              pointerEvents: 'auto',
            }}
          >
            {isAddingToCart ? t('common.loading') : t('bookDetail.addToCart')}
          </button>
        </div>
      )}
      {(authors?.length || authorName) && (
        <div style={{ display: 'flex', alignItems: 'center', gap: 6, marginTop: 8, flexWrap: 'wrap' }}>
          {authors && authors.length > 0
            ? authors.slice(0, 3).map((a) => {
                const id = authorId(a)
                const name = a.name ?? 'Unknown'
                return (
                  <div key={id ?? name} style={{ display: 'flex', alignItems: 'center', gap: 4 }}>
                    <div style={{ position: 'relative', width: 24, height: 24, flexShrink: 0 }}>
                      {a.photo && (
                        <img
                          src={resolveCoverUrl(a.photo)}
                          alt={name}
                          style={{
                            width: 24,
                            height: 24,
                            borderRadius: '50%',
                            objectFit: 'cover',
                            position: 'absolute',
                            inset: 0,
                          }}
                          onError={(e) => {
                            e.currentTarget.style.display = 'none'
                            const fallback = e.currentTarget.nextElementSibling as HTMLElement
                            if (fallback) fallback.style.display = 'flex'
                          }}
                        />
                      )}
                      <div
                        style={{
                          width: 24,
                          height: 24,
                          borderRadius: '50%',
                          background: getAuthorColor(name),
                          color: '#fff',
                          fontSize: 10,
                          display: a.photo ? 'none' : 'flex',
                          alignItems: 'center',
                          justifyContent: 'center',
                          fontWeight: 600,
                        }}
                      >
                        {getInitials(name)}
                      </div>
                    </div>
                    {id ? (
                      <Link
                        to={`/authors/${id}`}
                        style={{ fontSize: 12, color: 'var(--color-primary)' }}
                        className="hover:underline"
                        onClick={(e) => e.stopPropagation()}
                      >
                        {name}
                      </Link>
                    ) : (
                      <span style={{ fontSize: 12, color: 'var(--color-text-muted)' }}>{name}</span>
                    )}
                  </div>
                )
              })
            : authorName && <span style={{ fontSize: 12, color: 'var(--color-text-muted)' }}>{authorName}</span>}
        </div>
      )}
    </div>
  )
}
