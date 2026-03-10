import { useState, useEffect } from 'react'
import { useQuery } from '@tanstack/react-query'
import { useParams, useNavigate, Link } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { books, cart } from '../lib/api'
import { useAuth } from '../contexts/AuthContext'
import { useSettings, formatWeight } from '../contexts/SettingsContext'
import { calculateDiscountedPrice, resolveCoverUrl } from '../lib/utils'

export function BookDetail() {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()
  const { userType } = useAuth()
  const { settings } = useSettings()
  const [showOriginal, setShowOriginal] = useState(false)
  const { t } = useTranslation()

  useEffect(() => {
    const onEsc = (e: KeyboardEvent) => e.key === 'Escape' && setShowOriginal(false)
    if (showOriginal) {
      document.addEventListener('keydown', onEsc)
      document.body.style.overflow = 'hidden'
    }
    return () => {
      document.removeEventListener('keydown', onEsc)
      document.body.style.overflow = ''
    }
  }, [showOriginal])

  const { data, isLoading, error } = useQuery({
    queryKey: ['book', id],
    queryFn: async () => {
      const res = await books.get(id!)
      return res.data
    },
    enabled: !!id,
  })

  const addToCart = async () => {
    if (userType !== 'customer') {
      navigate('/login')
      return
    }
    try {
      await cart.addItem(id!, 1)
      navigate('/cart')
    } catch {
      alert(t('common.error'))
    }
  }

  if (isLoading) return <div className="text-center py-12" style={{ color: 'var(--color-text-muted)' }}>{t('common.loading')}</div>
  if (error || !data?.data) return <div className="text-center py-12" style={{ color: 'var(--color-discount)' }}>{t('common.notFound')}</div>

  const book = data.data
  const authors = Array.isArray(book.authors) ? book.authors : []
  const category = book.category

  const InfoRow = ({ label, value, alwaysShow }: { label: string; value: React.ReactNode; alwaysShow?: boolean }) => {
    const show = alwaysShow || (value != null && value !== '')
    return show ? (
      <div className="flex gap-2 py-1">
        <span className="font-medium w-28" style={{ color: 'var(--color-text-muted)' }}>{label}:</span>
        <span style={{ color: 'var(--color-text)' }}>{value ?? '—'}</span>
      </div>
    ) : null
  }

  const thumbUrl = book.cover_image_thumb ?? book.cover_image
  const originalUrl = book.cover_image
  const hasOriginal = originalUrl && originalUrl !== thumbUrl

  const { finalPrice, discountUsed } = calculateDiscountedPrice(
    book.price,
    book.discount_percent,
    settings.global_discount
  )

  return (
    <div className="max-w-4xl">
      {showOriginal && originalUrl && (
        <div
          className="fixed inset-0 z-50 bg-black/80 flex items-center justify-center p-4"
          onClick={() => setShowOriginal(false)}
          role="presentation"
        >
          <button
            type="button"
            className="absolute top-4 end-4 text-white text-2xl hover:opacity-80"
            onClick={() => setShowOriginal(false)}
          >
            ×
          </button>
          <img
            src={resolveCoverUrl(originalUrl)}
            alt={book.title}
            className="max-w-full max-h-[90vh] object-contain"
            onClick={(e) => e.stopPropagation()}
          />
        </div>
      )}

      <div className="flex flex-col sm:flex-row gap-8 items-start">
        {/* Cover Image */}
        {thumbUrl && (
          <div className="flex-shrink-0">
            <button
              type="button"
              onClick={() => hasOriginal && setShowOriginal(true)}
              className={`block text-left ${hasOriginal ? 'cursor-zoom-in' : 'cursor-default'}`}
            >
              <img
                src={resolveCoverUrl(thumbUrl)}
                alt={book.title}
                className="rounded-lg w-[200px] h-[280px] object-cover shadow-md"
                onError={(e) => (e.currentTarget.style.display = 'none')}
              />
              {hasOriginal && (
                <p className="text-xs text-stone-500 mt-1 text-center">{t('bookDetail.clickToViewOriginal')}</p>
              )}
            </button>
          </div>
        )}

        {/* Book Details */}
        <div className="flex-1 min-w-0">
          <h1 className="text-2xl font-bold mb-2" style={{ color: 'var(--color-text)' }}>{book.title}</h1>
          <div className="flex items-baseline gap-3 mb-6">
            {discountUsed > 0 && (
              <span className="text-xl line-through" style={{ color: 'var(--color-text-muted)' }}>
                ${book.price.toFixed(2)}
              </span>
            )}
            <span className="text-3xl font-bold" style={{ color: 'var(--color-primary)' }}>
              ${finalPrice.toFixed(2)}
            </span>
            {discountUsed > 0 && (
              <span
                className="px-2 py-1 rounded text-sm font-bold"
                style={{ background: 'var(--color-primary-light)', color: 'var(--color-primary)' }}
              >
                {t('discount.special', { percent: discountUsed })}
              </span>
            )}
          </div>
          <div className="space-y-0 mb-4" style={{ color: 'var(--color-text)' }}>
            <InfoRow
              label={t('bookDetail.authors')}
              alwaysShow
              value={
                authors.length > 0 ? (
                  <>
                    {authors.map((a: { _id?: string; id?: string; name?: string }, i) => {
                      const authorId = a._id ?? a.id
                      return (
                        <span key={authorId ?? i}>
                          {i > 0 && ', '}
                          {authorId ? (
                            <Link to={`/authors/${authorId}`} style={{ color: 'var(--color-primary)' }} className="hover:underline">
                              {a.name || 'Unknown'}
                            </Link>
                          ) : (
                            <span>{a.name || 'Unknown'}</span>
                          )}
                        </span>
                      )
                    })}
                  </>
                ) : '—'
              }
            />
            <InfoRow
              label={t('bookDetail.category')}
              value={
                category && (category._id ? (
                  <Link to={`/categories/${category._id}`} style={{ color: 'var(--color-primary)' }} className="hover:underline">
                    {category.subject_title ?? category.dewey_code}
                  </Link>
                ) : (
                  category.subject_title ?? category.dewey_code
                ))
              }
            />
            <InfoRow label={t('bookDetail.isbn')} value={book.isbn} />
            <InfoRow label={t('bookDetail.publisher')} value={book.publisher} />
            <InfoRow label={t('bookDetail.year')} value={book.publish_year} />
            <InfoRow label={t('bookDetail.pages')} value={book.pages != null ? String(book.pages) : null} />
            <InfoRow label={t('bookDetail.size')} value={book.size} />
            <InfoRow label={t('bookDetail.weight')} value={book.weight != null ? formatWeight(book.weight, settings.weight_unit) : null} />
            <InfoRow label={t('bookDetail.edition')} value={book.edition_number != null ? String(book.edition_number) : null} />
            <InfoRow label={t('bookDetail.bindingType')} value={book.binding_type} />
            <InfoRow label={t('bookDetail.paperType')} value={book.paper_type} />
            <InfoRow
              label={t('bookDetail.stock')}
              value={
                book.stock_quantity !== undefined
                  ? book.stock_quantity > 0
                    ? t('bookDetail.inStock', { count: book.stock_quantity })
                    : t('bookDetail.outOfStock')
                  : null
              }
            />
          </div>
          {book.description && <p className="text-stone-600 mb-4">{book.description}</p>}
          {userType === 'customer' && book.stock_quantity > 0 && (
            <button
              onClick={addToCart}
              className="px-6 py-2.5 rounded-lg font-medium text-white transition-colors hover:opacity-90"
              style={{ background: 'var(--color-primary)' }}
            >
              {t('bookDetail.addToCart')}
            </button>
          )}
          {userType !== 'customer' && book.stock_quantity > 0 && (
            <button
              onClick={() => navigate('/login')}
              className="px-6 py-2.5 rounded-lg font-medium text-white flex items-center gap-2 transition-colors hover:opacity-90"
              style={{ background: 'var(--color-primary)' }}
            >
              <span>🔒</span> {t('bookDetail.loginToAddToCart')}
            </button>
          )}
        </div>
      </div>
    </div>
  )
}
