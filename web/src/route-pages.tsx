/**
 * Lazily loaded page chunks for smaller initial bundles (Vite/Rollup).
 */
import { lazy } from 'react'

export const HomePage = lazy(() =>
  import('./pages/HomePage').then((m) => ({ default: m.HomePage })),
)
export const BookList = lazy(() =>
  import('./pages/BookList').then((m) => ({ default: m.BookList })),
)
export const BookDetail = lazy(() =>
  import('./pages/BookDetail').then((m) => ({ default: m.BookDetail })),
)
export const Login = lazy(() => import('./pages/Login').then((m) => ({ default: m.Login })))
export const Register = lazy(() =>
  import('./pages/Register').then((m) => ({ default: m.Register })),
)
export const CartPage = lazy(() => import('./pages/Cart').then((m) => ({ default: m.CartPage })))
export const Checkout = lazy(() =>
  import('./pages/Checkout').then((m) => ({ default: m.Checkout })),
)
export const Orders = lazy(() => import('./pages/Orders').then((m) => ({ default: m.Orders })))
export const OrderDetail = lazy(() =>
  import('./pages/OrderDetail').then((m) => ({ default: m.OrderDetail })),
)
export const CustomerAccount = lazy(() =>
  import('./pages/CustomerAccount').then((m) => ({ default: m.CustomerAccount })),
)
export const AdminDashboard = lazy(() =>
  import('./pages/AdminDashboard').then((m) => ({ default: m.AdminDashboard })),
)
export const AdminBooks = lazy(() =>
  import('./pages/AdminBooks').then((m) => ({ default: m.AdminBooks })),
)
export const AdminBookForm = lazy(() =>
  import('./pages/AdminBookForm').then((m) => ({ default: m.AdminBookForm })),
)
export const AdminOrders = lazy(() =>
  import('./pages/AdminOrders').then((m) => ({ default: m.AdminOrders })),
)
export const AdminEmployees = lazy(() =>
  import('./pages/AdminEmployees').then((m) => ({ default: m.AdminEmployees })),
)
export const AdminCustomers = lazy(() =>
  import('./pages/AdminCustomers').then((m) => ({ default: m.AdminCustomers })),
)
export const AdminAuthors = lazy(() =>
  import('./pages/AdminAuthors').then((m) => ({ default: m.AdminAuthors })),
)
export const AdminAuthorForm = lazy(() =>
  import('./pages/AdminAuthorForm').then((m) => ({ default: m.AdminAuthorForm })),
)
export const AdminCategories = lazy(() =>
  import('./pages/AdminCategories').then((m) => ({ default: m.AdminCategories })),
)
export const AdminPublishers = lazy(() =>
  import('./pages/AdminPublishers').then((m) => ({ default: m.AdminPublishers })),
)
export const AdminWarehouses = lazy(() =>
  import('./pages/AdminWarehouses').then((m) => ({ default: m.AdminWarehouses })),
)
export const AdminSettings = lazy(() =>
  import('./pages/AdminSettings').then((m) => ({ default: m.AdminSettings })),
)
export const AdminCountries = lazy(() =>
  import('./pages/AdminCountries').then((m) => ({ default: m.AdminCountries })),
)
export const AdminReportsBooksWithoutCover = lazy(() =>
  import('./pages/AdminReportsBooksWithoutCover').then((m) => ({
    default: m.AdminReportsBooksWithoutCover,
  })),
)
export const AdminWarehouseBrowse = lazy(() =>
  import('./pages/AdminWarehouseBrowse').then((m) => ({
    default: m.AdminWarehouseBrowse,
  })),
)
export const AdminWarehouseBooksAdmin = lazy(() =>
  import('./pages/AdminWarehouseBooksAdmin').then((m) => ({
    default: m.AdminWarehouseBooksAdmin,
  })),
)
export const AuthorList = lazy(() =>
  import('./pages/AuthorList').then((m) => ({ default: m.AuthorList })),
)
export const AuthorBooks = lazy(() =>
  import('./pages/AuthorBooks').then((m) => ({ default: m.AuthorBooks })),
)
export const CategoryList = lazy(() =>
  import('./pages/CategoryList').then((m) => ({ default: m.CategoryList })),
)
export const CategoryBooks = lazy(() =>
  import('./pages/CategoryBooks').then((m) => ({ default: m.CategoryBooks })),
)
export const WarehouseList = lazy(() =>
  import('./pages/WarehouseList').then((m) => ({ default: m.WarehouseList })),
)
export const WarehouseBooks = lazy(() =>
  import('./pages/WarehouseBooks').then((m) => ({ default: m.WarehouseBooks })),
)
