import { useQuery } from '@tanstack/react-query'
import { Link } from 'react-router-dom'
import { authors as authorsApi } from '../lib/api'
import type { Author } from '../lib/api'

export function AuthorList() {
    const { data, isLoading, error } = useQuery({
        queryKey: ['authors'],
        queryFn: async () => {
            const res = await authorsApi.list()
            return res.data
        },
    })

    const items: Author[] = data?.data?.data ?? []

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
                فشل تحميل المؤلفين. يرجى المحاولة لاحقاً.
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
            <div style={{ marginBottom: 32 }}>
                <h1 style={{ fontSize: 28, fontWeight: 700, color: '#292524', marginBottom: 8 }}>
                    المؤلفون
                </h1>
                <p style={{ fontSize: 15, color: '#78716c' }}>
                    تصفح مؤلفينا واكتشف كتبهم
                </p>
            </div>

            {/* Authors Grid */}
            <div
                style={{
                    display: 'grid',
                    gridTemplateColumns: 'repeat(auto-fill, minmax(220px, 1fr))',
                    gap: 20,
                }}
            >
                {items.map((author) => (
                    <Link
                        key={author._id}
                        to={`/authors/${author._id}`}
                        style={{
                            textDecoration: 'none',
                            display: 'flex',
                            flexDirection: 'column',
                            alignItems: 'center',
                            padding: '28px 20px',
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
                        {/* Avatar */}
                        <div
                            style={{
                                width: 72,
                                height: 72,
                                borderRadius: '50%',
                                background: getColor(author.name),
                                color: '#fff',
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'center',
                                fontSize: 22,
                                fontWeight: 700,
                                marginBottom: 14,
                                flexShrink: 0,
                            }}
                        >
                            {getInitials(author.name)}
                        </div>

                        {/* Name */}
                        <div
                            style={{
                                fontSize: 16,
                                fontWeight: 600,
                                color: '#292524',
                                textAlign: 'center',
                                lineHeight: 1.4,
                            }}
                        >
                            {author.name}
                        </div>

                        {/* CTA */}
                        <div
                            style={{
                                marginTop: 12,
                                fontSize: 13,
                                color: '#92400e',
                                fontWeight: 500,
                            }}
                        >
                            عرض الكتب ←
                        </div>
                    </Link>
                ))}
            </div>

            {items.length === 0 && (
                <div style={{ textAlign: 'center', padding: '60px 0', color: '#78716c' }}>
                    <div style={{ fontSize: 48, marginBottom: 16 }}>✍️</div>
                    <p style={{ fontSize: 16 }}>لا يوجد مؤلفون حالياً.</p>
                </div>
            )}
        </div>
    )
}
