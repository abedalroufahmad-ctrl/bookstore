import { Link } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { useAuth } from '../contexts/AuthContext'

export function AdminDashboard() {
  const { t } = useTranslation()
  const { user, userType } = useAuth()
  const employeeRole = userType === 'employee' ? (user as { role?: string } | null)?.role : undefined
  const isManager = employeeRole === 'manager'
  const isWarehouseManager = employeeRole === 'warehouse_manager'
  const isPublisherManager = employeeRole === 'publisher_manager'
  const isScopedWarehouseUser = isWarehouseManager || (!isManager && userType === 'employee')

  return (
    <div>
      <h1 className="text-2xl font-bold text-amber-900 mb-6">{t('admin.dashboard')}</h1>
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        {(!isScopedWarehouseUser || isWarehouseManager || isPublisherManager) && (
          <Link
            to="/admin/books"
            className="block p-6 bg-white rounded-lg border border-stone-200 hover:shadow-md hover:border-amber-300 transition"
          >
            <h2 className="font-semibold text-amber-900">{t('admin.books')}</h2>
            <p className="text-sm text-stone-500 mt-1">{t('admin.manageCatalog')}</p>
          </Link>
        )}
        {(!isScopedWarehouseUser || isPublisherManager) && (
          <Link
            to="/admin/authors"
            className="block p-6 bg-white rounded-lg border border-stone-200 hover:shadow-md hover:border-amber-300 transition"
          >
            <h2 className="font-semibold text-amber-900">{t('admin.authors')}</h2>
            <p className="text-sm text-stone-500 mt-1">{t('admin.addManageAuthors')}</p>
          </Link>
        )}
        {!isScopedWarehouseUser && (
          <Link
            to="/admin/categories"
            className="block p-6 bg-white rounded-lg border border-stone-200 hover:shadow-md hover:border-amber-300 transition"
          >
            <h2 className="font-semibold text-amber-900">{t('admin.categories')}</h2>
            <p className="text-sm text-stone-500 mt-1">{t('admin.addManageCategories')}</p>
          </Link>
        )}
        {!isScopedWarehouseUser && (
          <Link
            to="/admin/publishers"
            className="block p-6 bg-white rounded-lg border border-stone-200 hover:shadow-md hover:border-amber-300 transition"
          >
            <h2 className="font-semibold text-amber-900">{t('admin.publishers') ?? 'Publishers'}</h2>
            <p className="text-sm text-stone-500 mt-1">{t('admin.managePublishers') ?? 'Add, edit, or delete publishers'}</p>
          </Link>
        )}
        {(!isScopedWarehouseUser || isWarehouseManager || isPublisherManager) && (
          <Link
            to="/admin/warehouse-books"
            className="block p-6 bg-white rounded-lg border border-stone-200 hover:shadow-md hover:border-amber-300 transition"
          >
            <h2 className="font-semibold text-amber-900">{t('admin.warehouseBooksTitle')}</h2>
            <p className="text-sm text-stone-500 mt-1">{t('admin.warehouseBooksHint')}</p>
          </Link>
        )}
        {(!isScopedWarehouseUser || isWarehouseManager || isPublisherManager) && (
          <Link
            to="/admin/warehouses"
            className="block p-6 bg-white rounded-lg border border-stone-200 hover:shadow-md hover:border-amber-300 transition"
          >
            <h2 className="font-semibold text-amber-900">{t('admin.warehouses')}</h2>
            <p className="text-sm text-stone-500 mt-1">{t('admin.manageWarehouses')}</p>
          </Link>
        )}
        {!isScopedWarehouseUser && (
          <Link
            to="/admin/countries"
            className="block p-6 bg-white rounded-lg border border-stone-200 hover:shadow-md hover:border-amber-300 transition"
          >
            <h2 className="font-semibold text-amber-900">{t('admin.countries')}</h2>
            <p className="text-sm text-stone-500 mt-1">{t('admin.manageCountries')}</p>
          </Link>
        )}
        {(!isPublisherManager) && (
        <Link
          to="/admin/orders"
          className="block p-6 bg-white rounded-lg border border-stone-200 hover:shadow-md hover:border-amber-300 transition"
        >
          <h2 className="font-semibold text-amber-900">{t('admin.orders')}</h2>
          <p className="text-sm text-stone-500 mt-1">{t('admin.viewManageOrders')}</p>
        </Link>
        )}
        {(!isScopedWarehouseUser || isWarehouseManager) && (
          <Link
            to="/admin/employees"
            className="block p-6 bg-white rounded-lg border border-stone-200 hover:shadow-md hover:border-amber-300 transition"
          >
            <h2 className="font-semibold text-amber-900">{t('admin.employees')}</h2>
            <p className="text-sm text-stone-500 mt-1">{t('admin.manageStaff')}</p>
          </Link>
        )}
        {!isScopedWarehouseUser && (
          <Link
            to="/admin/customers"
            className="block p-6 bg-white rounded-lg border border-stone-200 hover:shadow-md hover:border-amber-300 transition"
          >
            <h2 className="font-semibold text-amber-900">{t('admin.customers')}</h2>
            <p className="text-sm text-stone-500 mt-1">{t('admin.manageCustomers')}</p>
          </Link>
        )}
        <Link
          to="/admin/settings"
          className="block p-6 bg-white rounded-lg border border-stone-200 hover:shadow-md hover:border-amber-300 transition"
        >
          <h2 className="font-semibold text-amber-900">{t('admin.settingsLabel')}</h2>
          <p className="text-sm text-stone-500 mt-1">{t('admin.globalSettings')}</p>
        </Link>
        {!isScopedWarehouseUser && (
          <Link
            to="/admin/reports/books-without-cover"
            className="block p-6 bg-white rounded-lg border border-stone-200 hover:shadow-md hover:border-amber-300 transition"
          >
            <h2 className="font-semibold text-amber-900">{t('admin.reports.booksWithoutCover') ?? 'Books without cover'}</h2>
            <p className="text-sm text-stone-500 mt-1">{t('admin.reports.booksWithoutCoverHint') ?? 'List books missing cover image'}</p>
          </Link>
        )}
      </div>
    </div>
  )
}
