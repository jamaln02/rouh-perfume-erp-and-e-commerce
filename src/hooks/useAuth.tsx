import { useState, useEffect, createContext, useContext } from "react";

type AuthUser = {
  id: string;
  email: string;
  phone?: string | null;
  name?: string | null;
  role?: "admin" | "manager" | "employee" | "customer";
};

type AuthSession = {
  token: string;
};

type AuthError = {
  message: string;
};

interface AuthContextType {
  user: AuthUser | null;
  session: AuthSession | null;
  loading: boolean;
  isAdmin: boolean;
  permissions: string[];
  signUp: (email: string, password: string, fullName: string, phone: string) => Promise<{ error: AuthError | null }>;
  signIn: (identifier: string, password: string) => Promise<{ error: AuthError | null }>;
  signOut: () => Promise<void>;
}

const AuthContext = createContext<AuthContextType | undefined>(undefined);

export const AuthProvider = ({ children }: { children: React.ReactNode }) => {
  const apiBaseUrl = String(import.meta.env.VITE_API_URL || "");
  const [user, setUser] = useState<AuthUser | null>(null);
  const [session, setSession] = useState<AuthSession | null>(null);
  const [loading, setLoading] = useState(true);
  const [isAdmin, setIsAdmin] = useState(false);
  const [permissions, setPermissions] = useState<string[]>([]);

  const resetAuthState = () => {
    window.localStorage.removeItem("rouh_auth_token");
    window.sessionStorage.removeItem("rouh_auth_token");
    setSession(null);
    setUser(null);
    setIsAdmin(false);
    setPermissions([]);
  };

  useEffect(() => {
    const bootstrap = async () => {
      try {
        const response = await fetch(`${apiBaseUrl}/api/auth/me`, {
          headers: { Accept: "application/json" },
          credentials: "include",
        });

        if (response.status === 401) {
          resetAuthState();
          setLoading(false);
          return;
        }

        const data = await response.json();
        if (!response.ok || !data?.ok || !data?.user) {
          resetAuthState();
        } else {
          setSession({ token: "" });
          setUser(data.user as AuthUser);
          setIsAdmin(Boolean(data?.isAdmin));
          setPermissions(Array.isArray(data?.permissions) ? data.permissions : []);
        }
      } catch {
        // Keep the current UI state intact on transient network failure.
      } finally {
        setLoading(false);
      }
    };

    void bootstrap();
  }, [apiBaseUrl]);

  const signUp = async (email: string, password: string, fullName: string, phone: string) => {
    try {
      const response = await fetch(`${apiBaseUrl}/api/auth/register`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
        },
        credentials: "include",
        body: JSON.stringify({
          name: fullName,
          email,
          password,
          phone,
        }),
      });

      const data = await response.json();
      if (!response.ok || !data?.ok || !data?.user) {
        return { error: { message: data?.message || "Registration failed" } };
      }

      const nextUser = data.user as AuthUser;
      setSession({ token: "" });
      setUser(nextUser);
      setIsAdmin(Boolean(data?.isAdmin));
      setPermissions(Array.isArray(data?.permissions) ? data.permissions : []);
      return { error: null };
    } catch (error: unknown) {
      return { error: { message: error instanceof Error ? error.message : String(error) } };
    }
  };

  const signIn = async (identifier: string, password: string) => {
    try {
      const response = await fetch(`${apiBaseUrl}/api/auth/login`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
        },
        credentials: "include",
        body: JSON.stringify({ identifier, password }),
      });

      const data = await response.json();
      if (!response.ok || !data?.ok || !data?.user) {
        return { error: { message: data?.message || "Login failed" } };
      }

      const nextUser = data.user as AuthUser;
      setSession({ token: "" });
      setUser(nextUser);
      setIsAdmin(Boolean(data?.isAdmin));
      setPermissions(Array.isArray(data?.permissions) ? data.permissions : []);
      return { error: null };
    } catch (error: unknown) {
      return { error: { message: error instanceof Error ? error.message : String(error) } };
    }
  };

  const signOut = async () => {
    try {
      await fetch(`${apiBaseUrl}/api/auth/logout`, {
        method: "POST",
        headers: { Accept: "application/json" },
        credentials: "include",
      });
    } catch {
      // Ignore network failures on logout; local state is still cleared.
    }

    resetAuthState();
  };

  return (
    <AuthContext.Provider value={{ user, session, loading, isAdmin, permissions, signUp, signIn, signOut }}>
      {children}
    </AuthContext.Provider>
  );
};

export const useAuth = () => {
  const context = useContext(AuthContext);
  if (!context) throw new Error("useAuth must be used within AuthProvider");
  return context;
};
