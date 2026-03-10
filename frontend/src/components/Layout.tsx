import { Link, Outlet, useLocation } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { useAuth } from '../contexts/AuthContext'
import { SearchBox } from './SearchBox'

export function Layout() {
  const { user, userType, logout } = useAuth()
  const { t, i18n } = useTranslation()
  const location = useLocation()
  const isHomePage = location.pathname === '/'

  const toggleLang = () => {
    i18n.changeLanguage(i18n.language === 'ar' ? 'en' : 'ar')
  }

  return (
    <div className="min-h-screen" style={{ backgroundColor: 'var(--color-bg)' }} dir={i18n.dir()}>
      {/* Top bar - Made-in-China style */}
      <header className="top-bar">
        <div className="max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-8">
          {/* Home page: large search bar layout */}
          {isHomePage ? (
            <>
              <div className="flex items-center gap-6 py-4">
                <Link
                  to="/"
                  className="text-2xl font-bold shrink-0 flex items-center gap-2"
                  style={{ color: 'var(--color-primary)' }}
                >
                  <span className="w-10 h-10 rounded flex items-center justify-center text-white text-xl font-bold" style={{ background: 'var(--color-primary)' }}>B</span>
                  {t('nav.bookStore')}
                </Link>
                <div className="flex-1 min-w-0 max-w-2xl">
                  <SearchBox variant="home" className="w-full" homePlaceholder={t('home.searchPlaceholder')} />
                </div>
                <div className="flex items-center gap-4 shrink-0">
                  {!user ? (
                    <>
                      <Link to="/login" className="text-sm" style={{ color: 'var(--color-text)' }}>
                        {t('nav.login')}
                      </Link>
                      <span className="text-stone-300">/</span>
                      <Link to="/register" className="text-sm font-medium" style={{ color: 'var(--color-primary)' }}>
                        {t('nav.register')}
                      </Link>
                    </>
                  ) : (
                    <span className="flex items-center gap-2">
                      <span className="text-sm font-medium" style={{ color: 'var(--color-text)' }}>{user.name}</span>
                      <button onClick={logout} className="text-sm px-3 py-1.5 rounded-lg font-medium" style={{ background: 'var(--color-primary-light)', color: 'var(--color-primary)' }}>
                        {t('nav.logout')}
                      </button>
                    </span>
                  )}
                  {userType === 'customer' && (
                    <Link to="/cart" className="text-sm" style={{ color: 'var(--color-text)' }}>
                      🛒 {t('nav.cart')}
                    </Link>
                  )}
                  <button
                    onClick={toggleLang}
                    className="text-sm px-2 py-1 rounded border"
                    style={{ borderColor: 'var(--color-border)', color: 'var(--color-text-muted)' }}
                  >
                    {i18n.language === 'ar' ? 'EN' : 'عربي'}
                  </button>
                </div>
              </div>
              <nav className="flex gap-6 py-2 border-t" style={{ borderColor: 'var(--color-border)' }}>
                <Link to="/books" className="text-sm py-2" style={{ color: 'var(--color-text)' }}>{t('nav.books')}</Link>
                <Link to="/authors" className="text-sm py-2" style={{ color: 'var(--color-text)' }}>{t('nav.authors')}</Link>
                <Link to="/categories" className="text-sm py-2" style={{ color: 'var(--color-text)' }}>{t('nav.categories')}</Link>
                {userType === 'customer' && (
                  <>
                    <Link to="/cart" className="text-sm py-2" style={{ color: 'var(--color-text)' }}>{t('nav.cart')}</Link>
                    <Link to="/orders" className="text-sm py-2" style={{ color: 'var(--color-text)' }}>{t('nav.orders')}</Link>
                  </>
                )}
                {userType === 'employee' && (
                  <Link to="/admin" className="text-sm py-2 font-medium" style={{ color: 'var(--color-primary)' }}>{t('nav.admin')}</Link>
                )}
              </nav>
            </>
          ) : (
            <div className="flex justify-between items-center h-16 gap-4">
              <Link to="/" className="text-xl font-bold shrink-0" style={{ color: 'var(--color-primary)' }}>
                {t('nav.bookStore')}
              </Link>
              <SearchBox variant="nav" className="min-w-0 flex-1 max-w-[240px] sm:max-w-xs" />
              <nav className="flex gap-6 items-center shrink-0">
                <Link to="/books" className="text-sm font-medium hover:opacity-80" style={{ color: 'var(--color-text)' }}>{t('nav.books')}</Link>
                <Link to="/authors" className="text-sm font-medium hover:opacity-80" style={{ color: 'var(--color-text)' }}>{t('nav.authors')}</Link>
                <Link to="/categories" className="text-sm font-medium hover:opacity-80" style={{ color: 'var(--color-text)' }}>{t('nav.categories')}</Link>
                {userType === 'customer' && (
                  <>
                    <Link to="/cart" className="text-sm font-medium hover:opacity-80" style={{ color: 'var(--color-text)' }}>{t('nav.cart')}</Link>
                    <Link to="/orders" className="text-sm font-medium hover:opacity-80" style={{ color: 'var(--color-text)' }}>{t('nav.orders')}</Link>
                  </>
                )}
                {userType === 'employee' && (
                  <Link to="/admin" className="text-sm font-medium hover:opacity-80" style={{ color: 'var(--color-primary)' }}>{t('nav.admin')}</Link>
                )}
                {!user ? (
                  <>
                    <Link to="/login" className="text-sm font-medium px-4 py-2 rounded-lg" style={{ color: 'var(--color-text)' }}>{t('nav.login')}</Link>
                    <Link to="/register" className="text-sm font-medium px-4 py-2 rounded-lg text-white" style={{ background: 'var(--color-primary)' }}>{t('nav.register')}</Link>
                  </>
                ) : (
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
          )}
        </div>
      </header>

      <main className={isHomePage ? '' : 'max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8'}>
        <Outlet />
      </main>

      <footer className="site-footer">
        <div className="max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-8">
          <div className="flex flex-col sm:flex-row justify-between items-center gap-4">
            <div className="text-sm">© {new Date().getFullYear()} {t('nav.bookStore')}</div>
            <div className="flex gap-6 items-center">
              <Link to="/books">{t('nav.books')}</Link>
              <Link to="/authors">{t('nav.authors')}</Link>
              <Link to="/categories">{t('nav.categories')}</Link>
            </div>
          </div>
        </div>
      </footer>
    </div>
  )
}
