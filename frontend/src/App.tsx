import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AuthProvider, useAuth } from './contexts/AuthContext'
import { Layout } from './components/Layout'
import { Login } from './pages/Login'
import { Register } from './pages/Register'
import { BookList } from './pages/BookList'
import { HomePage } from './pages/HomePage'
import { BookDetail } from './pages/BookDetail'
import { CartPage } from './pages/Cart'
import { Checkout } from './pages/Checkout'
import { Orders } from './pages/Orders'
import { OrderDetail } from './pages/OrderDetail'
import { CustomerAccount } from './pages/CustomerAccount'
import { AdminDashboard } from './pages/AdminDashboard'
import { AdminBooks } from './pages/AdminBooks'
import { AdminBookForm } from './pages/AdminBookForm'
import { AdminOrders } from './pages/AdminOrders'
import { AdminEmployees } from './pages/AdminEmployees'
import { AdminCustomers } from './pages/AdminCustomers'
import { AdminAuthors } from './pages/AdminAuthors'
import { AdminAuthorForm } from './pages/AdminAuthorForm'
import { AdminCategories } from './pages/AdminCategories'
import { AdminWarehouses } from './pages/AdminWarehouses'
import { AuthorList } from './pages/AuthorList'
import { AuthorBooks } from './pages/AuthorBooks'
import { CategoryList } from './pages/CategoryList'
import { CategoryBooks } from './pages/CategoryBooks'
import { AdminSettings } from './pages/AdminSettings'
import { AdminCountries } from './pages/AdminCountries'
import { AdminReportsBooksWithoutCover } from './pages/AdminReportsBooksWithoutCover'
import { SettingsProvider } from './contexts/SettingsContext'

const queryClient = new QueryClient()

function AdminRoute({ children }: { children: React.ReactNode }) {
  const { userType, isLoading } = useAuth()
  if (isLoading) return <div className="py-12 text-center">Loading...</div>
  if (userType !== 'employee') return <Navigate to="/login" replace />
  return <>{children}</>
}

function CustomerRoute({ children }: { children: React.ReactNode }) {
  const { userType, isLoading } = useAuth()
  if (isLoading) return <div className="py-12 text-center">Loading...</div>
  if (userType !== 'customer') return <Navigate to="/login" replace />
  return <>{children}</>
}

function AppRoutes() {
  return (
    <Routes>
      <Route path="/" element={<Layout />}>
        <Route index element={<HomePage />} />
        <Route path="books" element={<BookList />} />
        <Route path="books/:id" element={<BookDetail />} />
        <Route path="authors" element={<AuthorList />} />
        <Route path="authors/:id" element={<AuthorBooks />} />
        <Route path="categories" element={<CategoryList />} />
        <Route path="categories/:id" element={<CategoryBooks />} />
        <Route path="login" element={<Login />} />
        <Route path="register" element={<Register />} />
        <Route
          path="cart"
          element={
            <CustomerRoute>
              <CartPage />
            </CustomerRoute>
          }
        />
        <Route
          path="checkout"
          element={
            <CustomerRoute>
              <Checkout />
            </CustomerRoute>
          }
        />
        <Route
          path="orders"
          element={
            <CustomerRoute>
              <Orders />
            </CustomerRoute>
          }
        />
        <Route
          path="orders/:id"
          element={
            <CustomerRoute>
              <OrderDetail />
            </CustomerRoute>
          }
        />
        <Route
          path="account"
          element={
            <CustomerRoute>
              <CustomerAccount />
            </CustomerRoute>
          }
        />
        <Route
          path="admin"
          element={
            <AdminRoute>
              <AdminDashboard />
            </AdminRoute>
          }
        />
        <Route
          path="admin/books"
          element={
            <AdminRoute>
              <AdminBooks />
            </AdminRoute>
          }
        />
        <Route
          path="admin/books/new"
          element={
            <AdminRoute>
              <AdminBookForm />
            </AdminRoute>
          }
        />
        <Route
          path="admin/books/:id/edit"
          element={
            <AdminRoute>
              <AdminBookForm />
            </AdminRoute>
          }
        />
        <Route
          path="admin/orders"
          element={
            <AdminRoute>
              <AdminOrders />
            </AdminRoute>
          }
        />
        <Route
          path="admin/employees"
          element={
            <AdminRoute>
              <AdminEmployees />
            </AdminRoute>
          }
        />
        <Route
          path="admin/customers"
          element={
            <AdminRoute>
              <AdminCustomers />
            </AdminRoute>
          }
        />
        <Route
          path="admin/authors"
          element={
            <AdminRoute>
              <AdminAuthors />
            </AdminRoute>
          }
        />
        <Route
          path="admin/authors/new"
          element={
            <AdminRoute>
              <AdminAuthorForm />
            </AdminRoute>
          }
        />
        <Route
          path="admin/authors/:id/edit"
          element={
            <AdminRoute>
              <AdminAuthorForm />
            </AdminRoute>
          }
        />
        <Route
          path="admin/categories"
          element={
            <AdminRoute>
              <AdminCategories />
            </AdminRoute>
          }
        />
        <Route
          path="admin/warehouses"
          element={
            <AdminRoute>
              <AdminWarehouses />
            </AdminRoute>
          }
        />
        <Route
          path="admin/settings"
          element={
            <AdminRoute>
              <AdminSettings />
            </AdminRoute>
          }
        />
        <Route
          path="admin/reports/books-without-cover"
          element={
            <AdminRoute>
              <AdminReportsBooksWithoutCover />
            </AdminRoute>
          }
        />
        <Route
          path="admin/countries"
          element={
            <AdminRoute>
              <AdminCountries />
            </AdminRoute>
          }
        />
      </Route>
      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  )
}

export default function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <BrowserRouter>
        <AuthProvider>
          <SettingsProvider>
            <AppRoutes />
          </SettingsProvider>
        </AuthProvider>
      </BrowserRouter>
    </QueryClientProvider>
  )
}
