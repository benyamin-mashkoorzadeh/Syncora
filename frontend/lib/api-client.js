export const backendUrl =
  process.env.NEXT_PUBLIC_BACKEND_URL ?? "http://localhost:8000";
const apiUrl =
  process.env.NEXT_PUBLIC_API_URL ?? `${backendUrl.replace(/\/$/, "")}/api/v1`;

export class ApiError extends Error {
  constructor(message, { status = 0, errors = {} } = {}) {
    super(message);
    this.name = "ApiError";
    this.status = status;
    this.errors = errors;
  }
}

let realtimeSocketIdProvider = null;

export function readCookie(name) {
  if (typeof document === "undefined") {
    return null;
  }

  const prefix = `${name}=`;
  const cookie = document.cookie
    .split(";")
    .map((value) => value.trim())
    .find((value) => value.startsWith(prefix));

  return cookie ? decodeURIComponent(cookie.slice(prefix.length)) : null;
}

export function registerRealtimeSocketIdProvider(provider) {
  realtimeSocketIdProvider = provider;
}

async function parseResponse(response) {
  if (response.status === 204) {
    return null;
  }

  const contentType = response.headers.get("content-type") ?? "";

  if (!contentType.includes("application/json")) {
    if (!response.ok) {
      throw new ApiError("Syncora could not complete that request.", {
        status: response.status,
      });
    }

    return null;
  }

  return response.json();
}

export async function initializeCsrf() {
  try {
    const response = await fetch(`${backendUrl}/sanctum/csrf-cookie`, {
      credentials: "include",
      headers: { Accept: "application/json" },
    });

    if (!response.ok) {
      throw new ApiError("Syncora could not establish a secure session.", {
        status: response.status,
      });
    }
  } catch (error) {
    if (error instanceof ApiError) {
      throw error;
    }

    throw new ApiError(
      "Syncora is having trouble reaching the server. Please try again.",
    );
  }
}

export async function apiRequest(path, options = {}) {
  const method = options.method ?? "GET";
  const mutates = !["GET", "HEAD", "OPTIONS"].includes(method.toUpperCase());

  if (mutates) {
    await initializeCsrf();
  }

  const headers = {
    Accept: "application/json",
    ...options.headers,
  };

  if (options.body && !(options.body instanceof FormData)) {
    headers["Content-Type"] = "application/json";
  }

  if (mutates) {
    const token = readCookie("XSRF-TOKEN");
    const socketId = realtimeSocketIdProvider?.();

    if (token) {
      headers["X-XSRF-TOKEN"] = token;
    }

    if (socketId) {
      headers["X-Socket-ID"] = socketId;
    }
  }

  let response;

  try {
    response = await fetch(`${apiUrl}${path}`, {
      ...options,
      method,
      credentials: "include",
      headers,
    });
  } catch (error) {
    if (error.name === "AbortError") {
      throw error;
    }

    throw new ApiError(
      "Syncora is having trouble reaching the server. Please try again.",
    );
  }

  const payload = await parseResponse(response);

  if (!response.ok) {
    throw new ApiError(payload?.message ?? "The request could not be completed.", {
      status: response.status,
      errors: payload?.errors ?? {},
    });
  }

  return payload;
}
