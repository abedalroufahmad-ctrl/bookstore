import { useQuery } from '@tanstack/react-query'
import { Link } from 'react-router-dom'
import { categories as categoriesApi } from '../lib/api'
import type { Category } from '../lib/api'

export function CategoryList() {
    const { data, isLoading, error } = useQuery({
        queryKey: ['categories'],
        queryFn: async () => {
            const res = await categoriesApi.list()
            return res.data
        },
    })

    const items: Category[] = data?.data?.data ?? []

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
                فشل تحميل التصنيفات. يرجى المحاولة لاحقاً.
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
            ['#fef3c7', '#92400e'],
            ['#dbeafe', '#1d4ed8'],
            ['#d1fae5', '#065f46'],
            ['#fce7f3', '#9d174d'],
            ['#e0e7ff', '#4338ca'],
            ['#fef9c3', '#854d0e'],
            ['#f3e8ff', '#6d28d9'],
            ['#ffedd5', '#9a3412'],
            ['#ecfccb', '#3f6212'],
            ['#cffafe', '#0e7490'],
        ]
        let hash = 0
        for (let i = 0; i < code.length; i++) hash = code.charCodeAt(i) + ((hash << 5) - hash)
        return colors[Math.abs(hash) % colors.length]
    }

    return (
        <div>
            {/* Header */}
            <div style={{ marginBottom: 32 }}>
                <h1 style={{ fontSize: 28, fontWeight: 700, color: '#292524', marginBottom: 8 }}>
                    التصنيفات
                </h1>
                <p style={{ fontSize: 15, color: '#78716c' }}>
                    تصفح الكتب حسب التصنيف
                </p>
            </div>

            {/* Categories Grid */}
            <div
                style={{
                    display: 'grid',
                    gridTemplateColumns: 'repeat(auto-fill, minmax(240px, 1fr))',
                    gap: 20,
                }}
            >
                {items.map((cat) => {
                    const [bgColor, textColor] = getColor(cat.dewey_code)
                    return (
                        <Link
                            key={cat._id}
                            to={`/categories/${cat._id}`}
                            style={{
                                textDecoration: 'none',
                                display: 'flex',
                                alignItems: 'center',
                                gap: 16,
                                padding: '20px 24px',
                                background: '#fff',
                                borderRadius: 12,
                                border: '1px solid #e7e5e4',
                                transition: 'all 0.2s ease',
                                cursor: 'pointer',
                            }}
                            onMouseEnter={(e) => {
                                e.currentTarget.style.transform = 'translateY(-4px)'
                                e.currentTarget.style.boxShadow = '0 8px 20px rgba(0,0,0,0.08)'
                            }}
                            onMouseLeave={(e) => {
                                e.currentTarget.style.transform = 'translateY(0)'
                                e.currentTarget.style.boxShadow = 'none'
                            }}
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
                                        color: '#292524',
                                        lineHeight: 1.4,
                                    }}
                                >
                                    {cat.subject_title}
                                </div>
                                <div
                                    style={{
                                        fontSize: 12,
                                        color: textColor,
                                        fontWeight: 500,
                                        marginTop: 4,
                                        background: bgColor,
                                        display: 'inline-block',
                                        padding: '2px 8px',
                                        borderRadius: 4,
                                    }}
                                >
                                    {cat.dewey_code}
                                </div>
                            </div>

                            <div style={{ fontSize: 13, color: '#92400e', fontWeight: 500, flexShrink: 0 }}>
                                ←
                            </div>
                        </Link>
                    )
                })}
            </div>

            {items.length === 0 && (
                <div style={{ textAlign: 'center', padding: '60px 0', color: '#78716c' }}>
                    <div style={{ fontSize: 48, marginBottom: 16 }}>📂</div>
                    <p style={{ fontSize: 16 }}>لا توجد تصنيفات حالياً.</p>
                </div>
            )}
        </div>
    )
}
