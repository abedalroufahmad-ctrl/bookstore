import { useQuery } from '@tanstack/react-query'
import { Link } from 'react-router-dom'
import { books as booksApi, authors as authorsApi, categories as categoriesApi } from '../lib/api'
import { BookCarousel } from '../components/BookCarousel'
import type { Book, Author, Category } from '../lib/api'

export function HomePage() {
    const { data, isLoading, error } = useQuery({
        queryKey: ['books'],
        queryFn: async () => {
            const res = await booksApi.list()
            return res.data
        },
    })

    const { data: authorsData } = useQuery({
        queryKey: ['authors'],
        queryFn: async () => {
            const res = await authorsApi.list()
            return res.data
        },
    })

    const { data: categoriesData } = useQuery({
        queryKey: ['categories'],
        queryFn: async () => {
            const res = await categoriesApi.list()
            return res.data
        },
    })

    const paginated = data?.data
    const items: Book[] = Array.isArray(paginated)
        ? paginated
        : (paginated?.data ?? [])

    const authorItems: Author[] = authorsData?.data?.data ?? []
    const categoryItems: Category[] = categoriesData?.data?.data ?? []

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
                جاري التحميل...
            </div>
        )
    }

    if (error) {
        return (
            <div style={{ textAlign: 'center', padding: '60px 0', color: '#dc2626' }}>
                فشل تحميل الكتب. يرجى المحاولة لاحقاً.
            </div>
        )
    }

    const featuredBooks = items.slice(0, 10)
    const newestBooks = [...items].reverse().slice(0, 10)

    // Author helpers
    const getAuthorColor = (name: string) => {
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

    // Category helpers
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

    const getCategoryColor = (code: string) => {
        const colors = [
            '#fef3c7', '#dbeafe', '#d1fae5', '#fce7f3',
            '#e0e7ff', '#fef9c3', '#f3e8ff', '#ffedd5',
            '#ecfccb', '#cffafe',
        ]
        let hash = 0
        for (let i = 0; i < code.length; i++) hash = code.charCodeAt(i) + ((hash << 5) - hash)
        return colors[Math.abs(hash) % colors.length]
    }

    return (
        <div>
            {/* Hero Banner */}
            <div className="hero-banner">
                <h1 className="hero-title">مرحباً بك في متجر الكتب</h1>
                <p className="hero-subtitle">
                    اكتشف أفضل الكتب العربية بأسعار مخفضة. خصومات تصل إلى 20% على جميع
                    الكتب!
                </p>
                <Link to="/books" className="hero-cta" style={{ textDecoration: 'none' }}>
                    تصفح جميع الكتب
                </Link>
            </div>

            {/* Featured Books Carousel */}
            <BookCarousel
                title="عروض مميزة"
                books={featuredBooks}
                discountPercent={20}
            />

            {/* Categories Section */}
            {categoryItems.length > 0 && (
                <div style={{ marginBottom: 40 }}>
                    <div className="section-header">
                        <h2 className="section-title">التصنيفات</h2>
                        <Link to="/categories" className="section-link">
                            عرض الكل ←
                        </Link>
                    </div>

                    <div
                        className="carousel-scroll"
                        style={{
                            display: 'flex',
                            gap: 16,
                            overflowX: 'auto',
                            padding: '8px 4px',
                        }}
                    >
                        {categoryItems.slice(0, 12).map((cat) => (
                            <Link
                                key={cat._id}
                                to={`/categories/${cat._id}`}
                                style={{
                                    textDecoration: 'none',
                                    display: 'flex',
                                    flexDirection: 'column',
                                    alignItems: 'center',
                                    padding: '20px 16px',
                                    background: '#fff',
                                    borderRadius: 12,
                                    border: '1px solid #e7e5e4',
                                    transition: 'all 0.2s ease',
                                    cursor: 'pointer',
                                    flex: '0 0 auto',
                                    width: 150,
                                }}
                                onMouseEnter={(e) => {
                                    e.currentTarget.style.transform = 'translateY(-4px)'
                                    e.currentTarget.style.boxShadow = '0 6px 16px rgba(0,0,0,0.08)'
                                }}
                                onMouseLeave={(e) => {
                                    e.currentTarget.style.transform = 'translateY(0)'
                                    e.currentTarget.style.boxShadow = 'none'
                                }}
                            >
                                <div
                                    style={{
                                        width: 52,
                                        height: 52,
                                        borderRadius: 12,
                                        background: getCategoryColor(cat.dewey_code),
                                        display: 'flex',
                                        alignItems: 'center',
                                        justifyContent: 'center',
                                        fontSize: 24,
                                        marginBottom: 10,
                                    }}
                                >
                                    {getCategoryIcon(cat.dewey_code)}
                                </div>
                                <div
                                    style={{
                                        fontSize: 13,
                                        fontWeight: 600,
                                        color: '#292524',
                                        textAlign: 'center',
                                        lineHeight: 1.4,
                                        overflow: 'hidden',
                                        textOverflow: 'ellipsis',
                                        whiteSpace: 'nowrap',
                                        width: '100%',
                                    }}
                                >
                                    {cat.subject_title}
                                </div>
                            </Link>
                        ))}
                    </div>
                </div>
            )}

            {/* Authors Section */}
            {authorItems.length > 0 && (
                <div style={{ marginBottom: 40 }}>
                    <div className="section-header">
                        <h2 className="section-title">المؤلفون</h2>
                        <Link to="/authors" className="section-link">
                            عرض الكل ←
                        </Link>
                    </div>

                    <div
                        className="carousel-scroll"
                        style={{
                            display: 'flex',
                            gap: 16,
                            overflowX: 'auto',
                            padding: '8px 4px',
                        }}
                    >
                        {authorItems.slice(0, 12).map((author) => (
                            <Link
                                key={author._id}
                                to={`/authors/${author._id}`}
                                style={{
                                    textDecoration: 'none',
                                    display: 'flex',
                                    flexDirection: 'column',
                                    alignItems: 'center',
                                    padding: '20px 16px',
                                    background: '#fff',
                                    borderRadius: 12,
                                    border: '1px solid #e7e5e4',
                                    transition: 'all 0.2s ease',
                                    cursor: 'pointer',
                                    flex: '0 0 auto',
                                    width: 140,
                                }}
                                onMouseEnter={(e) => {
                                    e.currentTarget.style.transform = 'translateY(-4px)'
                                    e.currentTarget.style.boxShadow = '0 6px 16px rgba(0,0,0,0.08)'
                                }}
                                onMouseLeave={(e) => {
                                    e.currentTarget.style.transform = 'translateY(0)'
                                    e.currentTarget.style.boxShadow = 'none'
                                }}
                            >
                                <div
                                    style={{
                                        width: 56,
                                        height: 56,
                                        borderRadius: '50%',
                                        background: getAuthorColor(author.name),
                                        color: '#fff',
                                        display: 'flex',
                                        alignItems: 'center',
                                        justifyContent: 'center',
                                        fontSize: 18,
                                        fontWeight: 700,
                                        marginBottom: 10,
                                    }}
                                >
                                    {getInitials(author.name)}
                                </div>
                                <div
                                    style={{
                                        fontSize: 13,
                                        fontWeight: 600,
                                        color: '#292524',
                                        textAlign: 'center',
                                        lineHeight: 1.4,
                                        overflow: 'hidden',
                                        textOverflow: 'ellipsis',
                                        whiteSpace: 'nowrap',
                                        width: '100%',
                                    }}
                                >
                                    {author.name}
                                </div>
                            </Link>
                        ))}
                    </div>
                </div>
            )}

            {/* Newest Books Carousel */}
            {newestBooks.length > 0 && (
                <BookCarousel
                    title="أحدث الكتب"
                    books={newestBooks}
                    discountPercent={20}
                />
            )}

            {/* Empty state */}
            {items.length === 0 && (
                <div style={{ textAlign: 'center', padding: '60px 0', color: '#78716c' }}>
                    <div style={{ fontSize: 48, marginBottom: 16 }}>📚</div>
                    <p style={{ fontSize: 16 }}>لا توجد كتب حالياً. أضف بعض الكتب من لوحة الإدارة.</p>
                </div>
            )}
        </div>
    )
}
