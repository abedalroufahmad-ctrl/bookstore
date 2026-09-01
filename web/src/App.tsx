import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { AuthProvider, useAuth } from './contexts/AuthContext'
import { Layout } from './components/Layout'
import {
  Login,
  Register,
  BookList,
  HomePage,
  BookDetail,
  CartPage,
  Checkout,
  Orders,
  OrderDetail,
  CustomerAccount,
  AdminDashboard,
  AdminBooks,
  AdminBookForm,
  AdminOrders,
  AdminEmployees,
  AdminCustomers,
  AdminAuthors,
  AdminAuthorForm,
  AdminCategories,
  AdminPublishers,
  AdminWarehouses,
  AuthorList,
  AuthorBooks,
  CategoryList,
  CategoryBooks,
  WarehouseList,
  WarehouseBooks,
  PublisherList,
  PublisherBooks,
  AdminSettings,
  PublisherSettings,
  AdminCountries,
  AdminReportsBooksWithoutCover,
  AdminWarehouseBrowse,
  AdminWarehouseBooksAdmin,
} from './route-pages'
import { SettingsProvider } from './contexts/SettingsContext'

const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      staleTime: 60_000,
      refetchOnWindowFocus: false,
    },
  },
})

import { AdminPos } from './pages/AdminPos'
import { AdminPosReports } from './pages/AdminPosReports'
import { AdminPosInvoice } from './pages/AdminPosInvoice'

function AdminRoute({ children }: { children: React.ReactNode }) {
  const { userType, isLoading } = useAuth()
  const { t } = useTranslation()
  if (isLoading) return <div className="py-12 text-center">{t('common.loading')}</div>
  if (userType !== 'employee') return <Navigate to="/login" replace />
  return <>{children}</>
}

function CustomerRoute({ children }: { children: React.ReactNode }) {
  const { userType, isLoading } = useAuth()
  const { t } = useTranslation()
  if (isLoading) return <div className="py-12 text-center">{t('common.loading')}</div>
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
        <Route path="warehouses" element={<WarehouseList />} />
        <Route path="warehouses/:id" element={<WarehouseBooks />} />
        <Route path="publishers" element={<PublisherList />} />
        <Route path="publishers/:id" element={<PublisherBooks />} />
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
          path="admin/publishers"
          element={
            <AdminRoute>
              <AdminPublishers />
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
          path="admin/warehouse-books"
          element={
            <AdminRoute>
              <AdminWarehouseBrowse />
            </AdminRoute>
          }
        />
        <Route
          path="admin/warehouse-books/:id"
          element={
            <AdminRoute>
              <AdminWarehouseBooksAdmin />
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
          path="admin/publisher-settings"
          element={
            <AdminRoute>
              <PublisherSettings />
            </AdminRoute>
          }
        />
        <Route
          path="admin/publishers/:id/settings"
          element={
            <AdminRoute>
              <PublisherSettings />
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
        <Route
          path="admin/pos"
          element={
            <AdminRoute>
              <AdminPos />
            </AdminRoute>
          }
        />
        <Route
          path="admin/pos/reports"
          element={
            <AdminRoute>
              <AdminPosReports />
            </AdminRoute>
          }
        />
        <Route
          path="admin/pos/invoices/:id"
          element={
            <AdminRoute>
              <AdminPosInvoice />
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
