import { useQuery } from '@tanstack/react-query'
import { Link } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { books as booksApi, warehousesPublic as warehousesApi } from '../lib/api'
import { resolveCoverUrl } from '../lib/utils'
import { BookCarousel } from '../components/BookCarousel'
import { useSettings } from '../contexts/SettingsContext'
import type { Book, Warehouse } from '../lib/api'

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

    const { data: warehousesData } = useQuery({
        queryKey: ['warehouses-home', settings.catalog_items_per_page],
        queryFn: async () => {
            const res = await warehousesApi.list({ per_page: settings.catalog_items_per_page })
            return res.data
        },
    })

    const paginated = data?.data
    const items: Book[] = Array.isArray(paginated)
        ? paginated
        : (paginated?.data ?? [])

    const warehouseItems: Warehouse[] = warehousesData?.data?.data ?? []

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

    const mainTopics = [
        { code: '000', name: 'Information, Computers, Public Business', icon: '💻' },
        { code: '100', name: 'Philosophy, Psychology, Ideas', icon: '🧠' },
        { code: '200', name: 'Religion', icon: '🕌' },
        { code: '300', name: 'Social Sciences, Society', icon: '🌍' },
        { code: '400', name: 'Language', icon: '🗣️' },
        { code: '500', name: 'Natural Sciences, Mathematics', icon: '🔬' },
        { code: '600', name: 'Technology, Applied Sciences', icon: '⚙️' },
        { code: '700', name: 'Arts, Entertainment, Sports', icon: '🎨' },
        { code: '800', name: 'Literature', icon: '📖' },
        { code: '900', name: 'History, Geography', icon: '🗺️' },
    ]

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
                    10 main topics
                </p>
                <ul className="space-y-1">
                    {mainTopics.map((topic) => (
                        <li key={topic.code}>
                            <Link
                                to={`/categories#topic-${topic.code}`}
                                className="flex items-center gap-2 py-2 px-2 rounded hover:bg-[var(--color-primary-light)] transition-colors"
                                style={{ textDecoration: 'none', color: 'var(--color-text)' }}
                            >
                                <span style={{ fontSize: 18 }}>{topic.icon}</span>
                                <span className="text-sm truncate">{topic.name}</span>
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
                <div className="flex items-center gap-2 mt-6 mb-2">
                    <span className="text-lg">🏭</span>
                    <h3 className="font-bold text-lg" style={{ color: 'var(--color-text)' }}>
                        {t('nav.warehouses')}
                    </h3>
                </div>
                <ul className="space-y-1">
                    {warehouseItems.slice(0, 10).map((w) => (
                        <li key={w._id}>
                            <Link
                                to={`/warehouses/${w._id}`}
                                className="flex items-center gap-2 py-2 px-2 rounded hover:bg-[var(--color-primary-light)] transition-colors"
                                style={{ textDecoration: 'none', color: 'var(--color-text)' }}
                            >
                                <span style={{ fontSize: 16 }}>🏭</span>
                                <span className="text-sm truncate">{w.name}</span>
                            </Link>
                        </li>
                    ))}
                </ul>
                <Link
                    to="/warehouses"
                    className="block mt-3 text-sm font-medium"
                    style={{ color: 'var(--color-primary)' }}
                >
                    {t('home.viewAllWarehouses')} →
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
                    <Link
                        to="/warehouses"
                        className="home-feature-card"
                        style={{ textDecoration: 'none', color: 'inherit' }}
                    >
                        <div className="home-feature-card-icon">🏭</div>
                        <div className="home-feature-card-title">{t('nav.warehouses')}</div>
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
                                className="shrink-0 rounded overflow-hidden relative"
                                style={{ width: 56, height: 80, background: '#f0f0f0' }}
                            >
                                {(() => {
                                    const c = (book.cover_image_thumb || book.cover_image)?.trim()
                                    const valid = c && c.toLowerCase() !== 'null' && c.toLowerCase() !== 'undefined'
                                    return valid ? (
                                        <img
                                            src={resolveCoverUrl(c)}
                                            alt={book.title}
                                            className="w-full h-full object-cover absolute inset-0"
                                            onError={(e) => {
                                                e.currentTarget.style.display = 'none'
                                                const next = e.currentTarget.nextElementSibling as HTMLElement
                                                if (next) next.style.display = 'block'
                                            }}
                                        />
                                    ) : null
                                })()}
                                <img
                                    src="/favicon.png"
                                    alt=""
                                    className="w-full h-full object-cover absolute inset-0"
                                    style={{
                                        display: (book.cover_image_thumb || book.cover_image)?.trim() &&
                                            (book.cover_image_thumb || book.cover_image)?.toLowerCase() !== 'null' &&
                                            (book.cover_image_thumb || book.cover_image)?.toLowerCase() !== 'undefined'
                                            ? 'none'
                                            : 'block',
                                    }}
                                />
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
