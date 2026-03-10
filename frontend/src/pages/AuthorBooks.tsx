import { useQuery } from '@tanstack/react-query'
import { useParams, Link, useSearchParams } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { authors as authorsApi, books as booksApi } from '../lib/api'
import { resolveCoverUrl } from '../lib/utils'
import { useSettings } from '../contexts/SettingsContext'
import { BookCard } from '../components/BookCard'
import { Pagination } from '../components/Pagination'
import type { Author, Book } from '../lib/api'

export function AuthorBooks() {
    const { id } = useParams<{ id: string }>()
    const [searchParams, setSearchParams] = useSearchParams()
    const search = searchParams.get('search') ?? ''
    const page = parseInt(searchParams.get('page') ?? '1', 10)
    const setPage = (p: number) => {
        const params = new URLSearchParams(searchParams)
        params.set('page', String(p))
        setSearchParams(params)
    }

    const { data: authorData, isLoading: authorLoading } = useQuery({
        queryKey: ['author', id],
        queryFn: async () => {
            const res = await authorsApi.get(id!)
            return res.data
        },
        enabled: !!id,
    })

    const { data: booksData, isLoading: booksLoading } = useQuery({
        queryKey: ['books', 'author', id, page, search],
        queryFn: async () => {
            const params: Record<string, string | number> = { author_id: id!, page, per_page: 32 }
            if (search) params.search = search
            const res = await booksApi.list(params)
            return res.data
        },
        enabled: !!id,
    })

    const author: Author | undefined = authorData?.data
    const paginated = booksData?.data
    const bookItems: Book[] = paginated?.data ?? []
    const meta = paginated && 'current_page' in paginated ? paginated : null

    const isLoading = authorLoading || booksLoading
    const { t } = useTranslation()
    const { settings } = useSettings()

    if (isLoading) {
        return (
            <div style={{ textAlign: 'center', padding: '60px 0', color: '#78716c' }}>
                <div
                    style={{
                        width: 40,
                        height: 40,
                        border: '3px solid #e7e5e4',
                        borderTopColor: '#92400e',
                        borderRadius: '50%',
                        animation: 'spin 0.8s linear infinite',
                        margin: '0 auto 16px',
                    }}
                />
                <style>{`@keyframes spin { to { transform: rotate(360deg); } }`}</style>
                {t('common.loading')}
            </div>
        )
    }

    // Generate a deterministic color from the name
    const getColor = (name: string) => {
        const colors = [
            '#92400e', '#78350f', '#a16207', '#4d7c0f',
            '#0e7490', '#1d4ed8', '#6d28d9', '#be123c',
            '#0f766e', '#7c2d12', '#4338ca', '#9f1239',
        ]
        let hash = 0
        for (let i = 0; i < name.length; i++) hash = name.charCodeAt(i) + ((hash << 5) - hash)
        return colors[Math.abs(hash) % colors.length]
    }

    const getInitials = (name: string) => {
        const parts = name.trim().split(/\s+/)
        if (parts.length >= 2) return parts[0][0] + parts[parts.length - 1][0]
        return name.substring(0, 2)
    }

    return (
        <div>
            {/* Breadcrumb */}
            <div style={{ marginBottom: 24, fontSize: 14, color: '#78716c' }}>
                <Link to="/" style={{ color: '#92400e', textDecoration: 'none' }}>
                    {t('nav.bookStore')}
                </Link>
                {' '}
                /{' '}
                <Link to="/authors" style={{ color: '#92400e', textDecoration: 'none' }}>
                    {t('authors.title')}
                </Link>
                {' '}
                / {author?.name}
            </div>

            {/* Author Header */}
            {author && (
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        gap: 20,
                        marginBottom: 32,
                        padding: '24px 28px',
                        background: '#fff',
                        borderRadius: 12,
                        border: '1px solid #e7e5e4',
                    }}
                >
                    {/* Avatar - show image when available, fallback to initials on error */}
                    <div
                        style={{
                            width: 80,
                            height: 80,
                            borderRadius: '50%',
                            flexShrink: 0,
                            position: 'relative',
                            overflow: 'hidden',
                        }}
                    >
                        {author.photo && (
                            <img
                                src={resolveCoverUrl(author.photo)}
                                alt={author.name}
                                style={{
                                    width: '100%',
                                    height: '100%',
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
                                width: '100%',
                                height: '100%',
                                borderRadius: '50%',
                                background: getColor(author.name),
                                color: '#fff',
                                display: author.photo ? 'none' : 'flex',
                                alignItems: 'center',
                                justifyContent: 'center',
                                fontSize: 26,
                                fontWeight: 700,
                                position: 'absolute',
                                inset: 0,
                            }}
                        >
                            {getInitials(author.name)}
                        </div>
                    </div>

                    <div>
                        <h1 style={{ fontSize: 24, fontWeight: 700, color: '#292524', margin: 0 }}>
                            {author.name}
                        </h1>
                        {(author.date_of_birth || author.date_of_death) && (
                            <p style={{ fontSize: 13, color: '#a8a29e', marginTop: 4 }}>
                                {author.date_of_birth && (
                                    <span>{t('authors.born')}: {new Date(author.date_of_birth).toLocaleDateString()}</span>
                                )}
                                {author.date_of_birth && author.date_of_death && ' · '}
                                {author.date_of_death && (
                                    <span>{t('authors.died')}: {new Date(author.date_of_death).toLocaleDateString()}</span>
                                )}
                            </p>
                        )}
                        {author.biography && (
                            <p style={{ fontSize: 14, color: '#78716c', marginTop: 6, lineHeight: 1.6 }}>
                                {author.biography}
                            </p>
                        )}
                        <p style={{ fontSize: 13, color: '#a8a29e', marginTop: 6 }}>
                            {t('authors.booksByAuthor', { name: '' }).replace('{{name}}', '').trim()} {meta?.total ?? bookItems.length}
                        </p>
                    </div>
                </div>
            )}

            {/* Books Grid */}
            {bookItems.length > 0 ? (
                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns: 'repeat(auto-fill, minmax(190px, 1fr))',
                        gap: 24,
                    }}
                >
                    {bookItems.map((book) => (
                        <BookCard
                            key={book._id}
                            id={book._id}
                            title={book.title}
                            price={book.price}
                            coverImage={book.cover_image}
                            coverImageThumb={book.cover_image_thumb}
                            authorName={book.authors?.map((a) => a.name).join('، ')}
                            authors={book.authors}
                            discountPercent={book.discount_percent ?? 0}
                            globalDiscount={settings.global_discount ?? 0}
                        />
                    ))}
                </div>
            ) : (
                <div style={{ textAlign: 'center', padding: '60px 0', color: '#78716c' }}>
                    <div style={{ fontSize: 48, marginBottom: 16 }}>📚</div>
                    <p style={{ fontSize: 16 }}>{t('authors.noBooksForAuthor')}</p>
                </div>
            )}
            {meta && (
                <div style={{ marginTop: 24 }}>
                    <Pagination
                        currentPage={meta.current_page}
                        lastPage={meta.last_page}
                        total={meta.total}
                        perPage={meta.per_page}
                        onPageChange={setPage}
                    />
                </div>
            )}
        </div>
    )
}
