import { Link } from 'react-router-dom'

export function AdminDashboard() {
  return (
    <div>
      <h1 className="text-2xl font-bold text-amber-900 mb-6">Admin Dashboard</h1>
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <Link
          to="/admin/books"
          className="block p-6 bg-white rounded-lg border border-stone-200 hover:shadow-md hover:border-amber-300 transition"
        >
          <h2 className="font-semibold text-amber-900">Books</h2>
          <p className="text-sm text-stone-500 mt-1">Manage catalog</p>
        </Link>
        <Link
          to="/admin/authors"
          className="block p-6 bg-white rounded-lg border border-stone-200 hover:shadow-md hover:border-amber-300 transition"
        >
          <h2 className="font-semibold text-amber-900">Authors</h2>
          <p className="text-sm text-stone-500 mt-1">Add and manage authors</p>
        </Link>
        <Link
          to="/admin/categories"
          className="block p-6 bg-white rounded-lg border border-stone-200 hover:shadow-md hover:border-amber-300 transition"
        >
          <h2 className="font-semibold text-amber-900">Categories</h2>
          <p className="text-sm text-stone-500 mt-1">Add and manage categories</p>
        </Link>
        <Link
          to="/admin/orders"
          className="block p-6 bg-white rounded-lg border border-stone-200 hover:shadow-md hover:border-amber-300 transition"
        >
          <h2 className="font-semibold text-amber-900">Orders</h2>
          <p className="text-sm text-stone-500 mt-1">View and manage orders</p>
        </Link>
        <Link
          to="/admin/employees"
          className="block p-6 bg-white rounded-lg border border-stone-200 hover:shadow-md hover:border-amber-300 transition"
        >
          <h2 className="font-semibold text-amber-900">Employees</h2>
          <p className="text-sm text-stone-500 mt-1">Manage staff</p>
        </Link>
      </div>
    </div>
  )
}
