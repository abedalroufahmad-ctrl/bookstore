import { useQuery } from '@tanstack/react-query'
import { Link, Navigate } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { useAuth } from '../contexts/authContextCore'
import { admin } from '../lib/api'

type ReportCounts = {
  books: number
  authors: number
  warehouses: number
  categories: number
  orders: number
  orderRevenue: number
}

const REPORT_LINKS: { key: keyof ReportCounts; path: string }[] = [
  { key: 'books', path: '/admin/books' },
  { key: 'authors', path: '/admin/authors' },
  { key: 'warehouses', path: '/admin/warehouses' },
  { key: 'categories', path: '/admin/categories' },
  { key: 'orders', path: '/admin/orders' },
]

export function PublisherReports() {
  const { t } = useTranslation()
  const { user, userType } = useAuth()
  const isPublisherManager =
    userType === 'employee' &&
    (user as { role?: string } | null)?.role === 'publisher_manager'
  const publisherId = (user as { publisher_id?: string } | null)?.publisher_id

  const { data, isLoading, isError, refetch } = useQuery({
    queryKey: ['publisher-report-counts', publisherId],
    enabled: isPublisherManager && Boolean(publisherId),
    queryFn: async (): Promise<ReportCounts> => {
      const [books, authors, warehouses, categories, orders, revenue] = await Promise.all([
        admin.books.list({ per_page: 1 }),
        admin.authors.list({ per_page: 1 }),
        admin.warehouses.list({ per_page: 1 }),
        admin.categories.list({ per_page: 1 }),
        admin.orders.list({ per_page: 1 }),
        admin.publishers.orderTotals(String(publisherId), { period: 'all', per_page: 1 }),
      ])

      return {
        books: books.data.data.total,
        authors: authors.data.data.total,
        warehouses: warehouses.data.data.total,
        categories: categories.data.data.total,
        orders: orders.data.data.total,
        orderRevenue: revenue.data.data.total_sum,
      }
    },
  })

  if (userType === 'employee' && !isPublisherManager) {
    return <Navigate to="/admin" replace />
  }

  if (!isPublisherManager) {
    return <div className="py-12 text-center">{t('common.loading')}</div>
  }

  return (
    <div>
      <div className="flex items-center gap-4 mb-6">
        <Link to="/admin" className="text-navy-700 hover:underline text-sm">
          ← {t('admin.dashboard')}
        </Link>
      </div>
      <h1 className="text-2xl font-bold text-navy-900 mb-2">
        {t('admin.publisherReports')}
      </h1>
      <p className="text-slate-600 mb-6">{t('admin.publisherReportsDescription')}</p>

      {isError || !publisherId ? (
        <div className="rounded-lg border border-red-200 bg-red-50 p-5 text-center">
          <p role="alert" className="text-sm text-red-700 mb-3">
            {t('admin.publisherReportsFailed')}
          </p>
          <button
            type="button"
            onClick={() => void refetch()}
            className="rounded-lg bg-white px-4 py-2 text-sm font-medium text-red-700 border border-red-200 hover:bg-red-100"
          >
            {t('common.retry')}
          </button>
        </div>
      ) : (
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
          {REPORT_LINKS.map(({ key, path }) => (
            <Link
              key={key}
              to={path}
              className="block rounded-lg border border-slate-200 bg-white p-6 transition hover:border-navy-300 hover:shadow-md"
            >
              <div className="flex items-center justify-between gap-4">
                <h2 className="font-semibold text-navy-900">{t(`admin.${key}`)}</h2>
                <span className="text-2xl font-bold text-navy-800" aria-live="polite">
                  {isLoading ? '—' : data?.[key].toLocaleString()}
                </span>
              </div>
            </Link>
          ))}
          <Link
            to="/admin/orders"
            className="block rounded-lg border border-slate-200 bg-white p-6 transition hover:border-navy-300 hover:shadow-md"
          >
            <h2 className="font-semibold text-navy-900">{t('admin.publisherOrderRevenue')}</h2>
            <span className="mt-2 block text-2xl font-bold text-navy-800" aria-live="polite">
              {isLoading ? '—' : `$${Number(data?.orderRevenue ?? 0).toFixed(2)}`}
            </span>
          </Link>
        </div>
      )}
    </div>
  )
}
