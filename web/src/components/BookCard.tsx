import { Link } from 'react-router-dom'
import { useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { calculateDiscountedPrice, resolveCoverUrl } from '../lib/utils'
import { formatWeight, useSettings } from '../contexts/SettingsContext'

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
  publisher?: string
  publisherId?: string
  /** Prefer this over single publisher/publisherId when present. */
  publishers?: { id?: string; name: string }[]
  warehouseName?: string
  warehouseId?: string
  /** Category / genre label for orange accent line. */
  categoryName?: string | null
  /** Stored weight in grams. */
  weight?: number | null
  discountPercent?: number
  globalDiscount: number
  condition?: 'new' | 'used'
  isSold?: boolean
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
  publisher,
  publisherId,
  publishers,
  warehouseName,
  warehouseId,
  categoryName,
  weight,
  discountPercent,
  globalDiscount,
  condition,
  isSold,
  onAddToCart,
  isAddingToCart,
  isInCart,
}: BookCardProps) {
  const { t } = useTranslation()
  const { settings } = useSettings()
  const [useFallbackCover, setUseFallbackCover] = useState(false)

  const { finalPrice, discountUsed, isSpecial } = calculateDiscountedPrice(
    price,
    discountPercent,
    globalDiscount
  )
  const weightLabel = formatWeight(weight, settings.weight_unit)

  const authorId = (a: AuthorRef) => a._id ?? a.id
  const coverUrl = useMemo(() => (coverImageThumb || coverImage || '').trim(), [coverImageThumb, coverImage])
  const isNullLike = coverUrl && (coverUrl.toLowerCase() === 'null' || coverUrl.toLowerCase() === 'undefined')
  const showRealCover = coverUrl && !isNullLike && !useFallbackCover
  const publisherEntries = useMemo(() => {
    if (publishers && publishers.length > 0) {
      return publishers.filter((p) => (p.name ?? '').trim())
    }
    if (publisher) return [{ id: publisherId, name: publisher }]
    return [] as { id?: string; name: string }[]
  }, [publishers, publisher, publisherId])

  return (
    <div className="book-card">
      <Link to={`/books/${id}`} style={{ textDecoration: 'none', color: 'inherit', display: 'block' }}>
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

          {condition === 'used' && (
            <div
              style={{
                position: 'absolute',
                top: 8,
                insetInlineStart: 8,
                background: isSold ? '#7f1d1d' : '#92400e',
                color: '#fff',
                fontSize: 10,
                padding: '2px 8px',
                borderRadius: 6,
                fontWeight: 700,
              }}
            >
              {isSold ? t('bookDetail.sold') : t('bookDetail.conditionUsed')}
            </div>
          )}

          {isSpecial && discountUsed > 0 && (
            <div className="discount-badge">{t('discount.special', { percent: discountUsed })}</div>
          )}

          {!isSpecial && discountUsed > 0 && (
            <div className="global-discount-badge">{t('discount.save', { percent: discountUsed })}</div>
          )}
        </div>

        <div className="book-title-text">{title}</div>
        {categoryName && <div className="book-category-text">{categoryName}</div>}
        {weightLabel && (
          <div className="book-weight-text">
            {t('bookDetail.weight')}: {weightLabel}
          </div>
        )}
        <div className="book-price-row">
          {discountUsed > 0 && (
            <span className="original-price">${price.toFixed(2)}</span>
          )}
          <span className="discounted-price">
            ${finalPrice.toFixed(2)}
          </span>
        </div>
      </Link>

      {(publisherEntries.length > 0 || warehouseName) && (
        <div className="book-card-meta">
          {publisherEntries.length > 0 && (
            <div style={{ display: 'flex', flexWrap: 'wrap', gap: 4, justifyContent: 'center', alignItems: 'center' }}>
              {publisherEntries.slice(0, 2).map((p, i) => (
                <span key={p.id ?? p.name}>
                  {i > 0 && <span style={{ marginInlineEnd: 4 }}>،</span>}
                  {p.id ? (
                    <Link
                      to={`/publishers/${p.id}`}
                      onClick={(e) => e.stopPropagation()}
                      className="hover:underline"
                      style={{ color: 'var(--color-primary)' }}
                    >
                      {p.name}
                    </Link>
                  ) : (
                    <span>{p.name}</span>
                  )}
                </span>
              ))}
            </div>
          )}
          {warehouseName && (
            warehouseId ? (
              <Link
                to={`/warehouses/${warehouseId}`}
                onClick={(e) => e.stopPropagation()}
                className="hover:underline"
                style={{ color: 'var(--color-primary)' }}
              >
                {warehouseName}
              </Link>
            ) : (
              <span>{warehouseName}</span>
            )
          )}
        </div>
      )}

      {onAddToCart && (
        <button
          type="button"
          className={`book-card-cart-btn${isInCart ? ' is-in-cart' : ''}`}
          onClick={(e) => {
            e.preventDefault()
            e.stopPropagation()
            onAddToCart(id)
          }}
          disabled={isAddingToCart}
          style={{ cursor: isAddingToCart ? 'wait' : 'pointer' }}
        >
          {isAddingToCart ? t('common.loading') : t('bookDetail.addToCart')}
        </button>
      )}

      {(authors?.length || authorName) && (
        <div className="book-card-authors">
          {authors && authors.length > 0
            ? authors.slice(0, 3).map((a) => {
                const aid = authorId(a)
                const name = a.name ?? t('common.unknown')
                return (
                  <div key={aid ?? name} style={{ display: 'flex', alignItems: 'center', gap: 4 }}>
                    <div style={{ position: 'relative', width: 22, height: 22, flexShrink: 0 }}>
                      {a.photo && (
                        <img
                          src={resolveCoverUrl(a.photo)}
                          alt={name}
                          style={{
                            width: 22,
                            height: 22,
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
                          width: 22,
                          height: 22,
                          borderRadius: '50%',
                          background: getAuthorColor(name),
                          color: '#fff',
                          fontSize: 9,
                          display: a.photo ? 'none' : 'flex',
                          alignItems: 'center',
                          justifyContent: 'center',
                          fontWeight: 600,
                        }}
                      >
                        {getInitials(name)}
                      </div>
                    </div>
                    {aid ? (
                      <Link
                        to={`/authors/${aid}`}
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
