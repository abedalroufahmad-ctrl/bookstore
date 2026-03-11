import { useQuery } from '@tanstack/react-query'
import { Link, useSearchParams } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { categories as categoriesApi } from '../lib/api'
import { Pagination } from '../components/Pagination'
import { useSettings } from '../contexts/SettingsContext'
import type { Category } from '../lib/api'

export function CategoryList() {
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
        queryKey: ['categories', page, search, settings.catalog_items_per_page],
        queryFn: async () => {
            const queryParams: Record<string, string | number> = { page, per_page: settings.catalog_items_per_page }
            if (search) queryParams.search = search
            const res = await categoriesApi.list(queryParams)
            return res.data
        },
    })

    const paginated = data?.data
    const items: Category[] = paginated?.data ?? []
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
                {t('categories.loadError')}
            </div>
        )
    }

    // Category icons/emojis based on dewey code ranges
    const getCategoryIcon = (deweyCode: string) => {
        const code = parseInt(deweyCode, 10)
        if (code < 100) return '💻'
        if (code < 200) return '🧠'
        if (code < 300) return '🕌'
        if (code < 400) return '🌍'
        if (code < 500) return '🗣️'
        if (code < 600) return '🔬'
        if (code < 700) return '⚙️'
        if (code < 800) return '🎨'
        if (code < 900) return '📖'
        return '🗺️'
    }

    const getColor = (code: string) => {
        const colors = [
            ['#fff0f2'],
            ['#eff6ff'],
            ['#ecfdf5'],
            ['#fdf2f8'],
            ['#eef2ff'],
            ['#fefce8'],
            ['#faf5ff'],
            ['#fff7ed'],
        ]
        let hash = 0
        for (let i = 0; i < code.length; i++) hash = code.charCodeAt(i) + ((hash << 5) - hash)
        return colors[Math.abs(hash) % colors.length]
    }

    return (
        <div>
            {/* Header */}
            <div className="mb-8">
                <h1 className="text-2xl font-bold" style={{ color: 'var(--color-text)' }}>
                    {t('categories.title')}
                </h1>
            </div>

            {/* Categories Grid */}
            <div
                className="grid gap-4"
                style={{ gridTemplateColumns: 'repeat(auto-fill, minmax(260px, 1fr))' }}
            >
                {items.map((cat) => {
                    const [bgColor] = getColor(cat.dewey_code)
                    return (
                        <Link
                            key={cat._id}
                            to={`/categories/${cat._id}`}
                            className="category-card flex items-center gap-4"
                            style={{ textDecoration: 'none', color: 'inherit' }}
                        >
                            {/* Icon */}
                            <div
                                style={{
                                    width: 56,
                                    height: 56,
                                    borderRadius: 12,
                                    background: bgColor,
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'center',
                                    fontSize: 26,
                                    flexShrink: 0,
                                }}
                            >
                                {getCategoryIcon(cat.dewey_code)}
                            </div>

                            <div style={{ flex: 1, minWidth: 0 }}>
                                <div
                                    style={{
                                        fontSize: 16,
                                        fontWeight: 600,
                                        color: 'var(--color-text)',
                                        lineHeight: 1.4,
                                    }}
                                >
                                    {cat.subject_title}
                                </div>
                                <div
                                    style={{
                                        fontSize: 12,
                                        color: 'var(--color-text-muted)',
                                        marginTop: 4,
                                    }}
                                >
                                    Dewey: {cat.dewey_code}
                                </div>
                            </div>

                            <div style={{ fontSize: 18, color: 'var(--color-primary)', fontWeight: 500, flexShrink: 0 }}>
                                ←
                            </div>
                        </Link>
                    )
                })}
            </div>

            {items.length === 0 && (
                <div className="text-center py-20" style={{ color: 'var(--color-text-muted)' }}>
                    <div className="text-5xl mb-4">📂</div>
                    <p className="text-lg">{t('categories.noCategories')}</p>
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
