import { apiRequest } from "@/lib/api-client";

function messagesPath(workspaceSlug, projectSlug) {
  return `/workspaces/${encodeURIComponent(workspaceSlug)}/projects/${encodeURIComponent(projectSlug)}/chat/messages`;
}

export const chatApi = {
  list(workspaceSlug, projectSlug, { page = 1, signal } = {}) {
    return apiRequest(`${messagesPath(workspaceSlug, projectSlug)}?page=${page}`, { signal });
  },

  send(workspaceSlug, projectSlug, body) {
    return apiRequest(messagesPath(workspaceSlug, projectSlug), {
      method: "POST",
      body: JSON.stringify({ body }),
    });
  },
};
