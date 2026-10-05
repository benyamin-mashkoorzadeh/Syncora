import { apiRequest } from "@/lib/api-client";

function taskPath(workspaceSlug, projectSlug, taskId = null) {
  const base = `/workspaces/${encodeURIComponent(workspaceSlug)}/projects/${encodeURIComponent(projectSlug)}/tasks`;
  return taskId === null ? base : `${base}/${taskId}`;
}

function pagePath(path, page) {
  return `${path}?page=${encodeURIComponent(page)}`;
}

export const taskApi = {
  list(workspaceSlug, projectSlug, { signal } = {}) {
    return apiRequest(taskPath(workspaceSlug, projectSlug), { signal });
  },

  create(workspaceSlug, projectSlug, payload) {
    return apiRequest(taskPath(workspaceSlug, projectSlug), {
      method: "POST",
      body: JSON.stringify(payload),
    });
  },

  show(workspaceSlug, projectSlug, taskId, { signal } = {}) {
    return apiRequest(taskPath(workspaceSlug, projectSlug, taskId), { signal });
  },

  update(workspaceSlug, projectSlug, taskId, payload) {
    return apiRequest(taskPath(workspaceSlug, projectSlug, taskId), {
      method: "PATCH",
      body: JSON.stringify(payload),
    });
  },

  comments(workspaceSlug, projectSlug, taskId, { page = 1, signal } = {}) {
    return apiRequest(pagePath(`${taskPath(workspaceSlug, projectSlug, taskId)}/comments`, page), { signal });
  },

  addComment(workspaceSlug, projectSlug, taskId, body) {
    return apiRequest(`${taskPath(workspaceSlug, projectSlug, taskId)}/comments`, {
      method: "POST",
      body: JSON.stringify({ body }),
    });
  },

  updateComment(workspaceSlug, projectSlug, taskId, commentId, body) {
    return apiRequest(`${taskPath(workspaceSlug, projectSlug, taskId)}/comments/${commentId}`, {
      method: "PATCH",
      body: JSON.stringify({ body }),
    });
  },

  deleteComment(workspaceSlug, projectSlug, taskId, commentId) {
    return apiRequest(`${taskPath(workspaceSlug, projectSlug, taskId)}/comments/${commentId}`, {
      method: "DELETE",
    });
  },

  activities(workspaceSlug, projectSlug, taskId, { page = 1, signal } = {}) {
    return apiRequest(pagePath(`${taskPath(workspaceSlug, projectSlug, taskId)}/activities`, page), { signal });
  },
};
