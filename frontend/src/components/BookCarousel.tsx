import { useRef } from 'react'
import { BookCard } from './BookCard'
import type { Book } from '../lib/api'

interface BookCarouselProps {
    title: string
    books: Book[]
    discountPercent?: number
    showAllLink?: string
}

export function BookCarousel({
    title,
    books,
    discountPercent = 20,
    showAllLink,
}: BookCarouselProps) {
    const scrollRef = useRef<HTMLDivElement>(null)

    const scroll = (direction: 'left' | 'right') => {
        if (!scrollRef.current) return
        const scrollAmount = 600
        // In RTL, scroll directions are inverted
        const delta = direction === 'left' ? scrollAmount : -scrollAmount
        scrollRef.current.scrollBy({ left: delta, behavior: 'smooth' })
    }

    if (books.length === 0) return null

    return (
        <div style={{ marginBottom: 40 }}>
            <div className="section-header">
                <h2 className="section-title">{title}</h2>
                {showAllLink && (
                    <a href={showAllLink} className="section-link">
                        عرض المزيد ←
                    </a>
                )}
            </div>

            <div style={{ position: 'relative' }}>
                {/* Right arrow (scrolls forward in RTL) */}
                <button
                    className="carousel-arrow carousel-arrow-left"
                    onClick={() => scroll('left')}
                    aria-label="التالي"
                >
                    ‹
                </button>

                <div
                    ref={scrollRef}
                    className="carousel-scroll"
                    style={{
                        display: 'flex',
                        gap: 20,
                        overflowX: 'auto',
                        scrollSnapType: 'x mandatory',
                        padding: '8px 4px',
                    }}
                >
                    {books.map((book) => {
                        const authorName = book.authors?.map((a) => a.name).join('، ') || ''
                        return (
                            <BookCard
                                key={book._id}
                                id={book._id}
                                title={book.title}
                                price={book.price}
                                coverImage={book.cover_image_thumb || book.cover_image}
                                authorName={authorName}
                                discountPercent={discountPercent}
                            />
                        )
                    })}
                </div>

                {/* Left arrow (scrolls backward in RTL) */}
                <button
                    className="carousel-arrow carousel-arrow-right"
                    onClick={() => scroll('right')}
                    aria-label="السابق"
                >
                    ›
                </button>
            </div>
        </div>
    )
}
