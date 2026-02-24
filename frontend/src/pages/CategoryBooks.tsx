import { useQuery } from '@tanstack/react-query'
import { useParams, Link } from 'react-router-dom'
import { categories as categoriesApi, books as booksApi } from '../lib/api'
import { BookCard } from '../components/BookCard'
import type { Category, Book } from '../lib/api'

export function CategoryBooks() {
    const { id } = useParams<{ id: string }>()

    const { data: categoryData, isLoading: categoryLoading } = useQuery({
        queryKey: ['category', id],
        queryFn: async () => {
            const res = await categoriesApi.get(id!)
            return res.data
        },
        enabled: !!id,
    })

    const { data: booksData, isLoading: booksLoading } = useQuery({
        queryKey: ['books', 'category', id],
        queryFn: async () => {
            const res = await booksApi.list({ category_id: id! })
            return res.data
        },
        enabled: !!id,
    })

    const category: Category | undefined = categoryData?.data
    const paginated = booksData?.data
    const bookItems: Book[] = Array.isArray(paginated)
        ? paginated
        : (paginated?.data ?? [])

    const isLoading = categoryLoading || booksLoading

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

    // Category icons based on dewey code
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

    return (
        <div>
            {/* Breadcrumb */}
            <div style={{ marginBottom: 24, fontSize: 14, color: '#78716c' }}>
                <Link to="/" style={{ color: '#92400e', textDecoration: 'none' }}>
                    الرئيسية
                </Link>
                {' '}
                /{' '}
                <Link to="/categories" style={{ color: '#92400e', textDecoration: 'none' }}>
                    التصنيفات
                </Link>
                {' '}
                / {category?.subject_title}
            </div>

            {/* Category Header */}
            {category && (
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
                    <div
                        style={{
                            width: 72,
                            height: 72,
                            borderRadius: 16,
                            background: '#fef3c7',
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'center',
                            fontSize: 32,
                            flexShrink: 0,
                        }}
                    >
                        {getCategoryIcon(category.dewey_code)}
                    </div>

                    <div>
                        <h1 style={{ fontSize: 24, fontWeight: 700, color: '#292524', margin: 0 }}>
                            {category.subject_title}
                        </h1>
                        <div
                            style={{
                                marginTop: 8,
                                fontSize: 13,
                                color: '#92400e',
                                fontWeight: 500,
                                background: '#fef3c7',
                                display: 'inline-block',
                                padding: '3px 10px',
                                borderRadius: 4,
                            }}
                        >
                            رمز ديوي: {category.dewey_code}
                        </div>
                        <p style={{ fontSize: 13, color: '#a8a29e', marginTop: 8 }}>
                            {bookItems.length} {bookItems.length === 1 ? 'كتاب' : 'كتب'}
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
                            coverImage={book.cover_image_thumb || book.cover_image}
                            authorName={book.authors?.map((a) => a.name).join('، ')}
                            discountPercent={20}
                        />
                    ))}
                </div>
            ) : (
                <div style={{ textAlign: 'center', padding: '60px 0', color: '#78716c' }}>
                    <div style={{ fontSize: 48, marginBottom: 16 }}>📚</div>
                    <p style={{ fontSize: 16 }}>لا توجد كتب في هذا التصنيف حالياً.</p>
                </div>
            )}
        </div>
    )
}
