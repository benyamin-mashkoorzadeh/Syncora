import { apiRequest } from "@/lib/api-client";

function projectPath(workspaceSlug, projectSlug = null) {
  const base = `/workspaces/${encodeURIComponent(workspaceSlug)}/projects`;
  return projectSlug ? `${base}/${encodeURIComponent(projectSlug)}` : base;
}

export const projectApi = {
  list(workspaceSlug, { page = 1, signal } = {}) {
    return apiRequest(`${projectPath(workspaceSlug)}?page=${page}`, { signal });
  },

  create(workspaceSlug, payload) {
    return apiRequest(projectPath(workspaceSlug), {
      method: "POST",
      body: JSON.stringify(payload),
    });
  },

  show(workspaceSlug, projectSlug, { signal } = {}) {
    return apiRequest(projectPath(workspaceSlug, projectSlug), { signal });
  },

  update(workspaceSlug, projectSlug, payload) {
    return apiRequest(projectPath(workspaceSlug, projectSlug), {
      method: "PATCH",
      body: JSON.stringify(payload),
    });
  },

  sprints(workspaceSlug, projectSlug, { page = 1, signal } = {}) {
    return apiRequest(`${projectPath(workspaceSlug, projectSlug)}/sprints?page=${page}`, { signal });
  },

  createSprint(workspaceSlug, projectSlug, payload) {
    return apiRequest(`${projectPath(workspaceSlug, projectSlug)}/sprints`, {
      method: "POST",
      body: JSON.stringify(payload),
    });
  },

  updateSprint(workspaceSlug, projectSlug, sprintId, payload) {
    return apiRequest(`${projectPath(workspaceSlug, projectSlug)}/sprints/${sprintId}`, {
      method: "PATCH",
      body: JSON.stringify(payload),
    });
  },
};
