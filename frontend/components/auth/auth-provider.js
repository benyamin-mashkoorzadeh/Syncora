"use client";

import { authApi } from "@/lib/auth-api";
import { ApiError } from "@/lib/api-client";
import { createContext, useCallback, useContext, useEffect, useMemo, useState } from "react";

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
  const [state, setState] = useState({
    status: "loading",
    user: null,
    error: null,
  });

  const refreshUser = useCallback(async ({ signal } = {}) => {
    try {
      const payload = await authApi.currentUser({ signal });
      setState({ status: "authenticated", user: payload.data, error: null });
      return payload.data;
    } catch (error) {
      if (error.name === "AbortError") {
        return null;
      }

      if (error instanceof ApiError && error.status === 401) {
        setState({ status: "unauthenticated", user: null, error: null });
        return null;
      }

      setState({
        status: "error",
        user: null,
        error: error.message,
      });
      return null;
    }
  }, []);

  useEffect(() => {
    const controller = new AbortController();

    authApi
      .currentUser({ signal: controller.signal })
      .then((payload) => {
        setState({ status: "authenticated", user: payload.data, error: null });
      })
      .catch((error) => {
        if (error.name === "AbortError") {
          return;
        }

        if (error instanceof ApiError && error.status === 401) {
          setState({ status: "unauthenticated", user: null, error: null });
          return;
        }

        setState({ status: "error", user: null, error: error.message });
      });

    return () => controller.abort();
  }, []);

  const value = useMemo(
    () => ({
      ...state,
      refreshUser,
      setAuthenticatedUser(user) {
        setState({ status: "authenticated", user, error: null });
      },
      clearUser() {
        setState({ status: "unauthenticated", user: null, error: null });
      },
    }),
    [refreshUser, state],
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth() {
  const context = useContext(AuthContext);

  if (!context) {
    throw new Error("useAuth must be used within AuthProvider.");
  }

  return context;
}
