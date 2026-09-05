import { Suspense } from 'react'
import { Link, Outlet, useLocation } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { useAuth } from '../contexts/AuthContext'
import { SearchBox } from './SearchBox'

export function Layout() {
  const { user, userType, logout } = useAuth()
  const { t, i18n } = useTranslation()
  const location = useLocation()
  const isHomePage = location.pathname === '/'
  const isBooksPage = location.pathname === '/books' || location.pathname.startsWith('/books/')
  const isDirectSales = userType === 'employee' && user?.role === 'direct_sales'
  const useWideHeader = isHomePage || location.pathname === '/books'

  const toggleLang = () => {
    i18n.changeLanguage(i18n.language === 'ar' ? 'en' : 'ar')
  }

  const navLinks = (
    <>
      <Link to="/books" className="text-sm py-2 font-medium hover:opacity-80" style={{ color: 'var(--color-text)' }}>{t('nav.books')}</Link>
      <Link to="/authors" className="text-sm py-2 font-medium hover:opacity-80" style={{ color: 'var(--color-text)' }}>{t('nav.authors')}</Link>
      <Link to="/categories" className="text-sm py-2 font-medium hover:opacity-80" style={{ color: 'var(--color-text)' }}>{t('nav.categories')}</Link>
      <Link to="/publishers" className="text-sm py-2 font-medium hover:opacity-80" style={{ color: 'var(--color-text)' }}>{t('nav.publishers')}</Link>
      <Link to="/warehouses" className="text-sm py-2 font-medium hover:opacity-80" style={{ color: 'var(--color-text)' }}>{t('nav.warehouses')}</Link>
      {userType === 'customer' && (
        <>
          <Link to="/account" className="text-sm py-2 font-medium hover:opacity-80" style={{ color: 'var(--color-primary)' }}>{t('nav.myAccount')}</Link>
          <Link to="/orders" className="text-sm py-2 font-medium hover:opacity-80" style={{ color: 'var(--color-text)' }}>{t('nav.orders')}</Link>
        </>
      )}
      {userType === 'employee' && (
        <Link to="/admin" className="text-sm py-2 font-medium hover:opacity-80" style={{ color: 'var(--color-primary)' }}>{t('nav.admin')}</Link>
      )}
    </>
  )

  const userBlock = (
    <div className="flex items-center gap-3 shrink-0">
      {!user ? (
        <>
          <Link to="/login" className="text-sm" style={{ color: 'var(--color-text)' }}>
            {t('nav.login')}
          </Link>
          <Link to="/register" className="header-cta">
            {t('nav.register')}
          </Link>
        </>
      ) : (
        <span className="flex items-center gap-2">
          <span
            className="w-9 h-9 rounded-full flex items-center justify-center text-white text-sm font-bold"
            style={{ background: 'var(--color-primary)' }}
            aria-hidden
          >
            {(user.name || '?').charAt(0).toUpperCase()}
          </span>
          <span className="hidden sm:flex flex-col leading-tight">
            <span className="text-sm font-semibold" style={{ color: 'var(--color-primary)' }}>{user.name}</span>
            {user.email && (
              <span className="text-xs" style={{ color: 'var(--color-text-muted)' }}>{user.email}</span>
            )}
          </span>
          <button
            onClick={logout}
            className="text-sm px-3 py-1.5 rounded-lg font-medium"
            style={{ background: 'var(--color-primary-light)', color: 'var(--color-primary)' }}
          >
            {t('nav.logout')}
          </button>
        </span>
      )}
      {userType === 'customer' && (
        <Link to="/cart" className="header-cart-link" aria-label={t('nav.cart')}>
          <span aria-hidden>🛒</span>
          <span className="hidden sm:inline">{t('nav.cart')}</span>
        </Link>
      )}
      <button
        onClick={toggleLang}
        title={t('common.switchLanguage')}
        className="px-2 py-1 rounded text-xs font-bold border"
        style={{ borderColor: 'var(--color-border)', color: 'var(--color-text-muted)' }}
      >
        {i18n.language === 'ar' ? 'EN' : 'عربي'}
      </button>
    </div>
  )

  return (
    <div className="min-h-screen" style={{ backgroundColor: 'var(--color-bg)' }} dir={i18n.dir()}>
      <header className="top-bar print:hidden">
        <div className="max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-8">
          {isDirectSales ? (
            <div className="flex justify-between items-center h-16 gap-4">
              <Link to="/admin/pos" className="text-xl font-bold shrink-0" style={{ color: 'var(--color-primary)' }}>
                {t('nav.bookStore')}
              </Link>
              <nav className="flex gap-4 items-center shrink-0">
                <Link to="/admin/pos" className="text-sm font-medium hover:opacity-80" style={{ color: 'var(--color-text)' }}>
                  {t('admin.posTerminal')}
                </Link>
                <Link to="/admin/pos/reports" className="text-sm font-medium hover:opacity-80" style={{ color: 'var(--color-text)' }}>
                  {t('admin.posReports')}
                </Link>
                {user && (
                  <span className="flex items-center gap-2">
                    <span className="text-sm font-medium" style={{ color: 'var(--color-text)' }}>{user.name}</span>
                    <button onClick={logout} className="px-3 py-1.5 rounded-lg text-sm font-medium" style={{ background: 'var(--color-primary-light)', color: 'var(--color-primary)' }}>
                      {t('nav.logout')}
                    </button>
                  </span>
                )}
                <button onClick={toggleLang} title={t('common.switchLanguage')} className="px-2 py-1 rounded text-xs font-bold border" style={{ borderColor: 'var(--color-border)', color: 'var(--color-text-muted)' }}>
                  {i18n.language === 'ar' ? 'EN' : 'عربي'}
                </button>
              </nav>
            </div>
          ) : useWideHeader ? (
            <>
              <div className="flex items-center gap-4 sm:gap-6 py-4">
                <Link
                  to="/"
                  className="text-xl sm:text-2xl font-bold shrink-0 flex items-center gap-2"
                  style={{ color: 'var(--color-primary)' }}
                >
                  <span
                    className="w-10 h-10 rounded-lg flex items-center justify-center text-white text-xl font-bold"
                    style={{ background: 'var(--color-primary)' }}
                  >
                    B
                  </span>
                  <span className="hidden xs:inline sm:inline">{t('nav.bookStore')}</span>
                </Link>
                <div className="flex-1 min-w-0 max-w-2xl">
                  <SearchBox
                    variant="home"
                    className="w-full"
                    homePlaceholder={t('home.searchPlaceholder')}
                  />
                </div>
                {userBlock}
              </div>
              <nav className="flex flex-wrap items-center gap-x-6 gap-y-1 py-2 border-t" style={{ borderColor: 'var(--color-border)' }}>
                {navLinks}
                <div className="ms-auto py-1">
                  <Link to="/books" className="header-cta">
                    {t('home.browseBooks')}
                  </Link>
                </div>
              </nav>
            </>
          ) : (
            <div className="flex justify-between items-center h-16 gap-4">
              <Link to="/" className="text-xl font-bold shrink-0" style={{ color: 'var(--color-primary)' }}>
                {t('nav.bookStore')}
              </Link>
              <SearchBox variant="nav" className="min-w-0 flex-1 max-w-[240px] sm:max-w-xs" />
              <nav className="flex gap-4 sm:gap-6 items-center shrink-0 flex-wrap justify-end">
                {navLinks}
                {!user ? (
                  <>
                    <Link to="/login" className="text-sm font-medium" style={{ color: 'var(--color-text)' }}>{t('nav.login')}</Link>
                    <Link to="/register" className="header-cta">{t('nav.register')}</Link>
                  </>
                ) : (
                  <span className="flex items-center gap-2">
                    <span className="text-sm font-medium" style={{ color: 'var(--color-text)' }}>{user.name}</span>
                    <button onClick={logout} className="px-3 py-1.5 rounded-lg text-sm font-medium" style={{ background: 'var(--color-primary-light)', color: 'var(--color-primary)' }}>
                      {t('nav.logout')}
                    </button>
                  </span>
                )}
                {userType === 'customer' && (
                  <Link to="/cart" className="header-cart-link">{t('nav.cart')}</Link>
                )}
                <button onClick={toggleLang} title={t('common.switchLanguage')} className="px-2 py-1 rounded text-xs font-bold border" style={{ borderColor: 'var(--color-border)', color: 'var(--color-text-muted)' }}>
                  {i18n.language === 'ar' ? 'EN' : 'عربي'}
                </button>
              </nav>
            </div>
          )}
        </div>
      </header>

      <main className={`${isHomePage ? '' : isBooksPage && location.pathname === '/books' ? 'max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-8 py-8' : 'max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8'} print:max-w-none print:mx-0 print:px-0 print:py-0`}>
        <Suspense
          fallback={
            <div className="py-16 text-center text-stone-600 text-sm">{t('common.loading')}</div>
          }
        >
          <Outlet />
        </Suspense>
      </main>

      <footer className="site-footer print:hidden">
        <div className="max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-8">
          <div className="flex flex-col sm:flex-row justify-between items-center gap-4">
            <div className="text-sm">© {new Date().getFullYear()} {t('nav.bookStore')}</div>
            <div className="flex gap-6 items-center">
              <Link to="/books">{t('nav.books')}</Link>
              <Link to="/authors">{t('nav.authors')}</Link>
              <Link to="/categories">{t('nav.categories')}</Link>
              <Link to="/publishers">{t('nav.publishers')}</Link>
              <Link to="/warehouses">{t('nav.warehouses')}</Link>
            </div>
          </div>
        </div>
      </footer>
    </div>
  )
}
