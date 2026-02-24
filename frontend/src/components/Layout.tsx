import { Link, Outlet } from 'react-router-dom'
import { useAuth } from '../contexts/AuthContext'

export function Layout() {
  const { user, userType, logout } = useAuth()

  return (
    <div className="min-h-screen bg-stone-50">
      <nav className="bg-amber-900 text-amber-50 shadow">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="flex justify-between h-14 items-center">
            <Link to="/" className="text-xl font-bold">
              Book Store
            </Link>
            <div className="flex gap-4 items-center">
              {userType === 'customer' && (
                <>
                  <Link to="/" className="hover:text-amber-200">Books</Link>
                  <Link to="/cart" className="hover:text-amber-200">Cart</Link>
                  <Link to="/orders" className="hover:text-amber-200">Orders</Link>
                </>
              )}
              {userType === 'employee' && (
                <>
                  <Link to="/admin" className="hover:text-amber-200">Admin</Link>
                </>
              )}
              {!user ? (
                <>
                  <Link to="/login" className="hover:text-amber-200">Login</Link>
                  <Link to="/register" className="hover:text-amber-200">Register</Link>
                </>
              ) : (
                <span className="flex items-center gap-2">
                  <span className="text-sm">{user.name}</span>
                  <button
                    onClick={logout}
                    className="px-3 py-1 rounded bg-amber-800 hover:bg-amber-700 text-sm"
                  >
                    Logout
                  </button>
                </span>
              )}
            </div>
          </div>
        </div>
      </nav>
      <main className="max-w-7xl mx-auto px-4 py-8">
        <Outlet />
      </main>
    </div>
  )
}
