import { apiRequest } from "@/lib/api-client";

export const workspaceApi = {
  list({ signal } = {}) {
    return apiRequest("/workspaces", { signal });
  },

  create(payload) {
    return apiRequest("/workspaces", {
      method: "POST",
      body: JSON.stringify(payload),
    });
  },

  show(slug, { signal } = {}) {
    return apiRequest(`/workspaces/${encodeURIComponent(slug)}`, { signal });
  },

  update(slug, payload) {
    return apiRequest(`/workspaces/${encodeURIComponent(slug)}`, {
      method: "PATCH",
      body: JSON.stringify(payload),
    });
  },

  members(slug, { signal } = {}) {
    return apiRequest(`/workspaces/${encodeURIComponent(slug)}/members`, {
      signal,
    });
  },

  addMember(slug, email) {
    return apiRequest(`/workspaces/${encodeURIComponent(slug)}/members`, {
      method: "POST",
      body: JSON.stringify({ email }),
    });
  },

  removeMember(slug, membershipId) {
    return apiRequest(
      `/workspaces/${encodeURIComponent(slug)}/members/${membershipId}`,
      { method: "DELETE" },
    );
  },
};
