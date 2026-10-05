import { apiRequest } from "@/lib/api-client";

export const notificationApi = {
  list({ page = 1, signal } = {}) {
    return apiRequest(`/notifications?page=${page}`, { signal });
  },

  markRead(notificationId) {
    return apiRequest(`/notifications/${notificationId}/read`, { method: "PATCH" });
  },

  markAllRead() {
    return apiRequest("/notifications/read-all", { method: "PATCH" });
  },
};
