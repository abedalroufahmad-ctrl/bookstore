import { useQuery } from '@tanstack/react-query'
import { Link } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { books as booksApi, categories as categoriesApi } from '../lib/api'
import { resolveCoverUrl } from '../lib/utils'
import { BookCarousel } from '../components/BookCarousel'
import { useSettings } from '../contexts/SettingsContext'
import type { Book, Category } from '../lib/api'

export function HomePage() {
    const { t } = useTranslation()
    const { settings } = useSettings()
    const { data, isLoading, error } = useQuery({
        queryKey: ['books', settings.catalog_items_per_page],
        queryFn: async () => {
            const res = await booksApi.list({ per_page: settings.catalog_items_per_page })
            return res.data
        },
    })

    const { data: categoriesData } = useQuery({
        queryKey: ['categories', settings.catalog_items_per_page],
        queryFn: async () => {
            const res = await categoriesApi.list({ per_page: settings.catalog_items_per_page })
            return res.data
        },
    })

    const paginated = data?.data
    const items: Book[] = Array.isArray(paginated)
        ? paginated
        : (paginated?.data ?? [])

    const categoryItems: Category[] = categoriesData?.data?.data ?? []

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
                {t('home.loading')}
            </div>
        )
    }

    if (error) {
        return (
            <div className="text-center py-20" style={{ color: 'var(--color-discount)' }}>
                {t('home.loadError')}
            </div>
        )
    }

    const featuredBooks = items.slice(0, 10)
    const newestBooks = [...items].reverse().slice(0, 10)
    const youMightLike = items.slice(0, 8)

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

    const getCategoryBg = (code: string) => {
        const colors = ['#eef2ff', '#fdf2f8', '#ecfdf5', '#fff7ed', '#fefce8', '#faf5ff']
        let hash = 0
        for (let i = 0; i < code.length; i++) hash = code.charCodeAt(i) + ((hash << 5) - hash)
        return colors[Math.abs(hash) % colors.length]
    }

    return (
        <div className="home-three-column">
            {/* Left sidebar - Categories (right in RTL) */}
            <aside className="home-sidebar home-sidebar-categories">
                <div className="flex items-center gap-2 mb-4">
                    <span className="text-lg">☰</span>
                    <h3 className="font-bold text-lg" style={{ color: 'var(--color-text)' }}>
                        {t('home.allCategories')}
                    </h3>
                </div>
                <p className="text-sm mb-4" style={{ color: 'var(--color-text-muted)' }}>
                    {t('home.categoriesCount', { count: categoryItems.length })}
                </p>
                <ul className="space-y-1">
                    {categoryItems.slice(0, 15).map((cat) => (
                        <li key={cat._id}>
                            <Link
                                to={`/categories/${cat._id}`}
                                className="flex items-center gap-2 py-2 px-2 rounded hover:bg-[var(--color-primary-light)] transition-colors"
                                style={{ textDecoration: 'none', color: 'var(--color-text)' }}
                            >
                                <span style={{ fontSize: 18 }}>{getCategoryIcon(cat.dewey_code)}</span>
                                <span className="text-sm truncate">{cat.subject_title}</span>
                            </Link>
                        </li>
                    ))}
                </ul>
                <Link
                    to="/categories"
                    className="block mt-4 text-sm font-medium"
                    style={{ color: 'var(--color-primary)' }}
                >
                    {t('home.moreCategories')} →
                </Link>
            </aside>

            {/* Center - Hero + Content */}
            <main className="home-center">
                {/* Hero Banner - blue/purple gradient like image */}
                <div className="hero-banner-mic">
                    <div className="hero-banner-mic-content">
                        <h1 className="hero-banner-mic-title">{t('home.heroTitle')}</h1>
                        <ul className="hero-banner-mic-bullets">
                            <li>{t('home.heroBullet1')}</li>
                            <li>{t('home.heroBullet2')}</li>
                            <li>{t('home.heroBullet3')}</li>
                        </ul>
                        <Link
                            to="/books"
                            className="hero-banner-mic-cta"
                            style={{ textDecoration: 'none' }}
                        >
                            {t('home.heroCta')}
                        </Link>
                    </div>
                </div>

                {/* Feature cards */}
                <div className="home-feature-cards">
                    <Link
                        to="/books"
                        className="home-feature-card"
                        style={{ textDecoration: 'none', color: 'inherit' }}
                    >
                        <div className="home-feature-card-icon">📚</div>
                        <div className="home-feature-card-title">{t('home.browseBooks')}</div>
                    </Link>
                    <Link
                        to="/authors"
                        className="home-feature-card"
                        style={{ textDecoration: 'none', color: 'inherit' }}
                    >
                        <div className="home-feature-card-icon">✍️</div>
                        <div className="home-feature-card-title">{t('home.topAuthors')}</div>
                    </Link>
                    <Link
                        to="/categories"
                        className="home-feature-card"
                        style={{ textDecoration: 'none', color: 'inherit' }}
                    >
                        <div className="home-feature-card-icon">📂</div>
                        <div className="home-feature-card-title">{t('home.categories')}</div>
                    </Link>
                </div>

                {/* Featured Books */}
                <div className="home-section">
                    <BookCarousel
                        title={t('home.featuredBooks')}
                        books={featuredBooks}
                        globalDiscount={settings.global_discount}
                    />
                </div>

                {/* Newest Books */}
                <div className="home-section">
                    <BookCarousel
                        title={t('home.newestBooks')}
                        books={newestBooks}
                        globalDiscount={settings.global_discount}
                    />
                </div>

                {items.length === 0 && (
                    <div className="text-center py-20" style={{ color: 'var(--color-text-muted)' }}>
                        <div className="text-5xl mb-4">📚</div>
                        <p className="text-lg">{t('home.noBooks')}</p>
                    </div>
                )}
            </main>

            {/* Right sidebar - You might like (left in RTL) */}
            <aside className="home-sidebar home-sidebar-you-might-like">
                <h3 className="font-bold text-lg mb-4" style={{ color: 'var(--color-text)' }}>
                    {t('home.youMightLike')}
                </h3>
                <div className="space-y-3 overflow-y-auto" style={{ maxHeight: 480 }}>
                    {youMightLike.map((book) => (
                        <Link
                            key={book._id}
                            to={`/books/${book._id}`}
                            className="flex gap-3 p-2 rounded-lg hover:bg-[var(--color-primary-light)] transition-colors"
                            style={{ textDecoration: 'none', color: 'inherit' }}
                        >
                            <div
                                className="shrink-0 rounded overflow-hidden"
                                style={{
                                    width: 56,
                                    height: 80,
                                    background: '#f0f0f0',
                                }}
                            >
                                {(book.cover_image_thumb || book.cover_image) ? (
                                    <img
                                        src={resolveCoverUrl(book.cover_image_thumb || book.cover_image)}
                                        alt={book.title}
                                        className="w-full h-full object-cover"
                                        onError={(e) => {
                                            e.currentTarget.style.display = 'none'
                                            const next = e.currentTarget.nextElementSibling as HTMLElement
                                            if (next) next.style.display = 'flex'
                                        }}
                                    />
                                ) : null}
                                <div
                                    className="w-full h-full flex items-center justify-center text-xs text-center p-1"
                                    style={{
                                        display: (book.cover_image_thumb || book.cover_image) ? 'none' : 'flex',
                                        background: getCategoryBg(book.category_id || '0'),
                                        color: 'var(--color-text-muted)',
                                    }}
                                >
                                    {book.title?.slice(0, 2) || '📖'}
                                </div>
                            </div>
                            <div className="min-w-0 flex-1">
                                <div
                                    className="text-sm font-medium truncate"
                                    style={{ color: 'var(--color-text)' }}
                                >
                                    {book.title}
                                </div>
                                <div className="text-xs mt-0.5 font-medium" style={{ color: 'var(--color-primary)' }}>
                                    ${book.price?.toFixed(2) ?? '—'}
                                </div>
                            </div>
                        </Link>
                    ))}
                </div>
            </aside>
        </div>
    )
}
