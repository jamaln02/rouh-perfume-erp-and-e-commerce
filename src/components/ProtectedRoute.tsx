import type { JSX } from "react";
import { Navigate, useLocation } from "react-router-dom";
import { useAuth } from "@/hooks/useAuth";

/**
 * ProtectedRoute — central front-end access-control component.
 *
 * It enforces THREE layers of protection before rendering any protected subtree:
 *
 *   1. AUTH LOADING STATE — while the auth bootstrap (`/api/auth/me`) is in
 *      flight we show a spinner instead of redirecting. This prevents a
 *      race condition where a logged-in admin is bounced to /auth because
 *      `user` is still null on first render.
 *
 *   2. AUTHENTICATION — if there is no authenticated user, redirect to the
 *      login page (`/auth`). The current location is preserved in router
 *      state so login can send the user back to where they were going.
 *
 *   3. AUTHORIZATION — optional role + permission checks:
 *        - `requireAdmin` (default true): the user must be a staff member
 *          (admin / manager / employee). Non-staff customers are sent home.
 *        - `requiredPermission`: when provided, the user's permission list
 *          must include it. If not, redirect to `fallbackPath` (default
 *          "/admin/orders" for staff or "/" for non-staff).
 *
 * NOTE (defence in depth): the front-end is NEVER the sole authority. Every
 * protected back-end route is also guarded by the `auth.token` middleware
 * (and `admin.token` / `permission` where applicable) so a user who tampers
 * with client state still cannot access data they are not allowed to see.
 */
interface ProtectedRouteProps {
  children: JSX.Element;
  requireAdmin?: boolean;
  requiredPermission?: string;
  fallbackPath?: string;
  redirectTo?: string;
}

const ProtectedRoute = ({
  children,
  requireAdmin = true,
  requiredPermission,
  fallbackPath,
  redirectTo = "/auth",
}: ProtectedRouteProps) => {
  const { user, loading, isAdmin, permissions } = useAuth();
  const location = useLocation();

  // 1) Wait for the auth bootstrap to finish before deciding.
  if (loading) {
    return (
      <div className="min-h-screen flex items-center justify-center">
        <div className="animate-spin h-8 w-8 border-2 border-primary border-t-transparent rounded-full" />
      </div>
    );
  }

  // 2) Not authenticated → go to login, remember where we came from.
  if (!user) {
    return <Navigate to={redirectTo} replace state={{ from: location }} />;
  }

  // 3) Authorization: admin/staff gate.
  if (requireAdmin && !isAdmin) {
    return <Navigate to="/" replace />;
  }

  // 4) Authorization: specific permission gate.
  if (requiredPermission && !permissions.includes(requiredPermission)) {
    const fallback = fallbackPath ?? (isAdmin ? "/admin/orders" : "/");
    return <Navigate to={fallback} replace />;
  }

  return children;
};

export default ProtectedRoute;
