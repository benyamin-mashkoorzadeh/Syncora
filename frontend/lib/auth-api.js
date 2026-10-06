import { apiRequest } from "@/lib/api-client";

export const authApi = {
  register(payload) {
    return apiRequest("/auth/register", {
      method: "POST",
      body: JSON.stringify(payload),
    });
  },

  login(payload) {
    return apiRequest("/auth/login", {
      method: "POST",
      body: JSON.stringify(payload),
    });
  },

  demoLogin() {
    return apiRequest("/auth/demo", { method: "POST" });
  },

  logout() {
    return apiRequest("/auth/logout", { method: "POST" });
  },

  currentUser({ signal } = {}) {
    return apiRequest("/auth/user", { signal });
  },

  updateAvatar(avatar) {
    const body = new FormData();
    body.append("avatar", avatar);

    return apiRequest("/auth/user/avatar", { method: "POST", body });
  },

  removeAvatar() {
    return apiRequest("/auth/user/avatar", { method: "DELETE" });
  },

  forgotPassword(email) {
    return apiRequest("/auth/forgot-password", {
      method: "POST",
      body: JSON.stringify({ email }),
    });
  },

  resetPassword(payload) {
    return apiRequest("/auth/reset-password", {
      method: "POST",
      body: JSON.stringify(payload),
    });
  },

  resendVerification() {
    return apiRequest("/auth/email/verification-notification", {
      method: "POST",
    });
  },

  verifyEmail({ id, hash, expires, signature }) {
    const query = new URLSearchParams({ expires, signature });

    return apiRequest(
      `/auth/email/verify/${encodeURIComponent(id)}/${encodeURIComponent(hash)}?${query}`,
    );
  },
};
