import { useQuery } from '@tanstack/react-query'
import { Link, useSearchParams } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { authors as authorsApi } from '../lib/api'
import { resolveCoverUrl } from '../lib/utils'
import { Pagination } from '../components/Pagination'
import { useSettings } from '../contexts/SettingsContext'
import type { Author } from '../lib/api'

export function AuthorList() {
    const { t } = useTranslation()
    const { settings } = useSettings()
    const [searchParams, setSearchParams] = useSearchParams()
    const search = searchParams.get('search') ?? ''
    const page = parseInt(searchParams.get('page') ?? '1', 10)
    const setPage = (p: number) => {
        const params = new URLSearchParams(searchParams)
        params.set('page', String(p))
        setSearchParams(params)
    }
    const { data, isLoading, error } = useQuery({
        queryKey: ['authors', page, search, settings.catalog_items_per_page],
        queryFn: async () => {
            const queryParams: Record<string, string | number> = { page, per_page: settings.catalog_items_per_page }
            if (search) queryParams.search = search
            const res = await authorsApi.list(queryParams)
            return res.data
        },
    })

    const paginated = data?.data
    const items: Author[] = paginated?.data ?? []
    const meta = paginated && 'current_page' in paginated ? paginated : null

    if (isLoading) {
        return (
            <div className="text-center py-20" style={{ color: 'var(--color-text-muted)' }}>
                <div
                    className="mx-auto mb-4 rounded-full border-2 animate-spin"
                    style={{
                        width: 44,
                        height: 44,
                        borderColor: 'var(--color-border)',
                        borderTopColor: 'var(--color-primary)',
                    }}
                />
                {t('common.loading')}
            </div>
        )
    }

    if (error) {
        return (
            <div className="text-center py-20" style={{ color: 'var(--color-discount)' }}>
                {t('authors.loadError')}
            </div>
        )
    }

    // Generate initials for the avatar
    const getInitials = (name: string) => {
        const parts = name.trim().split(/\s+/)
        if (parts.length >= 2) return parts[0][0] + parts[parts.length - 1][0]
        return name.substring(0, 2)
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

    return (
        <div>
            {/* Header */}
            <div className="mb-8">
                <h1 className="text-2xl font-bold" style={{ color: 'var(--color-text)' }}>
                    {t('authors.title')}
                </h1>
            </div>

            {/* Authors Grid */}
            <div
                className="grid gap-4"
                style={{ gridTemplateColumns: 'repeat(auto-fill, minmax(220px, 1fr))' }}
            >
                {items.map((author) => (
                    <Link
                        key={author._id}
                        to={`/authors/${author._id}`}
                        className="category-card flex flex-col items-center"
                        style={{ textDecoration: 'none', color: 'inherit' }}
                    >
                        {/* Circular author photo - always try to show image when photo exists, fallback to initials */}
                        <div
                            style={{
                                width: 100,
                                height: 100,
                                borderRadius: '50%',
                                marginBottom: 12,
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
                                    fontSize: 28,
                                    fontWeight: 700,
                                    position: 'absolute',
                                    inset: 0,
                                }}
                            >
                                {getInitials(author.name)}
                            </div>
                        </div>

                        {/* Author name */}
                        <div
                            style={{
                                fontSize: 15,
                                fontWeight: 600,
                                color: 'var(--color-primary)',
                                textAlign: 'center',
                                lineHeight: 1.5,
                            }}
                        >
                            {author.name}
                        </div>
                    </Link>
                ))}
            </div>

            {items.length === 0 && (
                <div className="text-center py-20" style={{ color: 'var(--color-text-muted)' }}>
                    <div className="text-5xl mb-4">✍️</div>
                    <p className="text-lg">{t('authors.noAuthors')}</p>
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
